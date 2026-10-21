#!/usr/bin/env php
<?php
/**
 * DockerCart API Log Cleanup — CLI worker.
 *
 * Deletes oc_api_log rows older than `config_api_log_retention_days` (default
 * 30, 0 = keep forever) and expired oc_api_idempotency rows (24h TTL).
 * Registered as the system scheduler task
 * `api_log_cleanup` (daily) by
 * docker/mysql/migrations/20261022_admin_rest_api.sql.
 *
 * The journal is security evidence: it is trimmed by age, not cleared on key
 * deletion, so it always outlives the keys it references.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_api_log_cleanup.php
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
	fwrite(STDERR, "[api-log-cleanup] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[api-log-cleanup] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

const API_LOG_RETENTION_DAYS = 30;

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

	// The retention window lives in the shared `config` settings code.
	$setting = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'config' AND `key` = 'config_api_log_retention_days' AND `store_id` = 0");

	$days = $setting->num_rows ? (int)$setting->row['value'] : API_LOG_RETENTION_DAYS;

	if ($days <= 0) {
		fwrite(STDOUT, "[api-log-cleanup] Retention disabled (config_api_log_retention_days = 0) — nothing to do.\n");

		exit(0);
	}

	$db->query("DELETE FROM `" . DB_PREFIX . "api_log` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)");

	$affected = $db->countAffected();

	// Idempotency answers are only useful for retry windows of minutes/hours.
	$db->query("DELETE FROM `" . DB_PREFIX . "api_idempotency` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

	$idempotency_affected = $db->countAffected();

	fwrite(STDOUT, "[api-log-cleanup] Purged " . (int)$affected . " API log row(s) older than " . $days . " day(s) and "
		. (int)$idempotency_affected . " expired idempotency row(s).\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[api-log-cleanup] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
