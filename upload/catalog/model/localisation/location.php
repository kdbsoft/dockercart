<?php
class ModelLocalisationLocation extends Model {
	public function getLocation($location_id) {
		$cache_key = 'location.catalog.item.' . (int)$location_id;
		$location_data = $this->cache->get($cache_key);

		if ($location_data === false) {
			$query = $this->db->query("SELECT location_id, name, address, geocode, telephone, fax, image, open, comment FROM " . DB_PREFIX . "location WHERE location_id = '" . (int)$location_id . "'");
			$location_data = $query->row;
			$this->cache->set($cache_key, $location_data);
		}

		return $location_data;
	}
}