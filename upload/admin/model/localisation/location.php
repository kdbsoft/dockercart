<?php
class ModelLocalisationLocation extends Model {
	public function addLocation($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "location SET name = '" . $this->db->escape($data['name']) . "', address = '" . $this->db->escape($data['address']) . "', geocode = '" . $this->db->escape($data['geocode']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', fax = '" . $this->db->escape($data['fax']) . "', image = '" . $this->db->escape($data['image']) . "', open = '" . $this->db->escape($data['open']) . "', comment = '" . $this->db->escape($data['comment']) . "'");

		$this->cache->delete('location');
		$this->cache->flush();
	
		return $this->db->getLastId();
	}

	public function editLocation($location_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "location SET name = '" . $this->db->escape($data['name']) . "', address = '" . $this->db->escape($data['address']) . "', geocode = '" . $this->db->escape($data['geocode']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', fax = '" . $this->db->escape($data['fax']) . "', image = '" . $this->db->escape($data['image']) . "', open = '" . $this->db->escape($data['open']) . "', comment = '" . $this->db->escape($data['comment']) . "' WHERE location_id = '" . (int)$location_id . "'");

		$this->cache->delete('location');
		$this->cache->flush();
	}

	public function deleteLocation($location_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "location WHERE location_id = " . (int)$location_id);

		$this->cache->delete('location');
		$this->cache->flush();
	}

	public function getLocation($location_id) {
		$cache_key = 'location.item.' . (int)$location_id;
		$location_data = $this->cache->get($cache_key);

		if ($location_data === false) {
			$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "location WHERE location_id = '" . (int)$location_id . "'");
			$location_data = $query->row;
			$this->cache->set($cache_key, $location_data);
		}

		return $location_data;
	}

	public function getLocations($data = array()) {
		$cache_key = 'location.list.' . md5(json_encode($data));
		$locations_data = $this->cache->get($cache_key);

		if ($locations_data !== false) {
			return $locations_data;
		}

		$sql = "SELECT location_id, name, address FROM " . DB_PREFIX . "location";

		$sort_data = array(
			'name',
			'address',
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
		$locations_data = $query->rows;
		$this->cache->set($cache_key, $locations_data);

		return $locations_data;
	}

	public function getTotalLocations() {
		$cache_key = 'location.total';
		$locations_total = $this->cache->get($cache_key);

		if ($locations_total !== false) {
			return (int)$locations_total;
		}

		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "location");
		$locations_total = (int)$query->row['total'];
		$this->cache->set($cache_key, $locations_total);

		return $locations_total;
	}
}
