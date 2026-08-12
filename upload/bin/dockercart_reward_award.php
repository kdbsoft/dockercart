#!/usr/bin/env php
<?php
/**
 * DockerCart Reward Points Auto-Award — CLI Worker
 *
 * Awards order reward points to customers whose orders reached a complete
 * status at least config_reward_delay_days days ago (default 14), i.e. after
 * the return/refund window. Runs once per day via the scheduler daemon.
 *
 * Only orders that are still in a complete status and not yet awarded are
 * processed (reward_awarded = 0). Orders that left the complete status are
 * skipped — they get their revocation handled by the status-change flow.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_reward_award.php
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
	fwrite(STDERR, "[reward-award] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[reward-award] ERROR: DIR_APPLICATION not defined\n");
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

	if (!$config->get('config_reward_auto_award')) {
		fwrite(STDOUT, "[reward-award] Auto-award is disabled (config_reward_auto_award = 0).\n");
		exit(0);
	}

	$days = (int)$config->get('config_reward_delay_days');
	$days = $days > 0 ? $days : 14;

	$complete_statuses = (array)$config->get('config_complete_status');
	$complete_statuses = array_values(array_filter(array_map('intval', $complete_statuses), function ($id) {
		return $id > 0;
	}));

	if (empty($complete_statuses)) {
		fwrite(STDOUT, "[reward-award] No complete statuses configured, nothing to do.\n");
		exit(0);
	}

	$in = implode(',', $complete_statuses);

	$orders = $db->query(
		"SELECT order_id FROM `" . DB_PREFIX . "order`
		 WHERE order_status_id IN (" . $in . ")
		   AND reward_awarded = '0'
		   AND customer_id > '0'
		   AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)"
	);

	if (!$orders->num_rows) {
		fwrite(STDOUT, "[reward-award] No orders due for reward award.\n");
		exit(0);
	}

	$library_path = DIR_SYSTEM . 'library/dockercart_reward.php';

	if (is_file($library_path)) {
		require_once $library_path;
	}

	$reward = class_exists('\DockercartReward') ? new \DockercartReward($registry) : null;

	if (!$reward) {
		fwrite(STDERR, "[reward-award] DockercartReward library not available.\n");
		exit(1);
	}

	$awarded = 0;
	$skipped = 0;

	foreach ($orders->rows as $row) {
		$order_id = (int)$row['order_id'];

		// Lock the order row and re-check the guard: it may have been awarded
		// by a concurrent run or by an admin between the sweep and now.
		$check = $db->query(
			"SELECT order_id FROM `" . DB_PREFIX . "order`
			 WHERE order_id = '" . $order_id . "'
			   AND order_status_id IN (" . $in . ")
			   AND reward_awarded = '0'
			   AND customer_id > '0'"
		);

		if (!$check->num_rows) {
			$skipped++;
			continue;
		}

		$db->query('START TRANSACTION');

		try {
			if ($reward->awardOrderReward($order_id) === 1) {
				$awarded++;
			}

			$db->query('COMMIT');
		} catch (\Throwable $e) {
			$db->query('ROLLBACK');
			throw $e;
		}
	}

	fwrite(STDOUT, "[reward-award] Done. Awarded: " . $awarded . ", skipped: " . $skipped . ".\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[reward-award] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
