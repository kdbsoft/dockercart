#!/usr/bin/env php
<?php
/**
 * DockerCart Session GC — CLI worker.
 *
 * Expired sessions are removed here instead of inside a web request:
 * the `file` adaptor used to glob the whole session directory on a
 * probabilistic 1/100 basis (an O(number of sessions) latency spike on a
 * random page view) and the `db` adaptor ran a DELETE against `oc_session`
 * (which had no index on `expire`). Production now sets
 * `session.gc_probability = 0` (docker/php.prod.ini) and this hourly worker
 * owns both stores:
 *
 *   - storage/session/<a>/<b>/sess_* (canonical, sharded over 256 directories by
 *     Session\File::pathFor) and the legacy flat storage/session/sess_* older
 *     than `session.gc_maxlifetime` (Session\File::collectGarbage — never
 *     deletes a file a live request holds locked);
 *   - `oc_session` rows whose `expire` passed. Every app follows
 *     SESSION_ENGINE now, so this is empty unless the store is configured with
 *     `SESSION_ENGINE=db` (or still has rows from the old stock OpenCart
 *     storefront default, which pinned `db` in system/config/catalog.php).
 *     Keeping the purge makes the task correct in either configuration.
 *
 * Registered as the system scheduler task `session_gc` (hourly, hidden from
 * the scheduler UI, see migration 20261127_session_gc_task.sql).
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_session_gc.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure (the storefront/DB purge failed; the file purge already ran)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[session-gc] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_SESSION')) {
	fwrite(STDERR, "[session-gc] ERROR: DIR_SESSION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

// Same default as docker/php.prod.ini; only a missing/zero ini value falls back.
const SESSION_GC_DEFAULT_LIFETIME = 86400;

$maxlifetime = (int)ini_get('session.gc_maxlifetime');

if ($maxlifetime <= 0) {
	$maxlifetime = SESSION_GC_DEFAULT_LIFETIME;
}

try {
	$files_removed = \Session\File::collectGarbage(DIR_SESSION, $maxlifetime);
} catch (\Throwable $e) {
	fwrite(STDERR, '[session-gc] FATAL: file purge failed: ' . $e->getMessage() . "\n");
	exit(1);
}

fwrite(STDOUT, '[session-gc] Removed ' . $files_removed . ' session file(s) older than '
	. $maxlifetime . " second(s) from " . DIR_SESSION . ".\n");

try {
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

	$db->query("DELETE FROM `" . DB_PREFIX . "session` WHERE `expire` < '" . $db->escape(gmdate('Y-m-d H:i:s', time())) . "'");

	fwrite(STDOUT, '[session-gc] Purged ' . (int)$db->countAffected() . " expired row(s) from " . DB_PREFIX . "session.\n");
} catch (\Throwable $e) {
	fwrite(STDERR, '[session-gc] ERROR: ' . DB_PREFIX . 'session purge failed: ' . $e->getMessage() . "\n");
	exit(1);
}

exit(0);
