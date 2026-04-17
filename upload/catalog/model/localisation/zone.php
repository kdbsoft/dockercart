<?php
class ModelLocalisationZone extends Model {
	private $zoneDescriptionTableExists = null;

	public function getZone($zone_id) {
		$cache_key = 'zone.catalog.item.' . (int)$zone_id . '.' . (int)$this->config->get('config_language_id');
		$zone_data = $this->cache->get($cache_key);

		if ($zone_data === false) {
			if ($this->hasZoneDescriptionTable()) {
				$query = $this->db->query("SELECT z.zone_id, z.country_id, COALESCE(zd.name, z.name) AS name, z.code, z.status FROM " . DB_PREFIX . "zone z LEFT JOIN " . DB_PREFIX . "zone_description zd ON (z.zone_id = zd.zone_id AND zd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE z.zone_id = '" . (int)$zone_id . "' AND z.status = '1'");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$zone_id . "' AND status = '1'");
			}

			$zone_data = $query->row;
			$this->cache->set($cache_key, $zone_data);
		}

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

	private function hasZoneDescriptionTable() {
		if ($this->zoneDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "zone_description") . "'");
			$this->zoneDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->zoneDescriptionTableExists;
	}
}