<?php
namespace Session;

class File {
	/**
	 * How long to wait for a session lock held by a concurrent same-session
	 * request before giving up and proceeding unlocked (best-effort: a slow
	 * endpoint must never hang the request). Mirrors the GET_LOCK timeout of
	 * the DB adaptor.
	 */
	const LOCK_TIMEOUT = 10;

	/**
	 * Mode of a session file: owner + group, never world. The payload is the
	 * authentication state (customer_id, admin user_id, cart), so it must not
	 * be readable by every local user of a shared host; group write is still
	 * required because www-data may reach the file through the `staff` group
	 * (see the permission invariant in docker/entrypoint.sh).
	 */
	const FILE_MODE = 0640;

	/**
	 * Mode of a shard directory. mkdir() masks the mode with the process umask
	 * (022 -> 0750: no group write), so the umask is cleared around the call
	 * and every level ends up group-writable. The setgid bit cannot be requested
	 * through the mode either — Linux only inherits it from a setgid parent, and
	 * that is exactly what happens here: `storage/session` is 2775 (see the
	 * permission invariant in docker/entrypoint.sh), and chmod() is deliberately
	 * avoided because a process outside the directory's group (root in a user
	 * namespace, a CLI worker) has its chmod drop the inherited bit again.
	 */
	const DIRECTORY_MODE = 02770;

	/**
	 * Shard levels of 16 buckets each: `md5(id)` gives two hex digits, so the
	 * logic is 16 × 16 = 256 leaf directories — the same shape as PHP's own
	 * `session.save_path = "2;/path"`.
	 *
	 * Why: a busy storefront creates one session per client, and a flat
	 * directory then holds as many entries as sessions touched within
	 * `session.gc_maxlifetime` (86400 s → one day of sessions). That is
	 * harmless at 10k, unpleasant at 200k (glob() alone allocates ~20 MB and
	 * every `unlink()` competes with the whole directory index) and fatal
	 * around a million. 256 buckets divide the count by 256 and keep every
	 * directory-level operation local to one shard.
	 */
	const SHARD_LEVELS = 2;

	private $directory;

	/**
	 * Path of the file the locked handle below belongs to (the sharded target
	 * or, for a session not rewritten since the sharded layout was introduced,
	 * its legacy flat predecessor).
	 */
	private $file = '';

	/**
	 * Handle locked (LOCK_EX) for the current session id, held for the whole
	 * request until write(). All reads and writes go through it, so concurrent
	 * requests on the same session serialise instead of racing the file.
	 */
	private $handle = null;

	/**
	 * Session id the handle above is locked for.
	 */
	private $session_id = '';

	/**
	 * Raw payload exactly as read; used to skip write-backs that would not
	 * change anything.
	 */
	private $raw = null;

	/**
	 * @param	mixed	$directory	storage directory with a trailing slash; only a
	 *								non-empty string is used (system/framework.php
	 *								passes the registry to every adaptor), the
	 *								DIR_SESSION constant is the default
	 */
	public function __construct($directory = null) {
		if (is_string($directory) && $directory !== '') {
			$this->directory = rtrim($directory, '/') . '/';
		} else {
			$this->directory = defined('DIR_SESSION') ? rtrim(DIR_SESSION, '/') . '/' : '';
		}
	}

	/**
	 * Canonical path of a session: `<a>/<b>/sess_<id>` where `a`/`b` are the
	 * first two hex digits of md5(id). Hashing (instead of cutting the id) keeps
	 * the layout defined for every id the Session class accepts
	 * (`[a-zA-Z0-9,\-]{22,52}`) and cannot produce a path separator.
	 *
	 * @param	string	$directory		with or without a trailing slash
	 * @param	string	$session_id
	 * @return	string
	 */
	public static function pathFor($directory, $session_id) {
		$id = basename($session_id);
		$hash = md5($id);
		$path = rtrim($directory, '/') . '/';

		for ($i = 0; $i < self::SHARD_LEVELS; $i++) {
			$path .= $hash[$i] . '/';
		}

		return $path . 'sess_' . $id;
	}

	/**
	 * Flat `sess_<id>` at the top of the directory — where releases before the
	 * sharded layout put every session. Still readable, never written.
	 */
	private function legacyPath($session_id) {
		return $this->directory . 'sess_' . basename($session_id);
	}

	public function read($session_id) {
		$this->closeHandle();

		$target = self::pathFor($this->directory, $session_id);
		$legacy = $this->legacyPath($session_id);

		$file = $target;

		// A session that has not been rewritten since the upgrade still lives
		// flat. Read it from there; write() moves it into its shard the first
		// time the request actually changes something.
		if (!is_file($target) && is_file($legacy)) {
			$file = $legacy;
		}

		// A missing session is deliberately NOT created here. Crawlers, probes
		// and prefetches arrive without the session cookie, so a file per
		// request is pure garbage; a session that never holds anything is
		// indistinguishable from a missing one. The file (and the lock) appear
		// in write(), on the first request that stores something.
		if (!is_file($file)) {
			$this->raw = '';

			return array();
		}

		// 'c+' creates the file when a concurrent session_gc run removed it
		// between the check above and here, without truncating a live one.
		$handle = @fopen($file, 'c+');

		if (!$handle) {
			return array();
		}

		$stat = fstat($handle);

		if (!$stat) {
			fclose($handle);

			return array();
		}

		// A file this method had to create (a concurrent session_gc run removed
		// it in between) comes from fopen(), so it needs the session mode.
		// Sessions created by older releases are covered by the entrypoint's
		// world-bit strip at boot.
		if ($stat['size'] === 0) {
			@chmod($file, self::FILE_MODE);
		}

		// Refresh mtime on every read. PHP's own files handler rewrites the
		// session each request, so `session.gc_maxlifetime` behaves as an idle
		// timeout; this adapter deliberately skips unchanged write-backs (see
		// write()), which would turn it into an absolute lifetime and log an
		// active operator out after a day.
		@touch($file);

		$this->lock($handle);

		$this->handle = $handle;
		$this->file = $file;
		$this->session_id = $session_id;

		if (empty($stat['size'])) {
			$this->raw = '';

			return array();
		}

		$data = '';

		while (!feof($handle)) {
			$data .= fread($handle, 8192);
		}

		$this->raw = $data;

		// Corrupt or truncated session files (disk full, legacy damage) used to
		// surface as "unserialize(): Extra data" warnings and a non-array return
		// that broke consumers. Treat them as empty sessions, quietly — a
		// damaged file is a logout, not an error worth a log line per request.
		$unserialized = @unserialize($data, array('allowed_classes' => false));
		if (!is_array($unserialized)) {
			return array();
		}

		return $unserialized;
	}

	public function write($session_id, $data) {
		if (!$session_id) {
			$this->closeHandle();

			return true;
		}

		$serialized = serialize($data);
		$target = self::pathFor($this->directory, $session_id);
		$held = ($this->handle && $session_id === $this->session_id) ? $this->file : '';

		// Nothing to remember: a read-only request whose session did not exist
		// (a crawler, a probe, a prefetch) must not leave a file behind. This
		// is where a bot flood used to turn into one session file per request.
		if ($serialized === 'a:0:{}' && !is_file($target) && ($held === '' || !is_file($held))) {
			$this->closeHandle();

			return true;
		}

		// Skip the write-back when the session was read but not modified.
		// Under parallel requests (e.g. the admin dashboard fires a dozen
		// widgets at once) most requests never touch the session, and a
		// read-only request must never overwrite newer data with the stale
		// copy it read before it had to proceed unlocked. It also leaves a
		// legacy flat file where it is: no change, nothing to migrate.
		if ($held !== '' && $serialized === $this->raw) {
			$this->closeHandle();

			return true;
		}

		// Normal path: the request holds the locked handle for this session id,
		// so rewriting through it is atomic with respect to any other request of
		// the same session.
		if ($held !== '' && $held === $target) {
			ftruncate($this->handle, 0);
			fseek($this->handle, 0);

			fwrite($this->handle, $serialized);
			fflush($this->handle);

			$this->closeHandle();

			return true;
		}

		// Fallback: writing a session id whose handle is not held (rotate() or a
		// fresh adaptor), or moving a legacy flat session into its shard. Open,
		// lock, rewrite, release.
		$written = $this->writeFile($target, $serialized);

		if ($held !== '' && $held !== $target) {
			$this->closeHandle();
		}

		// The shard now holds this session id, so a flat predecessor is dead
		// weight: the sharded copy is read first and the flat one would only
		// come back after the shard was collected. Dropped on every write, not
		// just on the migrating one, because a previous write may have released
		// the handle on the legacy file already.
		$legacy = $this->legacyPath($session_id);

		if ($written && $legacy !== $target && is_file($legacy)) {
			@unlink($legacy);
		}

		return $written;
	}

	public function destroy($session_id) {
		$this->closeHandle($session_id);

		foreach (array_unique(array(self::pathFor($this->directory, $session_id), $this->legacyPath($session_id))) as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}

		return true;
	}

	/**
	 * Creates the shard directory when needed and rewrites the session file
	 * under an exclusive lock.
	 *
	 * @param	string	$file		full path of the session file
	 * @param	string	$serialized
	 * @return	bool
	 */
	private function writeFile($file, $serialized) {
		if (!$this->makeDirectory(dirname($file))) {
			return false;
		}

		$exists = is_file($file);

		$handle = @fopen($file, 'c+');

		if (!$handle) {
			return false;
		}

		if (!$exists) {
			@chmod($file, self::FILE_MODE);
		}

		$this->lock($handle);

		ftruncate($handle, 0);
		fseek($handle, 0);

		fwrite($handle, $serialized);
		fflush($handle);

		flock($handle, LOCK_UN);
		fclose($handle);

		return true;
	}

	/**
	 * Creates a shard directory (with its two levels) group-writable, whatever
	 * umask the request runs with.
	 *
	 * @param	string	$directory
	 * @return	bool
	 */
	private function makeDirectory($directory) {
		if (is_dir($directory)) {
			return true;
		}

		$umask = umask(0);

		$created = @mkdir($directory, self::DIRECTORY_MODE, true);

		umask($umask);

		return $created || is_dir($directory);
	}

	/**
	 * Releases the request-lifetime lock and handle, if any.
	 *
	 * @param	string	$session_id	only release when the held session id matches; empty matches anything
	 */
	private function closeHandle($session_id = '') {
		if ($this->handle === null) {
			return;
		}

		if ($session_id !== '' && $session_id !== $this->session_id) {
			return;
		}

		flock($this->handle, LOCK_UN);
		fclose($this->handle);

		$this->handle = null;
		$this->file = '';
		$this->session_id = '';
		$this->raw = null;
	}

	/**
	 * Acquires LOCK_EX on a session file handle, waiting up to
	 * LOCK_TIMEOUT for a concurrent same-session request to finish.
	 * On timeout the request proceeds unlocked: it then reads a valid
	 * (whole-file) copy, and read-only requests skip the write-back, so
	 * the worst case is a lost update rather than a wiped session.
	 */
	private function lock($handle) {
		$deadline = microtime(true) + self::LOCK_TIMEOUT;

		while (!flock($handle, LOCK_EX | LOCK_NB)) {
			if (microtime(true) >= $deadline) {
				return;
			}

			usleep(100000);
		}
	}

	/**
	 * Removes session files older than $maxlifetime (mtime based, like PHP's
	 * files handler) and returns how many were deleted.
	 *
	 * This is the single GC implementation: production runs it from the
	 * `session_gc` scheduler task (bin/dockercart_session_gc.php), because
	 * globbing the whole session directory inside an arbitrary request is an
	 * O(number of sessions) latency spike on a busy shop. The probabilistic
	 * in-request run below stays as a dev-only fallback (session.gc_probability
	 * is 0 in docker/php.prod.ini).
	 *
	 * @param	string	$directory		with a trailing slash (DIR_SESSION)
	 * @param	int		$maxlifetime	seconds; 0 or less disables the run
	 * @return	int
	 */
	public static function collectGarbage($directory, $maxlifetime) {
		$maxlifetime = (int)$maxlifetime;

		if ($maxlifetime <= 0 || !is_dir($directory)) {
			return 0;
		}

		$expire = time() - $maxlifetime;
		$removed = 0;

		foreach (self::sessionFiles(rtrim($directory, '/') . '/') as $file) {
			if (!is_file($file)) {
				continue;
			}

			clearstatcache(true, $file);

			if (filemtime($file) >= $expire) {
				continue;
			}

			// Never remove a session file that is currently locked by a
			// live request: an in-flight session must not disappear.
			$handle = @fopen($file, 'r');

			if ($handle) {
				$in_use = !flock($handle, LOCK_EX | LOCK_NB);

				flock($handle, LOCK_UN);
				fclose($handle);

				if ($in_use) {
					continue;
				}
			}

			if (@unlink($file)) {
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * Yields every session file of the directory: the legacy flat ones at the
	 * top level, then the sharded `<a>/<b>/sess_*`. One level of globbing at a
	 * time, so the peak memory stays at a single level's worth of names instead
	 * of doubling when both layouts are merged into one array.
	 *
	 * @param	string	$directory		with a trailing slash
	 * @return	\Generator
	 */
	private static function sessionFiles($directory) {
		$files = glob($directory . 'sess_*');

		if ($files) {
			foreach ($files as $file) {
				yield $file;
			}
		}

		$files = glob($directory . str_repeat('*/', self::SHARD_LEVELS) . 'sess_*', GLOB_NOSORT);

		if ($files) {
			foreach ($files as $file) {
				yield $file;
			}
		}
	}

	public function __destruct() {
		$this->closeHandle();

		$gc_divisor = ini_get('session.gc_divisor');
		$gc_divisor = ($gc_divisor === false || $gc_divisor === '') ? 1 : (int)$gc_divisor;

		$gc_probability = ini_get('session.gc_probability');
		$gc_probability = ($gc_probability === false || $gc_probability === '') ? 1 : (int)$gc_probability;

		if ($gc_probability > 0 && $gc_divisor > 0 && (rand() % $gc_divisor) < $gc_probability) {
			self::collectGarbage(defined('DIR_SESSION') ? DIR_SESSION : '', (int)ini_get('session.gc_maxlifetime'));
		}
	}
}
