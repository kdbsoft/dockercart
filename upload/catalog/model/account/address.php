<?php
class ModelAccountAddress extends Model {
	public function addAddress($customer_id, $data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "address SET customer_id = '" . (int)$customer_id . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', company = '" . $this->db->escape($data['company']) . "', address_1 = '" . $this->db->escape($data['address_1']) . "', address_2 = '" . $this->db->escape($data['address_2']) . "', postcode = '" . $this->db->escape($data['postcode']) . "', city = '" . $this->db->escape($data['city']) . "', zone_id = '" . (int)$data['zone_id'] . "', country_id = '" . (int)$data['country_id'] . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['address']) ? json_encode($data['custom_field']['address']) : '') . "'");

		$address_id = $this->db->getLastId();

		if (!empty($data['default'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$customer_id . "'");
		}

		return $address_id;
	}

	public function editAddress($address_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "address SET firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', company = '" . $this->db->escape($data['company']) . "', address_1 = '" . $this->db->escape($data['address_1']) . "', address_2 = '" . $this->db->escape($data['address_2']) . "', postcode = '" . $this->db->escape($data['postcode']) . "', city = '" . $this->db->escape($data['city']) . "', zone_id = '" . (int)$data['zone_id'] . "', country_id = '" . (int)$data['country_id'] . "', custom_field = '" . $this->db->escape(isset($data['custom_field']['address']) ? json_encode($data['custom_field']['address']) : '') . "' WHERE address_id  = '" . (int)$address_id . "' AND customer_id = '" . (int)$this->customer->getId() . "'");

		if (!empty($data['default'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = '" . (int)$address_id . "' WHERE customer_id = '" . (int)$this->customer->getId() . "'");
		}
	}

	public function deleteAddress($address_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "address WHERE address_id = '" . (int)$address_id . "' AND customer_id = '" . (int)$this->customer->getId() . "'");
		$default_query = $this->db->query("SELECT address_id FROM " . DB_PREFIX . "customer WHERE address_id = '" . (int)$address_id . "' AND customer_id = '" . (int)$this->customer->getId() . "'");
		if ($default_query->num_rows) {
			$this->db->query("UPDATE " . DB_PREFIX . "customer SET address_id = 0 WHERE customer_id = '" . (int)$this->customer->getId() . "'");
		}
	}

	public function getAddress($address_id) {
		$address_query = $this->db->query("SELECT a.*, c.name AS country_name, c.iso_code_2, c.iso_code_3, c.address_format, z.name AS zone_name, z.code AS zone_code FROM " . DB_PREFIX . "address a LEFT JOIN `" . DB_PREFIX . "country` c ON (c.country_id = a.country_id) LEFT JOIN `" . DB_PREFIX . "zone` z ON (z.zone_id = a.zone_id) WHERE a.address_id = '" . (int)$address_id . "' AND a.customer_id = '" . (int)$this->customer->getId() . "'");

		if ($address_query->num_rows) {
			$address_data = array(
				'address_id'     => $address_query->row['address_id'],
				'firstname'      => $address_query->row['firstname'],
				'lastname'       => $address_query->row['lastname'],
				'company'        => $address_query->row['company'],
				'address_1'      => $address_query->row['address_1'],
				'address_2'      => $address_query->row['address_2'],
				'postcode'       => $address_query->row['postcode'],
				'city'           => $address_query->row['city'],
				'zone_id'        => $address_query->row['zone_id'],
				'zone'           => $address_query->row['zone_name'] ?? '',
				'zone_code'      => $address_query->row['zone_code'] ?? '',
				'country_id'     => $address_query->row['country_id'],
				'country'        => $address_query->row['country_name'] ?? '',
				'iso_code_2'     => $address_query->row['iso_code_2'] ?? '',
				'iso_code_3'     => $address_query->row['iso_code_3'] ?? '',
				'address_format' => $address_query->row['address_format'] ?? '',
				'custom_field'   => json_decode($address_query->row['custom_field'], true)
			);

			return $address_data;
		} else {
			return false;
		}
	}

	public function getAddresses() {
		$address_data = array();

		$query = $this->db->query("SELECT a.*, c.name AS country_name, c.iso_code_2, c.iso_code_3, c.address_format, z.name AS zone_name, z.code AS zone_code FROM " . DB_PREFIX . "address a LEFT JOIN `" . DB_PREFIX . "country` c ON (c.country_id = a.country_id) LEFT JOIN `" . DB_PREFIX . "zone` z ON (z.zone_id = a.zone_id) WHERE a.customer_id = '" . (int)$this->customer->getId() . "'");

		foreach ($query->rows as $result) {
			$address_data[$result['address_id']] = array(
				'address_id'     => $result['address_id'],
				'firstname'      => $result['firstname'],
				'lastname'       => $result['lastname'],
				'company'        => $result['company'],
				'address_1'      => $result['address_1'],
				'address_2'      => $result['address_2'],
				'postcode'       => $result['postcode'],
				'city'           => $result['city'],
				'zone_id'        => $result['zone_id'],
				'zone'           => $result['zone_name'] ?? '',
				'zone_code'      => $result['zone_code'] ?? '',
				'country_id'     => $result['country_id'],
				'country'        => $result['country_name'] ?? '',
				'iso_code_2'     => $result['iso_code_2'] ?? '',
				'iso_code_3'     => $result['iso_code_3'] ?? '',
				'address_format' => $result['address_format'] ?? '',
				'custom_field'   => json_decode($result['custom_field'], true)
			);
		}

		return $address_data;
	}

	public function getTotalAddresses() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "address WHERE customer_id = '" . (int)$this->customer->getId() . "'");

		return $query->row['total'];
	}
}
