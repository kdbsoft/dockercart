<?php
class ModelCatalogInformation extends Model {
	public function getInformation($information_id) {
		$cache_key = 'information.item.' . (int)$information_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$information = $this->cache->get($cache_key);

		if ($information === null || $information === false) {
			$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) LEFT JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE i.information_id = '" . (int)$information_id . "' AND id.language_id = '" . (int)$this->config->get('config_language_id') . "' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND i.status = '1'");

			$information = $query->row;
			$this->cache->set($cache_key, $information, 3600);
		}

		return $information;
	}

	public function getInformations() {
		$cache_key = 'information.list.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');
		$information_data = $this->cache->get($cache_key);

		if (!is_array($information_data)) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information i LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id) LEFT JOIN " . DB_PREFIX . "information_to_store i2s ON (i.information_id = i2s.information_id) WHERE id.language_id = '" . (int)$this->config->get('config_language_id') . "' AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND i.status = '1' ORDER BY i.sort_order, LCASE(id.title) ASC");

			$information_data = $query->rows;
			$this->cache->set($cache_key, $information_data, 3600);
		}

		return $information_data;
	}

	public function getInformationLayoutId($information_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_layout WHERE information_id = '" . (int)$information_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}
}