<?php
class ModelLocalisationZone extends Model {
	private $zoneDescriptionTableExists = null;
	private $countryDescriptionTableExists = null;

	public function addZone($data) {
		$zone_description = $this->prepareZoneDescriptionData($data);

		$this->db->query("INSERT INTO " . DB_PREFIX . "zone SET status = '" . (int)$data['status'] . "', name = '" . $this->db->escape($zone_description['name']) . "', code = '" . $this->db->escape($data['code']) . "', country_id = '" . (int)$data['country_id'] . "'");

		$zone_id = $this->db->getLastId();

		if ($this->hasZoneDescriptionTable()) {
			foreach ($zone_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "zone_description SET zone_id = '" . (int)$zone_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "'");
			}
		}

		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
		
		return $zone_id;
	}

	public function editZone($zone_id, $data) {
		$zone_description = $this->prepareZoneDescriptionData($data);

		$this->db->query("UPDATE " . DB_PREFIX . "zone SET status = '" . (int)$data['status'] . "', name = '" . $this->db->escape($zone_description['name']) . "', code = '" . $this->db->escape($data['code']) . "', country_id = '" . (int)$data['country_id'] . "' WHERE zone_id = '" . (int)$zone_id . "'");

		if ($this->hasZoneDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "zone_description WHERE zone_id = '" . (int)$zone_id . "'");

			foreach ($zone_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "zone_description SET zone_id = '" . (int)$zone_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "'");
			}
		}

		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function deleteZone($zone_id) {
		if ($this->hasZoneDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "zone_description WHERE zone_id = '" . (int)$zone_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$zone_id . "'");

		$this->cache->delete('zone');
		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function getZone($zone_id) {
		$cache_key = 'zone.item.' . (int)$zone_id . '.' . (int)$this->config->get('config_language_id');
		$zone_data = $this->cache->get($cache_key);

		if ($zone_data === false) {
			if ($this->hasZoneDescriptionTable()) {
				$query = $this->db->query("SELECT z.*, COALESCE(zd.name, z.name) AS name FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "zone_description zd ON (z.zone_id = zd.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE z.zone_id = '" . (int)$zone_id . "'");
			} else {
				$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$zone_id . "'");
			}

			$zone_data = $query->row;
			$this->cache->set($cache_key, $zone_data);
		}

		return $zone_data;
	}

	public function getZones($data = array()) {
		$cache_key = 'zone.list.' . (int)$this->config->get('config_language_id') . '.' . md5(json_encode($data));
		$zone_data = $this->cache->get($cache_key);

		if ($zone_data !== false) {
			return $zone_data;
		}

		if ($this->hasZoneDescriptionTable()) {
			$sql = "SELECT z.zone_id, z.country_id, COALESCE(zd.name, z.name) AS name, z.code, z.status, " . ($this->hasCountryDescriptionTable() ? "COALESCE(cd.name, c.name)" : "c.name") . " AS country FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "zone_description zd ON (z.zone_id = zd.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN " . DB_PREFIX . "country c ON (z.country_id = c.country_id)";

			if ($this->hasCountryDescriptionTable()) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')";
			}
		} else {
			$sql = "SELECT *, z.name, c.name AS country FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "country c ON (z.country_id = c.country_id)";
		}

		$conditions = array();

		if (!empty($data['filter_country_id'])) {
			$conditions[] = "z.country_id = '" . (int)$data['filter_country_id'] . "'";
		}

		if ($conditions) {
			$sql .= " WHERE " . implode(" AND ", $conditions);
		}

		$sort_data = array(
			'c.name' => 'country',
			'z.name' => 'name',
			'z.code' => 'z.code'
		);

		if (isset($data['sort']) && isset($sort_data[$data['sort']])) {
			$sql .= " ORDER BY " . $sort_data[$data['sort']];
		} else {
			$sql .= " ORDER BY country";
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
		$zone_data = $query->rows;
		$this->cache->set($cache_key, $zone_data);

		return $zone_data;
	}

	public function getZonesByCountryId($country_id) {
		$cache_key = $this->hasZoneDescriptionTable() ? 'zone.' . (int)$country_id . '.' . (int)$this->config->get('config_language_id') : 'zone.' . (int)$country_id;
		$zone_data = $this->cache->get($cache_key);

		if ($zone_data === false) {
			if ($this->hasZoneDescriptionTable()) {
				$query = $this->db->query("SELECT z.zone_id, z.country_id, COALESCE(zd.name, z.name) AS name, z.code, z.status FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "zone_description zd ON (z.zone_id = zd.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE z.country_id = '" . (int)$country_id . "' AND z.status = '1' ORDER BY name");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE country_id = '" . (int)$country_id . "' AND status = '1' ORDER BY name");
			}

			$zone_data = $query->rows;

			$this->cache->set($cache_key, $zone_data);
		}

		return $zone_data;
	}

	public function getZoneDescriptions($zone_id) {
		$cache_key = 'zone.descriptions.' . (int)$zone_id;
		$zone_description_data = $this->cache->get($cache_key);

		if ($zone_description_data !== false) {
			return $zone_description_data;
		}

		$zone_description_data = array();

		if ($this->hasZoneDescriptionTable()) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_description WHERE zone_id = '" . (int)$zone_id . "'");

			foreach ($query->rows as $result) {
				$zone_description_data[$result['language_id']] = array('name' => $result['name']);
			}
		}

		if (!$zone_description_data) {
			$zone_query = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$zone_id . "'");

			if ($zone_query->num_rows) {
				$zone_description_data[(int)$this->config->get('config_language_id')] = array('name' => $zone_query->row['name']);
			}
		}

		$this->cache->set($cache_key, $zone_description_data);

		return $zone_description_data;
	}

	public function getTotalZones($data = array()) {
		$cache_key = 'zone.total.' . md5(json_encode($data));
		$zone_total = $this->cache->get($cache_key);

		if ($zone_total !== false) {
			return (int)$zone_total;
		}

		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "zone";

		if (!empty($data['filter_country_id'])) {
			$sql .= " WHERE country_id = '" . (int)$data['filter_country_id'] . "'";
		}

		$query = $this->db->query($sql);
		$zone_total = (int)$query->row['total'];
		$this->cache->set($cache_key, $zone_total);

		return $zone_total;
	}

	public function getTotalZonesByCountryId($country_id) {
		$cache_key = 'zone.total.by_country.' . (int)$country_id;
		$zone_total = $this->cache->get($cache_key);

		if ($zone_total !== false) {
			return (int)$zone_total;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "zone WHERE country_id = '" . (int)$country_id . "'");
		$zone_total = (int)$query->row['total'];
		$this->cache->set($cache_key, $zone_total);

		return $zone_total;
	}

	private function prepareZoneDescriptionData($data) {
		$descriptions = array();
		$config_language_id = (int)$this->config->get('config_language_id');

		if (!empty($data['zone_description']) && is_array($data['zone_description'])) {
			foreach ($data['zone_description'] as $language_id => $description) {
				$descriptions[(int)$language_id] = array(
					'name' => isset($description['name']) ? trim($description['name']) : ''
				);
			}
		}

		if (!$descriptions) {
			$descriptions[$config_language_id] = array(
				'name' => isset($data['name']) ? trim($data['name']) : ''
			);
		}

		if (!isset($descriptions[$config_language_id])) {
			$first_description = reset($descriptions);
			$descriptions[$config_language_id] = array(
				'name' => $first_description['name']
			);
		}

		return array(
			'name' => $descriptions[$config_language_id]['name'],
			'descriptions' => $descriptions
		);
	}

	private function hasZoneDescriptionTable() {
		if ($this->zoneDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "zone_description") . "'");
			$this->zoneDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->zoneDescriptionTableExists;
	}

	private function hasCountryDescriptionTable() {
		if ($this->countryDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "country_description") . "'");
			$this->countryDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->countryDescriptionTableExists;
	}
}