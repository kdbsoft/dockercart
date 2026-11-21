<?php
class ModelCatalogProductBundle extends Model {
	/**
	 * Normalises bundle lines to [{product_id, variant_id}].
	 * Accepts legacy ints, "product_id:variant_id" strings and arrays.
	 *
	 * @param array $lines
	 * @return array<int, array{product_id: int, variant_id: int}>
	 */
	public function normalizeBundleLines($lines) {
		$normalised = array();
		$seen = array();

		if (!is_array($lines)) {
			return $normalised;
		}

		foreach ($lines as $line) {
			$product_id = 0;
			$variant_id = 0;

			if (is_array($line)) {
				$product_id = isset($line['product_id']) ? (int)$line['product_id'] : 0;
				$variant_id = isset($line['variant_id']) ? (int)$line['variant_id'] : 0;
			} elseif (is_string($line) && strpos($line, ':') !== false) {
			$parts = explode(':', $line, 2);
			$product_id = (int)$parts[0];
				$variant_id = (int)$parts[1];
			} else {
				$product_id = (int)$line;
			}

			if ($product_id <= 0 || $variant_id < 0) {
				continue;
			}

			$key = $product_id . ':' . $variant_id;

			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$normalised[] = array('product_id' => $product_id, 'variant_id' => $variant_id);
		}

		return $normalised;
	}

	/**
	 * Parses a "product_id[:variant_id],..." CSV into bundle lines.
	 *
	 * @param string $csv
	 * @return array<int, array{product_id: int, variant_id: int}>
	 */
	public function parseBundleLinesCsv($csv) {
		if (!is_string($csv) || trim($csv) === '') {
			return array();
		}

		return $this->normalizeBundleLines(explode(',', $csv));
	}

	/**
	 * Encodes bundle lines back to the admin CSV format.
	 *
	 * @param array $lines
	 * @return string
	 */
	public function encodeBundleLinesCsv($lines) {
		$lines = $this->normalizeBundleLines($lines);
		$parts = array();

		foreach ($lines as $line) {
			$parts[] = $line['variant_id'] > 0 ? $line['product_id'] . ':' . $line['variant_id'] : (string)$line['product_id'];
		}

		return implode(',', $parts);
	}

	/**
	 * Returns product_ids of configurable products added without a variant.
	 * An empty array means every configurable line pins an exact variant.
	 *
	 * @param array $lines normalised or raw lines
	 * @return array<int>
	 */
	public function findConfigurableWithoutVariant($lines) {
		$lines = $this->normalizeBundleLines($lines);

		if (empty($lines)) {
			return array();
		}

		$product_ids = array_unique(array_map('intval', array_column($lines, 'product_id')));
		$in = implode(',', $product_ids);

		$configurable = array();
		$query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_configurable WHERE product_id IN (" . $in . ") AND is_configurable = '1'");

		foreach ($query->rows as $row) {
			$configurable[(int)$row['product_id']] = true;
		}

		if (empty($configurable)) {
			return array();
		}

		$missing = array();

		foreach ($lines as $line) {
			if (isset($configurable[$line['product_id']]) && $line['variant_id'] <= 0) {
				$missing[] = $line['product_id'];
			}
		}

		return array_values(array_unique($missing));
	}

	public function addBundle($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle SET name = '" . $this->db->escape($data['name']) . "', discount_type = '" . $this->db->escape($data['discount_type']) . "', discount_value = '" . (float)$data['discount_value'] . "', date_start = '" . $this->db->escape($data['date_start']) . "', date_end = '" . $this->db->escape($data['date_end']) . "', status = '" . (int)$data['status'] . "', sort_order = '" . (int)$data['sort_order'] . "', auto_renew = '" . (int)(!empty($data['auto_renew'])) . "', date_added = NOW()");

		$bundle_id = $this->db->getLastId();

		if (isset($data['bundle_product'])) {
			foreach ($this->normalizeBundleLines($data['bundle_product']) as $line) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$line['product_id'] . "', variant_id = '" . (int)$line['variant_id'] . "'");
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
		$this->db->query("UPDATE " . DB_PREFIX . "product_bundle SET name = '" . $this->db->escape($data['name']) . "', discount_type = '" . $this->db->escape($data['discount_type']) . "', discount_value = '" . (float)$data['discount_value'] . "', date_start = '" . $this->db->escape($data['date_start']) . "', date_end = '" . $this->db->escape($data['date_end']) . "', status = '" . (int)$data['status'] . "', sort_order = '" . (int)$data['sort_order'] . "', auto_renew = '" . (int)(!empty($data['auto_renew'])) . "' WHERE bundle_id = '" . (int)$bundle_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle_id . "'");

		if (isset($data['bundle_product'])) {
			foreach ($this->normalizeBundleLines($data['bundle_product']) as $line) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_bundle_product SET bundle_id = '" . (int)$bundle_id . "', product_id = '" . (int)$line['product_id'] . "', variant_id = '" . (int)$line['variant_id'] . "'");
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

	public function copyBundle($bundle_id)
	{
		$query = $this->db->query(
			"SELECT * FROM " .
				DB_PREFIX .
				"product_bundle WHERE bundle_id = '" .
				(int) $bundle_id .
				"'",
		);

		if (!$query->num_rows) {
			return false;
		}

		$bundle = $query->row;

		$data = [];

		$data["name"] = $this->getUniqueCopyName(
			$bundle["name"],
			DB_PREFIX . "product_bundle",
			"name",
		);
		$data["discount_type"] = $bundle["discount_type"];
		$data["discount_value"] = $bundle["discount_value"];
		$data["date_start"] = $bundle["date_start"];
		$data["date_end"] = $bundle["date_end"];
		$data["status"] = $bundle["status"];
		$data["sort_order"] = $bundle["sort_order"];
		$data["auto_renew"] = $bundle["auto_renew"];
		$data["bundle_product"] = $this->getBundleProducts($bundle_id);
		$data["bundle_store"] = $this->getBundleStores($bundle_id);

		return $this->addBundle($data);
	}

	private function getUniqueCopyName($original, $table, $column)
	{
		$base = $original;

		if (preg_match('/^(.+)-copy(\d*)$/', $original, $matches)) {
			$base = $matches[1];
		}

		$counter = 0;

		do {
			$counter++;
			$suffix = $counter > 1 ? (string) $counter : "";
			$candidate = $base . "-copy" . $suffix;

			$query = $this->db->query(
				"SELECT COUNT(*) AS total FROM " .
					$table .
					" WHERE " .
					$column .
					" = '" .
					$this->db->escape($candidate) .
					"'",
			);
		} while ($query->row["total"] > 0);

		return $candidate;
	}

	public function getBundle($bundle_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "product_bundle WHERE bundle_id = '" . (int)$bundle_id . "'");

		return $query->row;
	}

	public function getBundles($data = array()) {
		$sql = "SELECT b.bundle_id, b.name, b.discount_type, b.discount_value, b.date_start, b.date_end, b.status, b.sort_order, b.auto_renew, COUNT(bp.product_id) as product_count FROM " . DB_PREFIX . "product_bundle b LEFT JOIN " . DB_PREFIX . "product_bundle_product bp ON (b.bundle_id = bp.bundle_id)";

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

	public function getBundlesByProduct($product_id) {
		$query = $this->db->query(
			"SELECT DISTINCT b.* FROM " . DB_PREFIX . "product_bundle b "
			. "INNER JOIN " . DB_PREFIX . "product_bundle_product bp ON (b.bundle_id = bp.bundle_id) "
			. "WHERE bp.product_id = '" . (int) $product_id . "' "
			. "ORDER BY b.date_added ASC"
		);

		return $query->rows;
	}

	/**
	 * Bundle lines with variants: [{product_id, variant_id}].
	 * variant_id = 0 means a simple product without variants.
	 *
	 * @param int $bundle_id
	 * @return array<int, array{product_id: int, variant_id: int}>
	 */
	public function getBundleProducts($bundle_id) {
		$query = $this->db->query("SELECT product_id, variant_id FROM " . DB_PREFIX . "product_bundle_product WHERE bundle_id = '" . (int)$bundle_id . "' ORDER BY bundle_product_id ASC");

		$products = array();

		foreach ($query->rows as $row) {
			$products[] = array(
				'product_id' => (int)$row['product_id'],
				'variant_id' => isset($row['variant_id']) ? (int)$row['variant_id'] : 0
			);
		}

		return $products;
	}

	/**
	 * Legacy flat product ids for callers that only need ids.
	 *
	 * @param int $bundle_id
	 * @return array<int>
	 */
	public function getBundleProductIds($bundle_id) {
		$ids = array();

		foreach ($this->getBundleProducts($bundle_id) as $line) {
			$ids[] = (int)$line['product_id'];
		}

		return $ids;
	}

	public function getBundleStores($bundle_id) {
		$query = $this->db->query("SELECT store_id FROM " . DB_PREFIX . "product_bundle_store WHERE bundle_id = '" . (int)$bundle_id . "'");

		$stores = array();

		foreach ($query->rows as $row) {
			$stores[] = $row['store_id'];
		}

		return $stores;
	}

	public function updateBundleField($bundle_id, $data) {
		$string_fields = array('name', 'date_start', 'date_end');
		$int_fields = array('status', 'sort_order');

		$sets = array();
		foreach ($string_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . $this->db->escape($data[$field]) . "'";
			}
		}
		foreach ($int_fields as $field) {
			if (isset($data[$field])) {
				$sets[] = "`" . $field . "` = '" . (int)$data[$field] . "'";
			}
		}

		if (!empty($sets)) {
			$this->db->query("UPDATE " . DB_PREFIX . "product_bundle SET " . implode(', ', $sets) . " WHERE bundle_id = '" . (int)$bundle_id . "'");
		}
	}

	public function updateBundleDiscount($bundle_id, $value, $type) {
		$this->db->query("UPDATE " . DB_PREFIX . "product_bundle SET discount_value = '" . (float)$value . "', discount_type = '" . $this->db->escape($type) . "' WHERE bundle_id = '" . (int)$bundle_id . "'");
	}
}
