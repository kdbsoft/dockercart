<?php
class ModelExtensionModuleDockercartGdpr extends Model {
	public function install() {
		// Tables created via migration
	}

	public function uninstall() {
	}

	public function getConsentLog($data = array()) {
		$sql = "SELECT c.*, cst.firstname, cst.lastname, cst.email
				FROM `" . DB_PREFIX . "dockercart_gdpr_consent` c
				LEFT JOIN `" . DB_PREFIX . "customer` cst ON (c.customer_id = cst.customer_id)
				WHERE 1=1";

		if (!empty($data['filter_customer'])) {
			$sql .= " AND (cst.firstname LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
			          OR cst.lastname LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
			          OR cst.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_consent_type'])) {
			$sql .= " AND c.consent_type = '" . $this->db->escape($data['filter_consent_type']) . "'";
		}

		$sql .= " ORDER BY c.date_added DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalConsentLog($data = array()) {
		$sql = "SELECT COUNT(*) AS total
				FROM `" . DB_PREFIX . "dockercart_gdpr_consent` c
				LEFT JOIN `" . DB_PREFIX . "customer` cst ON (c.customer_id = cst.customer_id)
				WHERE 1=1";

		if (!empty($data['filter_customer'])) {
			$sql .= " AND (cst.firstname LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
			          OR cst.lastname LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
			          OR cst.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_consent_type'])) {
			$sql .= " AND c.consent_type = '" . $this->db->escape($data['filter_consent_type']) . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getRequests($data = array()) {
		$sql = "SELECT r.*, cst.firstname, cst.lastname, cst.email
				FROM `" . DB_PREFIX . "dockercart_gdpr_request` r
				LEFT JOIN `" . DB_PREFIX . "customer` cst ON (r.customer_id = cst.customer_id)
				WHERE 1=1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND r.status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		if (!empty($data['filter_type'])) {
			$sql .= " AND r.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		$sql .= " ORDER BY r.date_added DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalRequests($data = array()) {
		$sql = "SELECT COUNT(*) AS total
				FROM `" . DB_PREFIX . "dockercart_gdpr_request` r
				LEFT JOIN `" . DB_PREFIX . "customer` cst ON (r.customer_id = cst.customer_id)
				WHERE 1=1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND r.status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		if (!empty($data['filter_type'])) {
			$sql .= " AND r.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getRequest($request_id) {
		$query = $this->db->query(
			"SELECT r.*, cst.firstname, cst.lastname, cst.email
			 FROM `" . DB_PREFIX . "dockercart_gdpr_request` r
			 LEFT JOIN `" . DB_PREFIX . "customer` cst ON (r.customer_id = cst.customer_id)
			 WHERE r.request_id = " . (int)$request_id
		);

		return $query->num_rows ? $query->row : null;
	}

	public function setRequestStatus($request_id, $status) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_gdpr_request` SET
			 `status` = '" . $this->db->escape($status) . "',
			 `date_processed` = NOW()
			 WHERE `request_id` = " . (int)$request_id
		);
	}

	public function getCookieGroups($data = array()) {
		$sql = "SELECT g.*, gd.name, gd.description
				FROM `" . DB_PREFIX . "dockercart_cookie_group` g
				LEFT JOIN `" . DB_PREFIX . "dockercart_cookie_group_description` gd
				  ON (g.cookie_group_id = gd.cookie_group_id AND gd.language_id = " . (int)$this->config->get('config_language_id') . ")
				WHERE 1=1";

		if (isset($data['filter_status'])) {
			$sql .= " AND g.status = " . (int)$data['filter_status'];
		}

		$sql .= " ORDER BY g.sort_order ASC";

		return $this->db->query($sql)->rows;
	}

	public function getCookieGroup($cookie_group_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_cookie_group`
			 WHERE cookie_group_id = " . (int)$cookie_group_id
		);

		return $query->num_rows ? $query->row : null;
	}

	public function getCookieGroupDescriptions($cookie_group_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_cookie_group_description`
			 WHERE cookie_group_id = " . (int)$cookie_group_id
		);

		$descriptions = array();

		foreach ($query->rows as $row) {
			$descriptions[(int)$row['language_id']] = array(
				'name' => $row['name'],
				'description' => $row['description']
			);
		}

		return $descriptions;
	}

	public function addCookieGroup($data) {
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "dockercart_cookie_group` SET
			 `sort_order` = " . (int)$data['sort_order'] . ",
			 `is_required` = " . (isset($data['is_required']) ? 1 : 0) . ",
			 `status` = " . (isset($data['status']) ? 1 : 0) . ",
			 `date_added` = NOW(),
			 `date_modified` = NOW()"
		);

		$cookie_group_id = (int)$this->db->getLastId();

		foreach ($data['group_description'] as $language_id => $value) {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_cookie_group_description` SET
				 `cookie_group_id` = " . $cookie_group_id . ",
				 `language_id` = " . (int)$language_id . ",
				 `name` = '" . $this->db->escape($value['name']) . "',
				 `description` = '" . $this->db->escape($value['description']) . "'"
			);
		}

		return $cookie_group_id;
	}

	public function editCookieGroup($cookie_group_id, $data) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_cookie_group` SET
			 `sort_order` = " . (int)$data['sort_order'] . ",
			 `is_required` = " . (isset($data['is_required']) ? 1 : 0) . ",
			 `status` = " . (isset($data['status']) ? 1 : 0) . ",
			 `date_modified` = NOW()
			 WHERE `cookie_group_id` = " . (int)$cookie_group_id
		);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_group_description` WHERE cookie_group_id = " . (int)$cookie_group_id);

		foreach ($data['group_description'] as $language_id => $value) {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_cookie_group_description` SET
				 `cookie_group_id` = " . $cookie_group_id . ",
				 `language_id` = " . (int)$language_id . ",
				 `name` = '" . $this->db->escape($value['name']) . "',
				 `description` = '" . $this->db->escape($value['description']) . "'"
			);
		}
	}

	public function deleteCookieGroup($cookie_group_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_group` WHERE cookie_group_id = " . (int)$cookie_group_id);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_group_description` WHERE cookie_group_id = " . (int)$cookie_group_id);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie` WHERE cookie_group_id = " . (int)$cookie_group_id);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_description` WHERE cookie_id NOT IN (SELECT cookie_id FROM `" . DB_PREFIX . "dockercart_cookie`)");
	}

	public function getCookies($cookie_group_id, $data = array()) {
		$sql = "SELECT c.*, cd.description
				FROM `" . DB_PREFIX . "dockercart_cookie` c
				LEFT JOIN `" . DB_PREFIX . "dockercart_cookie_description` cd
				  ON (c.cookie_id = cd.cookie_id AND cd.language_id = " . (int)$this->config->get('config_language_id') . ")
				WHERE c.cookie_group_id = " . (int)$cookie_group_id;

		$sql .= " ORDER BY c.sort_order ASC";

		return $this->db->query($sql)->rows;
	}

	public function getCookie($cookie_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_cookie`
			 WHERE cookie_id = " . (int)$cookie_id
		);

		return $query->num_rows ? $query->row : null;
	}

	public function getCookieDescriptions($cookie_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_cookie_description`
			 WHERE cookie_id = " . (int)$cookie_id
		);

		$descriptions = array();

		foreach ($query->rows as $row) {
			$descriptions[(int)$row['language_id']] = array(
				'description' => $row['description']
			);
		}

		return $descriptions;
	}

	public function addCookie($data) {
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "dockercart_cookie` SET
			 `cookie_group_id` = " . (int)$data['cookie_group_id'] . ",
			 `name` = '" . $this->db->escape($data['name']) . "',
			 `provider` = '" . $this->db->escape($data['provider']) . "',
			 `domain` = '" . $this->db->escape($data['domain']) . "',
			 `duration` = '" . $this->db->escape($data['duration']) . "',
			 `sort_order` = " . (int)$data['sort_order'] . ",
			 `status` = " . (isset($data['status']) ? 1 : 0) . ",
			 `date_added` = NOW(),
			 `date_modified` = NOW()"
		);

		$cookie_id = (int)$this->db->getLastId();

		foreach ($data['cookie_description'] as $language_id => $value) {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_cookie_description` SET
				 `cookie_id` = " . $cookie_id . ",
				 `language_id` = " . (int)$language_id . ",
				 `description` = '" . $this->db->escape($value['description']) . "'"
			);
		}

		return $cookie_id;
	}

	public function editCookie($cookie_id, $data) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_cookie` SET
			 `cookie_group_id` = " . (int)$data['cookie_group_id'] . ",
			 `name` = '" . $this->db->escape($data['name']) . "',
			 `provider` = '" . $this->db->escape($data['provider']) . "',
			 `domain` = '" . $this->db->escape($data['domain']) . "',
			 `duration` = '" . $this->db->escape($data['duration']) . "',
			 `sort_order` = " . (int)$data['sort_order'] . ",
			 `status` = " . (isset($data['status']) ? 1 : 0) . ",
			 `date_modified` = NOW()
			 WHERE `cookie_id` = " . (int)$cookie_id
		);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_description` WHERE cookie_id = " . (int)$cookie_id);

		foreach ($data['cookie_description'] as $language_id => $value) {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_cookie_description` SET
				 `cookie_id` = " . $cookie_id . ",
				 `language_id` = " . (int)$language_id . ",
				 `description` = '" . $this->db->escape($value['description']) . "'"
			);
		}
	}

	public function deleteCookie($cookie_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie` WHERE cookie_id = " . (int)$cookie_id);
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_cookie_description` WHERE cookie_id = " . (int)$cookie_id);
	}

	public function getTotalPendingRequests() {
		$query = $this->db->query(
			"SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_gdpr_request`
			 WHERE status = 'pending'"
		);

		return (int)$query->row['total'];
	}
}
