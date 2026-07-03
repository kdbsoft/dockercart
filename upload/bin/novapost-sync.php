#!/usr/bin/env php
<?php
/**
 * DockerCart NovaPost Division Sync — CLI Worker
 *
 * Bootstraps OpenCart admin and synchronizes Nova Post divisions
 * via the shared Model method (single source of truth).
 *
 * Usage:
 *   php /var/www/html/bin/novapost-sync.php
 *   php /var/www/html/bin/novapost-sync.php --sandbox
 *   php /var/www/html/bin/novapost-sync.php --countries=PL,UA --categories=CargoBranch,PostBranch
 *
 * Exit codes:
 *   0 — success (or partial)
 *   1 — failure
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

// Fake minimal HTTP environment so OpenCart internals don't choke.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

$config_path = __DIR__ . '/../admin/config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[novapost-sync] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[novapost-sync] ERROR: DIR_APPLICATION not defined\n");
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
		$config->get('db_engine') ?: 'mysqli',
		$config->get('db_hostname') ?: 'mariadb',
		$config->get('db_username') ?: 'dockercart',
		$config->get('db_password') ?: 'dockercart_password',
		$config->get('db_database') ?: 'dockercart',
		$config->get('db_port') ?: '3306'
	);
	$registry->set('db', $db);

	// Cache
	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	// Defaults
	$config->set('config_store_id', 0);
	$config->set('config_language_id', 1);

	// Parse CLI arguments
	$options = getopt('', ['sandbox', 'countries:', 'categories:', 'api-key:']);

	// Load settings model to read module config (properly unserialized)
	$loader->model('setting/setting');
	$moduleSettings = $registry->get('model_setting_setting')->getSetting('shipping_dockercart_novapost');

	$apiKey = $options['api-key'] ?? ($moduleSettings['shipping_dockercart_novapost_api_key'] ?? '');
	if (empty($apiKey)) {
		fwrite(STDERR, "[novapost-sync] ERROR: API key not configured. Set it in admin or pass --api-key=YOUR_KEY\n");
		exit(1);
	}

	$sandbox = isset($options['sandbox'])
		? true
		: !empty($moduleSettings['shipping_dockercart_novapost_sandbox']);

	$countryCodes = isset($options['countries'])
		? explode(',', $options['countries'])
		: ($moduleSettings['shipping_dockercart_novapost_country_codes'] ?? ['PL', 'UA']);

	$categories = isset($options['categories'])
		? explode(',', $options['categories'])
		: ($moduleSettings['shipping_dockercart_novapost_division_categories'] ?? ['CargoBranch', 'PostBranch', 'Postomat', 'PUDO']);

	echo "[novapost-sync] Starting sync...\n";
	echo "  API Key: " . substr($apiKey, 0, 8) . "...\n";
	echo "  Sandbox: " . ($sandbox ? 'Yes' : 'No') . "\n";
	echo "  Countries: " . implode(', ', $countryCodes) . "\n";
	echo "  Categories: " . implode(', ', $categories) . "\n";

	// Load model and sync divisions
	$loader->model('extension/shipping/dockercart_novapost');
	$model = $registry->get('model_extension_shipping_dockercart_novapost');

	$result = $model->syncDivisions($apiKey, $sandbox, $countryCodes, $categories);

	$status = $result['total_errors'] > 0 ? 'partial' : 'success';

	echo "[novapost-sync] Sync completed!\n";
	echo "  Status: {$status}\n";
	echo "  Loaded: {$result['total_loaded']}\n";
	echo "  Errors: {$result['total_errors']}\n";

	if (!empty($result['errors'])) {
		echo "\n  Errors:\n";
		foreach ($result['errors'] as $err) {
			echo "    - {$err}\n";
		}
	}

	exit($result['total_loaded'] > 0 ? 0 : 1);
} catch (\Throwable $e) {
	fwrite(STDERR, "[novapost-sync] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
