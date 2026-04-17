<?php
class ModelLocalisationGeoZone extends Model {
	private $geoZoneDescriptionTableExists = null;
	private $countryDescriptionTableExists = null;
	private $zoneDescriptionTableExists = null;

	public function addGeoZone($data) {
		$geo_zone_description = $this->prepareGeoZoneDescriptionData($data);

		$this->db->query("INSERT INTO " . DB_PREFIX . "geo_zone SET name = '" . $this->db->escape($geo_zone_description['name']) . "', description = '" . $this->db->escape($geo_zone_description['description']) . "', date_added = NOW()");

		$geo_zone_id = $this->db->getLastId();

		if ($this->hasGeoZoneDescriptionTable()) {
			foreach ($geo_zone_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "geo_zone_description SET geo_zone_id = '" . (int)$geo_zone_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "', description = '" . $this->db->escape($description['description']) . "'");
			}
		}

		if (isset($data['zone_to_geo_zone'])) {
			foreach ($data['zone_to_geo_zone'] as $value) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "' AND country_id = '" . (int)$value['country_id'] . "' AND zone_id = '" . (int)$value['zone_id'] . "'");				

				$this->db->query("INSERT INTO " . DB_PREFIX . "zone_to_geo_zone SET country_id = '" . (int)$value['country_id'] . "', zone_id = '" . (int)$value['zone_id'] . "', geo_zone_id = '" . (int)$geo_zone_id . "', date_added = NOW()");
			}
		}

		$this->cache->delete('geo_zone');
		$this->cache->flush();
		
		return $geo_zone_id;
	}

	public function editGeoZone($geo_zone_id, $data) {
		$geo_zone_description = $this->prepareGeoZoneDescriptionData($data);

		$this->db->query("UPDATE " . DB_PREFIX . "geo_zone SET name = '" . $this->db->escape($geo_zone_description['name']) . "', description = '" . $this->db->escape($geo_zone_description['description']) . "', date_modified = NOW() WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

		if ($this->hasGeoZoneDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "geo_zone_description WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

			foreach ($geo_zone_description['descriptions'] as $language_id => $description) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "geo_zone_description SET geo_zone_id = '" . (int)$geo_zone_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($description['name']) . "', description = '" . $this->db->escape($description['description']) . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

		if (isset($data['zone_to_geo_zone'])) {
			foreach ($data['zone_to_geo_zone'] as $value) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "' AND country_id = '" . (int)$value['country_id'] . "' AND zone_id = '" . (int)$value['zone_id'] . "'");				

				$this->db->query("INSERT INTO " . DB_PREFIX . "zone_to_geo_zone SET country_id = '" . (int)$value['country_id'] . "', zone_id = '" . (int)$value['zone_id'] . "', geo_zone_id = '" . (int)$geo_zone_id . "', date_added = NOW()");
			}
		}

		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function deleteGeoZone($geo_zone_id) {
		if ($this->hasGeoZoneDescriptionTable()) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "geo_zone_description WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

		$this->cache->delete('geo_zone');
		$this->cache->flush();
	}

	public function getGeoZone($geo_zone_id) {
		$cache_key = 'geo_zone.item.' . (int)$geo_zone_id . '.' . (int)$this->config->get('config_language_id');
		$geo_zone_data = $this->cache->get($cache_key);

		if ($geo_zone_data === false) {
			if ($this->hasGeoZoneDescriptionTable()) {
				$query = $this->db->query("SELECT gz.*, COALESCE(gzd.name, gz.name) AS name, COALESCE(gzd.description, gz.description) AS description FROM " . DB_PREFIX . "geo_zone gz LEFT JOIN " . DB_PREFIX . "geo_zone_description gzd ON (gz.geo_zone_id = gzd.geo_zone_id AND gzd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE gz.geo_zone_id = '" . (int)$geo_zone_id . "'");
			} else {
				$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");
			}

			$geo_zone_data = $query->row;
			$this->cache->set($cache_key, $geo_zone_data);
		}

		return $geo_zone_data;
	}

	public function getGeoZones($data = array()) {
		if ($data) {
			$cache_key = 'geo_zone.list.' . (int)$this->config->get('config_language_id') . '.' . md5(json_encode($data));
			$geo_zone_data = $this->cache->get($cache_key);

			if ($geo_zone_data !== false) {
				return $geo_zone_data;
			}

			if ($this->hasGeoZoneDescriptionTable()) {
				$sql = "SELECT gz.geo_zone_id, COALESCE(gzd.name, gz.name) AS name, COALESCE(gzd.description, gz.description) AS description, gz.date_added, gz.date_modified FROM " . DB_PREFIX . "geo_zone gz LEFT JOIN " . DB_PREFIX . "geo_zone_description gzd ON (gz.geo_zone_id = gzd.geo_zone_id AND gzd.language_id = '" . (int)$this->config->get('config_language_id') . "')";
			} else {
				$sql = "SELECT * FROM " . DB_PREFIX . "geo_zone";
			}

			$sort_data = array(
				'name',
				'description'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
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
			$geo_zone_data = $query->rows;
			$this->cache->set($cache_key, $geo_zone_data);

			return $geo_zone_data;
		} else {
			$cache_key = $this->hasGeoZoneDescriptionTable() ? 'geo_zone.' . (int)$this->config->get('config_language_id') : 'geo_zone';
			$geo_zone_data = $this->cache->get($cache_key);

			if ($geo_zone_data === false) {
				if ($this->hasGeoZoneDescriptionTable()) {
					$query = $this->db->query("SELECT gz.geo_zone_id, COALESCE(gzd.name, gz.name) AS name, COALESCE(gzd.description, gz.description) AS description, gz.date_added, gz.date_modified FROM " . DB_PREFIX . "geo_zone gz LEFT JOIN " . DB_PREFIX . "geo_zone_description gzd ON (gz.geo_zone_id = gzd.geo_zone_id AND gzd.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY name ASC");
				} else {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "geo_zone ORDER BY name ASC");
				}

				$geo_zone_data = $query->rows;

				$this->cache->set($cache_key, $geo_zone_data);
			}

			return $geo_zone_data;
		}
	}

	public function getGeoZoneDescriptions($geo_zone_id) {
		$cache_key = 'geo_zone.descriptions.' . (int)$geo_zone_id;
		$geo_zone_description_data = $this->cache->get($cache_key);

		if ($geo_zone_description_data !== false) {
			return $geo_zone_description_data;
		}

		$geo_zone_description_data = array();

		if ($this->hasGeoZoneDescriptionTable()) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "geo_zone_description WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

			foreach ($query->rows as $result) {
				$geo_zone_description_data[$result['language_id']] = array(
					'name'        => $result['name'],
					'description' => $result['description']
				);
			}
		}

		if (!$geo_zone_description_data) {
			$geo_zone_query = $this->db->query("SELECT name, description FROM " . DB_PREFIX . "geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

			if ($geo_zone_query->num_rows) {
				$geo_zone_description_data[(int)$this->config->get('config_language_id')] = array(
					'name'        => $geo_zone_query->row['name'],
					'description' => $geo_zone_query->row['description']
				);
			}
		}

		$this->cache->set($cache_key, $geo_zone_description_data);

		return $geo_zone_description_data;
	}

	public function getTotalGeoZones() {
		$cache_key = 'geo_zone.total';
		$geo_zone_total = $this->cache->get($cache_key);

		if ($geo_zone_total !== false) {
			return (int)$geo_zone_total;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "geo_zone");
		$geo_zone_total = (int)$query->row['total'];
		$this->cache->set($cache_key, $geo_zone_total);

		return $geo_zone_total;
	}

	public function getZoneToGeoZones($geo_zone_id) {
		$cache_key = 'geo_zone.map.' . (int)$geo_zone_id;
		$zone_to_geo_zone_data = $this->cache->get($cache_key);

		if ($zone_to_geo_zone_data === false) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "' ORDER BY country_id ASC, zone_id ASC");
			$zone_to_geo_zone_data = $query->rows;
			$this->cache->set($cache_key, $zone_to_geo_zone_data);
		}

		return $zone_to_geo_zone_data;
	}

	public function getTotalZoneToGeoZoneByGeoZoneId($geo_zone_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$geo_zone_id . "'");

		return $query->row['total'];
	}

	public function getTotalZoneToGeoZoneByCountryId($country_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$country_id . "'");

		return $query->row['total'];
	}

	public function getTotalZoneToGeoZoneByZoneId($zone_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "zone_to_geo_zone WHERE zone_id = '" . (int)$zone_id . "'");

		return $query->row['total'];
	}

	public function getZonesByGeoZones($geo_zone_ids) {
		if (empty($geo_zone_ids)) {
			return array();
		}

		$cache_key = 'geo_zone.zones.' . (int)$this->config->get('config_language_id') . '.' . md5(json_encode($geo_zone_ids));
		$zones_data = $this->cache->get($cache_key);

		if ($zones_data !== false) {
			return $zones_data;
		}

		$sql  = "SELECT DISTINCT zgz.country_id, z.zone_id, c.`name` AS country, z.`name` AS zone ";
		$sql .= "FROM `".DB_PREFIX."zone_to_geo_zone` AS zgz ";
		$sql .= "LEFT JOIN `".DB_PREFIX."country` c ON c.country_id=zgz.country_id ";
		$sql .= "LEFT JOIN `".DB_PREFIX."zone` z ON z.country_id=c.country_id ";

		if ($this->hasCountryDescriptionTable()) {
			$sql  = "SELECT DISTINCT zgz.country_id, z.zone_id, COALESCE(cd.name, c.`name`) AS country, ";
			$sql .= ($this->hasZoneDescriptionTable() ? "COALESCE(zd.name, z.`name`)" : "z.`name`") . " AS zone ";
			$sql .= "FROM `" . DB_PREFIX . "zone_to_geo_zone` AS zgz ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "country` c ON c.country_id=zgz.country_id ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "country_description` cd ON (cd.country_id = c.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "zone` z ON z.country_id=c.country_id ";

			if ($this->hasZoneDescriptionTable()) {
				$sql .= "LEFT JOIN `" . DB_PREFIX . "zone_description` zd ON (zd.zone_id = z.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') ";
			}
		} elseif ($this->hasZoneDescriptionTable()) {
			$sql  = "SELECT DISTINCT zgz.country_id, z.zone_id, c.`name` AS country, COALESCE(zd.name, z.`name`) AS zone ";
			$sql .= "FROM `" . DB_PREFIX . "zone_to_geo_zone` AS zgz ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "country` c ON c.country_id=zgz.country_id ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "zone` z ON z.country_id=c.country_id ";
			$sql .= "LEFT JOIN `" . DB_PREFIX . "zone_description` zd ON (zd.zone_id = z.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') ";
		}

		$sql .= "WHERE zgz.geo_zone_id IN (".implode(',',$geo_zone_ids).") ";
		$sql .= "ORDER BY country_id ASC, zone ASC;";
		$query = $this->db->query( $sql );
		$results = array();
		foreach ($query->rows as $row) {
			$country_id = $row['country_id'];
			if (!isset($results[$country_id])) {
				$results[$country_id] = array();
			}
			$results[$country_id][$row['zone_id']] = $row['zone'];
		}

		$this->cache->set($cache_key, $results);

		return $results;
	}

	private function prepareGeoZoneDescriptionData($data) {
		$descriptions = array();
		$config_language_id = (int)$this->config->get('config_language_id');

		if (!empty($data['geo_zone_description']) && is_array($data['geo_zone_description'])) {
			foreach ($data['geo_zone_description'] as $language_id => $description) {
				$descriptions[(int)$language_id] = array(
					'name'        => isset($description['name']) ? trim($description['name']) : '',
					'description' => isset($description['description']) ? trim($description['description']) : ''
				);
			}
		}

		if (!$descriptions) {
			$descriptions[$config_language_id] = array(
				'name'        => isset($data['name']) ? trim($data['name']) : '',
				'description' => isset($data['description']) ? trim($data['description']) : ''
			);
		}

		if (!isset($descriptions[$config_language_id])) {
			$first_description = reset($descriptions);
			$descriptions[$config_language_id] = array(
				'name'        => $first_description['name'],
				'description' => $first_description['description']
			);
		}

		return array(
			'name' => $descriptions[$config_language_id]['name'],
			'description' => $descriptions[$config_language_id]['description'],
			'descriptions' => $descriptions
		);
	}

	private function hasGeoZoneDescriptionTable() {
		if ($this->geoZoneDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "geo_zone_description") . "'");
			$this->geoZoneDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->geoZoneDescriptionTableExists;
	}

	private function hasCountryDescriptionTable() {
		if ($this->countryDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "country_description") . "'");
			$this->countryDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->countryDescriptionTableExists;
	}

	private function hasZoneDescriptionTable() {
		if ($this->zoneDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "zone_description") . "'");
			$this->zoneDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->zoneDescriptionTableExists;
	}
}