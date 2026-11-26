<?php
/**
 * File session adaptor: sharded layout, GC contract, file mode and mtime
 * semantics.
 *
 * The adaptor is pointed at a throwaway directory, so the suite never touches
 * storage/session.
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

/**
 * Recursively empties a directory (canonical layout is <a>/<b>/sess_*).
 */
function session_test_prune(string $dir): void {
	foreach (glob($dir . '*', GLOB_NOSORT) ?: array() as $path) {
		if (is_dir($path)) {
			@chmod($path, 0770);
			session_test_prune($path . '/');
			@rmdir($path);

			continue;
		}

		@chmod($path, 0600);
		@unlink($path);
	}
}

function session_test_cleanup(string $dir): void {
	session_test_prune($dir);

	@rmdir($dir);
}

/**
 * A session file in the pre-sharding (legacy) flat layout.
 */
function session_test_file(string $dir, string $id, int $age_seconds = 0): string {
	$file = $dir . 'sess_' . $id;

	file_put_contents($file, serialize(array('customer_id' => 1)));

	if ($age_seconds > 0) {
		touch($file, time() - $age_seconds);
	}

	return $file;
}

/**
 * A session file in the canonical sharded layout, directories created.
 */
function session_test_sharded_file(string $dir, string $id, int $age_seconds = 0): string {
	$file = \Session\File::pathFor($dir, $id);

	@mkdir(dirname($file), 02770, true);

	file_put_contents($file, serialize(array('customer_id' => 1)));

	if ($age_seconds > 0) {
		touch($file, time() - $age_seconds);
	}

	return $file;
}

return array(
	'session file: new sessions land in a 256-bucket shard, not in the root' => static function (): void {
		$dir = session_test_dir();

		// storage/session is 2775 in production: group-write + setgid is what a
		// shard has to keep, because www-data reaches the files through the
		// `staff` group and a root CLI worker has to be able to write too.
		chmod($dir, 02770);

		$id = bin2hex(random_bytes(13));

		$adaptor = new \Session\File($dir);
		$adaptor->write($id, array('customer_id' => 5));

		$file = \Session\File::pathFor($dir, $id);

		Assert::true(is_file($file), 'write() must create the session in its shard');
		Assert::false(is_file($dir . 'sess_' . $id), 'a new session must not be written flat');

		// <a>/<b>/sess_<id>, bucket names derived from md5(id).
		$parts = explode('/', substr($file, strlen($dir)));

		Assert::count(3, $parts);
		Assert::same(substr(md5($id), 0, 1), $parts[0], 'first shard level is the first md5 digit');
		Assert::same(substr(md5($id), 1, 1), $parts[1], 'second shard level is the second md5 digit');
		Assert::same('sess_' . $id, $parts[2]);

		Assert::same(0640, fileperms($file) & 0777, 'session files must stay owner+group only');

		$shard = dirname($file);
		$shard_mode = fileperms($shard);

		Assert::same(0770, $shard_mode & 0777, 'the shard directory must stay group-writable');
		Assert::true((bool)($shard_mode & 02000), 'the shard directory must inherit setgid from storage/session');

		$reader = new \Session\File($dir);

		Assert::same(array('customer_id' => 5), $reader->read($id), 'a sharded session must be readable');

		unset($adaptor, $reader);

		session_test_cleanup($dir);
	},

	'session file: the shard path cannot be walked out of' => static function (): void {
		$dir = session_test_dir();

		$path = \Session\File::pathFor($dir, '../../etc/passwd');

		Assert::false(str_contains($path, '..'), 'a traversal id must be flattened by basename()');
		Assert::same($dir . substr(md5('passwd'), 0, 1) . '/' . substr(md5('passwd'), 1, 1) . '/sess_passwd', $path);

		session_test_cleanup($dir);
	},

	'session file: ids spread over the shards instead of one directory' => static function (): void {
		$dir = session_test_dir();
		$adaptor = new \Session\File($dir);

		$shards = array();

		for ($i = 0; $i < 200; $i++) {
			$id = bin2hex(random_bytes(13));

			$adaptor->write($id, array('i' => $i));

			$shards[dirname(\Session\File::pathFor($dir, $id))] = true;
		}

		Assert::true(count($shards) > 120, '200 ids must fan out over the 256 shard directories, got ' . count($shards));

		unset($adaptor);

		session_test_cleanup($dir);
	},

	'session file: read() leaves no file behind for a missing session' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		$adaptor = new \Session\File($dir);

		Assert::same(array(), $adaptor->read($id));

		Assert::false(is_file(\Session\File::pathFor($dir, $id)), 'read() must not create a session file');
		Assert::false(is_file($dir . 'sess_' . $id), 'read() must not create a flat session file');

		// Session::close() always writes back, even for a read-only request.
		$adaptor->write($id, array());

		Assert::same(array(), glob($dir . '*', GLOB_NOSORT) ?: array(), 'an empty session must not create anything on disk');

		// The first stored value is what creates the file.
		$adaptor->write($id, array('language' => 'en-gb'));

		Assert::true(is_file(\Session\File::pathFor($dir, $id)), 'storing something must create the session file');
		Assert::same(array('language' => 'en-gb'), $adaptor->read($id));

		unset($adaptor);

		session_test_cleanup($dir);
	},

	'session file: a legacy flat session is readable and moved on write' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		$legacy = $dir . 'sess_' . $id;

		file_put_contents($legacy, serialize(array('customer_id' => 9)));
		touch($legacy, time() - 60);

		$adaptor = new \Session\File($dir);

		Assert::same(array('customer_id' => 9), $adaptor->read($id), 'sessions written before the sharded layout must stay readable');

		// No change -> no write -> no migration (read-only requests never write).
		$adaptor->write($id, array('customer_id' => 9));

		Assert::true(is_file($legacy), 'an unchanged legacy session is left where it is');

		$adaptor->write($id, array('customer_id' => 9, 'cart' => 1));

		Assert::false(is_file($legacy), 'the legacy file must be dropped once the shard holds the session');
		Assert::same(serialize(array('customer_id' => 9, 'cart' => 1)), file_get_contents(\Session\File::pathFor($dir, $id)));

		$reader = new \Session\File($dir);

		Assert::same(array('customer_id' => 9, 'cart' => 1), $reader->read($id));

		unset($adaptor, $reader);

		session_test_cleanup($dir);
	},

	'session file: destroy() removes both layouts' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		$legacy = $dir . 'sess_' . $id;
		$sharded = session_test_sharded_file($dir, $id);

		file_put_contents($legacy, serialize(array()));

		$adaptor = new \Session\File($dir);
		$adaptor->destroy($id);

		Assert::false(is_file($sharded), 'the sharded file must be deleted');
		Assert::false(is_file($legacy), 'a legacy file for the same id must be deleted too');

		unset($adaptor);

		session_test_cleanup($dir);
	},

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

	'session gc: walks the sharded tree as well as legacy flat files' => static function (): void {
		$dir = session_test_dir();

		$stale_sharded = session_test_sharded_file($dir, 'sharded_stale_a', 7200);
		$fresh_sharded = session_test_sharded_file($dir, 'sharded_fresh_b', 10);
		$stale_flat = session_test_file($dir, 'flat_stale_c', 7200);
		$fresh_flat = session_test_file($dir, 'flat_fresh_d', 10);

		$locked = session_test_sharded_file($dir, 'sharded_locked_e', 7200);
		$handle = fopen($locked, 'r');
		Assert::true(flock($handle, LOCK_EX | LOCK_NB), 'test setup must be able to lock the session file');

		Assert::same(2, \Session\File::collectGarbage($dir, 3600), 'one stale file per layout must be collected');

		Assert::false(is_file($stale_sharded), 'a stale sharded session must be deleted');
		Assert::false(is_file($stale_flat), 'a stale legacy session must be deleted');
		Assert::true(is_file($fresh_sharded), 'a fresh sharded session must survive');
		Assert::true(is_file($fresh_flat), 'a fresh legacy session must survive');
		Assert::true(is_file($locked), 'a locked sharded session must survive');

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

	'session file: read() refreshes mtime so an active session is not collected' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		$legacy = $dir . 'sess_' . $id;
		file_put_contents($legacy, serialize(array('customer_id' => 7)));
		touch($legacy, time() - 90000);

		$sharded_id = bin2hex(random_bytes(13));
		$sharded = session_test_sharded_file($dir, $sharded_id, 90000);

		$adaptor = new \Session\File($dir);

		Assert::same(array('customer_id' => 7), $adaptor->read($id));
		Assert::same(array('customer_id' => 1), $adaptor->read($sharded_id));

		Assert::same(0, \Session\File::collectGarbage($dir, 86400), 'an active session must not be collected');

		unset($adaptor);

		session_test_cleanup($dir);
	},

	'session file: a corrupt payload reads as an empty session' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		$legacy = $dir . 'sess_' . $id;
		file_put_contents($legacy, 'not-a-serialized-array');

		$sharded_id = bin2hex(random_bytes(13));
		$sharded = \Session\File::pathFor($dir, $sharded_id);
		@mkdir(dirname($sharded), 02770, true);
		file_put_contents($sharded, 'not-a-serialized-array');

		$adaptor = new \Session\File($dir);

		Assert::same(array(), $adaptor->read($id));
		Assert::same(array(), $adaptor->read($sharded_id));

		unset($adaptor);

		session_test_cleanup($dir);
	},

	'session file: write() without a prior read creates a private file' => static function (): void {
		$dir = session_test_dir();
		$id = bin2hex(random_bytes(13));

		// rotate()/a fresh adaptor writes a session id whose handle is not held,
		// which goes through the open+lock+rewrite fallback.
		$adaptor = new \Session\File($dir);
		$adaptor->write($id, array('customer_id' => 3));

		$file = \Session\File::pathFor($dir, $id);

		Assert::same(serialize(array('customer_id' => 3)), file_get_contents($file));
		Assert::same(0640, fileperms($file) & 0777, 'session files must stay owner+group only');

		$reader = new \Session\File($dir);
		Assert::same(array('customer_id' => 3), $reader->read($id));

		unset($adaptor, $reader);

		session_test_cleanup($dir);
	},

	'session file: the default directory comes from DIR_SESSION' => static function (): void {
		// system/framework.php passes the registry as the first constructor
		// argument; a non-string must fall back to the constant, never be used
		// as a path.
		$registry = new \stdClass();

		$adaptor = new \Session\File($registry);
		$id = bin2hex(random_bytes(13));

		$adaptor->write($id, array('customer_id' => 4));

		$file = \Session\File::pathFor(DIR_SESSION, $id);

		Assert::true(is_file($file), 'a non-string constructor argument must fall back to DIR_SESSION');

		@unlink($file);
		@rmdir(dirname($file));
		@rmdir(dirname($file, 2));

		unset($adaptor);
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
	}
);
