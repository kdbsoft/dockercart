#!/usr/bin/env php
<?php
/**
 * DockerCart Currency Refresh — CLI Worker
 *
 * Bootstraps OpenCart admin and refreshes currency exchange rates via the
 * active currency engine (ECB or Fixer.io).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_currency_refresh.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure (DB error, config missing, model error, etc.)
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

$config_path = __DIR__ . '/../admin/config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-currency] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-currency] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	// Config
	$config = new Config();
	$config->load('default');
	$config->load('admin');
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

	// Defaults
	$config->set('config_store_id',    0);
	$config->set('config_language_id', 1);

	// Determine active currency engine from DB
	$query = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_currency_engine'");

	$engine = '';
	if ($query->num_rows) {
		$engine = trim((string)$query->row['value']);
	}

	// Load model and refresh — fallback to ECB if no engine configured
	if ($engine === 'fixer') {
		$loader->model('extension/currency/fixer');
		$model = $registry->get('model_extension_currency_fixer');
		$engine_label = 'Fixer.io';
	} else {
		$loader->model('extension/currency/ecb');
		$model = $registry->get('model_extension_currency_ecb');
		$engine_label = 'ECB';
	}

	echo "[dockercart-currency] Refreshing rates via {$engine_label}...\n";

	$model->refresh();

	echo "[dockercart-currency] {$engine_label} rates refreshed successfully.\n";
	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-currency] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
