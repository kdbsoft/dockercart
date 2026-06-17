<?php
class ModelAccountApi extends Model {
	public function login($username, $key) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api` WHERE `username` = '" . $this->db->escape($username) . "' AND `status` = '1'");

		if ($query->num_rows) {
			$hash = $query->row['key'];

			if (password_verify($key, $hash)) {
				return $query->row;
			}

			if ($hash === $key) {
				$this->db->query("UPDATE `" . DB_PREFIX . "api` SET `key` = '" . $this->db->escape(password_hash($key, PASSWORD_ARGON2ID)) . "' WHERE api_id = '" . (int)$query->row['api_id'] . "'");
				return $query->row;
			}
		}

		return array();
	}

	public function addApiSession($api_id, $session_id, $ip) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "api_session` SET `api_id` = '" . (int)$api_id . "', `session_id` = '" . $this->db->escape($session_id) . "', `ip` = '" . $this->db->escape($ip) . "', `date_added` = NOW(), `date_modified` = NOW()");

		return $this->db->getLastId();
	}

	public function getApiIps($api_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE `api_id` = '" . (int)$api_id . "'");

		return $query->rows;
	}
}
