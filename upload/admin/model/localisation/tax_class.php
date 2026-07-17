<?php
class ModelLocalisationTaxClass extends Model {
	public function addTaxClass($data) {
		foreach ($data['tax_class_description'] as $language_id => $value) {
			if (isset($tax_class_id)) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tax_class SET tax_class_id = '" . (int)$tax_class_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', date_added = NOW(), date_modified = NOW()");
			} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tax_class SET language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', date_added = NOW(), date_modified = NOW()");

				$tax_class_id = $this->db->getLastId();
			}
		}

		if (isset($data['tax_rule'])) {
			foreach ($data['tax_rule'] as $tax_rule) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tax_rule SET tax_class_id = '" . (int)$tax_class_id . "', tax_rate_id = '" . (int)$tax_rule['tax_rate_id'] . "', based = '" . $this->db->escape($tax_rule['based']) . "', priority = '" . (int)$tax_rule['priority'] . "'");
			}
		}

		$this->cache->delete('tax_class');

		return $tax_class_id;
	}

	public function editTaxClass($tax_class_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "tax_class WHERE tax_class_id = '" . (int)$tax_class_id . "'");

		foreach ($data['tax_class_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "tax_class SET tax_class_id = '" . (int)$tax_class_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($value['title']) . "', description = '" . $this->db->escape($value['description']) . "', date_modified = NOW(), date_added = NOW()");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . (int)$tax_class_id . "'");

		if (isset($data['tax_rule'])) {
			foreach ($data['tax_rule'] as $tax_rule) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "tax_rule SET tax_class_id = '" . (int)$tax_class_id . "', tax_rate_id = '" . (int)$tax_rule['tax_rate_id'] . "', based = '" . $this->db->escape($tax_rule['based']) . "', priority = '" . (int)$tax_rule['priority'] . "'");
			}
		}

		$this->cache->delete('tax_class');
	}

	public function deleteTaxClass($tax_class_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "tax_class WHERE tax_class_id = '" . (int)$tax_class_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . (int)$tax_class_id . "'");

		$this->cache->delete('tax_class');
	}

	public function getTaxClass($tax_class_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_class WHERE tax_class_id = '" . (int)$tax_class_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getTaxClasses($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "tax_class WHERE language_id = '" . (int)$this->config->get('config_language_id') . "'";

			$sql .= " ORDER BY title";

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
			}

			if (isset($data['start']) || isset($data['limit'])) {
				if ($data['start'] < 0) {
					$data['start'] = 0;
				}

				if ($data['limit'] < 1) {
					$data['limit'] = 20;
				}

				$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
			}

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$tax_class_data = $this->cache->get('tax_class.' . (int)$this->config->get('config_language_id'));

			if (!$tax_class_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_class WHERE language_id = '" . (int)$this->config->get('config_language_id') . "'");

				$tax_class_data = $query->rows;

				$this->cache->set('tax_class.' . (int)$this->config->get('config_language_id'), $tax_class_data);
			}

			return $tax_class_data;
		}
	}

	public function getTaxClassDescriptions($tax_class_id) {
		$tax_class_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_class WHERE tax_class_id = '" . (int)$tax_class_id . "'");

		foreach ($query->rows as $result) {
			$tax_class_data[$result['language_id']] = array(
				'title'       => $result['title'],
				'description' => $result['description']
			);
		}

		return $tax_class_data;
	}

	public function getTotalTaxClasses() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "tax_class WHERE language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row['total'];
	}

	public function getTaxRules($tax_class_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . (int)$tax_class_id . "' ORDER BY priority ASC");

		return $query->rows;
	}

	public function getTotalTaxRulesByTaxRateId($tax_rate_id) {
		$query = $this->db->query("SELECT COUNT(DISTINCT tax_class_id) AS total FROM " . DB_PREFIX . "tax_rule WHERE tax_rate_id = '" . (int)$tax_rate_id . "'");

		return $query->row['total'];
	}
}
