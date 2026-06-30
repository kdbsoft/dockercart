#!/usr/bin/env php
<?php
/**
 * DockerCart Google Base Generate — CLI Worker
 *
 * Bootstraps OpenCart catalog and regenerates Google Base XML feed files
 * directly (no HTTP/curl bridge). Called by the scheduler daemon or manually.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_googlebase_generate.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure (config missing, generation error, etc.)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

// Fake minimal HTTP environment so OpenCart internals don't choke.
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-googlebase] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-googlebase] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	// Config
	$config = new Config();
	$config->load('default');
	$config->load('catalog');
	$registry->set('config', $config);

	// Log
	$log = new Log($config->get('error_filename') ?: 'error.log');
	$registry->set('log', $log);

	// Event
	$event = new Event($registry);
	$registry->set('event', $event);

	// Loader
	$loader = new Loader($registry);
	$registry->set('load', $loader);

	// Database
	$db = new DB(
		$config->get('db_engine')    ?: 'mysqli',
		$config->get('db_hostname')  ?: 'mariadb',
		$config->get('db_username')  ?: 'dockercart',
		$config->get('db_password')  ?: 'dockercart_password',
		$config->get('db_database')  ?: 'dockercart',
		$config->get('db_port')      ?: '3306'
	);
	$registry->set('db', $db);

	// Cache
	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	// Defaults required by catalog
	$config->set('config_store_id',    0);
	$config->set('config_language_id', 1);

	// Load all settings from DB (same as catalog startup controller)
	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	// URL Router — required by catalog controller
	$url = new Url($config->get('config_url') ?: HTTP_SERVER, $config->get('config_ssl') ?: HTTPS_SERVER);
	$registry->set('url', $url);

	// Session mock — buildAlternateUrls() / language switching needs session->data
	$session = new stdClass();
	$session->data = ['language' => $config->get('config_language') ?: 'en-gb'];
	$registry->set('session', $session);

	// ── Load and run the googlebase controller ───────────────────────
	$controller_file = DIR_APPLICATION . 'controller/extension/feed/dockercart_googlebase.php';
	if (!is_file($controller_file)) {
		throw new \RuntimeException('Google Base controller not found: ' . $controller_file);
	}

	require_once $controller_file;

	$controller = new ControllerExtensionFeedDockercartGooglebase($registry);

	echo "[dockercart-googlebase] Starting feed generation...\n";

	$controller->generate();

	// ── Output summary ───────────────────────────────────────────────
	$files = glob(DIR_APPLICATION . '../google-base*.xml');
	$files = $files ?: [];

	echo "[dockercart-googlebase] Done. Files generated: " . count($files) . "\n";
	foreach ($files as $f) {
		$size = is_file($f) ? filesize($f) : 0;
		echo "  " . basename($f) . " (" . number_format($size) . " bytes)\n";
	}

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-googlebase] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
