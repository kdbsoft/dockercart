<?php
class ModelLocalisationCountry extends Model {
	private $countryDescriptionTableExists = null;

	public function addCountry($data) {
		$country_description = $this->prepareCountryDescriptionData($data);

		$this->db->query("INSERT INTO " . DB_PREFIX . "country SET name = '" . $this->db->escape($country_description['name']) . "', iso_code_2 = '" . $this->db->escape($data['iso_code_2']) . "', iso_code_3 = '" . $this->db->escape($data['iso_code_3']) . "', address_format = '" . $this->db->escape($country_description['address_format']) . "', postcode_required = '" . (int)$data['postcode_required'] . "', status = '" . (int)$data['status'] . "'");

		$country_id = $this->db->getLastId();

		if ($this->hasCountryDescriptionTable()) {
			foreach ($country_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "country_description SET country_id = '" . (int)$country_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "', address_format = '" . $this->db->escape($description['address_format']) . "'");
			}
		}

		$this->cache->delete('country');
		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
		
		return $country_id;
	}

	public function editCountry($country_id, $data) {
		$country_description = $this->prepareCountryDescriptionData($data);

		$this->db->query("UPDATE " . DB_PREFIX . "country SET name = '" . $this->db->escape($country_description['name']) . "', iso_code_2 = '" . $this->db->escape($data['iso_code_2']) . "', iso_code_3 = '" . $this->db->escape($data['iso_code_3']) . "', address_format = '" . $this->db->escape($country_description['address_format']) . "', postcode_required = '" . (int)$data['postcode_required'] . "', status = '" . (int)$data['status'] . "' WHERE country_id = '" . (int)$country_id . "'");

		if ($this->hasCountryDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "country_description WHERE country_id = '" . (int)$country_id . "'");

			foreach ($country_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "country_description SET country_id = '" . (int)$country_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "', address_format = '" . $this->db->escape($description['address_format']) . "'");
			}
		}

		$this->cache->delete('country');
		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function deleteCountry($country_id) {
		if ($this->hasCountryDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "country_description WHERE country_id = '" . (int)$country_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "'");

		$this->cache->delete('country');
		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function getCountry($country_id) {
		$cache_key = 'country.item.' . (int)$country_id . '.' . (int)$this->config->get('config_language_id');
		$country_data = $this->cache->get($cache_key);

		if ($country_data === false) {
			if ($this->hasCountryDescriptionTable()) {
				$query = $this->db->query("SELECT c.*, COALESCE(cd.name, c.name) AS name, COALESCE(cd.address_format, c.address_format) AS address_format FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE c.country_id = '" . (int)$country_id . "'");
			} else {
				$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "'");
			}

			$country_data = $query->row;
			$this->cache->set($cache_key, $country_data);
		}

		return $country_data;
	}

	public function getCountries($data = array()) {
		if ($data) {
			$cache_key = 'country.list.' . (int)$this->config->get('config_language_id') . '.' . md5(json_encode($data));
			$country_data = $this->cache->get($cache_key);

			if ($country_data !== false) {
				return $country_data;
			}

			if ($this->hasCountryDescriptionTable()) {
				$sql = "SELECT c.country_id, COALESCE(cd.name, c.name) AS name, c.iso_code_2, c.iso_code_3, COALESCE(cd.address_format, c.address_format) AS address_format, c.postcode_required, c.status FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')";
			} else {
				$sql = "SELECT * FROM " . DB_PREFIX . "country";
			}

			if (!empty($data['filter_name'])) {
				if ($this->hasCountryDescriptionTable()) {
					$sql .= " WHERE COALESCE(cd.name, c.name) LIKE '" . $this->db->escape($data['filter_name']) . "%'";
				} else {
					$sql .= " WHERE name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			$sort_data = array(
				'name' => $this->hasCountryDescriptionTable() ? 'name' : 'name',
				'iso_code_2' => $this->hasCountryDescriptionTable() ? 'c.iso_code_2' : 'iso_code_2',
				'iso_code_3' => $this->hasCountryDescriptionTable() ? 'c.iso_code_3' : 'iso_code_3'
			);

			if (isset($data['sort']) && isset($sort_data[$data['sort']])) {
				$sql .= " ORDER BY " . $sort_data[$data['sort']];
			} else {
				$sql .= " ORDER BY name";
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
			$country_data = $query->rows;
			$this->cache->set($cache_key, $country_data);

			return $country_data;
		} else {
			$cache_key = $this->hasCountryDescriptionTable() ? 'country.admin.' . (int)$this->config->get('config_language_id') : 'country.admin';
			$country_data = $this->cache->get($cache_key);

			if ($country_data === false) {
				if ($this->hasCountryDescriptionTable()) {
					$query = $this->db->query("SELECT c.country_id, COALESCE(cd.name, c.name) AS name, c.iso_code_2, c.iso_code_3, COALESCE(cd.address_format, c.address_format) AS address_format, c.postcode_required, c.status FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY name ASC");
				} else {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country ORDER BY name ASC");
				}

				$country_data = $query->rows;

				$this->cache->set($cache_key, $country_data);
			}

			return $country_data;
		}
	}

	public function getCountryDescriptions($country_id) {
		$cache_key = 'country.descriptions.' . (int)$country_id;
		$country_description_data = $this->cache->get($cache_key);

		if ($country_description_data !== false) {
			return $country_description_data;
		}

		$country_description_data = array();

		if ($this->hasCountryDescriptionTable()) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country_description WHERE country_id = '" . (int)$country_id . "'");

			foreach ($query->rows as $result) {
				$country_description_data[$result['language_id']] = array(
					'name'           => $result['name'],
					'address_format' => $result['address_format']
				);
			}
		}

		if (!$country_description_data) {
			$country_query = $this->db->query("SELECT name, address_format FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "'");

			if ($country_query->num_rows) {
				$country_description_data[(int)$this->config->get('config_language_id')] = array(
					'name'           => $country_query->row['name'],
					'address_format' => $country_query->row['address_format']
				);
			}
		}

		$this->cache->set($cache_key, $country_description_data);

		return $country_description_data;
	}

	public function getTotalCountries($data = array()) {
		$cache_key = 'country.total.' . (int)$this->config->get('config_language_id') . '.' . md5(json_encode($data));
		$country_total = $this->cache->get($cache_key);

		if ($country_total !== false) {
			return (int)$country_total;
		}

		if ($this->hasCountryDescriptionTable()) {
			$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')";
		} else {
			$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "country";
		}

		if (!empty($data['filter_name'])) {
			if ($this->hasCountryDescriptionTable()) {
				$sql .= " WHERE COALESCE(cd.name, c.name) LIKE '" . $this->db->escape($data['filter_name']) . "%'";
			} else {
				$sql .= " WHERE name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
			}
		}

		$query = $this->db->query($sql);
		$country_total = (int)$query->row['total'];
		$this->cache->set($cache_key, $country_total);

		return $country_total;
	}

	private function prepareCountryDescriptionData($data) {
		$descriptions = array();
		$config_language_id = (int)$this->config->get('config_language_id');

		if (!empty($data['country_description']) && is_array($data['country_description'])) {
			foreach ($data['country_description'] as $language_id => $description) {
				$descriptions[(int)$language_id] = array(
					'name'           => isset($description['name']) ? trim($description['name']) : '',
					'address_format' => isset($description['address_format']) ? trim($description['address_format']) : ''
				);
			}
		}

		if (!$descriptions) {
			$descriptions[$config_language_id] = array(
				'name'           => isset($data['name']) ? trim($data['name']) : '',
				'address_format' => isset($data['address_format']) ? trim($data['address_format']) : ''
			);
		}

		if (!isset($descriptions[$config_language_id])) {
			$first_description = reset($descriptions);
			$descriptions[$config_language_id] = array(
				'name'           => $first_description['name'],
				'address_format' => $first_description['address_format']
			);
		}

		return array(
			'name'         => $descriptions[$config_language_id]['name'],
			'address_format' => $descriptions[$config_language_id]['address_format'],
			'descriptions' => $descriptions
		);
	}

	private function hasCountryDescriptionTable() {
		if ($this->countryDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "country_description") . "'");
			$this->countryDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->countryDescriptionTableExists;
	}
}