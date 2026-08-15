<?php
declare(strict_types=1);

/**
 * DockerCart - GUI "System Update" background worker.
 *
 * Run detached by admin/controller/tool/update.php. Mirrors `make update`:
 * download the remote branch archive, sync upload/ files (VERSION included),
 * apply SQL migrations, refresh OCMOD modifications. Takes a database backup
 * before touching anything. Infrastructure changes (composer.json/lock,
 * Dockerfile, docker-compose) are detected and reported, not applied.
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

$dbHost = defined('DB_HOSTNAME') ? DB_HOSTNAME : 'mariadb';
$dbUser = defined('DB_USERNAME') ? DB_USERNAME : 'dockercart';
$dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : 'dockercart_password';
$dbDatabase = defined('DB_DATABASE') ? DB_DATABASE : 'dockercart';
$dbPort = defined('DB_PORT') ? (string)DB_PORT : '3306';

$updateDir = rtrim(DIR_STORAGE, '/') . '/dockercart_update/';
$tmpDir = $updateDir . 'tmp/';
$backupDir = $updateDir . 'backup/';
$statusFile = $updateDir . 'status.json';
$lockFile = $updateDir . 'lock';
$maintenancePrevFile = $updateDir . 'maintenance_prev';

@mkdir($updateDir, 0775, true);
@mkdir($tmpDir, 0775, true);
@mkdir($backupDir, 0775, true);

$GLOBALS['update_status_file'] = $statusFile;
$GLOBALS['update_warnings'] = [];

function update_status_atomic_write(array $status): void {
	$file = $GLOBALS['update_status_file'];
	$json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	$tmp = $file . '.tmp';

	@file_put_contents($tmp, $json);

	// rename() is atomic on the same filesystem, so the admin poll never reads
	// a half-written status file. Fall back to a direct write if it fails.
	if (!@rename($tmp, $file)) {
		@file_put_contents($file, $json);
		@unlink($tmp);
	}
}

function update_set_status(string $step, int $percent, string $message, ?string $appendLog = null): array {
	static $status = null;

	if ($status === null) {
		$status = [
			'step'       => '',
			'percent'    => 0,
			'message'    => '',
			'log'        => [],
			'warning'    => [],
			'done'       => false,
			'error'      => null,
			'pid'        => getmypid(),
			'started_at' => date('c')
		];
	}

	if (!empty($GLOBALS['update_warnings']) && is_array($GLOBALS['update_warnings'])) {
		$status['warning'] = array_values(array_unique($GLOBALS['update_warnings']));
	}

	$status['step'] = $step;
	$status['percent'] = $percent;
	$status['message'] = $message;

	if ($appendLog !== null) {
		$status['log'][] = '[' . date('H:i:s') . '] ' . $appendLog;
	}

	update_status_atomic_write($status);

	return $status;
}

function update_set_warning(string $warning): void {
	$GLOBALS['update_warnings'][] = $warning;
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

/**
 * Copies every file under $src into $dst, writing each target atomically
 * (temp file + rename) so concurrent web requests never see a half-written
 * file. Returns the number of files successfully copied.
 */
function update_sync_directory(string $src, string $dst): int {
	$count = 0;
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

			$tmp = $target . '.upd-tmp';

			if (@copy($file->getPathname(), $tmp)) {
				if (@rename($tmp, $target)) {
					$count++;
				} else {
					@unlink($tmp);
				}
			}
		}
	}

	return $count;
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

/**
 * Keeps only the $keep most recent pre-update dumps in the backup dir.
 */
function update_prune_backups(string $dir, int $keep): void {
	$files = glob($dir . 'pre-update-*.sql.gz');

	if (!$files || count($files) <= $keep) {
		return;
	}

	sort($files);

	foreach (array_slice($files, 0, count($files) - $keep) as $file) {
		@unlink($file);
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

	// Database backup BEFORE any change (restore from
	// storage/dockercart_update/backup/ if something goes wrong).
	update_set_status('backup', 5, 'Backing up database...');
	$backupFile = $backupDir . 'pre-update-' . date('Ymd-His') . '.sql.gz';
	$backupCmd = 'MYSQL_PWD=' . escapeshellarg($dbPass)
		. ' mysqldump --no-tablespaces --single-transaction --skip-lock-tables -h ' . escapeshellarg($dbHost)
		. ' -P ' . escapeshellarg($dbPort)
		. ' -u ' . escapeshellarg($dbUser)
		. ' ' . escapeshellarg($dbDatabase)
		. ' | gzip -9 > ' . escapeshellarg($backupFile) . ' 2>&1';
	exec($backupCmd, $backupOut, $backupRc);

	if ($backupRc !== 0 || !is_file($backupFile) || filesize($backupFile) < 100) {
		throw new RuntimeException('Database backup failed (rc=' . $backupRc . '): ' . implode("\n", $backupOut));
	}

	update_set_status('backup', 8, 'Database backed up', 'Backup: ' . basename($backupFile));
	update_prune_backups($backupDir, 3);

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

	$synced = update_sync_directory($srcUpload, $dstUpload);

	if ($synced === 0) {
		throw new RuntimeException('File sync copied 0 files - the webroot is likely not writable by www-data');
	}

	update_set_status('sync', 50, 'Files synchronized (' . $synced . ' files)', 'File sync complete: ' . $synced . ' files');

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

	// VERSION lives in upload/ and was synced above; verify it landed and
	// matches the archive (sync failures are silent, so check explicitly).
	update_set_status('version', 60, 'Verifying VERSION...');
	$versionPath = rtrim($webroot, '/') . '/VERSION';
	$deployed = @file_get_contents($versionPath);
	$expected = trim((string)@file_get_contents($srcRoot . 'upload/VERSION'));

	if ($expected === '') {
		// Backwards compatibility with archives that still carry VERSION at the repo root.
		$expected = trim((string)@file_get_contents($srcRoot . 'VERSION'));
	}

	if ($expected === '') {
		throw new RuntimeException('Archive does not contain a VERSION file');
	}

	if ($deployed === false || trim($deployed) !== $expected) {
		update_set_warning('The VERSION file was not deployed (' . ($deployed === false ? 'missing' : 'stale') . '). The webroot is probably not writable by www-data.');
		update_set_status('version', 65, 'VERSION check failed', 'Expected ' . $expected . ', found ' . ($deployed === false ? 'missing' : trim($deployed)));
	} else {
		update_set_status('version', 65, 'VERSION verified', 'VERSION set to ' . $expected);
	}

	// Detect dependency/infrastructure changes the GUI cannot apply: the
	// root-level composer files are mounted read-only, so a changed lock file
	// means the storage/vendor deps and the image are out of date.
	foreach (['composer.json', 'composer.lock'] as $composerFile) {
		if (is_file('/var/www/' . $composerFile) && is_file($srcRoot . $composerFile)) {
			if (md5_file('/var/www/' . $composerFile) !== md5_file($srcRoot . $composerFile)) {
				update_set_warning($composerFile . ' in this release differs from the deployed one. Dependencies are NOT installed by the GUI - run `make update` on the host to sync them and rebuild the image.');
			}
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
	update_status_atomic_write($status);

	echo "Update complete.\n";
} catch (Throwable $e) {
	$msg = 'ERROR: ' . $e->getMessage();
	$status = update_set_status('error', 0, 'Update failed: ' . $e->getMessage(), $msg);
	$status['error'] = $e->getMessage();
	update_status_atomic_write($status);

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
