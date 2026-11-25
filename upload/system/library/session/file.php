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

	private $directory;

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

	public function read($session_id) {
		$this->closeHandle();

		$file = DIR_SESSION . 'sess_' . basename($session_id);

		// 'c+' creates the file for a new session without truncating an
		// existing one. The exclusive lock is held until write() so the
		// request never races another one on the same session file, like
		// PHP's built-in files session handler does.
		$exists = is_file($file);

		$handle = @fopen($file, 'c+');

		if (!$handle) {
			return array();
		}

		if (!$exists) {
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
		$this->session_id = $session_id;

		clearstatcache(true, $file);

		$stat = fstat($handle);

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

		// Skip the write-back when the session was read but not modified.
		// Under parallel requests (e.g. the admin dashboard fires a dozen
		// widgets at once) most requests never touch the session, and a
		// read-only request must never overwrite newer data with the stale
		// copy it read before it had to proceed unlocked.
		if ($this->handle && $session_id === $this->session_id && $serialized === $this->raw) {
			$this->closeHandle();

			return true;
		}

		// Normal path: the request holds the locked handle for this session
		// id, so rewriting through it is atomic with respect to any other
		// request of the same session.
		if ($this->handle && $session_id === $this->session_id) {
			ftruncate($this->handle, 0);
			fseek($this->handle, 0);

			fwrite($this->handle, $serialized);
			fflush($this->handle);

			$this->closeHandle();

			return true;
		}

		// Fallback: writing a session id whose handle is not held (rotate()
		// or a fresh adaptor). Open, lock, rewrite, release.
		$file = DIR_SESSION . 'sess_' . basename($session_id);

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

	public function destroy($session_id) {
		$this->closeHandle($session_id);

		$file = DIR_SESSION . 'sess_' . basename($session_id);

		if (is_file($file)) {
			unlink($file);
		}

		return true;
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

		$files = glob($directory . 'sess_*');

		if (!$files) {
			return 0;
		}

		foreach ($files as $file) {
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
