<?php
class ModelCatalogAttributeGroup extends Model {
	public function addAttributeGroup($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = '" . (int)$data['sort_order'] . "'");

		$attribute_group_id = $this->db->getLastId();

		foreach ($data['attribute_group_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		return $attribute_group_id;
	}

	public function editAttributeGroup($attribute_group_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "attribute_group SET sort_order = '" . (int)$data['sort_order'] . "' WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group_description WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		foreach ($data['attribute_group_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}
	}

	public function deleteAttributeGroup($attribute_group_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_group_description WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
	}

	public function copyAttributeGroup($attribute_group_id)
	{
		$query = $this->db->query(
			"SELECT * FROM " .
				DB_PREFIX .
				"attribute_group WHERE attribute_group_id = '" .
				(int) $attribute_group_id .
				"'",
		);

		if (!$query->num_rows) {
			return false;
		}

		$group = $query->row;

		$data = [];

		$data["sort_order"] = $group["sort_order"];
		$data["attribute_group_description"] = $this->getAttributeGroupDescriptions(
			$attribute_group_id,
		);

		$default_language_id = (int) $this->config->get("config_language_id");

		if (
			isset(
				$data["attribute_group_description"][$default_language_id][
					"name"
				],
			)
		) {
			$data["attribute_group_description"][$default_language_id][
				"name"
			] = $this->getUniqueCopyName(
				$data["attribute_group_description"][$default_language_id][
					"name"
				],
				DB_PREFIX . "attribute_group_description",
				"name",
			);
		}

		return $this->addAttributeGroup($data);
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

	public function getAttributeGroup($attribute_group_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_group WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		return $query->row;
	}

	public function getAttributeGroups($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "attribute_group ag LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE agd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		$sort_data = array(
			'agd.name',
			'ag.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY agd.name";
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

	public function getAttributeGroupDescriptions($attribute_group_id) {
		$attribute_group_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_group_description WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		foreach ($query->rows as $result) {
			$attribute_group_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_group_data;
	}

	public function getTotalAttributeGroups() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute_group");

		return $query->row['total'];
	}

	public function updateAttributeGroupField($attribute_group_id, $data) {
		$int_fields = array('sort_order');

		$sets = array();
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
			}
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "attribute_group SET " . implode(', ', $sets) . " WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
		}
	}

	public function updateAttributeGroupNames($attribute_group_id, $names) {
		foreach ($names as $language_id => $name) {
			$name = trim((string)$name);

			$this->db->query("UPDATE " . DB_PREFIX . "attribute_group_description SET name = '" . $this->db->escape($name) . "' WHERE attribute_group_id = '" . (int)$attribute_group_id . "' AND language_id = '" . (int)$language_id . "'");
		}
	}
}