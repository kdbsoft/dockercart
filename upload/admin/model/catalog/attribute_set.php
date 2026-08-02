<?php
class ModelCatalogAttributeSet extends Model {
	public function addAttributeSet($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_set SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "'");

		$attribute_set_id = $this->db->getLastId();

		foreach ($data['attribute_set_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_set_description SET attribute_set_id = '" . (int)$attribute_set_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveSetAttributes($attribute_set_id, $data);

		return $attribute_set_id;
	}

	public function editAttributeSet($attribute_set_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "attribute_set SET sort_order = '" . (int)$data['sort_order'] . "', status = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "' WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_set_description WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");

		foreach ($data['attribute_set_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_set_description SET attribute_set_id = '" . (int)$attribute_set_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveSetAttributes($attribute_set_id, $data);
	}

	private function saveSetAttributes($attribute_set_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_set_attribute WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");

		if (!isset($data['attribute_set_attribute']) || !is_array($data['attribute_set_attribute'])) {
			return;
		}

		$attributes = array();

		foreach ($data['attribute_set_attribute'] as $d) {
			if (isset($d['attribute_id']) && (int)$d['attribute_id'] > 0) {
				$attributes[(int)$d['attribute_id']] = (int)$d['attribute_id'];
			}
		}

		$sort_order = 0;

		foreach ($attributes as $attribute_id) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_set_attribute SET attribute_set_id = '" . (int)$attribute_set_id . "', attribute_id = '" . (int)$attribute_id . "', sort_order = '" . (int)$sort_order . "'");

			$sort_order++;
		}
	}

	public function deleteAttributeSet($attribute_set_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_set WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_set_description WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_set_attribute WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");
	}

	public function copyAttributeSet($attribute_set_id)
	{
		$query = $this->db->query(
			"SELECT * FROM " .
				DB_PREFIX .
				"attribute_set WHERE attribute_set_id = '" .
				(int) $attribute_set_id .
				"'",
		);

		if (!$query->num_rows) {
			return false;
		}

		$set = $query->row;

		$data = [];

		$data["sort_order"] = $set["sort_order"];
		$data["status"] = $set["status"];
		$data["attribute_set_description"] = $this->getAttributeSetDescriptions(
			$attribute_set_id,
		);

		$data["attribute_set_attribute"] = $this->getAttributeSetAttributes(
			$attribute_set_id,
		);

		$default_language_id = (int) $this->config->get("config_language_id");

		if (
			isset(
				$data["attribute_set_description"][$default_language_id][
					"name"
				],
			)
		) {
			$data["attribute_set_description"][$default_language_id][
				"name"
			] = $this->getUniqueCopyName(
				$data["attribute_set_description"][$default_language_id][
					"name"
				],
				DB_PREFIX . "attribute_set_description",
				"name",
			);
		}

		return $this->addAttributeSet($data);
	}

	private function getUniqueCopyName($original, $table, $column)
	{
		$base = $original;

		if (preg_match('/^(.+)-copy(\d*)$/', $original, $matches)) {
			$base = $matches[1];
		}

		$counter = 0;

		do {
			$counter++;
			$suffix = $counter > 1 ? (string) $counter : "";
			$candidate = $base . "-copy" . $suffix;

			$query = $this->db->query(
				"SELECT COUNT(*) AS total FROM " .
					$table .
					" WHERE " .
					$column .
					" = '" .
					$this->db->escape($candidate) .
					"'",
			);
		} while ($query->row["total"] > 0);

		return $candidate;
	}

	public function getAttributeSet($attribute_set_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_set WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");

		return $query->row;
	}

	public function getAttributeSets($data = array()) {
		$sql = "SELECT *, (SELECT COUNT(*) FROM " . DB_PREFIX . "attribute_set_attribute asa WHERE asa.attribute_set_id = ast.attribute_set_id) AS attribute_count FROM " . DB_PREFIX . "attribute_set ast LEFT JOIN " . DB_PREFIX . "attribute_set_description astd ON (ast.attribute_set_id = astd.attribute_set_id) WHERE astd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$sort_data = array(
			'astd.name',
			'ast.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY astd.name";
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

	public function getAttributeSetDescriptions($attribute_set_id) {
		$attribute_set_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_set_description WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");

		foreach ($query->rows as $result) {
			$attribute_set_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_set_data;
	}

	public function getAttributeSetAttributes($attribute_set_id) {
		$query = $this->db->query("SELECT asa.*, ad.name, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute_set_attribute asa LEFT JOIN " . DB_PREFIX . "attribute a ON (asa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (asa.attribute_id = ad.attribute_id) WHERE asa.attribute_set_id = '" . (int)$attribute_set_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY asa.sort_order ASC, ad.name ASC");

		return $query->rows;
	}

	public function getTotalAttributeSets() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute_set");

		return $query->row['total'];
	}

	public function updateAttributeSetField($attribute_set_id, $data) {
		$int_fields = array('sort_order', 'status');

		$sets = array();
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
			}
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "attribute_set SET " . implode(', ', $sets) . " WHERE attribute_set_id = '" . (int)$attribute_set_id . "'");
		}
	}

	public function updateAttributeSetNames($attribute_set_id, $names) {
		foreach ($names as $language_id => $name) {
			$name = trim((string)$name);

			$this->db->query("UPDATE " . DB_PREFIX . "attribute_set_description SET name = '" . $this->db->escape($name) . "' WHERE attribute_set_id = '" . (int)$attribute_set_id . "' AND language_id = '" . (int)$language_id . "'");
		}
	}
}
