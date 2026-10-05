<?php
namespace Session;

final class DB {
	public $maxlifetime;
	public $db;

	/**
	 * Name of the MySQL named lock held for the current session id, if any.
	 */
	private $lock_name = null;

	/**
	 * How long to wait for a session lock held by a concurrent same-session
	 * request before giving up and proceeding unlocked (best-effort: a slow
	 * endpoint must never hang the whole checkout).
	 */
	const LOCK_TIMEOUT = 10;

	public function __construct($registry) {
		$this->db = $registry->get('db');

		$this->maxlifetime = ini_get('session.gc_maxlifetime') !== null ? (int)ini_get('session.gc_maxlifetime') : 1440;

		$this->gc();
	}

	public function read($session_id) {
		$this->acquireLock($session_id);

		$query = $this->db->query("SELECT `data` FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" . $this->db->escape($session_id) . "' AND `expire` > '" . $this->db->escape(gmdate('Y-m-d H:i:s', time())) . "'");

		if ($query->num_rows) {
			return json_decode($query->row['data'], true);
		} else {
			return array();
		}
	}

	public function write($session_id, $data) {
		if ($session_id) {
			$this->db->query("REPLACE INTO `" . DB_PREFIX . "session` SET `session_id` = '" . $this->db->escape($session_id) . "', `data` = '" . $this->db->escape(json_encode($data)) . "', `expire` = '" . $this->db->escape(gmdate('Y-m-d H:i:s', time() + (int)$this->maxlifetime)) . "'");
		}

		$this->releaseLock();

		return true;
	}

	public function destroy($session_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" . $this->db->escape($session_id) . "'");

		if ($this->lock_name !== null && $this->lock_name === $this->lockNameFor($session_id)) {
			$this->releaseLock();
		}

		return true;
	}

	public function gc() {
		if (ini_get('session.gc_divisor') && $gc_divisor = (int)ini_get('session.gc_divisor')) {
			$gc_divisor = $gc_divisor === 0 ? 100 : $gc_divisor;
		} else {
			$gc_divisor = 100;
		}

		if (ini_get('session.gc_probability')) {
			$gc_probability = (int)ini_get('session.gc_probability');
		} else {
			$gc_probability = 1;
		}

		if (mt_rand() / mt_getrandmax() < $gc_probability / $gc_divisor) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "session` WHERE `expire` < '" . $this->db->escape(gmdate('Y-m-d H:i:s', time())) . "'");

			return true;
		}
	}

	/**
	 * Serializes concurrent same-session requests via a MySQL named lock, so a
	 * slow request can no longer write back the stale session copy it read at
	 * startup over a concurrently committed one (e.g. a divisions search
	 * reverting a just-saved shipping_method). Mirrors the flock() semantics of
	 * the file adaptor; different sessions use different lock names and never
	 * block each other. Best-effort: if the lock cannot be acquired in time the
	 * request proceeds unlocked rather than hanging.
	 */
	private function acquireLock($session_id) {
		$this->releaseLock();

		$name = $this->lockNameFor($session_id);

		try {
			$query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($name) . "', " . (int)self::LOCK_TIMEOUT . ") AS `locked`");
		} catch (\Exception $e) {
			unset($e);

			return;
		}

		if (!empty($query->row['locked'])) {
			$this->lock_name = $name;
		}
	}

	private function releaseLock() {
		if ($this->lock_name === null) {
			return;
		}

		try {
			$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($this->lock_name) . "')");
		} catch (\Exception $e) {
			unset($e);
		}

		$this->lock_name = null;
	}

	private function lockNameFor($session_id) {
		return 'dc_session_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$session_id);
	}
}
