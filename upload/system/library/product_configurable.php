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
		$old_option_ids = array();
		$old_query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

		foreach ($old_query->rows as $row) {
			$old_option_ids[] = (int)$row['option_id'];
		}

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

		// When axes are removed (but some remain), delete all variants — they would
		// become unresolvable as their hashes include the removed axis values.
		if ($is_configurable) {
			$removed = array_diff($old_option_ids, array_map('intval', $option_ids));

			if (!empty($removed)) {
				$vids_query = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "'");
				$vids = array();

				foreach ($vids_query->rows as $row) {
					$vids[] = (int)$row['variant_id'];
				}

				if (!empty($vids)) {
					$ids = implode(',', $vids);
					$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id IN (" . $ids . ")");
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE product_id = '" . (int)$product_id . "'");
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "'");
				}
			}
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
			$row['values'] = $this->getOptionValues($row['option_id'], $product_id);
			$options[] = $row;
		}

		return $options;
	}

	public function getOptionValues($option_id, $product_id = null) {
		if ($product_id !== null) {
			$query = $this->db->query("SELECT ov.option_value_id, ovd.name, ov.color_code FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_id = '" . (int)$product_id . "' AND pov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

			return $query->rows;
		}

		$query = $this->db->query("SELECT ov.option_value_id, ovd.name, ov.color_code FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

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
		$variant_hash = $this->buildVariantHashFromValues(isset($data['values']) ? $data['values'] : array());

		if ($variant_hash !== '') {
			$conflict = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND variant_hash = '" . $this->db->escape($variant_hash) . "'");

			if ($conflict->num_rows) {
				throw new \RuntimeException('Variant with the same option combination already exists for this product');
			}
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant SET product_id = '" . (int)$product_id . "', model = '" . $this->db->escape(isset($data['model']) ? $data['model'] : '') . "', sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "', image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', variant_hash = '" . $this->db->escape($variant_hash) . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "'");

		$variant_id = $this->db->getLastId();

		if (!empty($data['values'])) {
			foreach ($data['values'] as $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant_value SET variant_id = '" . (int)$variant_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$value['option_id'] . "', option_value_id = '" . (int)$value['option_value_id'] . "'");
			}
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

		$hash_update = '';
		$variant_hash = '';

		if (isset($data['values'])) {
			$variant_hash = $this->buildVariantHashFromValues($data['values']);
			$hash_update = ", variant_hash = '" . $this->db->escape($variant_hash) . "'";

			if ($variant_hash !== '') {
				$conflict = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND variant_hash = '" . $this->db->escape($variant_hash) . "' AND variant_id != '" . (int)$variant_id . "'");

				if ($conflict->num_rows) {
					throw new \RuntimeException('Variant with the same option combination already exists for this product');
				}
			}
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET model = '" . $this->db->escape(isset($data['model']) ? $data['model'] : '') . "', sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "', image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "'" . $hash_update . ", sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "' WHERE variant_id = '" . (int)$variant_id . "'");

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

		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . (int)$variant_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE variant_id = '" . (int)$variant_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if ($product_id) {
			$default_query = $this->db->query("SELECT default_variant_id FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

			if ($default_query->num_rows && empty($default_query->row['default_variant_id'])) {
				$next_default = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1' ORDER BY sort_order ASC, variant_id ASC LIMIT 1");

				if ($next_default->num_rows) {
					$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = '" . (int)$next_default->row['variant_id'] . "' WHERE product_id = '" . (int)$product_id . "'");
				}
			}

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
			$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id IN (" . implode(',', $variant_ids) . ")");
			$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id IN (" . implode(',', $variant_ids) . ")");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "'");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . (int)$product_id . "'");

		$this->touchProduct($product_id);
	}

	/**
	 * Disables configurable mode for a product.
	 *
	 * - Zeroes axis product_option_value.price (so the option becomes a regular simple option).
	 * - Sets product_configurable.is_configurable = 0.
	 * - When $purge_variants is true (default), also deletes all variants and axis
	 *   configuration via deleteAllVariants(). This is the typical admin "switch to
	 *   simple" flow.
	 * - When $purge_variants is false, only the is_configurable flag is flipped and
	 *   axis POV prices are zeroed; variants remain in the database (useful for
	 *   temporarily disabling configurable mode without losing variant data).
	 *
	 * @param int $product_id
	 * @param bool $purge_variants When true, deletes variants + axes. Default true.
	 */
	public function disableConfigurable($product_id, $purge_variants = true) {
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

		$this->setConfigurable($product_id, 0);

		if ($purge_variants) {
			$this->deleteAllVariants($product_id);
		} else {
			$this->touchProduct($product_id);
		}
	}

	public function setDefaultVariant($variant_id) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if ($variant_query->num_rows) {
			$product_id = (int)$variant_query->row['product_id'];
			$this->db->query("UPDATE " . DB_PREFIX . "product_configurable SET default_variant_id = '" . (int)$variant_id . "' WHERE product_id = '" . (int)$product_id . "'");

			$this->touchProduct($product_id);
		}
	}

	public function resolveVariant($product_id, $option_values) {
		if (empty($option_values)) {
			return array();
		}

		$hash = $this->buildVariantHash($option_values);

		if ($hash === '') {
			return array();
		}

		$query = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND variant_hash = '" . $this->db->escape($hash) . "' AND status = '1' LIMIT 1");

		if ($query->num_rows) {
			return $this->getVariant((int)$query->row['variant_id']);
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

	public function getAggregatedPriceRange($product_id, $customer_group_id = null) {
		if ($customer_group_id !== null) {
			$query = $this->db->query("SELECT MIN(COALESCE(cgp.price, pv.price)) AS min_price, MAX(COALESCE(cgp.price, pv.price)) AS max_price FROM " . DB_PREFIX . "product_variant pv LEFT JOIN " . DB_PREFIX . "dockercart_product_variant_customer_group_price cgp ON (cgp.variant_id = pv.variant_id AND cgp.customer_group_id = '" . (int)$customer_group_id . "') WHERE pv.product_id = '" . (int)$product_id . "' AND pv.status = '1'");
		} else {
			$query = $this->db->query("SELECT MIN(price) AS min_price, MAX(price) AS max_price FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1'");
		}

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

	public function getVariantCustomerGroupPrices($product_id) {
		$query = $this->db->query("SELECT cgp.variant_id, cgp.customer_group_id, cgp.price FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price cgp INNER JOIN " . DB_PREFIX . "product_variant pv ON (cgp.variant_id = pv.variant_id) WHERE pv.product_id = '" . (int)$product_id . "'");

		$result = array();

		foreach ($query->rows as $row) {
			$result[(int)$row['variant_id']][] = array(
				'customer_group_id' => (int)$row['customer_group_id'],
				'price' => $row['price'],
			);
		}

		return $result;
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

	public function buildVariantHash(array $option_values) {
		if (empty($option_values)) {
			return '';
		}

		ksort($option_values);

		$parts = array();

		foreach ($option_values as $option_id => $option_value_id) {
			$parts[] = (int)$option_value_id;
		}

		return implode('-', $parts);
	}

	public function buildVariantHashFromValues(array $values) {
		if (empty($values)) {
			return '';
		}

		$map = array();

		foreach ($values as $value) {
			if (!isset($value['option_id'], $value['option_value_id'])) {
				continue;
			}

			$map[(int)$value['option_id']] = (int)$value['option_value_id'];
		}

		return $this->buildVariantHash($map);
	}

	public function rebuildVariantHashes($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product_variant v JOIN (SELECT variant_id, GROUP_CONCAT(option_value_id ORDER BY option_id SEPARATOR '-') AS h FROM " . DB_PREFIX . "product_variant_value WHERE product_id = '" . (int)$product_id . "' GROUP BY variant_id) t ON t.variant_id = v.variant_id SET v.variant_hash = t.h WHERE v.product_id = '" . (int)$product_id . "'");

		$this->touchProduct($product_id);
	}

	public function findDuplicateVariant($product_id, $variant_hash, $exclude_variant_id = 0) {
		if ($variant_hash === '') {
			return false;
		}

		$sql = "SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND variant_hash = '" . $this->db->escape($variant_hash) . "'";

		if ($exclude_variant_id) {
			$sql .= " AND variant_id != '" . (int)$exclude_variant_id . "'";
		}

		$query = $this->db->query($sql . " LIMIT 1");

		return $query->num_rows ? (int)$query->row['variant_id'] : false;
	}

	public function getVariantSpecials($variant_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "' ORDER BY priority ASC, price ASC");

		return $query->rows;
	}

	public function getVariantsSpecials($product_id) {
		$query = $this->db->query("SELECT vs.* FROM " . DB_PREFIX . "dockercart_product_variant_special vs INNER JOIN " . DB_PREFIX . "product_variant pv ON (vs.variant_id = pv.variant_id) WHERE pv.product_id = '" . (int)$product_id . "'");

		$result = array();

		foreach ($query->rows as $row) {
			$result[(int)$row['variant_id']][] = array(
				'variant_special_id' => (int)$row['variant_special_id'],
				'customer_group_id'  => (int)$row['customer_group_id'],
				'priority'           => (int)$row['priority'],
				'price'              => $row['price'],
				'date_start'         => $row['date_start'],
				'date_end'           => $row['date_end'],
				'auto_renew'         => (int)$row['auto_renew'],
			);
		}

		return $result;
	}

	public function getVariantSpecialPrice($variant_id, $customer_group_id) {
		$query = $this->db->query("SELECT price FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "' AND customer_group_id = '" . (int)$customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

		if ($query->num_rows) {
			return (float)$query->row['price'];
		}

		return null;
	}

	public function setVariantSpecials($variant_id, $specials) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "'");

		foreach ($specials as $special) {
			if (empty($special['customer_group_id']) || !isset($special['price']) || $special['price'] === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_special SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$special['customer_group_id'] . "', priority = '" . (int)(isset($special['priority']) ? $special['priority'] : 1) . "', price = '" . (float)$special['price'] . "', date_start = '" . $this->db->escape(isset($special['date_start']) ? $special['date_start'] : '0000-00-00') . "', date_end = '" . $this->db->escape(isset($special['date_end']) ? $special['date_end'] : '0000-00-00') . "', auto_renew = '" . (int)(!empty($special['auto_renew'])) . "'");
		}
	}

	public function deleteAllVariantSpecials($variant_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "'");
	}

	public function getVariantsDiscounts($product_id) {
		$query = $this->db->query("SELECT vd.* FROM " . DB_PREFIX . "dockercart_product_variant_discount vd INNER JOIN " . DB_PREFIX . "product_variant pv ON (vd.variant_id = pv.variant_id) WHERE pv.product_id = '" . (int)$product_id . "' ORDER BY vd.quantity ASC, vd.priority ASC");

		$result = array();

		foreach ($query->rows as $row) {
			$result[(int)$row['variant_id']][] = array(
				'variant_discount_id' => (int)$row['variant_discount_id'],
				'customer_group_id'   => (int)$row['customer_group_id'],
				'quantity'            => (int)$row['quantity'],
				'priority'            => (int)$row['priority'],
				'price'               => $row['price'],
				'date_start'          => $row['date_start'],
				'date_end'            => $row['date_end'],
				'auto_renew'          => (int)$row['auto_renew'],
			);
		}

		return $result;
	}

	public function getVariantDiscountPrice($variant_id, $customer_group_id, $quantity) {
		$query = $this->db->query("SELECT price FROM " . DB_PREFIX . "dockercart_product_variant_discount WHERE variant_id = '" . (int)$variant_id . "' AND customer_group_id = '" . (int)$customer_group_id . "' AND quantity <= '" . (int)$quantity . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity DESC, priority ASC, price ASC LIMIT 1");

		if ($query->num_rows) {
			return (float)$query->row['price'];
		}

		return null;
	}

	public function setVariantDiscounts($variant_id, $discounts) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_discount WHERE variant_id = '" . (int)$variant_id . "'");

		foreach ($discounts as $discount) {
			if (empty($discount['customer_group_id']) || !isset($discount['price']) || $discount['price'] === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_discount SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$discount['customer_group_id'] . "', quantity = '" . (int)(isset($discount['quantity']) ? $discount['quantity'] : 0) . "', priority = '" . (int)(isset($discount['priority']) ? $discount['priority'] : 1) . "', price = '" . (float)$discount['price'] . "', date_start = '" . $this->db->escape(isset($discount['date_start']) ? $discount['date_start'] : '0000-00-00') . "', date_end = '" . $this->db->escape(isset($discount['date_end']) ? $discount['date_end'] : '0000-00-00') . "', auto_renew = '" . (int)(!empty($discount['auto_renew'])) . "'");
		}
	}

	public function deleteAllVariantDiscounts($variant_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_discount WHERE variant_id = '" . (int)$variant_id . "'");
	}

	private function touchProduct($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
	}
}
