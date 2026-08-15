<?php
declare(strict_types=1);

/**
 * DockerCart - GUI "System Update" background worker.
 *
 * Run detached by admin/controller/tool/update.php. Mirrors `make update`:
 * download the remote branch archive, sync upload/ files, rewrite VERSION in
 * place, apply SQL migrations, refresh OCMOD modifications.
 *
 * Usage: php dockercart_update.php <remote> <branch>
 */

if (PHP_SAPI !== 'cli') {
	exit('This script must be run from the command line.');
}

$remote = $argv[1] ?? '';
$branch = $argv[2] ?? '';

if (!$remote || !$branch) {
	fwrite(STDERR, "Usage: php dockercart_update.php <remote> <branch>\n");
	exit(1);
}

if (!preg_match('#^https://#i', $remote)) {
	fwrite(STDERR, "ERROR: remote must be an https:// URL\n");
	exit(1);
}

if (!preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $branch)) {
	fwrite(STDERR, "ERROR: invalid branch name: {$branch}\n");
	exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_SYSTEM')) {
	fwrite(STDERR, "ERROR: DIR_SYSTEM not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

$registry = new Registry();

$config = new Config();
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

$registry->set('log', new Log('update.log'));

$event = new Event($registry);
$registry->set('event', $event);

$loader = new Loader($registry);
$registry->set('load', $loader);

$db = new DB(
	$config->get('db_engine') ?: 'mysqli',
	$config->get('db_hostname') ?: 'mariadb',
	$config->get('db_username') ?: 'dockercart',
	$config->get('db_password') ?: 'dockercart_password',
	$config->get('db_database') ?: 'dockercart',
	$config->get('db_port') ?: '3306'
);
$registry->set('db', $db);

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

$updateDir = rtrim(DIR_STORAGE, '/') . '/dockercart_update/';
$tmpDir = $updateDir . 'tmp/';
$statusFile = $updateDir . 'status.json';
$lockFile = $updateDir . 'lock';
$maintenancePrevFile = $updateDir . 'maintenance_prev';

@mkdir($updateDir, 0775, true);
@mkdir($tmpDir, 0775, true);

$GLOBALS['update_status_file'] = $statusFile;

function update_set_status(string $step, int $percent, string $message, ?string $appendLog = null): array {
	$file = $GLOBALS['update_status_file'];
	static $status = null;

	if ($status === null) {
		$status = [
			'step'       => '',
			'percent'    => 0,
			'message'    => '',
			'log'        => [],
			'done'       => false,
			'error'      => null,
			'pid'        => getmypid(),
			'started_at' => date('c')
		];
	}

	$status['step'] = $step;
	$status['percent'] = $percent;
	$status['message'] = $message;

	if ($appendLog !== null) {
		$status['log'][] = '[' . date('H:i:s') . '] ' . $appendLog;
	}

	file_put_contents($file, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

	return $status;
}

function update_is_excluded(string $relative): bool {
	$rel = trim($relative, '/');
	$parts = explode('/', $rel);
	$lower = strtolower($rel);

	if ($rel === 'config.php' || $rel === 'admin/config.php') {
		return true;
	}

	if (strpos($lower, 'image/') === 0 || $parts[0] === 'image') {
		return true;
	}

	if ($parts[0] === 'tests' || $parts[0] === 'storage' || $parts[0] === '.git') {
		return true;
	}

	foreach ($parts as $p) {
		$pl = strtolower($p);

		if (strpos($pl, 'custom_') === 0 || strpos($pl, 'dockercart_custom_') === 0 || strpos($pl, 'dc_custom_') === 0) {
			return true;
		}
	}

	return false;
}

function update_sync_directory(string $src, string $dst): void {
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iter as $file) {
		$relative = ltrim(substr($file->getPathname(), strlen($src)), '/');

		if (update_is_excluded($relative)) {
			continue;
		}

		$target = $dst . $relative;

		if ($file->isDir()) {
			if (!is_dir($target)) {
				@mkdir($target, 0755, true);
			}
		} else {
			$dir = dirname($target);

			if (!is_dir($dir)) {
				@mkdir($dir, 0755, true);
			}

			copy($file->getPathname(), $target);
		}
	}
}

function update_remove_directory(string $dir): void {
	if (!is_dir($dir)) {
		return;
	}

	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iter as $file) {
		if ($file->isDir()) {
			@rmdir($file->getPathname());
		} else {
			@unlink($file->getPathname());
		}
	}

	@rmdir($dir);
}

function update_build_manifest(string $srcUpload): array {
	$manifest = [];
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($srcUpload, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iter as $file) {
		if ($file->isFile()) {
			$manifest[] = ltrim(substr($file->getPathname(), strlen($srcUpload)), '/');
		}
	}

	sort($manifest);

	return $manifest;
}

function update_prune_obsolete(string $srcUpload, string $dstUpload, string $updateDir): void {
	$manifestFile = $updateDir . 'manifest.json';

	// Build the new manifest from the freshly extracted upload/ tree.
	$newManifest = update_build_manifest($srcUpload);

	// Load the previously deployed manifest (if any). On the very first GUI
	// update there is no manifest, so we keep all local files (additive) and
	// only start pruning from the second update onward.
	$oldManifest = [];

	if (is_file($manifestFile)) {
		$old = json_decode(file_get_contents($manifestFile), true);

		if (is_array($old)) {
			$oldManifest = $old;
		}
	}

	if ($oldManifest) {
		$toDelete = array_diff($oldManifest, $newManifest);
		$deleted = 0;

		foreach ($toDelete as $rel) {
			if (update_is_excluded($rel)) {
				continue;
			}

			$target = $dstUpload . $rel;

			if (is_file($target)) {
				@unlink($target);
				$deleted++;
			}
		}

		// Best-effort cleanup of now-empty engine directories.
		update_remove_empty_dirs($dstUpload, $updateDir);

		if ($deleted > 0) {
			update_set_status('sync', 54, 'Pruned ' . $deleted . ' obsolete file(s)', 'Pruned ' . $deleted . ' obsolete file(s)');
		}
	}

	file_put_contents($manifestFile, json_encode($newManifest));
}

function update_remove_empty_dirs(string $dstUpload, string $updateDir): void {
	$base = rtrim($dstUpload, '/');

	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iter as $file) {
		if (!$file->isDir()) {
			continue;
		}

		$rel = ltrim(substr($file->getPathname(), strlen($base)), '/');

		if (update_is_excluded($rel) || $rel === '') {
			continue;
		}

		// Only remove directories that are now empty.
		if (@rmdir($file->getPathname()) === false) {
			continue;
		}
	}
}

/**
 * Mirror the entrypoint's permission fix (SGID dirs, group-writable files,
 * staff group) for the files this updater deployed, so host-side git and
 * editors keep working on them. Only succeeds on files owned by the worker
 * (www-data); everything else is left untouched.
 */
function update_fix_permissions(string $root): void {
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iter as $file) {
		$relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

		if (update_is_excluded($relative) || $relative === '') {
			continue;
		}

		$path = $file->getPathname();

		if ($file->isDir()) {
			@chmod($path, 02775);
		} else {
			@chmod($path, 0664);
		}

		@chgrp($path, 'staff');
	}
}

function update_apply_migrations(DB $db, string $prefix, string $dir): void {
	if (!is_dir($dir)) {
		update_set_status('migrations', 75, 'No migrations directory found', 'Skipping migrations (directory missing)');
		return;
	}

	$table = $prefix . 'schema_migrations';

	$db->query("CREATE TABLE IF NOT EXISTS `{$table}` ("
		. "filename VARCHAR(255) NOT NULL, "
		. "applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, "
		. "PRIMARY KEY (filename)) "
		. "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

	$files = glob($dir . '*.sql');

	if (!$files) {
		update_set_status('migrations', 80, 'No migration files', 'No SQL migrations to apply');
		return;
	}

	sort($files);

	$host = defined('DB_HOSTNAME') ? DB_HOSTNAME : 'mariadb';
	$user = defined('DB_USERNAME') ? DB_USERNAME : 'dockercart';
	$pass = defined('DB_PASSWORD') ? DB_PASSWORD : 'dockercart_password';
	$database = defined('DB_DATABASE') ? DB_DATABASE : 'dockercart';
	$port = defined('DB_PORT') ? (string)DB_PORT : '3306';

	foreach ($files as $file) {
		$filename = basename($file);
		$exists = $db->query("SELECT 1 FROM `{$table}` WHERE filename = '" . $db->escape($filename) . "' LIMIT 1");

		if ($exists->num_rows) {
			update_set_status('migrations', 80, 'Skipping ' . $filename, 'Already applied: ' . $filename);
			continue;
		}

		update_set_status('migrations', 80, 'Applying ' . $filename, 'Applying migration: ' . $filename);

		$cmd = 'MYSQL_PWD=' . escapeshellarg($pass)
			. ' mariadb -h ' . escapeshellarg($host)
			. ' -P ' . escapeshellarg($port)
			. ' -u ' . escapeshellarg($user)
			. ' ' . escapeshellarg($database)
			. ' < ' . escapeshellarg($file) . ' 2>&1';

		exec($cmd, $out, $rc);

		if ($rc !== 0) {
			throw new RuntimeException('Migration failed: ' . $filename . ' - ' . implode("\n", $out));
		}

		$db->query("INSERT INTO `{$table}` (filename) VALUES ('" . $db->escape($filename) . "')");
		update_set_status('migrations', 85, 'Applied ' . $filename, 'Applied: ' . $filename);
	}
}

// ---------------------------------------------------------------------------
// Lock
// ---------------------------------------------------------------------------

$lockHandle = fopen($lockFile, 'c');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
	fwrite(STDERR, "Another update is already running.\n");
	exit(1);
}

register_shutdown_function(static function () use ($lockHandle) {
	@flock($lockHandle, LOCK_UN);
	@fclose($lockHandle);
});

try {
	update_set_status('init', 2, 'Starting update...', 'Update started for ' . $remote . ' @ ' . $branch);

	// Maintenance mode ON (preserve previous value)
	$prev = $db->query("SELECT `value` FROM `{$prefix}setting` WHERE `code`='config' AND `key`='config_maintenance'");
	$prevVal = $prev->num_rows ? (string)$prev->row['value'] : '0';
	file_put_contents($maintenancePrevFile, $prevVal);
	$db->query("UPDATE `{$prefix}setting` SET `value`='1' WHERE `code`='config' AND `key`='config_maintenance'");

	// Download
	update_set_status('download', 10, 'Downloading archive...');
	$archive = $tmpDir . 'archive.tgz';
	$remoteUrl = rtrim($remote, '/') . '/archive/refs/heads/' . $branch . '.tar.gz';
	$cmd = 'curl -fsSL --connect-timeout 15 --max-time 120 ' . escapeshellarg($remoteUrl) . ' -o ' . escapeshellarg($archive) . ' 2>&1';
	exec($cmd, $out, $rc);

	if ($rc !== 0 || !is_file($archive) || filesize($archive) < 100) {
		throw new RuntimeException('Failed to download archive (rc=' . $rc . '): ' . implode("\n", $out));
	}
	update_set_status('download', 20, 'Archive downloaded', 'Downloaded ' . basename($archive));

	// Extract
	update_set_status('extract', 25, 'Extracting archive...');
	$extractCmd = 'tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($tmpDir) . ' 2>&1';
	exec($extractCmd, $out2, $rc2);

	if ($rc2 !== 0) {
		throw new RuntimeException('Failed to extract archive: ' . implode("\n", $out2));
	}

	$items = array_values(array_filter(scandir($tmpDir), static function ($x) use ($tmpDir) {
		return $x !== '.' && $x !== '..' && is_dir($tmpDir . $x);
	}));

	if (empty($items)) {
		throw new RuntimeException('No extracted directory found in archive');
	}

	$srcRoot = $tmpDir . $items[0] . '/';
	update_set_status('extract', 30, 'Extracted', 'Source: ' . $items[0]);

	// Sync upload/
	update_set_status('sync', 35, 'Synchronizing files...');
	$srcUpload = $srcRoot . 'upload/';
	$webroot = dirname(DIR_APPLICATION); // /var/www/html
	$dstUpload = rtrim($webroot, '/') . '/';

	if (!is_dir($srcUpload)) {
		throw new RuntimeException('Archive does not contain an upload/ directory');
	}

	update_sync_directory($srcUpload, $dstUpload);
	update_set_status('sync', 50, 'Files synchronized', 'File sync complete');

	// Prune engine files removed upstream (mirrors `git pull --ff-only`).
	// Safe deletion: only files previously tracked in our manifest may be
	// removed. User-added files (never in the manifest) are never touched.
	update_set_status('sync', 53, 'Pruning obsolete files...');
	update_prune_obsolete($srcUpload, $dstUpload, $updateDir);
	update_set_status('sync', 55, 'Pruning complete', 'Obsolete files pruned');

	// Match the entrypoint's permission scheme (SGID dirs, group-writable
	// files, staff group) for the files just deployed, so host-side git and
	// editors can still modify them after the update.
	update_fix_permissions($dstUpload);

	// Rewrite VERSION in place (inode preserved, bind mount sees new content)
	update_set_status('version', 60, 'Updating VERSION...');
	$newVersion = @file_get_contents($srcRoot . 'VERSION');

	if ($newVersion !== false) {
		$newVersion = trim($newVersion);
		$versionPath = dirname(dirname(DIR_APPLICATION)) . '/VERSION';

		$fh = @fopen($versionPath, 'r+');

		if ($fh) {
			ftruncate($fh, 0);
			fwrite($fh, $newVersion);
			fclose($fh);
			update_set_status('version', 65, 'VERSION updated', 'VERSION set to ' . $newVersion);
		} else {
			update_set_status('version', 65, 'VERSION skipped (not writable)', $versionPath . ' is not writable. Ensure the bind mount is rw (see docker-compose.yml).');
		}
	}

	// Migrations
	update_set_status('migrations', 70, 'Applying database migrations...');
	update_apply_migrations($db, $prefix, $srcRoot . 'docker/mysql/migrations/');
	update_set_status('migrations', 90, 'Migrations applied', 'Database up to date');

	// OCMOD refresh (reuse existing CLI)
	update_set_status('ocmod', 92, 'Refreshing OCMOD modifications...');
	$ocmodCmd = 'php ' . escapeshellarg(__DIR__ . '/dockercart_modification_refresh.php') . ' 2>&1';
	exec($ocmodCmd, $ocmodOut, $ocmodRc);
	update_set_status('ocmod', 96, 'OCMOD refreshed', implode("\n", array_slice($ocmodOut, -3)));

	// Restore maintenance mode
	$restore = @file_get_contents($maintenancePrevFile);
	$restoreVal = ($restore === false) ? '0' : $restore;
	$db->query("UPDATE `{$prefix}setting` SET `value`='" . $db->escape($restoreVal) . "' WHERE `code`='config' AND `key`='config_maintenance'");
	@unlink($maintenancePrevFile);

	$status = update_set_status('done', 100, 'Update complete', 'System update finished successfully.');
	$status['done'] = true;
	file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

	echo "Update complete.\n";
} catch (Throwable $e) {
	$msg = 'ERROR: ' . $e->getMessage();
	$status = update_set_status('error', 0, 'Update failed: ' . $e->getMessage(), $msg);
	$status['error'] = $e->getMessage();
	file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

	try {
		$restore = @file_get_contents($maintenancePrevFile);
		$restoreVal = ($restore === false) ? '0' : $restore;
		$db->query("UPDATE `{$prefix}setting` SET `value`='" . $db->escape($restoreVal) . "' WHERE `code`='config' AND `key`='config_maintenance'");
	} catch (Throwable $ignore) {
	}
	@unlink($maintenancePrevFile);

	fwrite(STDERR, $msg . "\n");
	exit(1);
} finally {
	update_remove_directory($tmpDir);
}
