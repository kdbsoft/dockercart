#!/usr/bin/env php
<?php
/**
 * DockerCart Import YML — Direct CLI Worker
 *
 * Bootstraps OpenCart catalog and runs the Import YML module's runImport()
 * method directly (no HTTP/curl bridge). Processes offers in chunks until
 * the import is complete.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_import_yml_run.php --profile_id=1
 *
 * Optional:
 *   --chunk_size=N   Offers per chunk (default: 40)
 *
 * Exit codes:
 *   0 — success
 *   1 — runtime error (profile not found, import failure, etc.)
 *   2 — invalid CLI arguments
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

// ── Parse CLI arguments ──────────────────────────────────────────────
$profile_id = 0;
$chunk_size = 40;

foreach ($argv as $arg) {
	if (strpos($arg, '--profile_id=') === 0) {
		$profile_id = (int)substr($arg, 13);
	} elseif (strpos($arg, '--chunk_size=') === 0) {
		$chunk_size = (int)substr($arg, 13);
	}
}

if ($profile_id <= 0) {
	fwrite(STDERR, "Required argument: --profile_id=N (must be > 0)\n");
	exit(2);
}

if ($chunk_size <= 0) {
	$chunk_size = 0;
}

// ── Bootstrap OpenCart catalog ────────────────────────────────────────
$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-import-yml] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-import-yml] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

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

// Defaults required by catalog models
$config->set('config_store_id',    0);
$config->set('config_language_id', 1);

// ── Load model ───────────────────────────────────────────────────────
$loader->model('extension/module/dockercart_import_yml');

$model = $registry->get('model_extension_module_dockercart_import_yml');

// ── Run chunked import ───────────────────────────────────────────────
echo "[dockercart-import-yml] Starting import: profile_id={$profile_id} chunk_size={$chunk_size}\n";

$offset   = 0;
$summary  = [];

try {
	while (true) {
		$summary = $model->runImport($profile_id, $offset, $chunk_size);

		if (empty($summary['in_progress'])) {
			break;
		}

		$next_offset = isset($summary['next_offset']) ? (int)$summary['next_offset'] : ($offset + $chunk_size);
		if ($next_offset <= $offset) {
			break;
		}

		$offset = $next_offset;
	}
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-import-yml] FAILED: " . $e->getMessage() . "\n");
	exit(1);
}

// ── Output summary ───────────────────────────────────────────────────
echo "Import success\n";
echo 'profile_id=' . (isset($summary['profile_id']) ? (int)$summary['profile_id'] : $profile_id) . "\n";
echo 'mode=' . (isset($summary['mode']) ? $summary['mode'] : '') . "\n";
echo 'total_offers=' . (isset($summary['total_offers']) ? (int)$summary['total_offers'] : 0) . "\n";
echo 'added=' . (isset($summary['added']) ? (int)$summary['added'] : 0) . "\n";
echo 'updated=' . (isset($summary['updated']) ? (int)$summary['updated'] : 0) . "\n";
echo 'skipped=' . (isset($summary['skipped']) ? (int)$summary['skipped'] : 0) . "\n";
echo 'errors=' . (isset($summary['errors']) ? (int)$summary['errors'] : 0) . "\n";
echo 'in_progress=' . (!empty($summary['in_progress']) ? '1' : '0') . "\n";

exit(0);
