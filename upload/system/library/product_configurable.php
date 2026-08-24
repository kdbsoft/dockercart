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

	// Trim DECIMAL(15,8) noise: "100.00000000" -> "100", "10.50000000" -> "10.5"
	public static function formatDecimal($value) {
		if ($value === null || $value === '') {
			return '';
		}

		$s = rtrim(rtrim(number_format((float)$value, 8, '.', ''), '0'), '.');

		return $s === '' ? '0' : $s;
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
			// Only option values that belong to at least one ENABLED variant
			// (status = 1) are returned — disabled variants are hidden from
			// the storefront, same rule as disabled products.
			$query = $this->db->query("SELECT ov.option_value_id, ovd.name, ov.color_code FROM " . DB_PREFIX . "product_option_value pov INNER JOIN " . DB_PREFIX . "product_variant_value pvv ON (pvv.product_id = pov.product_id AND pvv.option_id = pov.option_id AND pvv.option_value_id = pov.option_value_id) INNER JOIN " . DB_PREFIX . "product_variant pv ON (pv.variant_id = pvv.variant_id) LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_id = '" . (int)$product_id . "' AND pov.option_id = '" . (int)$option_id . "' AND pv.status = '1' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY ov.option_value_id, ovd.name, ov.color_code ORDER BY ov.sort_order ASC");

			return $query->rows;
		}

		$query = $this->db->query("SELECT ov.option_value_id, ovd.name, ov.color_code FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

		return $query->rows;
	}

	public function getVariants($product_id) {
		// Only ENABLED variants (status = 1) — disabled variants are hidden
		// from the storefront, same rule as disabled products.
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1' ORDER BY sort_order ASC, variant_id ASC");

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

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant SET product_id = '" . (int)$product_id . "', model = '" . $this->db->escape(isset($data['model']) ? $data['model'] : '') . "', sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', jan = '" . $this->db->escape(isset($data['jan']) ? $data['jan'] : '') . "', isbn = '" . $this->db->escape(isset($data['isbn']) ? $data['isbn'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "', length = '" . (float)(isset($data['length']) ? $data['length'] : 0) . "', width = '" . (float)(isset($data['width']) ? $data['width'] : 0) . "', height = '" . (float)(isset($data['height']) ? $data['height'] : 0) . "', image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "', variant_hash = '" . $this->db->escape($variant_hash) . "', sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "'");

		$variant_id = $this->db->getLastId();

		if (!empty($data['values'])) {
			foreach ($data['values'] as $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant_value SET variant_id = '" . (int)$variant_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$value['option_id'] . "', option_value_id = '" . (int)$value['option_value_id'] . "'");
			}
		}

		// Mirror the requested variant stock onto the default warehouse so
		// the cache write above survives recomputes (single write path).
		$warehouse = new \DockercartWarehouse($this->registry);
		$warehouse->setTotalQuantity((int)$product_id, (float)(isset($data['quantity']) ? $data['quantity'] : 0), (int)$variant_id, array('reference' => 'variant-form'));

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

		// Length/width/height are managed from the Dimensions & Weight variants
		// table which posts them explicitly; other writers (variant matrix
		// autosave) don't send them, so absent keys must keep current values
		// instead of zeroing them out.
		$dim_update = '';

		foreach (array('length', 'width', 'height') as $dim_col) {
			if (array_key_exists($dim_col, $data)) {
				$dim_update .= ', ' . $dim_col . " = '" . (float)$data[$dim_col] . "'";
			}
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET model = '" . $this->db->escape(isset($data['model']) ? $data['model'] : '') . "', sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', upc = '" . $this->db->escape(isset($data['upc']) ? $data['upc'] : '') . "', ean = '" . $this->db->escape(isset($data['ean']) ? $data['ean'] : '') . "', jan = '" . $this->db->escape(isset($data['jan']) ? $data['jan'] : '') . "', isbn = '" . $this->db->escape(isset($data['isbn']) ? $data['isbn'] : '') . "', mpn = '" . $this->db->escape(isset($data['mpn']) ? $data['mpn'] : '') . "', price = '" . (float)(isset($data['price']) ? $data['price'] : 0) . "', quantity = '" . (float)(isset($data['quantity']) ? $data['quantity'] : 0) . "', subtract = '" . (int)(isset($data['subtract']) ? $data['subtract'] : 1) . "', weight = '" . (float)(isset($data['weight']) ? $data['weight'] : 0) . "', weight_class_id = '" . (int)(isset($data['weight_class_id']) ? $data['weight_class_id'] : 0) . "'" . $dim_update . ", image = '" . $this->db->escape(isset($data['image']) ? $data['image'] : '') . "'" . $hash_update . ", sort_order = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "', status = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "' WHERE variant_id = '" . (int)$variant_id . "'");

		if (isset($data['values'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE variant_id = '" . (int)$variant_id . "'");

			foreach ($data['values'] as $value) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_variant_value SET variant_id = '" . (int)$variant_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$value['option_id'] . "', option_value_id = '" . (int)$value['option_value_id'] . "'");
			}
		}

		// The UPDATE above always rewrites quantity, so mirror its effective
		// value onto the default warehouse as well (single write path).
		$warehouse = new \DockercartWarehouse($this->registry);
		$warehouse->setTotalQuantity((int)$product_id, (float)(isset($data['quantity']) ? $data['quantity'] : 0), (int)$variant_id, array('reference' => 'variant-form'));

		$this->touchProduct($product_id);
	}

	public function updateVariantQuantity($variant_id, $quantity) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		// Single write path: the requested total becomes a delta on the
		// default warehouse (journal + cache recompute).
		$warehouse = new \DockercartWarehouse($this->registry);
		$warehouse->setTotalQuantity((int)$variant_query->row['product_id'], (float)$quantity, (int)$variant_id, array('reference' => 'variant-form'));

		$this->touchProduct((int)$variant_query->row['product_id']);
	}

	public function updateVariantPrice($variant_id, $price) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET price = '" . (float)$price . "' WHERE variant_id = '" . (int)$variant_id . "'");

		$this->touchProduct((int)$variant_query->row['product_id']);
	}

	public function updateVariantPricing($variant_id, $price, array $cg_prices = array()) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$product_id = (int)$variant_query->row['product_id'];

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET price = '" . (float)$price . "' WHERE variant_id = '" . (int)$variant_id . "'");

		// Replace customer group prices atomically.
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . (int)$variant_id . "'");

		foreach ($cg_prices as $cg) {
			if (empty($cg['customer_group_id']) || !isset($cg['price']) || $cg['price'] === '' || $cg['price'] === null) {
				continue;
			}

			if (!is_numeric($cg['price'])) {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_customer_group_price SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$cg['customer_group_id'] . "', price = '" . (float)$cg['price'] . "'");
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

			// Drop the variant's stock rows and holds so no ghost quantities
			// survive in the source of truth, then refresh the caches.
			$this->db->query("DELETE FROM " . DB_PREFIX . "warehouse_stock WHERE product_id = '" . (int)$product_id . "' AND variant_id = '" . (int)$variant_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "stock_reservation WHERE product_id = '" . (int)$product_id . "' AND variant_id = '" . (int)$variant_id . "'");

			$warehouse = new \DockercartWarehouse($this->registry);
			$warehouse->recomputeTotals((int)$product_id);

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

			// The deleted variants' stock rows and holds must not survive as
			// ghost quantities in the source of truth.
			$this->db->query("DELETE FROM " . DB_PREFIX . "warehouse_stock WHERE product_id = '" . (int)$product_id . "' AND variant_id > 0");
			$this->db->query("DELETE FROM " . DB_PREFIX . "stock_reservation WHERE product_id = '" . (int)$product_id . "' AND variant_id > 0");
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

	/**
	 * Aggregate stock for many products in one query.
	 * Returns [product_id => ['variants_in_stock', 'total_variants']].
	 */
	public function getAggregatedStocksByProductIds(array $product_ids) {
		$result = array();

		if (empty($product_ids)) {
			return $result;
		}

		$ids = array_values(array_unique(array_map('intval', $product_ids)));
		$query = $this->db->query("SELECT product_id, SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) AS variants_in_stock, COUNT(*) AS total_variants FROM " . DB_PREFIX . "product_variant WHERE product_id IN (" . implode(',', $ids) . ") AND status = '1' GROUP BY product_id");

		foreach ($query->rows as $row) {
			$result[(int)$row['product_id']] = array(
				'variants_in_stock' => (int)$row['variants_in_stock'],
				'total_variants'    => (int)$row['total_variants'],
			);
		}

		return $result;
	}

	public function getAggregatedPriceRange($product_id, $customer_group_id = null) {
		// The global % customer group discount/markup applies to every variant
		// without a per-variant group price override (mirrors the storefront
		// product page, cart and listings).
		$cg_multiplier = 1.0;

		if ($customer_group_id !== null) {
			$group_query = $this->db->query("SELECT discount_percent, markup_percent FROM " . DB_PREFIX . "customer_group WHERE customer_group_id = '" . (int)$customer_group_id . "'");

			if ($group_query->num_rows) {
				$customer_group_discount = (float)$group_query->row['discount_percent'];

				if ($customer_group_discount < 0) {
					$customer_group_discount = 0;
				} elseif ($customer_group_discount > 100) {
					$customer_group_discount = 100;
				}

				$customer_group_markup = (float)$group_query->row['markup_percent'];

				if ($customer_group_markup < 0) {
					$customer_group_markup = 0;
				} elseif ($customer_group_markup > 100) {
					$customer_group_markup = 100;
				}

				if ($customer_group_discount > 0 && $customer_group_markup > 0) {
					$customer_group_markup = 0;
				}

				if ($customer_group_discount > 0) {
					$cg_multiplier = (100 - $customer_group_discount) / 100;
				} elseif ($customer_group_markup > 0) {
					$cg_multiplier = (100 + $customer_group_markup) / 100;
				}
			}
		}

		if ($customer_group_id !== null) {
			$query = $this->db->query("SELECT MIN(CASE WHEN cgp.price IS NOT NULL AND cgp.price > 0 THEN cgp.price ELSE pv.price * " . (float)$cg_multiplier . " END) AS min_price, MAX(CASE WHEN cgp.price IS NOT NULL AND cgp.price > 0 THEN cgp.price ELSE pv.price * " . (float)$cg_multiplier . " END) AS max_price FROM " . DB_PREFIX . "product_variant pv LEFT JOIN " . DB_PREFIX . "dockercart_product_variant_customer_group_price cgp ON (cgp.variant_id = pv.variant_id AND cgp.customer_group_id = '" . (int)$customer_group_id . "') WHERE pv.product_id = '" . (int)$product_id . "' AND pv.status = '1'");
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

	public function updateVariantDimensions($variant_id, $length, $width, $height, $weight) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET length = '" . (float)$length . "', width = '" . (float)$width . "', height = '" . (float)$height . "', weight = '" . (float)$weight . "' WHERE variant_id = '" . (int)$variant_id . "'");

		$this->touchProduct((int)$variant_query->row['product_id']);
	}

	// Partial update of code columns only — unlike updateVariant(), absent
	// keys keep their current values instead of wiping the row.
	public function updateVariantCodes($variant_id, array $codes) {
		$allowed = array('sku', 'upc', 'ean', 'jan', 'isbn', 'mpn');

		$update = '';

		foreach ($allowed as $col) {
			if (array_key_exists($col, $codes)) {
				$update .= ', ' . $col . " = '" . $this->db->escape((string)$codes[$col]) . "'";
			}
		}

		if (!$update) {
			return;
		}

		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET" . substr($update, 2) . " WHERE variant_id = '" . (int)$variant_id . "'");

		$this->touchProduct((int)$variant_query->row['product_id']);
	}

	public function updateVariantImage($variant_id, $image) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$variant_id . "'");

		if (!$variant_query->num_rows) {
			return;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET image = '" . $this->db->escape((string)$image) . "' WHERE variant_id = '" . (int)$variant_id . "'");

		$this->touchProduct((int)$variant_query->row['product_id']);
	}

	public function getAggregatedWeightRange($product_id) {
		$query = $this->db->query("SELECT MIN(weight) AS min_weight, MAX(weight) AS max_weight FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1'");

		if ($query->num_rows) {
			return array(
				'min' => (float)$query->row['min_weight'],
				'max' => (float)$query->row['max_weight'],
			);
		}

		return array('min' => 0, 'max' => 0);
	}

	public function getAggregatedDimensionsRange($product_id) {
		$query = $this->db->query("SELECT MIN(length) AS min_length, MAX(length) AS max_length, MIN(width) AS min_width, MAX(width) AS max_width, MIN(height) AS min_height, MAX(height) AS max_height FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . (int)$product_id . "' AND status = '1'");

		if ($query->num_rows) {
			return array(
				'length' => array('min' => (float)$query->row['min_length'], 'max' => (float)$query->row['max_length']),
				'width'  => array('min' => (float)$query->row['min_width'], 'max' => (float)$query->row['max_width']),
				'height' => array('min' => (float)$query->row['min_height'], 'max' => (float)$query->row['max_height']),
			);
		}

		return array(
			'length' => array('min' => 0, 'max' => 0),
			'width'  => array('min' => 0, 'max' => 0),
			'height' => array('min' => 0, 'max' => 0),
		);
	}

	/**
	 * Collect the set of product_option_value rows that belong to at least one
	 * ENABLED variant (status = 1) of a configurable product. Used to filter
	 * axis option values in getProductOptions() so disabled variants are never
	 * rendered on the storefront (same rule as disabled products).
	 *
	 * @param int $product_id
	 * @return array list of product_option_value_id ints
	 */
	public function getEnabledVariantValueIds($product_id) {
		$query = $this->db->query(
			"SELECT pov.product_option_value_id FROM " . DB_PREFIX . "product_option_value pov "
			. "INNER JOIN " . DB_PREFIX . "product_variant_value pvv ON (pvv.product_id = pov.product_id AND pvv.option_id = pov.option_id AND pvv.option_value_id = pov.option_value_id) "
			. "INNER JOIN " . DB_PREFIX . "product_variant pv ON (pv.variant_id = pvv.variant_id) "
			. "WHERE pov.product_id = '" . (int)$product_id . "' AND pv.status = '1' "
			. "GROUP BY pov.product_option_value_id"
		);

		return array_map('intval', array_column($query->rows, 'product_option_value_id'));
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
				'variant_id'         => (int)$row['variant_id'],
				'customer_group_id'  => (int)$row['customer_group_id'],
				'priority'           => (int)$row['priority'],
				'price'              => $row['price'],
				'date_start'         => $row['date_start'],
				'date_end'           => $row['date_end'],
				'auto_renew'         => (int)$row['auto_renew'],
				'date_added'         => isset($row['date_added']) ? $row['date_added'] : '0000-00-00 00:00:00',
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

	public function getVariantSpecialEndDate($variant_id, $customer_group_id) {
		$query = $this->db->query("SELECT date_end FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "' AND customer_group_id = '" . (int)$customer_group_id . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

		if ($query->num_rows) {
			$date_end = (string)$query->row['date_end'];

			if ($date_end !== '' && $date_end !== '0000-00-00' && $date_end !== '0000-00-00 00:00:00') {
				return strtotime($date_end);
			}
		}

		return null;
	}

	public function setVariantSpecials($variant_id, $specials) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "'");

		foreach ($specials as $special) {
			if (empty($special['customer_group_id']) || !isset($special['price']) || $special['price'] === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_special SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$special['customer_group_id'] . "', priority = '" . (int)(isset($special['priority']) ? $special['priority'] : 1) . "', price = '" . (float)$special['price'] . "', date_start = '" . $this->db->escape(isset($special['date_start']) ? $special['date_start'] : '0000-00-00') . "', date_end = '" . $this->db->escape(isset($special['date_end']) ? $special['date_end'] : '0000-00-00') . "', auto_renew = '" . (int)(!empty($special['auto_renew'])) . "', date_added = '" . $this->db->escape(!empty($special['date_added']) ? $special['date_added'] : date('Y-m-d H:i:s')) . "'");
		}
	}

	public function deleteAllVariantSpecials($variant_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . (int)$variant_id . "'");
	}

	/**
	 * Bulk variant hydration for multiple products (N+1 killer for listings).
	 * Returns:
	 *   'configurable' => [product_id => configurable row (only is_configurable=1)]
	 *   'options'      => [product_id => [axis rows with 'values' => [option_value rows]]]
	 *   'variants'     => [product_id => [variant rows with 'values' => [value rows]]]
	 */
	public function getConfigurableDataByProductIds(array $product_ids) {
		$result = array(
			'configurable' => array(),
			'options'      => array(),
			'variants'     => array(),
		);

		if (empty($product_ids)) {
			return $result;
		}

		$ids = array_values(array_unique(array_map('intval', $product_ids)));
		$in = implode(',', $ids);
		$language_id = (int)$this->config->get('config_language_id');

		$config_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_configurable WHERE product_id IN (" . $in . ") AND is_configurable = '1'");

		foreach ($config_query->rows as $row) {
			$result['configurable'][(int)$row['product_id']] = $row;
		}

		if (empty($result['configurable'])) {
			return $result;
		}

		$configurable_ids = array_keys($result['configurable']);

		$options_query = $this->db->query("SELECT pco.product_id, pco.option_id, pco.position, o.type, od.name FROM " . DB_PREFIX . "product_configurable_option pco LEFT JOIN `" . DB_PREFIX . "option` o ON (pco.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE pco.product_id IN (" . implode(',', $configurable_ids) . ") AND od.language_id = '" . $language_id . "' ORDER BY pco.product_id ASC, pco.position ASC");

		$axis_option_ids = array();

		foreach ($options_query->rows as $row) {
			$pid = (int)$row['product_id'];
			$result['options'][$pid][] = $row;
			$axis_option_ids[$pid][] = (int)$row['option_id'];
		}

		// Axis option values: one query for all (product_id, option_id) pairs
		$value_sql_parts = array();

		foreach ($axis_option_ids as $pid => $option_ids) {
			foreach ($option_ids as $oid) {
				$value_sql_parts[] = "(pov.product_id = '" . $pid . "' AND pov.option_id = '" . $oid . "')";
			}
		}

		if (!empty($value_sql_parts)) {
			// Only option values that belong to at least one ENABLED variant
			// (status = 1) — disabled variants are hidden from the storefront.
			$values_query = $this->db->query("SELECT pov.product_id, pov.option_id, ov.option_value_id, ovd.name, ov.color_code FROM " . DB_PREFIX . "product_option_value pov INNER JOIN " . DB_PREFIX . "product_variant_value pvv ON (pvv.product_id = pov.product_id AND pvv.option_id = pov.option_id AND pvv.option_value_id = pov.option_value_id) INNER JOIN " . DB_PREFIX . "product_variant pv ON (pv.variant_id = pvv.variant_id) LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE (" . implode(' OR ', $value_sql_parts) . ") AND pv.status = '1' AND ovd.language_id = '" . $language_id . "' GROUP BY pov.product_id, pov.option_id, ov.option_value_id, ovd.name, ov.color_code ORDER BY ov.sort_order ASC, ov.option_value_id ASC");

			$values_by_key = array();

			foreach ($values_query->rows as $row) {
				$values_by_key[(int)$row['product_id'] . ':' . (int)$row['option_id']][] = array(
					'option_value_id' => (int)$row['option_value_id'],
					'name'            => $row['name'],
					'color_code'      => $row['color_code'],
				);
			}

			foreach ($result['options'] as $pid => &$axes) {
				foreach ($axes as &$axis) {
					$key = $pid . ':' . (int)$axis['option_id'];
					$axis['values'] = isset($values_by_key[$key]) ? $values_by_key[$key] : array();
				}
				unset($axis);
			}
			unset($axes);
		}

		// Variants + values (only ENABLED variants — disabled ones are not
		// shown anywhere on the storefront, same rule as disabled products).
		$variants_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE product_id IN (" . implode(',', $configurable_ids) . ") AND status = '1' ORDER BY product_id ASC, sort_order ASC, variant_id ASC");

		$variant_ids = array();

		foreach ($variants_query->rows as $row) {
			$result['variants'][(int)$row['product_id']][] = $row;
			$variant_ids[] = (int)$row['variant_id'];
		}

		if (!empty($variant_ids)) {
			$values_query = $this->db->query("SELECT pvv.*, ovd.name FROM " . DB_PREFIX . "product_variant_value pvv LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pvv.option_value_id = ovd.option_value_id) WHERE pvv.variant_id IN (" . implode(',', $variant_ids) . ") AND ovd.language_id = '" . $language_id . "' ORDER BY pvv.variant_id ASC, pvv.option_id ASC");

			$values_by_variant = array();

			foreach ($values_query->rows as $row) {
				$values_by_variant[(int)$row['variant_id']][] = $row;
			}

			foreach ($result['variants'] as &$variants) {
				foreach ($variants as &$variant) {
					$variant['values'] = isset($values_by_variant[(int)$variant['variant_id']]) ? $values_by_variant[(int)$variant['variant_id']] : array();
				}
				unset($variant);
			}
			unset($variants);
		}

		return $result;
	}

	/**
	 * Variant defaults for many products in one query.
	 * Returns [product_id => variant row (with 'values')].
	 */
	public function getDefaultVariantsByProductIds(array $product_ids) {
		$result = array();

		if (empty($product_ids)) {
			return $result;
		}

		$ids = array_values(array_unique(array_map('intval', $product_ids)));
		$language_id = (int)$this->config->get('config_language_id');

		$config_query = $this->db->query("SELECT product_id, default_variant_id FROM " . DB_PREFIX . "product_configurable WHERE product_id IN (" . implode(',', $ids) . ") AND default_variant_id IS NOT NULL AND default_variant_id > 0");

		$variant_ids = array();
		$variant_by_product = array();

		foreach ($config_query->rows as $row) {
			$vid = (int)$row['default_variant_id'];
			$variant_by_product[(int)$row['product_id']] = $vid;
			$variant_ids[] = $vid;
		}

		if (empty($variant_ids)) {
			return $result;
		}

		$variants_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE variant_id IN (" . implode(',', $variant_ids) . ")");

		$variant_rows = array();

		foreach ($variants_query->rows as $row) {
			$variant_rows[(int)$row['variant_id']] = $row;
		}

		$values_query = $this->db->query("SELECT pvv.*, ovd.name FROM " . DB_PREFIX . "product_variant_value pvv LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pvv.option_value_id = ovd.option_value_id) WHERE pvv.variant_id IN (" . implode(',', $variant_ids) . ") AND ovd.language_id = '" . $language_id . "' ORDER BY pvv.variant_id ASC, pvv.option_id ASC");

		$values_by_variant = array();

		foreach ($values_query->rows as $row) {
			$values_by_variant[(int)$row['variant_id']][] = $row;
		}

		foreach ($variant_by_product as $pid => $vid) {
			if (isset($variant_rows[$vid])) {
				$variant = $variant_rows[$vid];
				$variant['values'] = isset($values_by_variant[$vid]) ? $values_by_variant[$vid] : array();
				$result[$pid] = $variant;
			}
		}

		return $result;
	}

	/**
	 * Bulk variant pricing data for many products (3 queries total).
	 * Returns:
	 *   'cg_prices'  => [product_id => [variant_id => price]]  (only current customer group)
	 *   'specials'   => [variant_id => [active special rows]]
	 *   'discounts'  => [variant_id => [discount rows]]
	 */
	public function getVariantPricingByProductIds(array $product_ids, $customer_group_id = 0) {
		$result = array(
			'cg_prices' => array(),
			'specials'  => array(),
			'discounts' => array(),
		);

		if (empty($product_ids)) {
			return $result;
		}

		$ids = array_values(array_unique(array_map('intval', $product_ids)));
		$in = implode(',', $ids);

		$cg_query = $this->db->query("SELECT pv.product_id, cgp.variant_id, cgp.price FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price cgp INNER JOIN " . DB_PREFIX . "product_variant pv ON (cgp.variant_id = pv.variant_id) WHERE pv.product_id IN (" . $in . ") AND cgp.customer_group_id = '" . (int)$customer_group_id . "'");

		foreach ($cg_query->rows as $row) {
			$result['cg_prices'][(int)$row['product_id']][(int)$row['variant_id']] = (float)$row['price'];
		}

		$specials_query = $this->db->query("SELECT vs.*, pv.product_id FROM " . DB_PREFIX . "dockercart_product_variant_special vs INNER JOIN " . DB_PREFIX . "product_variant pv ON (vs.variant_id = pv.variant_id) WHERE pv.product_id IN (" . $in . ")");

		foreach ($specials_query->rows as $row) {
			$result['specials'][(int)$row['variant_id']][] = $row;
		}

		$discounts_query = $this->db->query("SELECT vd.*, pv.product_id FROM " . DB_PREFIX . "dockercart_product_variant_discount vd INNER JOIN " . DB_PREFIX . "product_variant pv ON (vd.variant_id = pv.variant_id) WHERE pv.product_id IN (" . $in . ") ORDER BY vd.quantity ASC, vd.priority ASC");

		foreach ($discounts_query->rows as $row) {
			$result['discounts'][(int)$row['variant_id']][] = $row;
		}

		return $result;
	}

	public function getVariantsDiscounts($product_id) {
		$query = $this->db->query("SELECT vd.* FROM " . DB_PREFIX . "dockercart_product_variant_discount vd INNER JOIN " . DB_PREFIX . "product_variant pv ON (vd.variant_id = pv.variant_id) WHERE pv.product_id = '" . (int)$product_id . "' ORDER BY vd.quantity ASC, vd.priority ASC");

		$result = array();

		foreach ($query->rows as $row) {
			$result[(int)$row['variant_id']][] = array(
				'variant_discount_id' => (int)$row['variant_discount_id'],
				'variant_id'          => (int)$row['variant_id'],
				'customer_group_id'   => (int)$row['customer_group_id'],
				'quantity'            => (int)$row['quantity'],
				'priority'            => (int)$row['priority'],
				'price'               => $row['price'],
				'date_start'          => $row['date_start'],
				'date_end'            => $row['date_end'],
				'auto_renew'          => (int)$row['auto_renew'],
				'date_added'          => isset($row['date_added']) ? $row['date_added'] : '0000-00-00 00:00:00',
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

			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_product_variant_discount SET variant_id = '" . (int)$variant_id . "', customer_group_id = '" . (int)$discount['customer_group_id'] . "', quantity = '" . (int)(isset($discount['quantity']) ? $discount['quantity'] : 0) . "', priority = '" . (int)(isset($discount['priority']) ? $discount['priority'] : 1) . "', price = '" . (float)$discount['price'] . "', date_start = '" . $this->db->escape(isset($discount['date_start']) ? $discount['date_start'] : '0000-00-00') . "', date_end = '" . $this->db->escape(isset($discount['date_end']) ? $discount['date_end'] : '0000-00-00') . "', auto_renew = '" . (int)(!empty($discount['auto_renew'])) . "', date_added = '" . $this->db->escape(!empty($discount['date_added']) ? $discount['date_added'] : date('Y-m-d H:i:s')) . "'");
		}
	}

	public function deleteAllVariantDiscounts($variant_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_discount WHERE variant_id = '" . (int)$variant_id . "'");
	}

	/**
	 * Persist variant-scoped promotions submitted by the admin product form.
	 *
	 * Each row must carry a variant_id; rows referencing variants that do not
	 * belong to the product are dropped. Variants that currently have promos
	 * but are absent from the submitted rows get their promos cleared, so
	 * removing a promo card in the form really deletes the row on save.
	 *
	 * @param int   $product_id
	 * @param array $special_rows  flat list of product_special-style rows with variant_id
	 * @param array $discount_rows  flat list of product_discount-style rows with variant_id
	 */
	public function applyVariantPromotions($product_id, array $special_rows = array(), array $discount_rows = array()) {
		$product_id = (int)$product_id;

		if (!$product_id) {
			return;
		}

		$query = $this->db->query("SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . $product_id . "'");

		$owned = array();

		foreach ($query->rows as $row) {
			$owned[(int)$row['variant_id']] = true;
		}

		if (!$owned && !$special_rows && !$discount_rows) {
			return;
		}

		$specials_by_variant = array();
		$discounts_by_variant = array();
		$touched = array();

		foreach ($special_rows as $row) {
			$variant_id = isset($row['variant_id']) ? (int)$row['variant_id'] : 0;

			if ($variant_id > 0 && isset($owned[$variant_id])) {
				$specials_by_variant[$variant_id][] = $row;
				$touched[$variant_id] = true;
			}
		}

		foreach ($discount_rows as $row) {
			$variant_id = isset($row['variant_id']) ? (int)$row['variant_id'] : 0;

			if ($variant_id > 0 && isset($owned[$variant_id])) {
				$discounts_by_variant[$variant_id][] = $row;
				$touched[$variant_id] = true;
			}
		}

		// Variants whose existing promos are not present in the submission
		// must be cleared as well (card removed in the UI).
		$existing_specials = $this->getVariantsSpecials($product_id);
		$existing_discounts = $this->getVariantsDiscounts($product_id);

		foreach ($existing_specials as $variant_id => $rows) {
			$touched[(int)$variant_id] = true;
		}

		foreach ($existing_discounts as $variant_id => $rows) {
			$touched[(int)$variant_id] = true;
		}

		foreach (array_keys($touched) as $variant_id) {
			$this->setVariantSpecials($variant_id, isset($specials_by_variant[$variant_id]) ? $specials_by_variant[$variant_id] : array());
			$this->setVariantDiscounts($variant_id, isset($discounts_by_variant[$variant_id]) ? $discounts_by_variant[$variant_id] : array());
		}
	}

	private function touchProduct($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
	}

	/**
	 * Build the cart/order option_data for a variant: one entry per axis
	 * option value (product_option_id, product_option_value_id, name, value,
	 * type), mirroring the storefront add-to-cart payload. Returns [] when
	 * the variant is missing.
	 *
	 * @param int $variant_id
	 * @return array
	 */
	public function getVariantOptionData($variant_id) {
		$variant = $this->getVariant((int)$variant_id);

		if (empty($variant) || empty($variant['values'])) {
			return array();
		}

		$product_id = (int)$variant['product_id'];

		$option_ids = array();
		$option_value_ids = array();

		foreach ($variant['values'] as $value) {
			$option_ids[(int)$value['option_id']] = true;
			$option_value_ids[(int)$value['option_value_id']] = true;
		}

		$query = $this->db->query(
			"SELECT po.product_option_id, po.product_id, po.option_id, po.required, o.type FROM "
			. DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) "
			. "WHERE po.product_id = '" . $product_id . "' AND po.option_id IN (" . implode(',', array_keys($option_ids)) . ") ORDER BY po.sort_order ASC"
		);

		$product_option_rows = array();

		foreach ($query->rows as $row) {
			$product_option_rows[(int)$row['option_id']] = $row;
		}

		$query = $this->db->query(
			"SELECT pov.product_option_id, pov.product_option_value_id, pov.option_value_id, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM "
			. DB_PREFIX . "product_option_value pov WHERE pov.product_option_id IN ("
			. implode(',', array_map(function ($po) {
				return (int)$po['product_option_id'];
			}, array_values($product_option_rows)))
			. ") AND pov.option_value_id IN (" . implode(',', array_keys($option_value_ids)) . ")"
		);

		$product_option_value_rows = array();

		foreach ($query->rows as $row) {
			$key = (int)$row['product_option_id'] . ':' . (int)$row['option_value_id'];
			$product_option_value_rows[$key] = $row;
		}

		$option_data = array();

		foreach ($variant['values'] as $value) {
			$po = isset($product_option_rows[(int)$value['option_id']]) ? $product_option_rows[(int)$value['option_id']] : array();

			if (empty($po)) {
				continue;
			}

			$key = (int)$po['product_option_id'] . ':' . (int)$value['option_value_id'];
			$pov = isset($product_option_value_rows[$key]) ? $product_option_value_rows[$key] : array();

			if (empty($pov)) {
				continue;
			}

			$option_data[] = array(
				'product_option_id'       => (int)$po['product_option_id'],
				'product_option_value_id' => (int)$pov['product_option_value_id'],
				'name'                    => (string)($value['name'] ?? ''),
				'value'                   => (string)($value['name'] ?? ''),
				'type'                    => (string)($po['type'] ?? ''),
			);
		}

		return $option_data;
	}

	/**
	 * Gift/BXGY reward variant data: the product's default variant when the
	 * gift product is configurable and the variant is available (status = 1
	 * and, when stock is tracked, quantity > 0). Returns [] for non-
	 * configurable products or when no valid variant exists — the caller then
	 * skips the gift line instead of adding a variant-less order row.
	 *
	 * @param int $gift_product_id
	 * @return array ['variant_id', 'variant_sku', 'model', 'label', 'option']
	 */
	public function getGiftVariantData($gift_product_id) {
		if (!$this->isConfigurable((int)$gift_product_id)) {
			return array();
		}

		$variant = $this->getDefaultVariant((int)$gift_product_id);

		if (empty($variant) || empty($variant['status'])) {
			return array();
		}

		$stock_checkout = (int)$this->config->get('config_stock_checkout');

		if ((int)$variant['subtract'] === 1 && (float)$variant['quantity'] <= 0 && !$stock_checkout) {
			return array();
		}

		$label_parts = array();

		foreach ($variant['values'] as $value) {
			if (!empty($value['name'])) {
				$label_parts[] = $value['name'];
			}
		}

		$option_data = $this->getVariantOptionData((int)$variant['variant_id']);

		return array(
			'variant_id'  => (int)$variant['variant_id'],
			'variant_sku' => (string)($variant['sku'] ?? ''),
			'model'       => !empty($variant['model']) ? $variant['model'] : (string)($variant['sku'] ?? ''),
			'label'       => implode(', ', $label_parts),
			'option'      => $option_data,
		);
	}
}
