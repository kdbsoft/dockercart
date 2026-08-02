<?php
class ModelCatalogOptionSet extends Model {
	public function addOptionSet($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "option_set SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "'");

		$option_set_id = $this->db->getLastId();

		foreach ($data['option_set_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "option_set_description SET option_set_id = '" . (int)$option_set_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveSetOptions($option_set_id, $data);

		return $option_set_id;
	}

	public function editOptionSet($option_set_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "option_set SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "' WHERE option_set_id = '" . (int)$option_set_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "option_set_description WHERE option_set_id = '" . (int)$option_set_id . "'");

		foreach ($data['option_set_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "option_set_description SET option_set_id = '" . (int)$option_set_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveSetOptions($option_set_id, $data);
	}

	private function saveSetOptions($option_set_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "option_set_option WHERE option_set_id = '" . (int)$option_set_id . "'");

		if (!isset($data['option_set_option']) || !is_array($data['option_set_option'])) {
			return;
		}

		$options = array();

		foreach ($data['option_set_option'] as $d) {
			if (isset($d['option_id']) && (int)$d['option_id'] > 0) {
				$options[(int)$d['option_id']] = (int)$d['option_id'];
			}
		}

		$sort_order = 0;

		foreach ($options as $option_id) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "option_set_option SET option_set_id = '" . (int)$option_set_id . "', option_id = '" . (int)$option_id . "', sort_order = '" . (int)$sort_order . "'");

			$sort_order++;
		}
	}

	public function deleteOptionSet($option_set_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "option_set WHERE option_set_id = '" . (int)$option_set_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "option_set_description WHERE option_set_id = '" . (int)$option_set_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "option_set_option WHERE option_set_id = '" . (int)$option_set_id . "'");
	}

	public function copyOptionSet($option_set_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_set WHERE option_set_id = '" . (int)$option_set_id . "'");

		if (!$query->num_rows) {
			return false;
		}

		$set = $query->row;

		$data = array();

		$data['sort_order'] = $set['sort_order'];
		$data['status'] = $set['status'];
		$data['option_set_description'] = $this->getOptionSetDescriptions($option_set_id);

		$data['option_set_option'] = $this->getOptionSetOptions($option_set_id);

		$default_language_id = (int)$this->config->get('config_language_id');

		if (isset($data['option_set_description'][$default_language_id]['name'])) {
			$data['option_set_description'][$default_language_id]['name'] = $this->getUniqueCopyName(
				$data['option_set_description'][$default_language_id]['name'],
				DB_PREFIX . 'option_set_description',
				'name'
			);
		}

		return $this->addOptionSet($data);
	}

	private function getUniqueCopyName($original, $table, $column) {
		$base = $original;

		if (preg_match('/^(.+)-copy(\d*)$/', $original, $matches)) {
			$base = $matches[1];
		}

		$counter = 0;

		do {
			$counter++;
			$suffix = $counter > 1 ? (string)$counter : '';
			$candidate = $base . '-copy' . $suffix;

			$query = $this->db->query("SELECT COUNT(*) AS total FROM " . $table . " WHERE " . $column . " = '" . $this->db->escape($candidate) . "'");
		} while ($query->row['total'] > 0);

		return $candidate;
	}

	public function getOptionSet($option_set_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_set WHERE option_set_id = '" . (int)$option_set_id . "'");

		return $query->row;
	}

	public function getOptionSets($data = array()) {
		$sql = "SELECT *, (SELECT COUNT(*) FROM " . DB_PREFIX . "option_set_option oso WHERE oso.option_set_id = ost.option_set_id) AS option_count FROM " . DB_PREFIX . "option_set ost LEFT JOIN " . DB_PREFIX . "option_set_description ostd ON (ost.option_set_id = ostd.option_set_id) WHERE ostd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$sort_data = array(
			'ostd.name',
			'ost.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY ostd.name";
		}

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
	}

	public function getOptionSetDescriptions($option_set_id) {
		$option_set_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_set_description WHERE option_set_id = '" . (int)$option_set_id . "'");

		foreach ($query->rows as $result) {
			$option_set_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $option_set_data;
	}

	public function getOptionSetOptions($option_set_id) {
		$query = $this->db->query("SELECT oso.*, o.type, od.name FROM " . DB_PREFIX . "option_set_option oso LEFT JOIN `" . DB_PREFIX . "option` o ON (oso.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (oso.option_id = od.option_id) WHERE oso.option_set_id = '" . (int)$option_set_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY oso.sort_order ASC, od.name ASC");

		return $query->rows;
	}

	public function getTotalOptionSets() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "option_set");

		return $query->row['total'];
	}

	public function updateOptionSetField($option_set_id, $data) {
		$int_fields = array('sort_order', 'status');

		$sets = array();
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
			}
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "option_set SET " . implode(', ', $sets) . " WHERE option_set_id = '" . (int)$option_set_id . "'");
		}
	}

	public function updateOptionSetNames($option_set_id, $names) {
		foreach ($names as $language_id => $name) {
			$name = trim((string)$name);

			$this->db->query("UPDATE " . DB_PREFIX . "option_set_description SET name = '" . $this->db->escape($name) . "' WHERE option_set_id = '" . (int)$option_set_id . "' AND language_id = '" . (int)$language_id . "'");
		}
	}
}
