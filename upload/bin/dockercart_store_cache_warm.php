#!/usr/bin/env php
<?php
/**
 * DockerCart scheduled "extension store cache warm" worker.
 *
 * Runs on a schedule via the scheduler daemon (system task
 * `dockercart_store_cache_warm`, hourly, hidden from the scheduler UI).
 * Refreshes the local YML catalog cache
 * (storage/dockercart/extension_store/yml_<lang>.xml) for every admin
 * language, so the green "updates available" badge in the admin sidebar
 * (common/column_left -> DockercartExtensionStore::countAvailableUpdates())
 * reflects fresh catalog data on any admin page — without anyone having to
 * open the Add-ons Store first. The badge itself stays cache-only: it never
 * touches the network on page load.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_store_cache_warm.php
 *
 * Exit codes:
 *   0 — at least one catalog warmed or all caches still fresh
 *   1 — every catalog fetch failed (network/DNS/store outage)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../admin/config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-store-cache-warm] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

require_once DIR_SYSTEM . 'startup.php';

echo '[' . date('Y-m-d H:i:s') . '] Extension store cache warm starting...' . "\n";

// Bootstrap enough of the engine for a CLI worker (mirrors the other workers).
$registry = new Registry();
$config   = new Config();
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

$db = new DB(
	$config->get('db_engine')    ?: 'mysqli',
	$config->get('db_hostname')  ?: 'mariadb',
	$config->get('db_username')  ?: 'dockercart',
	$config->get('db_password')  ?: 'dockercart_password',
	$config->get('db_database')  ?: 'dockercart',
	$config->get('db_port')      ?: '3306'
);
$registry->set('db', $db);

if (!is_file(DIR_SYSTEM . 'library/dockercart/extension_store.php')) {
	echo '[' . date('Y-m-d H:i:s') . '] Extension Store library not found — nothing to warm.' . "\n";
	exit(0);
}

$store = new DockercartExtensionStore($registry);

// The sidebar badge (getCachedYml) tries yml_<admin lang>.xml, falls back to
// yml_en.xml, then to the newest file of any language; the store page serves
// the same files per admin language. Warming all three languages keeps both
// consumers instant for any admin UI language.
$langs = array('en', 'ru', 'uk');

$warmed   = 0;
$fresh    = 0;

foreach ($langs as $lang) {
	$cache_file = DIR_STORAGE . 'dockercart/extension_store/yml_' . $lang . '.xml';

	// getYml() re-fetches stale caches itself; the pre-check only lets the log
	// tell "still fresh — skipped" apart from an actual fetch.
	if (is_file($cache_file) && (time() - filemtime($cache_file)) < DockercartExtensionStore::CACHE_TTL) {
		$fresh++;
		echo '[' . date('Y-m-d H:i:s') . '] yml_' . $lang . '.xml cache is fresh — skipped' . "\n";
		continue;
	}

	try {
		$xml = $store->getYml($lang);
		$offers = isset($xml->shop->offers->offer) ? count($xml->shop->offers->offer) : 0;
		$warmed++;
		echo '[' . date('Y-m-d H:i:s') . '] yml_' . $lang . '.xml warmed (' . $offers . ' offers)' . "\n";
	} catch (\Throwable $e) {
		// A single language failing must not abort the run: getYml() already
		// falls back to en internally, and the badge degrades gracefully.
		echo '[' . date('Y-m-d H:i:s') . '] yml_' . $lang . '.xml warm failed: ' . $e->getMessage() . "\n";
		fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] yml_' . $lang . '.xml warm failed: ' . $e->getMessage() . "\n");
	}
}

if ($warmed === 0 && $fresh < count($langs)) {
	fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Extension store cache warm failed: no catalog could be fetched (store unreachable?).' . "\n");
	exit(1);
}

// Diagnostics: what the admin sidebar badge will show from the warm cache.
try {
	$updates = $store->countAvailableUpdates();

	echo '[' . date('Y-m-d H:i:s') . '] installed add-ons with available updates: ' . $updates . "\n";
	fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] warm complete: warmed=' . $warmed . ' fresh_skipped=' . $fresh . ' updates=' . $updates . "\n");
} catch (\Throwable $e) {
	echo '[' . date('Y-m-d H:i:s') . '] update count failed: ' . $e->getMessage() . "\n";
}

exit(0);