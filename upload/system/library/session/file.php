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
		$handle = @fopen($file, 'c+');

		if (!$handle) {
			return array();
		}

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

		$unserialized = unserialize($data, array('allowed_classes' => false));

		// Corrupt or truncated session files (disk full, legacy damage)
		// previously surfaced as "unserialize(): Extra data" warnings and a
		// non-array return that broke consumers. Treat them as empty sessions.
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

		$handle = @fopen($file, 'c+');

		if (!$handle) {
			return false;
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

	public function __destruct() {
		$this->closeHandle();

		if (ini_get('session.gc_divisor')) {
			$gc_divisor = ini_get('session.gc_divisor');
		} else {
			$gc_divisor = 1;
		}

		if (ini_get('session.gc_probability')) {
			$gc_probability = ini_get('session.gc_probability');
		} else {
			$gc_probability = 1;
		}

		if ((rand() % $gc_divisor) < $gc_probability) {
			$expire = time() - ini_get('session.gc_maxlifetime');

			$files = glob(DIR_SESSION . 'sess_*');

			foreach ($files as $file) {
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

				unlink($file);
			}
		}
	}
}
