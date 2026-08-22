#!/usr/bin/env php
<?php
/**
 * DockerCart Stock Reservation Cleanup — CLI Worker
 *
 * 1. Deletes expired unbound checkout holds (order_id IS NULL) so stock
 *    becomes available again for other customers.
 * 2. Releases holds bound to orders that have been sitting in a
 *    non-fulfilled status (not processing/complete) untouched for more than
 *    config_stock_reserve_stale_days days (0 = disabled) — a safety net so
 *    abandoned orders cannot lock stock forever.
 *
 * Holds bound to an active order are otherwise kept until stock is subtracted
 * (processing/complete) or the order is cancelled or refunded — see
 * DockercartStockReservation::releaseOrder().
 * Called by the scheduler daemon (every 15 minutes).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_reservation_cleanup.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[reservation-cleanup] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[reservation-cleanup] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	$config = new Config();
	$config->load('default');
	$config->load('catalog');
	$registry->set('config', $config);

	$log = new Log($config->get('error_filename') ?: 'error.log');
	$registry->set('log', $log);

	$event = new Event($registry);
	$registry->set('event', $event);

	$loader = new Loader($registry);
	$registry->set('load', $loader);

	$db = new DB(
		$config->get('db_engine')    ?: 'mysqli',
		$config->get('db_hostname')  ?: 'mariadb',
		$config->get('db_username')  ?: 'dockercart',
		$config->get('db_password')  ?: 'dockercart_password',
		$config->get('db_database')  ?: 'dockercart',
		$config->get('db_port')      ?: '3306'
	);
	$registry->set('db', $db);

	$config->set('config_store_id', 0);
	$config->set('config_language_id', 1);

	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	// Sweep only unbound holds: order-bound rows must survive until the order
	// is fulfilled (stock subtracted) or cancelled/refunded.
	$db->query("DELETE FROM `" . DB_PREFIX . "stock_reservation` WHERE order_id IS NULL AND expires_at < NOW()");

	fwrite(STDOUT, "[reservation-cleanup] Expired unbound checkout holds removed.\n");

	// Safety net: release bound holds of orders stuck in a non-fulfilled
	// status (never reached processing/complete) beyond the stale horizon.
	// Covers abandoned orders and status-0 resets from admin order edits that
	// are not part of config_cancelled_status. Cancelled/fulfilled orders
	// already release via DockercartStockReservation::releaseOrder().
	$stale_days = (int)$config->get('config_stock_reserve_stale_days');

	if ($stale_days > 0) {
		$kept_statuses = array_merge(
			(array)$config->get('config_processing_status'),
			(array)$config->get('config_complete_status')
		);

		$kept_statuses = array_values(array_unique(array_filter(array_map('intval', $kept_statuses), function ($id) {
			return $id > 0;
		})));

		$status_filter = empty($kept_statuses) ? '' : " AND o.order_status_id NOT IN (" . implode(',', $kept_statuses) . ")";

		$db->query("DELETE r FROM `" . DB_PREFIX . "stock_reservation` r INNER JOIN `" . DB_PREFIX . "order` o ON o.order_id = r.order_id WHERE o.date_modified < DATE_SUB(NOW(), INTERVAL " . $stale_days . " DAY)" . $status_filter);

		fwrite(STDOUT, "[reservation-cleanup] Holds of stale non-fulfilled orders (>{$stale_days}d) released.\n");
	}

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[reservation-cleanup] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
