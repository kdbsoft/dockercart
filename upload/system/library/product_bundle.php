<?php
class ProductBundle {
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function getBundleProducts($bundle_id) {
		$query = $this->db->query("SELECT bp.product_id, p.price, p.tax_class_id FROM " . DB_PREFIX . "product_bundle_product bp LEFT JOIN " . DB_PREFIX . "product p ON (bp.product_id = p.product_id) WHERE bp.bundle_id = '" . (int)$bundle_id . "' ORDER BY bp.bundle_product_id ASC");

		return $query->rows;
	}

	public function getBundlesByProduct($product_id, $store_id = 0) {
		$sql = "SELECT DISTINCT b.* FROM " . DB_PREFIX . "product_bundle b "
			. "INNER JOIN " . DB_PREFIX . "product_bundle_product bp ON (b.bundle_id = bp.bundle_id) "
			. "INNER JOIN " . DB_PREFIX . "product_bundle_store bs ON (b.bundle_id = bs.bundle_id) "
			. "WHERE bp.product_id = '" . (int)$product_id . "' "
			. "AND bs.store_id = '" . (int)$store_id . "' "
			. "AND b.status = '1' "
			. "AND (b.date_start = '0000-00-00' OR b.date_start <= CURDATE()) "
			. "AND (b.date_end = '0000-00-00' OR b.date_end >= CURDATE()) "
			. "ORDER BY b.sort_order ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getActiveBundles($store_id = 0) {
		$sql = "SELECT DISTINCT b.* FROM " . DB_PREFIX . "product_bundle b "
			. "INNER JOIN " . DB_PREFIX . "product_bundle_store bs ON (b.bundle_id = bs.bundle_id) "
			. "WHERE bs.store_id = '" . (int)$store_id . "' "
			. "AND b.status = '1' "
			. "AND (b.date_start = '0000-00-00' OR b.date_start <= CURDATE()) "
			. "AND (b.date_end = '0000-00-00' OR b.date_end >= CURDATE()) "
			. "ORDER BY b.sort_order ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function calculateDiscount($products_total, $discount_type, $discount_value, $set_count) {
		if ($discount_type == 'percentage') {
			return $products_total * ((float)$discount_value / 100) * (int)$set_count;
		} else {
			return (float)$discount_value * (int)$set_count;
		}
	}
}
