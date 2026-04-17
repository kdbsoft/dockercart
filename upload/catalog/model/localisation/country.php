<?php
class ModelLocalisationCountry extends Model {
	private $countryDescriptionTableExists = null;

	public function getCountry($country_id) {
		$cache_key = 'country.catalog.item.' . (int)$country_id . '.' . (int)$this->config->get('config_language_id');
		$country_data = $this->cache->get($cache_key);

		if ($country_data === false) {
			if ($this->hasCountryDescriptionTable()) {
				$query = $this->db->query("SELECT c.country_id, COALESCE(cd.name, c.name) AS name, c.iso_code_2, c.iso_code_3, COALESCE(cd.address_format, c.address_format) AS address_format, c.postcode_required, c.status FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE c.country_id = '" . (int)$country_id . "' AND c.status = '1'");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$country_id . "' AND status = '1'");
			}

			$country_data = $query->row;
			$this->cache->set($cache_key, $country_data);
		}

		return $country_data;
	}

	public function getCountries() {
		$cache_key = $this->hasCountryDescriptionTable() ? 'country.catalog.' . (int)$this->config->get('config_language_id') : 'country.catalog';
		$country_data = $this->cache->get($cache_key);

		if ($country_data === false) {
			if ($this->hasCountryDescriptionTable()) {
				$query = $this->db->query("SELECT c.country_id, COALESCE(cd.name, c.name) AS name, c.iso_code_2, c.iso_code_3, COALESCE(cd.address_format, c.address_format) AS address_format, c.postcode_required, c.status FROM " . DB_PREFIX . "country c LEFT JOIN " . DB_PREFIX . "country_description cd ON (c.country_id = cd.country_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE c.status = '1' ORDER BY name ASC");
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country WHERE status = '1' ORDER BY name ASC");
			}

			$country_data = $query->rows;

			$this->cache->set($cache_key, $country_data);
		}

		return $country_data;
	}

	private function hasCountryDescriptionTable() {
		if ($this->countryDescriptionTableExists === null) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape(DB_PREFIX . "country_description") . "'");
			$this->countryDescriptionTableExists = (bool)$query->row['total'];
		}

		return $this->countryDescriptionTableExists;
	}
}