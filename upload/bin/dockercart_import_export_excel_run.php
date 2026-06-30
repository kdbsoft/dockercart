#!/usr/bin/env php
<?php
/**
 * DockerCart Import/Export Excel — Direct CLI Worker
 *
 * Bootstraps OpenCart catalog and runs the Import/Export Excel module's
 * runImport() or runExport() directly (no HTTP/curl bridge).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_import_export_excel_run.php --profile_id=1
 *   php /var/www/html/bin/dockercart_import_export_excel_run.php --profile_id=1 --action=export
 *
 * Options:
 *   --profile_id=N   Profile ID to run (required, must be > 0)
 *   --action=TYPE    import|export (default: import)
 *
 * Exit codes:
 *   0 — success
 *   1 — runtime error (profile not found, import/export failure, etc.)
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
$action     = 'import';

foreach ($argv as $arg) {
	if (strpos($arg, '--profile_id=') === 0) {
		$profile_id = (int)substr($arg, 13);
	} elseif (strpos($arg, '--action=') === 0) {
		$action = strtolower(substr($arg, 9));
	}
}

if ($profile_id <= 0) {
	fwrite(STDERR, "Required argument: --profile_id=N (must be > 0)\n");
	exit(2);
}

if (!in_array($action, ['import', 'export'], true)) {
	fwrite(STDERR, "Invalid action: {$action}. Use import or export.\n");
	exit(2);
}

// ── Bootstrap OpenCart catalog ────────────────────────────────────────
$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-import-export-excel] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-import-export-excel] ERROR: DIR_APPLICATION not defined\n");
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
$loader->model('extension/module/dockercart_import_export_excel');

$model = $registry->get('model_extension_module_dockercart_import_export_excel');

// ── Run action ───────────────────────────────────────────────────────
echo "[dockercart-import-export-excel] Starting {$action}: profile_id={$profile_id}\n";

try {
	if ($action === 'import') {
		$model->runImport($profile_id);
		echo "[dockercart-import-export-excel] Import complete: profile_id={$profile_id}\n";
	} else {
		if (!method_exists($model, 'runExport')) {
			fwrite(STDERR, "[dockercart-import-export-excel] ERROR: runExport() method not found\n");
			exit(1);
		}
		$model->runExport($profile_id, 'xlsx');
		echo "[dockercart-import-export-excel] Export complete: profile_id={$profile_id}\n";
	}
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-import-export-excel] FAILED: " . $e->getMessage() . "\n");
	exit(1);
}

exit(0);
