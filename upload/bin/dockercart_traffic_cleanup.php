#!/usr/bin/env php
<?php
/**
 * DockerCart Traffic Source Cleanup — CLI Worker
 *
 * Deletes traffic source records older than 90 days.
 * Called by the scheduler daemon (daily).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_traffic_cleanup.php
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
	fwrite(STDERR, "[traffic-cleanup] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[traffic-cleanup] ERROR: DIR_APPLICATION not defined\n");
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

	$retention_days = 90;

	$db->query("DELETE FROM `" . DB_PREFIX . "dockercart_traffic_source` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$retention_days . " DAY)");

	fwrite(STDOUT, "[traffic-cleanup] Cleanup complete. Records older than {$retention_days} days removed.\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[traffic-cleanup] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
