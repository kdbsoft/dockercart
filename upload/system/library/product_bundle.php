<?php
/** @property \DB $db
 * @property \Cache $cache */
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
		$this->autoRenewBundles();

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
		$this->autoRenewBundles();

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

	private function autoRenewBundles() {
		static $done = false;
		$today = date('Y-m-d');
		$cache_key = 'auto_renew.bundle.' . $today;

		if ($done) {
			return;
		}

		if ($this->cache->get($cache_key)) {
			$done = true;
			return;
		}

		$expired = $this->db->query("
			SELECT * FROM " . DB_PREFIX . "product_bundle
			WHERE auto_renew = '1'
				AND status = '1'
				AND date_end < CURDATE()
				AND date_end != '0000-00-00'
		");

		foreach ($expired->rows as $bundle) {
			$duration = $this->db->query("SELECT DATEDIFF('" . $this->db->escape($bundle['date_end']) . "', '" . $this->db->escape($bundle['date_start']) . "') AS days")->row['days'];

			$check = $this->db->query("
				SELECT COUNT(*) AS total FROM " . DB_PREFIX . "product_bundle
				WHERE name = '" . $this->db->escape($bundle['name']) . "'
					AND discount_type = '" . $this->db->escape($bundle['discount_type']) . "'
					AND discount_value = '" . (float)$bundle['discount_value'] . "'
					AND date_end > CURDATE()
			");

			if ($check->row['total'] > 0) {
				continue;
			}

			$this->db->query("
				INSERT INTO " . DB_PREFIX . "product_bundle SET
					name = '" . $this->db->escape($bundle['name']) . "',
					discount_type = '" . $this->db->escape($bundle['discount_type']) . "',
					discount_value = '" . (float)$bundle['discount_value'] . "',
					date_start = CURDATE(),
					date_end = DATE_ADD(CURDATE(), INTERVAL " . (int)$duration . " DAY),
					status = '1',
					sort_order = '" . (int)$bundle['sort_order'] . "',
					auto_renew = '1'
			");

			$new_id = $this->db->getLastId();

			$products = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle['bundle_id'] . "'");

			foreach ($products->rows as $row) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_product SET bundle_id = '" . (int)$new_id . "', product_id = '" . (int)$row['product_id'] . "'");
			}

			$stores = $this->db->query("SELECT store_id FROM " . DB_PREFIX . "product_bundle_store WHERE bundle_id = '" . (int)$bundle['bundle_id'] . "'");

			foreach ($stores->rows as $row) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_store SET bundle_id = '" . (int)$new_id . "', store_id = '" . (int)$row['store_id'] . "'");
			}
		}

		$this->cache->set($cache_key, true, 86400);
		$done = true;
	}

	public function calculateDiscount($products_total, $discount_type, $discount_value, $set_count) {
		if ($discount_type == 'percentage') {
			return $products_total * ((float)$discount_value / 100) * (int)$set_count;
		} else {
			return (float)$discount_value * (int)$set_count;
		}
	}
}
