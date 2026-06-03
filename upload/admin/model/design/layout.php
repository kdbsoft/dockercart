<?php
class ModelDesignLayout extends Model {
	public function addLayout($data) {
		// Backward compat: convert old $data['name'] format to layout_description
		if (!isset($data['layout_description']) && isset($data['name'])) {
			$language_id = (int)$this->config->get('config_language_id');
			$data['layout_description'] = array(
				$language_id => array('name' => $data['name'])
			);
		}

		$name = '';
		if (!empty($data['layout_description'])) {
			$desc = reset($data['layout_description']);
			$name = isset($desc['name']) ? $desc['name'] : '';
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "layout SET name = '" . $this->db->escape($name) . "'");

		$layout_id = $this->db->getLastId();

		$this->saveLayoutDescriptions($layout_id, $data);

		if (isset($data['layout_route'])) {
			foreach ($data['layout_route'] as $layout_route) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "layout_route SET layout_id = '" . (int)$layout_id . "', store_id = '" . (int)$layout_route['store_id'] . "', route = '" . $this->db->escape($layout_route['route']) . "'");
			}
		}

		if (isset($data['layout_module'])) {
			foreach ($data['layout_module'] as $layout_module) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "layout_module SET layout_id = '" . (int)$layout_id . "', code = '" . $this->db->escape($layout_module['code']) . "', position = '" . $this->db->escape($layout_module['position']) . "', sort_order = '" . (int)$layout_module['sort_order'] . "'");
			}
		}

		return $layout_id;
	}

	public function editLayout($layout_id, $data) {
		// Backward compat: convert old $data['name'] format to layout_description
		if (!isset($data['layout_description']) && isset($data['name'])) {
			$language_id = (int)$this->config->get('config_language_id');
			$data['layout_description'] = array(
				$language_id => array('name' => $data['name'])
			);
		}

		$name = '';
		if (!empty($data['layout_description'])) {
			$desc = reset($data['layout_description']);
			$name = isset($desc['name']) ? $desc['name'] : '';
		}

		$this->db->query("UPDATE " . DB_PREFIX . "layout SET name = '" . $this->db->escape($name) . "' WHERE layout_id = '" . (int)$layout_id . "'");

		$this->saveLayoutDescriptions($layout_id, $data);

		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_route WHERE layout_id = '" . (int)$layout_id . "'");

		if (isset($data['layout_route'])) {
			foreach ($data['layout_route'] as $layout_route) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "layout_route SET layout_id = '" . (int)$layout_id . "', store_id = '" . (int)$layout_route['store_id'] . "', route = '" . $this->db->escape($layout_route['route']) . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_module WHERE layout_id = '" . (int)$layout_id . "'");

		if (isset($data['layout_module'])) {
			foreach ($data['layout_module'] as $layout_module) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "layout_module SET layout_id = '" . (int)$layout_id . "', code = '" . $this->db->escape($layout_module['code']) . "', position = '" . $this->db->escape($layout_module['position']) . "', sort_order = '" . (int)$layout_module['sort_order'] . "'");
			}
		}
	}

	public function deleteLayout($layout_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "layout WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_description WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_route WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_module WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "category_to_layout WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE layout_id = '" . (int)$layout_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "information_to_layout WHERE layout_id = '" . (int)$layout_id . "'");
	}

	public function getLayoutDescriptions($layout_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "layout_description WHERE layout_id = '" . (int)$layout_id . "'");

		$descriptions = array();
		foreach ($query->rows as $row) {
			$descriptions[$row['language_id']] = $row;
		}

		return $descriptions;
	}

	private function saveLayoutDescriptions($layout_id, $data) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "layout_description WHERE layout_id = '" . (int)$layout_id . "'");

		if (isset($data['layout_description'])) {
			foreach ($data['layout_description'] as $language_id => $value) {
				if (!empty($value['name'])) {
					$this->db->query("INSERT INTO " . DB_PREFIX . "layout_description SET layout_id = '" . (int)$layout_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
				}
			}
		}
	}

	public function getLayout($layout_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "layout WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->row;
	}

	public function getLayouts($data = array()) {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT l.*, ld.name FROM " . DB_PREFIX . "layout l LEFT JOIN " . DB_PREFIX . "layout_description ld ON (l.layout_id = ld.layout_id AND ld.language_id = '" . $language_id . "')";

		$sort_data = array('name');

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'name') {
				$sql .= " ORDER BY ld.name";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY ld.name";
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

	public function getLayoutRoutes($layout_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "layout_route WHERE layout_id = '" . (int)$layout_id . "'");

		return $query->rows;
	}

	public function getLayoutModules($layout_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "layout_module WHERE layout_id = '" . (int)$layout_id . "' ORDER BY position ASC, sort_order ASC");

		return $query->rows;
	}

	public function getTotalLayouts() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "layout");

		return $query->row['total'];
	}
}