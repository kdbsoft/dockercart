<?php
class ModelCatalogProductBundle extends Model {
	public function addBundle($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle SET name = '" . $this->db->escape($data['name']) . "', discount_type = '" . $this->db->escape($data['discount_type']) . "', discount_value = '" . (float)$data['discount_value'] . "', date_start = '" . $this->db->escape($data['date_start']) . "', date_end = '" . $this->db->escape($data['date_end']) . "', status = '" . (int)$data['status'] . "', sort_order = '" . (int)$data['sort_order'] . "'");

		$bundle_id = $this->db->getLastId();

		if (isset($data['bundle_product'])) {
			foreach ($data['bundle_product'] as $product_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$product_id . "'");
			}
		}

		if (isset($data['bundle_store'])) {
			foreach ($data['bundle_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_store SET bundle_id = '" . (int)$bundle_id . "', store_id = '" . (int)$store_id . "'");
			}
		}

		return $bundle_id;
	}

	public function editBundle($bundle_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "product_bundle SET name = '" . $this->db->escape($data['name']) . "', discount_type = '" . $this->db->escape($data['discount_type']) . "', discount_value = '" . (float)$data['discount_value'] . "', date_start = '" . $this->db->escape($data['date_start']) . "', date_end = '" . $this->db->escape($data['date_end']) . "', status = '" . (int)$data['status'] . "', sort_order = '" . (int)$data['sort_order'] . "' WHERE bundle_id = '" . (int)$bundle_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		if (isset($data['bundle_product'])) {
			foreach ($data['bundle_product'] as $product_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$product_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_store WHERE bundle_id = '" . (int)$bundle_id . "'");

		if (isset($data['bundle_store'])) {
			foreach ($data['bundle_store'] as $store_id) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_store SET bundle_id = '" . (int)$bundle_id . "', store_id = '" . (int)$store_id . "'");
			}
		}
	}

	public function deleteBundle($bundle_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle WHERE bundle_id = '" . (int)$bundle_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_store WHERE bundle_id = '" . (int)$bundle_id . "'");
	}

	public function getBundle($bundle_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product_bundle WHERE bundle_id = '" . (int)$bundle_id . "'");

		return $query->row;
	}

	public function getBundles($data = array()) {
		$sql = "SELECT b.bundle_id, b.name, b.discount_type, b.discount_value, b.date_start, b.date_end, b.status, b.sort_order, COUNT(bp.product_id) as product_count FROM " . DB_PREFIX . "product_bundle b LEFT JOIN " . DB_PREFIX . "product_bundle_product bp ON (b.bundle_id = bp.bundle_id)";

		$sql .= " GROUP BY b.bundle_id";

		$sort_data = array(
			'b.name',
			'product_count',
			'b.discount_value',
			'b.status',
			'b.date_start',
			'b.date_end',
			'b.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY b.sort_order";
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

	public function getTotalBundles() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_bundle");

		return $query->row['total'];
	}

	public function getBundleProducts($bundle_id) {
		$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		$products = array();

		foreach ($query->rows as $row) {
			$products[] = $row['product_id'];
		}

		return $products;
	}

	public function getBundleStores($bundle_id) {
		$query = $this->db->query("SELECT store_id FROM " . DB_PREFIX . "product_bundle_store WHERE bundle_id = '" . (int)$bundle_id . "'");

		$stores = array();

		foreach ($query->rows as $row) {
			$stores[] = $row['store_id'];
		}

		return $stores;
	}
}
