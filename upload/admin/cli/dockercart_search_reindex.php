<?php
/**
 * DockerCart Search - CLI Reindex Script
 *
 * Bootstrap-less CLI runner that rebuilds Manticore indexes.
 * Designed to run inside the Apache container entrypoint (background).
 */

declare(strict_types=1);

// Fake minimal HTTP environment so OpenCart internals don't choke.
$_SERVER['HTTP_HOST']    = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']  = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-reindex] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-reindex] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

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

// Defaults required by indexing models
$config->set('config_store_id',    0);
$config->set('config_language_id', 1);

// Load models
$loader->model('extension/module/dockercart_search');
$loader->model('localisation/language');

$model = $registry->get('model_extension_module_dockercart_search');

echo "[dockercart-reindex] Starting Manticore reindex...\n";

$result = $model->reindexAll();

if ($result['success']) {
	printf(
		"[dockercart-reindex] Completed: %d products, %d categories, %d manufacturers, %d information pages, %d orders, %d customers\n",
		$result['products'],
		$result['categories'],
		$result['manufacturers'],
		$result['information'],
		$result['orders'],
		$result['customers']
	);
	exit(0);
} else {
	fwrite(STDERR, "[dockercart-reindex] FAILED: " . $result['error'] . "\n");
	exit(1);
}
