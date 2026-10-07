<?php
namespace Session;

class Redis {
	/**
	 * How long to wait for a session lock held by a concurrent same-session
	 * request before giving up and proceeding unlocked (best-effort: a slow
	 * endpoint must never hang the request). Mirrors the GET_LOCK timeout of
	 * the DB adaptor and the flock timeout of the File adaptor.
	 */
	const LOCK_TIMEOUT = 10;

	/**
	 * TTL of the lock key itself: a request that dies while holding the lock
	 * must not block same-session requests forever.
	 */
	const LOCK_TTL = 15;

	private $redis;
	private $ttl = 86400;

	/**
	 * Session id the lock below is held for.
	 */
	private $session_id = '';

	/**
	 * Token of the held lock; null when no lock is held.
	 */
	private $lock_token = null;

	/**
	 * Payload exactly as read (or '' when the key was missing); used to skip
	 * write-backs that would not change anything.
	 */
	private $raw = null;

	/**
	 * True when the store was unreachable during read(): the request must not
	 * then write back an empty session over data that may still be intact.
	 */
	private $broken = false;

	public function __construct() {
		$this->redis = new \Redis();

		$hostname = defined('REDIS_HOSTNAME') ? REDIS_HOSTNAME : 'redis';
		$port = defined('REDIS_PORT') ? (int) REDIS_PORT : 6379;

		// pconnect reuses a persistent socket, which the server may have
		// closed since the last request; command() reconnects when the
		// first command on a stale socket fails. phpredis throws (rather
		// than returning false) when the host is unreachable, so the
		// constructor must not hard-fail the request: read()/write()
		// degrade to guest mode without wiping the stored session.
		try {
			@$this->redis->pconnect($hostname, $port);

			if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
				@$this->redis->auth(REDIS_PASSWORD);
			}
		} catch (\RedisException $e) {
			unset($e);
		}
	}

	/**
	 * True when the last command() failed (after one reconnect attempt).
	 */
	private $failed = false;

	public function read($session_id) {
		$this->releaseLock();

		$this->session_id = $session_id;

		// Acquire LOCK_EX equivalent for the whole request so concurrent
		// same-session requests serialise instead of racing the key. The
		// DB adaptor provides the same guarantee via a MySQL named lock.
		$this->acquireLock($session_id);

		$data = $this->command('get', 'session_' . $session_id);

		if ($data === false && $this->failed) {
			// The store is unreachable even after a reconnect: proceed as a
			// guest, but remember not to wipe the stored session on close.
			$this->raw = null;
			$this->broken = true;

			return array();
		}

		$this->broken = false;
		$this->raw = $data === false ? '' : $data;

		if ($data === false) {
			return array();
		}

		$decoded = json_decode($data, true);

		// Corrupt or truncated payloads must not break consumers.
		if (!is_array($decoded)) {
			return array();
		}

		return $decoded;
	}

	public function write($session_id, $data) {
		if (!$session_id) {
			$this->releaseLock();

			return true;
		}

		// The store was unreachable during read: leave whatever is stored
		// intact instead of overwriting it with an empty session.
		if ($this->broken) {
			$this->releaseLock();

			return true;
		}

		$encoded = json_encode($data);

		// Skip the write-back when the session was read but not modified.
		// Under parallel requests most requests never touch the session, and
		// a read-only request must never overwrite newer data with the stale
		// copy it read before it had to proceed unlocked.
		if ($this->lock_token !== null && $session_id === $this->session_id && $encoded === $this->raw) {
			$this->releaseLock();

			return true;
		}

		$this->command('setex', 'session_' . $session_id, $this->ttl, $encoded);

		$this->releaseLock($session_id);

		return true;
	}

	public function destroy($session_id) {
		$this->releaseLock($session_id);

		$this->command('del', 'session_' . $session_id);

		return true;
	}

	/**
	 * Executes a command, reconnecting once when the (persistent) connection
	 * turned out to be stale, and never throws: phpredis raises
	 * RedisException on unconnected objects. A legitimate "key missing"
	 * false return is not mistaken for a failure (see invoke()); failed
	 * commands set $this->failed so callers can tell the two apart.
	 */
	private function command($method, ...$args) {
		$this->failed = false;

		$result = $this->invoke($method, $args);

		if ($result === null) {
			$this->failed = true;

			$this->reconnect();

			$result = $this->invoke($method, $args);
		}

		return $result === null ? false : $result;
	}

	/**
	 * Invokes one command; null means the command failed (RedisException, or
	 * a false return while a Redis error is set), false is a legitimate
	 * false return (e.g. missing key).
	 */
	private function invoke($method, array $args) {
		try {
			@$this->redis->clearLastError();
		} catch (\RedisException $e) {
			unset($e);

			return null;
		}

		try {
			$result = @$this->redis->{$method}(...$args);
		} catch (\RedisException $e) {
			unset($e);

			return null;
		}

		if ($result === false) {
			try {
				$error = @$this->redis->getLastError();
			} catch (\RedisException $e) {
				unset($e);

				return null;
			}

			if ($error !== null) {
				return null;
			}
		}

		return $result;
	}

	private function reconnect() {
		try {
			@$this->redis->close();
		} catch (\RedisException $e) {
			unset($e);
		}

		$hostname = defined('REDIS_HOSTNAME') ? REDIS_HOSTNAME : 'redis';
		$port = defined('REDIS_PORT') ? (int) REDIS_PORT : 6379;

		try {
			if (!@$this->redis->connect($hostname, $port)) {
				return;
			}
		} catch (\RedisException $e) {
			unset($e);

			return;
		}

		if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
			@$this->redis->auth(REDIS_PASSWORD);
		}
	}

	/**
	 * Acquires the session lock, waiting up to LOCK_TIMEOUT for a concurrent
	 * same-session request to finish. On timeout the request proceeds
	 * unlocked: it then reads a valid (whole-payload) copy, and read-only
	 * requests skip the write-back, so the worst case is a lost update
	 * rather than a wiped session.
	 */
	private function acquireLock($session_id) {
		$token = bin2hex(random_bytes(8));
		$deadline = microtime(true) + self::LOCK_TIMEOUT;

		while (true) {
			$locked = $this->command('set', 'session_lock_' . $session_id, $token, ['nx', 'ex' => self::LOCK_TTL]);

			if ($locked) {
				$this->lock_token = $token;

				return true;
			}

			if (microtime(true) >= $deadline) {
				return false;
			}

			usleep(100000);
		}
	}

	/**
	 * Releases the held lock, if any.
	 *
	 * @param	string	$session_id	only release when the held session id matches; empty matches anything
	 */
	private function releaseLock($session_id = '') {
		if ($this->lock_token === null) {
			return;
		}

		if ($session_id !== '' && $session_id !== $this->session_id) {
			return;
		}

		$key = 'session_lock_' . $this->session_id;
		$token = $this->lock_token;

		$this->lock_token = null;
		$this->session_id = '';

		// Release only the lock we own (it may have expired and been
		// acquired by another request while this one was stuck).
		$this->command('eval', "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end", [$key, $token], 1);
	}

	public function __destruct() {
		$this->releaseLock();
	}
}
