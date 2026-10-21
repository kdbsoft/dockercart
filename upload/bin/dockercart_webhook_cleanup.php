#!/usr/bin/env php
<?php
/**
 * DockerCart Webhook Log Cleanup — CLI worker.
 *
 * Registered as the system scheduler task `webhook_cleanup` (daily, hidden from
 * the scheduler UI, see migration 20261025_webhooks.sql). Trims the delivery
 * journal by age so oc_dockercart_webhook_delivery does not grow without bound:
 *
 *   - delivered / cancelled / failed rows older than
 *     `config_webhook_log_retention_days` (default 30, 0 = keep forever);
 *   - pending rows of subscriptions that no longer exist (orphans).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_webhook_cleanup.php
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
	fwrite(STDERR, "[webhook-cleanup] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[webhook-cleanup] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

const WEBHOOK_LOG_RETENTION_DAYS = 30;

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

	// Orphaned rows (subscription deleted while the worker was down).
	$db->query("DELETE d FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d
		LEFT JOIN `" . DB_PREFIX . "dockercart_webhook_subscription` s ON (s.`subscription_id` = d.`subscription_id`)
		WHERE s.`subscription_id` IS NULL");

	$orphans = (int)$db->countAffected();

	$setting = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config' AND `key` = 'config_webhook_log_retention_days' AND `store_id` = '0'");

	$days = $setting->num_rows ? (int)$setting->row['value'] : WEBHOOK_LOG_RETENTION_DAYS;

	if ($days <= 0) {
		fwrite(STDOUT, "[webhook-cleanup] Retention disabled (config_webhook_log_retention_days = 0) — removed "
			. $orphans . " orphaned row(s) only.\n");

		exit(0);
	}

	$db->query("DELETE FROM `" . DB_PREFIX . "dockercart_webhook_delivery`
		WHERE `status` IN ('delivered', 'cancelled', 'failed')
		  AND `date_added` < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)");

	$purged = (int)$db->countAffected();

	fwrite(STDOUT, '[webhook-cleanup] Purged ' . $purged . ' delivery row(s) older than ' . $days
		. ' day(s) and ' . $orphans . " orphaned row(s).\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, '[webhook-cleanup] FATAL: ' . $e->getMessage() . "\n");
	exit(1);
}
