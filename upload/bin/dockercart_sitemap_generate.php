#!/usr/bin/env php
<?php
/**
 * DockerCart Sitemap Generate — CLI Worker
 *
 * Bootstraps OpenCart catalog and regenerates sitemap XML files directly
 * (no HTTP/curl bridge). Called by the scheduler daemon or manually.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_sitemap_generate.php
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
	fwrite(STDERR, "[dockercart-sitemap] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-sitemap] ERROR: DIR_APPLICATION not defined\n");
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

	// URL Router — required by buildAlternateUrls()
	$url = new Url($config->get('config_url') ?: HTTP_SERVER, $config->get('config_ssl') ?: HTTPS_SERVER);
	$registry->set('url', $url);

	// Session mock — buildAlternateUrls() writes to $this->session->data['language']
	// In CLI there is no real session; provide a minimal object.
	$session = new stdClass();
	$session->data = ['language' => $config->get('config_language') ?: 'en-gb'];
	$registry->set('session', $session);

	// ── Load and run the sitemap controller ──────────────────────────
	$controller_file = DIR_APPLICATION . 'controller/extension/feed/dockercart_sitemap.php';
	if (!is_file($controller_file)) {
		throw new \RuntimeException('Sitemap controller not found: ' . $controller_file);
	}

	require_once $controller_file;

	$controller = new ControllerExtensionFeedDockercartSitemap($registry);

	echo "[dockercart-sitemap] Starting sitemap generation...\n";

	$controller->generate();

	// ── Update last_generated + file_count in settings ──────────────
	$loader->model('setting/setting');
	$model_setting = $registry->get('model_extension_setting_setting');

	$files = glob(DIR_APPLICATION . '../sitemap*.xml');
	$gzfiles = glob(DIR_APPLICATION . '../sitemap*.xml.gz');
	$files = $files ?: [];
	$gzfiles = $gzfiles ?: [];
	$all_files = array_values(array_unique(array_merge($files, $gzfiles)));

	$last_generated = date('c');
	$file_count = count($all_files);

	$current_settings = $model_setting->getSetting('dockercart_sitemap');
	$current_settings['dockercart_sitemap_last_generated'] = $last_generated;
	$current_settings['dockercart_sitemap_file_count'] = $file_count;
	$model_setting->editSetting('dockercart_sitemap', $current_settings);

	echo "[dockercart-sitemap] Done. Files: {$file_count}, generated: {$last_generated}\n";
	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-sitemap] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
