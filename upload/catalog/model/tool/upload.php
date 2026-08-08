<?php
class ModelToolUpload extends Model {
	public function addUpload($name, $filename) {
		$code = sha1(uniqid(mt_rand(), true));

		$this->db->query("INSERT INTO `" . DB_PREFIX . "upload` SET `name` = '" . $this->db->escape($name) . "', `filename` = '" . $this->db->escape($filename) . "', `code` = '" . $this->db->escape($code) . "', `date_added` = NOW()");

		return $code;
	}

	public function getUploadByCode($code) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "upload` WHERE code = '" . $this->db->escape($code) . "'");

		return $query->row;
	}

	/**
	 * Bulk upload names by code (N+1 killer for cart/checkout file options).
	 * Returns [code => name].
	 */
	public function getUploadNamesByCodes(array $codes) {
		if (empty($codes)) {
			return array();
		}

		$implode = array();

		foreach ($codes as $code) {
			$implode[] = "'" . $this->db->escape($code) . "'";
		}

		$query = $this->db->query("SELECT code, name FROM `" . DB_PREFIX . "upload` WHERE code IN (" . implode(',', $implode) . ")");

		$result = array();

		foreach ($query->rows as $row) {
			$result[$row['code']] = $row['name'];
		}

		return $result;
	}
}