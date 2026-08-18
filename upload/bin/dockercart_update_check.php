#!/usr/bin/env php
<?php
/**
 * DockerCart scheduled "update available" check worker.
 *
 * Runs on a schedule via the scheduler daemon (system task
 * `dockercart_update_check`, hourly). Fetches the remote VERSION and
 * CHANGELOG and writes the result to the oc_setting cache the admin reads, so
 * page loads and the header update-bell never have to hit the network
 * themselves (a stale cache previously blocked admin views until it filled).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_update_check.php
 *
 * Exit codes:
 *   0 — check completed
 *   1 — could not fetch remote version
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
	fwrite(STDERR, "[dockercart-update-check] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

require_once DIR_SYSTEM . 'startup.php';

echo '[' . date('Y-m-d H:i:s') . '] Update check starting...' . "\n";

// Bootstrap enough of the engine for a CLI worker (mirrors other workers).
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

// Load all settings into config (same as the admin startup controller) so the
// model's pure fetch/parse helpers and the default remote/branch resolve.
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$config->set($result['key'], $result['value']);
	} else {
		$config->set($result['key'], json_decode($result['value'], true));
	}
}

// Instantiate the admin model directly: only its registry-free fetch/parse
// helpers are used (getRemoteVersion / getRemoteChangelog / parseChangelog),
// avoiding the Loader/Event machinery that needs a full request context.
require_once DIR_APPLICATION . 'model/tool/update.php';
$model = new ModelToolUpdate($registry);

$cfg    = $model->getConfig();
$remote = $cfg['remote'];
$branch = $cfg['branch'];

// Probe both endpoints up front so a connectivity failure is logged clearly
// instead of silently leaving the changelog empty.
$diag = $model->getRemoteDiagnostics($remote, $branch);

if ($diag['version'] !== 'OK') {
	echo '[' . date('Y-m-d H:i:s') . '] VERSION fetch failed: ' . $diag['version'] . ' (' . rtrim($remote, '/') . '/raw/' . $branch . '/upload/VERSION)' . "\n";
}

if ($diag['changelog'] !== 'OK') {
	echo '[' . date('Y-m-d H:i:s') . '] CHANGELOG fetch failed: ' . $diag['changelog'] . ' — changelog will be empty' . "\n";
}

$remoteVersion = $model->getRemoteVersion($remote, $branch, 10);
$changelog     = '';

if ($remoteVersion !== null) {
	if ($diag['changelog'] === 'OK') {
		$raw = $model->getRemoteChangelog($remote, $branch, 5);

		if ($raw !== null) {
			$changelog = $model->parseChangelog($raw, $remoteVersion);
		}
	}

	echo '[' . date('Y-m-d H:i:s') . '] remote_version=' . $remoteVersion . ' changelog_len=' . strlen($changelog) . "\n";
}

// Write the cache directly (same keys the admin + header read), so it stays
// warm without depending on the Loader. Persisted even on failure to throttle
// retries in the admin.
$cacheKey   = 'dockercart_update';
$persist = [
	'dockercart_update_last_check'     => (string)time(),
	'dockercart_update_remote_version' => $remoteVersion ?? (string)$config->get('dockercart_update_remote_version'),
	'dockercart_update_changelog'      => $changelog,
];

foreach ($persist as $key => $value) {
	$db->query(
		"DELETE FROM `" . DB_PREFIX . "setting`
		 WHERE `store_id` = '0' AND `key` = '" . $db->escape((string)$key) . "'"
	);

	$db->query(
		"INSERT INTO `" . DB_PREFIX . "setting`
		 SET `store_id` = '0',
		     `code`     = '" . $db->escape($cacheKey) . "',
		     `key`      = '" . $db->escape((string)$key) . "',
		     `value`    = '" . $db->escape((string)$value) . "'"
	);
}

if ($remoteVersion === null) {
	echo '[' . date('Y-m-d H:i:s') . '] Update check failed: VERSION unavailable (' . ($diag['version'] !== 'OK' ? $diag['version'] : 'empty body') . ').' . "\n";
	exit(1);
}

echo '[' . date('Y-m-d H:i:s') . '] Update check complete. remote=' . $remoteVersion . ' changelog_len=' . strlen($changelog) . "\n";

exit(0);