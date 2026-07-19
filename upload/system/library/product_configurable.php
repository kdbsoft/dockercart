<?php
/** @property \DB $db
 * @property \Config $config */
class ProductConfigurable {
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	public function setConfigurableOptions($product_id, $option_ids) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

		$position = 0;

		foreach ($option_ids as $option_id) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_configurable_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', position = '" . (int)$position . "'");
			$position++;
		}

		$is_configurable = !empty($option_ids) ? 1 : 0;

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_configurable SET product_id = '" . (int)$product_id . "', is_configurable = '" . (int)$is_configurable . "' ON DUPLICATE KEY UPDATE is_configurable = '" . (int)$is_configurable . "'");

		if (!$is_configurable) {
			$this->deleteAllVariants($product_id);
		}

		foreach ($option_ids as $option_id) {
			$existing = $this->db->query("SELECT product_option_id FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "' AND option_id = '" . (int)$option_id . "'");

			if ($existing->num_rows) {
				$this->db->query("
					UPDATE " . DB_PREFIX . "product_option_value
					SET price = '0', quantity = '0', subtract = '0'
					WHERE product_id = '" . (int)$product_id . "'
					AND option_id = '" . (int)$option_id . "'
				");

				continue;
			}

			$opt = $this->db->query("SELECT type FROM `" . DB_PREFIX . "option` WHERE option_id = '" . (int)$option_id . "'");

			if (!$opt->num_rows) {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', value = '', required = '1'");

			$product_option_id = $this->db->getLastId();

			$values = $this->db->query("SELECT option_value_id FROM " . DB_PREFIX . "option_value WHERE option_id = '" . (int)$option_id . "' ORDER BY sort_order ASC");

			foreach ($values->rows as $val) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$val['option_value_id'] . "', price = '0', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+'");
			}
		}

		$this->touchProduct($product_id);
	}

	public function setConfigurable($product_id, $is_configurable) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_configurable SET product_id = '" . (int)$product_id . "', is_configurable = '" . (int)$is_configurable . "' ON DUPLICATE KEY UPDATE is_configurable = '" . (int)$is_configurable . "'");

		$this->touchProduct($product_id);
	}

	public function getConfigurableOptions($product_id) {
		$query = $this->db->query("SELECT pco.option_id, pco.position, o.type, od.name FROM " . DB_PREFIX . "product_configurable_option pco LEFT JOIN `" . DB_PREFIX . "option` o ON (pco.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE pco.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY pco.position ASC");

		$options = array();

		foreach ($query->rows as $row) {
			$row['values'] = $this->getOptionValues($row['option_id']);
			$options[] = $row;
		}

		return $options;
	}

	public function getOptionValues($option_id) {
		$query = $this->db->query("SELECT ov.option_value_id, ovd.name, ov.color_code, ov.image FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

		return $query->rows;
	}

	public function getVariants($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC, variant_id ASC");

		$variants = array();

		foreach ($query->rows as $variant) {
			$variant['values'] = $this->getVariantValues($variant['variant_id']);
			$variants[] = $variant;
		}

		return $variants;
	}

	public function getVariant($variant_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if ($query->num_rows) {
			$variant = $query->row;
			$variant['values'] = $this->getVariantValues($variant_id);

			return $variant;
		}

		return array();
	}

	public function getVariantValues($variant_id) {
		$query = $this->db->query("SELECT pvv.*, ovd.name FROM " . DB_PREFIX . "product_variant_value pvv LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pvv.option_value_id = ovd.option_value_id) WHERE pvv.variant_id = '" . (int)$variant_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY pvv.option_id ASC");

		return $query->rows;
	}

	public function addVariant($product_id, $data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant SET product_id = '" . (int)$product_id . "', sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "', image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "', is_default = '" . (int)(!empty($data['is_default'])) . "'");

		$variant_id = $this->db->getLastId();

		if (!empty($data['values'])) {
			foreach ($data['values'] as $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant_value SET variant_id = '" . (int)$variant_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$value['option_id'] . "', option_value_id = '" . (int)$value['option_value_id'] . "'");
			}
		}

		if (!empty($data['is_default'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET is_default = '0' WHERE product_id = '" . (int)$product_id . "' AND variant_id != '" . (int)$variant_id . "'");
			$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = '" . (int)$variant_id . "' WHERE product_id = '" . (int)$product_id . "'");
		}

		$this->touchProduct($product_id);

		return $variant_id;
	}

	public function updateVariant($variant_id, $data) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$product_id = (int)$variant_query->row['product_id'];

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "', image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "', is_default = '" . (int)(!empty($data['is_default'])) . "' WHERE variant_id = '" . (int)$variant_id . "'");

		if (!empty($data['is_default'])) {
			$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET is_default = '0' WHERE product_id = '" . (int)$product_id . "' AND variant_id != '" . (int)$variant_id . "'");
			$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = '" . (int)$variant_id . "' WHERE product_id = '" . (int)$product_id . "'");
		}

		if (isset($data['values'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE variant_id = '" . (int)$variant_id . "'");

			foreach ($data['values'] as $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant_value SET variant_id = '" . (int)$variant_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$value['option_id'] . "', option_value_id = '" . (int)$value['option_value_id'] . "'");
			}
		}

		$this->touchProduct($product_id);
	}

	public function deleteVariant($variant_id) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		$product_id = 0;

		if ($variant_query->num_rows) {
			$product_id = (int)$variant_query->row['product_id'];

			$default_query = $this->db->query("SELECT default_variant_id FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

			if ($default_query->num_rows && $default_query->row['default_variant_id'] == $variant_id) {
				$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = NULL WHERE product_id = '" . (int)$product_id . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE variant_id = '" . (int)$variant_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if ($product_id) {
			$this->touchProduct($product_id);
		}
	}

	public function deleteAllVariants($product_id) {
		$variant_ids = array();
		$query = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$variant_ids[] = (int)$row['variant_id'];
		}

		if (!empty($variant_ids)) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

		$this->touchProduct($product_id);
	}

	public function disableConfigurable($product_id) {
		$axis_query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");
		$axis_option_ids = array();

		foreach ($axis_query->rows as $row) {
			$axis_option_ids[] = (int)$row['option_id'];
		}

		if (!empty($axis_option_ids)) {
			$this->db->query("
				UPDATE " . DB_PREFIX . "product_option_value pov
				INNER JOIN " . DB_PREFIX . "product_option po ON (pov.product_option_id = po.product_option_id)
				SET pov.price = '0'
				WHERE po.product_id = '" . (int)$product_id . "'
				AND pov.option_id IN (" . implode(',', $axis_option_ids) . ")
			");
		}

		$this->touchProduct($product_id);
	}

	public function setDefaultVariant($variant_id) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if ($variant_query->num_rows) {
			$product_id = (int)$variant_query->row['product_id'];
			$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = '" . (int)$variant_id . "' WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET is_default = '1' WHERE variant_id = '" . (int)$variant_id . "'");
			$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET is_default = '0' WHERE product_id = '" . (int)$product_id . "' AND variant_id != '" . (int)$variant_id . "'");

			$this->touchProduct($product_id);
		}
	}

	public function resolveVariant($product_id, $option_values) {
		ksort($option_values);
		$num_axes = count($option_values);

		if ($num_axes == 0) {
			return array();
		}

		$sql = "SELECT vv.variant_id FROM " . DB_PREFIX . "product_variant_value vv "
			. "INNER JOIN " . DB_PREFIX . "product_variant v ON (vv.variant_id = v.variant_id) "
			. "WHERE vv.product_id = '" . (int)$product_id . "' AND v.status = '1' "
			. "AND vv.variant_id IN ("
			. "  SELECT variant_id FROM " . DB_PREFIX . "product_variant_value "
			. "  WHERE product_id = '" . (int)$product_id . "' "
			. "  GROUP BY variant_id HAVING COUNT(*) = '" . (int)$num_axes . "'"
			. ") ";

		foreach ($option_values as $option_id => $option_value_id) {
			$sql .= " AND vv.variant_id IN (SELECT variant_id FROM " . DB_PREFIX . "product_variant_value WHERE option_id = '" . (int)$option_id . "' AND option_value_id = '" . (int)$option_value_id . "')";
		}

		$sql .= " GROUP BY vv.variant_id LIMIT 1";

		$query = $this->db->query($sql);

		if ($query->num_rows) {
			return $this->getVariant($query->row['variant_id']);
		}

		return array();
	}

	public function getDefaultVariant($product_id) {
		$config_query = $this->db->query("SELECT default_variant_id FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

		if ($config_query->num_rows && $config_query->row['default_variant_id']) {
			return $this->getVariant($config_query->row['default_variant_id']);
		}

		return array();
	}

	public function getConfigurable($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "' AND is_configurable = '1'");

		if ($query->num_rows) {
			return $query->row;
		}

		return array();
	}

	public function isConfigurable($product_id) {
		$query = $this->db->query("SELECT is_configurable FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

		if ($query->num_rows) {
			return (int)$query->row['is_configurable'] === 1;
		}

		return false;
	}

	public function getAggregatedPriceRange($product_id) {
		$query = $this->db->query("SELECT MIN(price) AS min_price, MAX(price) AS max_price FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1'");

		if ($query->num_rows) {
			return array(
				'min' => (float)$query->row['min_price'],
				'max' => (float)$query->row['max_price'],
			);
		}

		return array('min' => 0, 'max' => 0);
	}

	public function getAggregatedStock($product_id) {
		$query = $this->db->query("SELECT SUM(quantity) AS total_stock, SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) AS variants_in_stock, COUNT(*) AS total_variants FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1'");

		if ($query->num_rows) {
			return array(
				'total_stock' => (float)$query->row['total_stock'],
				'variants_in_stock' => (int)$query->row['variants_in_stock'],
				'total_variants' => (int)$query->row['total_variants'],
			);
		}

		return array('total_stock' => 0, 'variants_in_stock' => 0, 'total_variants' => 0);
	}

	public function getVariantCustomerGroupPrice($variant_id, $customer_group_id) {
		$query = $this->db->query("SELECT price FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . (int)$variant_id . "' AND customer_group_id = '" . (int)$customer_group_id . "'");

		if ($query->num_rows) {
			return (float)$query->row['price'];
		}

		return null;
	}

	public function setVariantCustomerGroupPrice($variant_id, $customer_group_id, $price) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_customer_group_price SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$customer_group_id . "', price = '" . (float)$price . "' ON DUPLICATE KEY UPDATE price = '" . (float)$price . "'");
	}

	public function deleteVariantCustomerGroupPrice($variant_id, $customer_group_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . (int)$variant_id . "' AND customer_group_id = '" . (int)$customer_group_id . "'");
	}

	public function deleteAllVariantCustomerGroupPrices($variant_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . (int)$variant_id . "'");
	}

	private function touchProduct($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
	}
}
