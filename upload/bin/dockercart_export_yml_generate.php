#!/usr/bin/env php
<?php
/**
 * DockerCart Export YML Generate — CLI Worker
 *
 * Regenerates YML feed files for active profiles by calling the
 * catalog controller via internal HTTP request (same bridge as admin AJAX).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_export_yml_generate.php
 *   php /var/www/html/bin/dockercart_export_yml_generate.php --profile_id=5
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

// Parse --profile_id argument
$profile_id = 0;
foreach ($argv as $arg) {
	if (strpos($arg, '--profile_id=') === 0) {
		$profile_id = (int)substr($arg, 13);
	}
}

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-export-yml] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-export-yml] ERROR: DIR_APPLICATION not defined\n");
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

	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	$config->set('config_store_id',    0);
	$config->set('config_language_id', 1);

	// Load settings from DB
	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	// Build catalog base URL
	if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
		$base = rtrim(HTTPS_CATALOG, '/');
	} elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
		$base = rtrim(HTTP_CATALOG, '/');
	} else {
		$base = rtrim($config->get('config_url') ?: HTTP_SERVER, '/');
	}

	// ── Load active profiles ──────────────────────────────────────────
	if ($profile_id > 0) {
		$profile_query = $db->query(
			"SELECT profile_id, name FROM `" . DB_PREFIX . "dockercart_export_yml_profile` WHERE profile_id = " . $profile_id . " AND status = 1"
		);
	} else {
		$profile_query = $db->query(
			"SELECT profile_id, name FROM `" . DB_PREFIX . "dockercart_export_yml_profile` WHERE status = 1 ORDER BY profile_id"
		);
	}

	if (!$profile_query->num_rows) {
		if ($profile_id > 0) {
			fwrite(STDERR, "[dockercart-export-yml] Profile #{$profile_id} not found or disabled.\n");
		} else {
			echo "[dockercart-export-yml] No active profiles found.\n";
		}
		exit(0);
	}

	echo "[dockercart-export-yml] Found " . $profile_query->num_rows . " active profile(s).\n";

	$success_count = 0;
	$error_count   = 0;

	foreach ($profile_query->rows as $profile) {
		$profile_id = (int)$profile['profile_id'];
		$name       = $profile['name'];

		echo "[dockercart-export-yml] Generating profile #{$profile_id} ({$name})...\n";

		$catalog_url = $base . '/index.php?route=extension/feed/dockercart_export_yml&profile_id=' . $profile_id . '&regenerate=1&admin_request=1';

		try {
			if (function_exists('curl_init')) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $catalog_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($ch, CURLOPT_TIMEOUT, 300);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

				$response = curl_exec($ch);
				$curl_errno = curl_errno($ch);
				$curl_error = curl_error($ch);
				$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);

				if ($curl_errno) {
					throw new \RuntimeException("cURL error: {$curl_error}");
				}
				if ($http_code >= 400) {
					throw new \RuntimeException("HTTP error: {$http_code}");
				}
			} else {
				$context = stream_context_create([
					'http' => ['timeout' => 300, 'ignore_errors' => true],
					'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
				]);
				$response = @file_get_contents($catalog_url, false, $context);
				if ($response === false) {
					throw new \RuntimeException('file_get_contents failed');
				}
			}

			$files = glob(DIR_APPLICATION . '../export-yml-' . $profile_id . '-*.xml');
			$count = $files ? count($files) : 0;
			echo "  OK — {$count} file(s) generated\n";
			$success_count++;

		} catch (\Throwable $e) {
			fwrite(STDERR, "  FAILED: " . $e->getMessage() . "\n");
			$error_count++;
		}
	}

	echo "[dockercart-export-yml] Done. Success: {$success_count}, Errors: {$error_count}\n";
	exit($error_count > 0 ? 1 : 0);

} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-export-yml] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
