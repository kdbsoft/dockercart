#!/usr/bin/env php
<?php
/**
 * DockerCart Backup to S3 — CLI Worker
 *
 * Dumps the database (mariadb-dump) and tars ./upload/image +
 * ./storage/download + ./storage/modification into a single .tar.gz,
 * uploads it to S3 (or S3-compatible) via rclone, then deletes local
 * staging files. Old S3 objects older than BACKUP_S3_RETENTION_DAYS are
 * deleted automatically.
 *
 * Registered by migration 20260706_register_backup_s3_task.sql as a
 * singleton scheduler task `backup_s3`. Toggle/schedule in admin:
 * System → Scheduler. Credentials come from BACKUP_S3_* env vars (see
 * .env.example); rclone config is generated at container start by
 * docker/entrypoint.sh (ensure_rclone_config).
 *
 * Exit codes:
 *   0 — success (or no-op when BACKUP_S3_ENABLED != true)
 *   1 — failure (missing creds, dump/tar/upload error, no disk space)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$config_path = __DIR__ . '/../admin/config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[backup-s3] ERROR: admin/config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;
require_once DIR_SYSTEM . 'startup.php';

// Minimal bootstrap — only need DB to update last_result.
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

$env = static function (string $key, string $default = ''): string {
	$value = getenv($key);

	return ($value === false || $value === '') ? $default : $value;
};

$log = static function (string $msg): void {
	echo '[' . date('Y-m-d H:i:s') . '] [backup-s3] ' . $msg . "\n";
};

$set_result = static function (array $data) use ($db): void {
	$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	$db->query(
		"UPDATE `" . DB_PREFIX . "dockercart_scheduler_task`
		 SET `last_result` = '" . $db->escape($json) . "',
			 `date_modified` = NOW()
		 WHERE `task_type` = 'backup_s3'"
	);
};

// === Gate: enabled flag ============================================ ==
$enabled = strtolower($env('BACKUP_S3_ENABLED', 'false'));

if ($enabled !== 'true' && $enabled !== '1' && $enabled !== 'yes') {
	$log('BACKUP_S3_ENABLED is not true — no-op.');
	$set_result(['status' => 'disabled', 'ts' => date('Ymd_His')]);
	exit(0);
}

// === Gate: required credentials ==================================== ==
$bucket     = $env('BACKUP_S3_BUCKET');
$access_key = $env('BACKUP_S3_ACCESS_KEY_ID');
$secret_key = $env('BACKUP_S3_SECRET_ACCESS_KEY');

if ($bucket === '' || $access_key === '' || $secret_key === '') {
	$log('ERROR: BACKUP_S3_BUCKET / BACKUP_S3_ACCESS_KEY_ID / BACKUP_S3_SECRET_ACCESS_KEY must be set.');
	$set_result(['status' => 'error', 'error' => 'missing_credentials', 'ts' => date('Ymd_His')]);
	exit(1);
}

// === Gate: rclone binary + config ================================== ==
if (trim((string) shell_exec('command -v rclone 2>/dev/null')) === '') {
	$log('ERROR: rclone binary not found in PATH.');
	$set_result(['status' => 'error', 'error' => 'rclone_missing', 'ts' => date('Ymd_His')]);
	exit(1);
}

$rc_config = getenv('RCLONE_CONFIG');

if ($rc_config === false || $rc_config === '' || !is_file($rc_config)) {
	$log('ERROR: RCLONE_CONFIG env not set or file missing (entrypoint should generate it).');
	$set_result(['status' => 'error', 'error' => 'rclone_config_missing', 'ts' => date('Ymd_His')]);
	exit(1);
}

// === Params ======================================================== ==
$path           = $env('BACKUP_S3_PATH', 'dockercart/backups');
$retention_days = max(1, (int) $env('BACKUP_S3_RETENTION_DAYS', '7'));
$staging_dir    = '/var/www/storage/backup';
$ts             = date('Ymd_His');
$sql_file       = "{$staging_dir}/db_{$ts}.sql";
$tar_file       = "{$staging_dir}/dockercart_{$ts}.tar.gz";
$remote_dir     = "s3:{$bucket}/{$path}";
$s3_key         = "{$path}/dockercart_{$ts}.tar.gz";
$remote_file    = "s3:{$bucket}/{$s3_key}";

// === Gate: free disk space (>= 1 GB) =============================== ==
if (!is_dir($staging_dir)) {
	if (!@mkdir($staging_dir, 0775, true) && !is_dir($staging_dir)) {
		$log("ERROR: cannot create staging dir {$staging_dir}.");
		$set_result(['status' => 'error', 'error' => 'cannot_create_staging', 'ts' => $ts]);
		exit(1);
	}
}

$free = @disk_free_space($staging_dir);

if ($free !== false && $free < 1073741824) {
	$free_mb = round($free / 1048576, 2);
	$log("ERROR: insufficient disk space on {$staging_dir} ({$free_mb} MB free, need >= 1 GB).");
	$set_result(['status' => 'error', 'error' => 'insufficient_disk', 'free_mb' => $free_mb, 'ts' => $ts]);
	exit(1);
}

$log("Starting backup: ts={$ts} bucket={$bucket} path={$path} retention={$retention_days}d");

// === 1. DB dump ==================================================== ==
$dump_err = tempnam('/tmp', 'dump_');
$dump_cmd = sprintf(
	'MYSQL_PWD=%s mariadb-dump --single-transaction --quick --hex-blob --routines --triggers --events --skip-ssl -h%s -u%s %s > %s 2> %s',
	escapeshellarg(DB_PASSWORD),
	escapeshellarg(DB_HOSTNAME),
	escapeshellarg(DB_USERNAME),
	escapeshellarg(DB_DATABASE),
	escapeshellarg($sql_file),
	escapeshellarg($dump_err)
);

$log('Running mariadb-dump...');
exec($dump_cmd, $o, $rc);

if ($rc !== 0 || !is_file($sql_file) || filesize($sql_file) === 0) {
	$err = is_file($dump_err) ? trim(file_get_contents($dump_err)) : '';
	if ($err === '') {
		$err = 'empty dump output';
	}
	$log("ERROR: mariadb-dump failed (exit={$rc}): {$err}");
	$set_result(['status' => 'error', 'error' => 'dump_failed', 'detail' => $err, 'ts' => $ts]);
	@unlink($sql_file);
	@unlink($dump_err);
	exit(1);
}
@unlink($dump_err);

$db_size = filesize($sql_file);
$log('DB dump OK: ' . round($db_size / 1048576, 2) . ' MB');

// === 2. tar.gz ===================================================== ==
// Multiple -C options switch the working dir for the following arguments,
// so the archive stores: db_<ts>.sql, image/, download/, modification/.
$tar_err = tempnam('/tmp', 'tar_');
$tar_cmd = sprintf(
	'tar -czf %s -C %s %s -C /var/www/html image -C /var/www/storage download modification 2> %s',
	escapeshellarg($tar_file),
	escapeshellarg($staging_dir),
	escapeshellarg('db_' . $ts . '.sql'),
	escapeshellarg($tar_err)
);

$log('Creating tar.gz...');
exec($tar_cmd, $o, $rc);

if ($rc !== 0 || !is_file($tar_file) || filesize($tar_file) === 0) {
	$err = is_file($tar_err) ? trim(file_get_contents($tar_err)) : '';
	$log("ERROR: tar failed (exit={$rc}): {$err}");
	$set_result(['status' => 'error', 'error' => 'tar_failed', 'detail' => $err, 'ts' => $ts]);
	@unlink($sql_file);
	@unlink($tar_file);
	@unlink($tar_err);
	exit(1);
}
@unlink($tar_err);

$tar_size = filesize($tar_file);
$log('tar.gz OK: ' . round($tar_size / 1048576, 2) . ' MB');
@unlink($sql_file);

// === 3. Upload to S3 via rclone ==================================== ==
$log("Uploading to {$remote_file}...");
$up_cmd = sprintf('rclone copyto %s %s 2>&1', escapeshellarg($tar_file), escapeshellarg($remote_file));
$up_out = [];
exec($up_cmd, $up_out, $rc);

if ($rc !== 0) {
	$err = trim(implode("\n", $up_out));
	$log("ERROR: rclone copyto failed (exit={$rc}): {$err}");
	$set_result([
		'status'      => 'error',
		'error'       => 'upload_failed',
		'detail'      => $err,
		'local_file'  => $tar_file,
		'ts'          => $ts,
	]);
	// Keep the local tar.gz for manual recovery / re-upload.
	exit(1);
}
$log('Upload OK.');

// === 4. Retention: delete S3 objects older than N days ============= ==
$deleted = [];
$log("Checking for old backups (older than {$retention_days}d) in {$remote_dir}...");
$ls_cmd = sprintf('rclone lsf %s --min-age %dd 2>&1', escapeshellarg($remote_dir), $retention_days);
$ls_out = [];
exec($ls_cmd, $ls_out, $rc);

if ($rc !== 0) {
	$log('WARNING: rclone lsf failed (exit=' . $rc . '), skipping retention: ' . trim(implode("\n", $ls_out)));
} else {
	foreach ($ls_out as $line) {
		$line = trim($line);

		if ($line === '' || strpos($line, 'dockercart_') !== 0 || substr($line, -7) !== '.tar.gz') {
			continue;
		}

		$del_remote = "s3:{$bucket}/{$path}/{$line}";
		$del_cmd = sprintf('rclone deletefile %s 2>&1', escapeshellarg($del_remote));
		$del_out = [];
		exec($del_cmd, $del_out, $rc);

		if ($rc === 0) {
			$deleted[] = $line;
			$log("Deleted old backup: {$line}");
		} else {
			$log('WARNING: failed to delete ' . $line . ': ' . trim(implode("\n", $del_out)));
		}
	}
}

// === 5. Cleanup local staging ===================================== ==
@unlink($tar_file);

// === 6. Final result =============================================== ==
$set_result([
	'status'            => 'ok',
	'ts'                => $ts,
	'size_bytes'        => $tar_size,
	'size_mb'           => round($tar_size / 1048576, 2),
	'db_size_bytes'     => $db_size,
	's3_key'            => $s3_key,
	'retention_deleted' => $deleted,
]);

$log('Backup complete. size=' . round($tar_size / 1048576, 2) . ' MB, deleted=' . count($deleted) . ' old.');

exit(0);
