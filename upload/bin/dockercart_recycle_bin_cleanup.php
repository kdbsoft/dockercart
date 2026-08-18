#!/usr/bin/env php
<?php
/**
 * DockerCart Recycle Bin Cleanup — CLI Worker
 *
 * Permanently removes recycle-bin snapshots that have outlived the retention
 * window (30 days). The original entity was already hard-deleted when it was
 * moved to the trash; this job only removes the remaining snapshot so it can
 * no longer be restored.
 * Called by the scheduler daemon (daily).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_recycle_bin_cleanup.php
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
	fwrite(STDERR, "[recycle-bin-cleanup] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[recycle-bin-cleanup] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

const RECYCLE_BIN_RETENTION_DAYS = 30;

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

	// Only purge records older than the retention window. Restored entries
	// (restored_at IS NOT NULL) are harmless to keep until then; the actual
	// entity lives on independently of the snapshot.
	$db->query(
		"DELETE FROM `" . DB_PREFIX . "trash`
		 WHERE deleted_at < DATE_SUB(NOW(), INTERVAL " . (int) RECYCLE_BIN_RETENTION_DAYS . " DAY)"
	);

	$affected = $db->countAffected();

	fwrite(STDOUT, "[recycle-bin-cleanup] Purged " . (int) $affected . " expired recycle-bin record(s).\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[recycle-bin-cleanup] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}