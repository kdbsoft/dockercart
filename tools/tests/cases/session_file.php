<?php
/**
 * File session adaptor: GC contract, file mode and mtime semantics.
 *
 * The adaptor is loaded directly (no framework bootstrap) and pointed at a
 * throwaway directory, so the suite never touches storage/session.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!defined('DIR_SESSION')) {
	$session_dir = sys_get_temp_dir() . '/dockercart_session_' . getmypid() . '/';

	if (!is_dir($session_dir)) {
		mkdir($session_dir, 0700, true);
	}

	define('DIR_SESSION', $session_dir);
}

require_once DIR_SYSTEM . 'library/session/file.php';

/**
 * Fresh empty session directory (with trailing slash, like DIR_SESSION).
 */
function session_test_dir(): string {
	$dir = sys_get_temp_dir() . '/dockercart_session_case_' . bin2hex(random_bytes(6)) . '/';

	mkdir($dir, 0700, true);

	return $dir;
}

function session_test_cleanup(string $dir): void {
	foreach (glob($dir . '*') ?: array() as $file) {
		@chmod($file, 0600);
		@unlink($file);
	}

	@rmdir($dir);
}

function session_test_file(string $dir, string $id, int $age_seconds = 0): string {
	$file = $dir . 'sess_' . $id;

	file_put_contents($file, serialize(array('customer_id' => 1)));

	if ($age_seconds > 0) {
		touch($file, time() - $age_seconds);
	}

	return $file;
}

return array(
	'session file: collectGarbage removes only stale, unlocked files' => static function (): void {
		$dir = session_test_dir();

		$stale = session_test_file($dir, 'stale_a', 7200);
		$fresh = session_test_file($dir, 'fresh_b', 10);

		// A file held open with an exclusive lock belongs to a live request and
		// must survive even when its mtime is old.
		$locked = session_test_file($dir, 'locked_c', 7200);
		$handle = fopen($locked, 'r');
		Assert::true(flock($handle, LOCK_EX | LOCK_NB), 'test setup must be able to lock the session file');

		$removed = \Session\File::collectGarbage($dir, 3600);

		Assert::same(1, $removed);
		Assert::false(is_file($stale), 'stale session file must be deleted');
		Assert::true(is_file($fresh), 'fresh session file must survive');
		Assert::true(is_file($locked), 'locked session file must survive');

		flock($handle, LOCK_UN);
		fclose($handle);

		session_test_cleanup($dir);
	},

	'session file: collectGarbage is a no-op without a directory or lifetime' => static function (): void {
		$dir = session_test_dir();

		session_test_file($dir, 'stale_d', 7200);

		Assert::same(0, \Session\File::collectGarbage($dir, 0));
		Assert::same(0, \Session\File::collectGarbage($dir . 'missing/', 3600));
		Assert::same(1, count(glob($dir . 'sess_*') ?: array()), 'nothing may be deleted');

		session_test_cleanup($dir);
	},

	'session file: a new session file is not world-readable' => static function (): void {
		$adaptor = new \Session\File();
		$id = bin2hex(random_bytes(13));

		Assert::same(array(), $adaptor->read($id));

		$file = DIR_SESSION . 'sess_' . $id;

		Assert::true(is_file($file), 'read() must create the session file');
		Assert::same(0640, fileperms($file) & 0777, 'session files must stay owner+group only');

		@unlink($file);
	},

	'session file: read() refreshes mtime so an active session is not collected' => static function (): void {
		$adaptor = new \Session\File();
		$id = bin2hex(random_bytes(13));
		$file = DIR_SESSION . 'sess_' . $id;

		file_put_contents($file, serialize(array('customer_id' => 7)));
		touch($file, time() - 90000);

		$data = $adaptor->read($id);

		Assert::same(array('customer_id' => 7), $data);
		Assert::same(0, \Session\File::collectGarbage(DIR_SESSION, 86400), 'an active session must not be collected');

		@unlink($file);
	},

	'session file: a corrupt payload reads as an empty session' => static function (): void {
		$adaptor = new \Session\File();
		$id = bin2hex(random_bytes(13));
		$file = DIR_SESSION . 'sess_' . $id;

		file_put_contents($file, 'not-a-serialized-array');

		Assert::same(array(), $adaptor->read($id));

		@unlink($file);
	},

	'session gc: the scheduler task points at an existing worker' => static function (): void {
		$migration = REPO_ROOT . '/docker/mysql/migrations/20261127_session_gc_task.sql';

		Assert::true(is_file($migration), 'session_gc migration must exist');

		$sql = file_get_contents($migration);

		Assert::contains("'session_gc'", $sql);
		Assert::contains('php /var/www/html/bin/dockercart_session_gc.php', $sql);
		Assert::contains('is_system', $sql);

		$worker = REPO_ROOT . '/upload/bin/dockercart_session_gc.php';

		Assert::true(is_file($worker), 'session_gc worker must exist');
		Assert::contains('Session\\File::collectGarbage', file_get_contents($worker));
	},

	'session gc: the worker purges oc_session as well' => static function (): void {
		$worker = file_get_contents(REPO_ROOT . '/upload/bin/dockercart_session_gc.php');

		// The storefront config pins the db engine, so the janitor has to cover
		// both stores even when SESSION_ENGINE=file.
		Assert::contains('DB_PREFIX . "session', $worker);
		Assert::contains('Session\\File::collectGarbage', $worker);

		$index_migration = REPO_ROOT . '/docker/mysql/migrations/20261128_session_expire_index.sql';

		Assert::true(is_file($index_migration), 'oc_session.expire index migration must exist');
		Assert::contains('idx_session_expire', file_get_contents($index_migration));
	},

	'session gc: every app follows SESSION_ENGINE' => static function (): void {
		// The stock OpenCart catalog config pinned 'db', which made the storefront
		// ignore SESSION_ENGINE and pay ~4 DB round trips per page view.
		$catalog = file_get_contents(UPLOAD_DIR . '/system/config/catalog.php');

		Assert::contains("defined('SESSION_ENGINE') ? SESSION_ENGINE", $catalog);
		Assert::notContains("session_engine']     = 'db'", $catalog);
	},

	'session gc: production ini collects outside the request' => static function (): void {
		$ini = file_get_contents(REPO_ROOT . '/docker/php.prod.ini');

		Assert::contains('session.gc_probability = 0', $ini, 'in-request GC must stay off in production');
		Assert::notContains("session.save_handler =", $ini, 'PHP save handler is not used by the app adaptor');
		Assert::notContains("session.save_path =", $ini, 'PHP save path is not used by the app adaptor');
	},

	'session file: write() without a prior read creates a private file' => static function (): void {
		$id = bin2hex(random_bytes(13));
		$file = DIR_SESSION . 'sess_' . $id;

		// rotate()/a fresh adaptor writes a session id whose handle is not held,
		// which goes through the open+lock+rewrite fallback.
		$adaptor = new \Session\File();
		$adaptor->write($id, array('customer_id' => 3));

		Assert::same(serialize(array('customer_id' => 3)), file_get_contents($file));
		Assert::same(0640, fileperms($file) & 0777, 'session files must stay owner+group only');

		$reader = new \Session\File();
		Assert::same(array('customer_id' => 3), $reader->read($id));

		unset($adaptor, $reader);

		@unlink($file);
	}
);
