<?php
class ModelCatalogProduct extends Model {
	public function updateViewed($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET viewed = (viewed + 1) WHERE product_id = '" . (int)$product_id . "'");
	}

	public function getProduct($product_id) {
		$this->autoRenewProductEntities();

		$cache_enabled = (int)$this->config->get('config_product_cache_status');
		$version_query = $this->db->query("SELECT date_modified FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");
		$cache_stamp = 0;

		if ($version_query->num_rows && !empty($version_query->row['date_modified'])) {
			$cache_stamp = (int)strtotime($version_query->row['date_modified']);
		}

		$cache_key = 'product.get.v4.' . (int)$product_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_customer_group_id') . '.' . $cache_stamp;

		if ($cache_enabled) {
			$cached = $this->cache->get($cache_key);

			if (is_array($cached) && isset($cached['expires_at']) && array_key_exists('data', $cached)) {
				if ((int)$cached['expires_at'] > time()) {
					return $cached['data'];
				}

				$this->cache->delete($cache_key);
			}
		}

		$query = $this->db->query("SELECT DISTINCT *, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC, pd2.product_discount_id ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC, ps.product_special_id ASC LIMIT 1) AS special, (SELECT ps2.date_end FROM " . DB_PREFIX . "product_special ps2 WHERE ps2.product_id = p.product_id AND ps2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps2.date_start = '0000-00-00' OR ps2.date_start < NOW()) AND (ps2.date_end = '0000-00-00' OR ps2.date_end > NOW())) ORDER BY ps2.priority ASC, ps2.price ASC, ps2.product_special_id ASC LIMIT 1) AS special_date_end, (SELECT price FROM " . DB_PREFIX . "dockercart_product_customer_group_price dcgp WHERE dcgp.product_id = p.product_id AND dcgp.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS customer_group_price, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, p.preorder, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, (SELECT 1 FROM " . DB_PREFIX . "product_gift pg WHERE pg.product_id = p.product_id AND (pg.date_start = '0000-00-00' OR pg.date_start <= NOW()) AND (pg.date_end = '0000-00-00' OR pg.date_end >= NOW()) LIMIT 1) AS has_gift, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) WHERE p.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			$price = (float)$query->row['price'];

			// Per-product customer group price override (DockerCart)
			$customer_group_price = $query->row['customer_group_price'];
			$has_customer_group_price = ($customer_group_price !== null && $customer_group_price !== '' && (float)$customer_group_price > 0);

			if ($has_customer_group_price) {
				$price = (float)$customer_group_price;
			}

			if ($query->row['discount'] !== null && $query->row['discount'] !== '' && (float)$query->row['discount'] < $price) {
				$price = (float)$query->row['discount'];
			}

			$special = null;

			if ($query->row['special'] !== null && $query->row['special'] !== '') {
				$special = (float)$query->row['special'];
			}

			$customer_group_discount = (float)$this->config->get('config_customer_group_discount');
			$customer_group_markup = (float)$this->config->get('config_customer_group_markup');

			if (!$has_customer_group_price) {
				if ($customer_group_discount > 0) {
					$discount_multiplier = (100 - $customer_group_discount) / 100;
					$price *= $discount_multiplier;

					if ($special !== null) {
						$special *= $discount_multiplier;
					}
				} elseif ($customer_group_markup > 0) {
					$markup_multiplier = (100 + $customer_group_markup) / 100;
					$price *= $markup_multiplier;

					if ($special !== null) {
						$special *= $markup_multiplier;
					}
				}
			}

			if ($special !== null && $special >= $price) {
				$special = null;
			}

			// Sale timer: unix timestamp of the active special's end date (0 = no end date)
			$special_date_end = 0;

			if ($special !== null && !empty($query->row['special_date_end'])) {
				$date_end = (string)$query->row['special_date_end'];

				if ($date_end !== '' && $date_end !== '0000-00-00' && $date_end !== '0000-00-00 00:00:00') {
					$special_date_end = (int)strtotime($date_end);
				}
			}

			$quantity_step = isset($query->row['quantity_step']) ? (float)$query->row['quantity_step'] : 1.0;

			if ($quantity_step <= 0) {
				$quantity_step = 1.0;
			}

			$product_data = array(
    			'product_id'       => $query->row['product_id'],
    			'main_category_id' => isset($query->row['main_category_id']) ? (int)$query->row['main_category_id'] : 0,
    			'name'             => $query->row['name'],
				'description'      => $query->row['description'],
				'meta_title'       => $query->row['meta_title'],
				'meta_description' => $query->row['meta_description'],
				'meta_keyword'     => $query->row['meta_keyword'],
				'tag'              => $query->row['tag'],
				'model'            => $query->row['model'],
				'sku'              => $query->row['sku'],
				'upc'              => $query->row['upc'],
				'ean'              => $query->row['ean'],
				'jan'              => $query->row['jan'],
				'isbn'             => $query->row['isbn'],
				'mpn'              => $query->row['mpn'],
				'location'         => $query->row['location'],
				'quantity'         => (float)$query->row['quantity'],
				'preorder'         => (int)$query->row['preorder'],
				'image'            => $query->row['image'],
				'model_3d'         => $query->row['model_3d'],
				'manufacturer_id'  => $query->row['manufacturer_id'],
				'manufacturer'     => $query->row['manufacturer'],
				'price'            => $price,
				'special'          => $special,
				'special_date_end' => $special_date_end,
				'reward'           => $query->row['reward'],
				'points'           => $query->row['points'],
				'tax_class_id'     => $query->row['tax_class_id'],
				'date_available'   => $query->row['date_available'],
				'weight'           => $query->row['weight'],
				'weight_class_id'  => $query->row['weight_class_id'],
				'length'           => $query->row['length'],
				'width'            => $query->row['width'],
				'height'           => $query->row['height'],
				'length_class_id'  => $query->row['length_class_id'],
				'subtract'         => $query->row['subtract'],
				'rating'           => round(($query->row['rating']===null) ? 0 : $query->row['rating'], 1),
				'reviews'          => $query->row['reviews'] ? $query->row['reviews'] : 0,
				'minimum'          => (float)$query->row['minimum'],
				'quantity_step'    => $quantity_step,
				'sort_order'       => $query->row['sort_order'],
				'status'           => $query->row['status'],
				'date_added'       => $query->row['date_added'],
				'date_modified'    => $query->row['date_modified'],
				'viewed'           => $query->row['viewed'],
				'has_gift'         => !empty($query->row['has_gift']),
				'discontinued'     => !empty($query->row['discontinued']),
				'call_for_price'   => !empty($query->row['call_for_price'])
			);

			$pc = new ProductConfigurable($this->registry);
			$configurable = $pc->getConfigurable($product_id);

			if (!empty($configurable)) {
				$product_data['is_configurable'] = true;
				$product_data['configurable_options'] = $pc->getConfigurableOptions($product_id);
				$product_data['variants'] = $pc->getVariants($product_id);

				$default_variant = $pc->getDefaultVariant($product_id);

				if ($default_variant) {
					$product_data['default_variant'] = $default_variant;
					$product_data['default_variant_id'] = $default_variant['variant_id'];

					$product_data['default_option_value_ids'] = array();
					if (!empty($default_variant['values'])) {
						foreach ($default_variant['values'] as $dv) {
							$product_data['default_option_value_ids'][] = (int)$dv['option_value_id'];
						}
					}

					// Override base product data with default variant values
					if (isset($default_variant['price']) && $default_variant['price'] !== '' && (float)$default_variant['price'] > 0) {
						$product_data['base_price'] = $product_data['price'];
						$product_data['price'] = (float)$default_variant['price'];
					}
					if (isset($default_variant['quantity'])) {
						$product_data['quantity'] = (float)$default_variant['quantity'];
					}
					if (isset($default_variant['subtract'])) {
						$product_data['subtract'] = (int)$default_variant['subtract'];
					}
					if (!empty($default_variant['model'])) {
						$product_data['model'] = $default_variant['model'];
					} elseif (!empty($default_variant['sku'])) {
						$product_data['model'] = $default_variant['sku'];
					}
					if (!empty($default_variant['upc'])) {
						$product_data['upc'] = $default_variant['upc'];
					}
					if (!empty($default_variant['ean'])) {
						$product_data['ean'] = $default_variant['ean'];
					}
					if (!empty($default_variant['mpn'])) {
						$product_data['mpn'] = $default_variant['mpn'];
					}
					if (!empty($default_variant['image'])) {
						$product_data['image'] = $default_variant['image'];
					}
					if (isset($default_variant['weight']) && (float)$default_variant['weight'] > 0) {
						$product_data['weight'] = (float)$default_variant['weight'];
					}
					if (isset($default_variant['weight_class_id']) && (int)$default_variant['weight_class_id'] > 0) {
						$product_data['weight_class_id'] = (int)$default_variant['weight_class_id'];
					}

					// Override special price from variant
					$variant_special = $pc->getVariantSpecialPrice((int)$default_variant['variant_id'], (int)$this->config->get('config_customer_group_id'));
					if ($variant_special !== null && (float)$variant_special < (float)$product_data['price']) {
						$product_data['special'] = (float)$variant_special;

						if (!$product_data['special_date_end']) {
							$variant_special_end = $pc->getVariantSpecialEndDate((int)$default_variant['variant_id'], (int)$this->config->get('config_customer_group_id'));
							$product_data['special_date_end'] = $variant_special_end !== null ? (int)$variant_special_end : 0;
						}
					}
				}

				$aggregated_stock = $pc->getAggregatedStock($product_id);
				$product_data['variants_in_stock'] = $aggregated_stock['variants_in_stock'];
				$product_data['total_variants'] = $aggregated_stock['total_variants'];

				$product_data['variant_specials'] = $pc->getVariantsSpecials($product_id);

				// Build variant swatches for listing cards
				$swatches = array();
				if (!empty($product_data['variants'])) {
					// Map of option_id => option_value_id for the default variant
					$default_map = array();
					if (!empty($default_variant['values'])) {
						foreach ($default_variant['values'] as $dv) {
							$default_map[(int)$dv['option_id']] = (int)$dv['option_value_id'];
						}
					}

					$best_variant = array();
					foreach ($product_data['variants'] as $v) {
						if (!$v['status']) continue;
						if (empty($v['values'])) continue;

						// Count how many other option values match the default variant
						$match = 0;
						foreach ($v['values'] as $vv) {
							$oid = (int)$vv['option_id'];
							if (isset($default_map[$oid]) && (int)$vv['option_value_id'] === $default_map[$oid]) {
								$match++;
							}
						}

						// For each option value in this variant, keep the best match
						foreach ($v['values'] as $vv) {
							$oid = (int)$vv['option_id'];
							$ovid = (int)$vv['option_value_id'];
							if (!isset($best_variant[$oid][$ovid]) || $match > $best_variant[$oid][$ovid]['match']) {
								$best_variant[$oid][$ovid] = array(
									'variant_id' => (int)$v['variant_id'],
									'name'       => $vv['name'],
									'image'      => $v['image'] ?? '',
									'match'      => $match,
								);
							}
						}
					}

					// Build color_code lookup from configurable_options values
					$color_code_map = array();
					foreach ($product_data['configurable_options'] as $axis) {
						foreach ($axis['values'] as $vv) {
							if (!empty($vv['color_code'])) {
								$color_code_map[(int)$vv['option_value_id']] = $vv['color_code'];
							}
						}
					}

					foreach ($product_data['configurable_options'] as $axis) {
						$oid = (int)$axis['option_id'];
						if (!empty($best_variant[$oid])) {
							$values = array();
							foreach ($best_variant[$oid] as $ovid => $bv) {
								$val = array(
									'option_value_id' => $ovid,
									'name'            => $bv['name'],
									'variant_id'      => $bv['variant_id'],
									'image'           => $bv['image'],
								);
								if (isset($color_code_map[$ovid])) {
									$val['color_code'] = $color_code_map[$ovid];
								}
								$values[] = $val;
							}
							$swatches[$oid] = array(
								'option_id' => $oid,
								'name'      => $axis['name'],
								'type'      => $axis['type'] ?? 'select',
								'values'    => $values,
							);
						}
					}
				}
				$product_data['variant_swatches'] = $swatches;
			}
		} else {
			$product_data = false;
		}

		if ($cache_enabled) {
			$this->cache->set($cache_key, array(
				'expires_at' => time() + 3600,
				'data'       => $product_data
			));
		}

		return $product_data;
	}

	public function getProducts($data = array()) {
		$sql = "SELECT p.product_id, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$tag_query = trim(html_entity_decode($data['filter_tag'], ENT_QUOTES, 'UTF-8'));

				if ($tag_query !== '') {
					$tag_phrase = "pd.tag LIKE '%" . $this->db->escape($tag_query) . "%'";

					$tag_tokens = preg_split('/[\s\p{P}\p{S}]+/u', $tag_query, -1, PREG_SPLIT_NO_EMPTY);
					$token_conditions = array();

					foreach ($tag_tokens as $token) {
						if (utf8_strlen($token) > 1 || is_numeric($token)) {
							$token_conditions[] = "pd.tag LIKE '%" . $this->db->escape($token) . "%'";
						}
					}

					if ($token_conditions) {
						$sql .= " (" . $tag_phrase . " OR (" . implode(" AND ", $token_conditions) . "))";
					} else {
						$sql .= " " . $tag_phrase;
					}
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$sql .= " GROUP BY p.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'p.quantity',
			'p.price',
			'rating',
			'p.sort_order',
			'p.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
			} else {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY (p.quantity <= 0) ASC, p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
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

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getProductSpecials($data = array()) {
		$sql = "SELECT DISTINCT ps.product_id, (SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = ps.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) GROUP BY ps.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'ps.price',
			'rating',
			'p.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, LCASE(" . $data['sort'] . ")";
			} else {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY (p.quantity <= 0) ASC, p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
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

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getLatestProducts($limit) {
		$product_data = $this->cache->get('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.date_added DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getPopularProducts($limit) {
		$product_data = $this->cache->get('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.viewed DESC, p.date_added DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getBestSellerProducts($limit) {
		$product_data = $this->cache->get('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();

			$query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY op.product_id ORDER BY total DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getProductAttributes($product_id) {
		$product_attribute_group_data = array();

		$product_attribute_group_query = $this->db->query("SELECT ag.attribute_group_id, agd.name FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE pa.product_id = '" . (int)$product_id . "' AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND ag.status = '1' GROUP BY ag.attribute_group_id ORDER BY ag.sort_order, agd.name");

		foreach ($product_attribute_group_query->rows as $product_attribute_group) {
			$product_attribute_data = array();

			$product_attribute_query = $this->db->query("SELECT a.attribute_id, ad.name, pa.text FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND a.attribute_group_id = '" . (int)$product_attribute_group['attribute_group_id'] . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "' AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "' AND a.status = '1' ORDER BY a.sort_order, ad.name");

			foreach ($product_attribute_query->rows as $product_attribute) {
				$product_attribute_data[] = array(
					'attribute_id' => $product_attribute['attribute_id'],
					'name'         => $product_attribute['name'],
					'text'         => $product_attribute['text']
				);
			}

			$product_attribute_group_data[] = array(
				'attribute_group_id' => $product_attribute_group['attribute_group_id'],
				'name'               => $product_attribute_group['name'],
				'attribute'          => $product_attribute_data
			);
		}

		return $product_attribute_group_data;
	}

	public function getProductOptions($product_id) {
		$product_option_data = array();

		$product_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "' AND o.status = '1' ORDER BY o.sort_order");

		foreach ($product_option_query->rows as $product_option) {
			$product_option_value_data = array();

			$product_option_value_query = $this->db->query("SELECT pov.product_option_value_id, pov.product_option_id, pov.product_id, pov.option_id, pov.option_value_id, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix, pov.is_hit, ov.color_code, ovd.name, cgp.price AS cg_price, cgp.price_prefix AS cg_price_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) LEFT JOIN " . DB_PREFIX . "dockercart_product_option_value_customer_group_price cgp ON (cgp.product_option_value_id = pov.product_option_value_id AND cgp.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') WHERE pov.product_id = '" . (int)$product_id . "' AND pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY pov.sort_order ASC, pov.product_option_value_id ASC");

			foreach ($product_option_value_query->rows as $product_option_value) {
				$price = $product_option_value['price'];
				$price_prefix = $product_option_value['price_prefix'];

				if ($product_option_value['cg_price'] !== null) {
					$price = $product_option_value['cg_price'];
					$price_prefix = $product_option_value['cg_price_prefix'];
				}

				$product_option_value_data[] = array(
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'name'                    => $product_option_value['name'],
					'color_code'              => $product_option_value['color_code'],
					'price'                   => $price,
					'price_prefix'            => $price_prefix,
				'weight'                  => $product_option_value['weight'],
				'weight_prefix'           => $product_option_value['weight_prefix'],
				'is_hit'                  => $product_option_value['is_hit']
				);
			}

			$product_option_data[] = array(
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $product_option['name'],
				'type'                 => $product_option['type'],
				'value'                => $product_option['value'],
				'required'             => $product_option['required'],
				'show_option_price'    => $product_option['show_option_price']
			);
		}

		$pc = new ProductConfigurable($this->registry);

		if ($pc->isConfigurable($product_id)) {
			$configurable_options = $pc->getConfigurableOptions($product_id);
			$configurable_option_ids = array();

			foreach ($configurable_options as $co) {
				$configurable_option_ids[] = (int)$co['option_id'];
			}

			foreach ($product_option_data as &$option) {
				if (in_array((int)$option['option_id'], $configurable_option_ids)) {
					$option['is_configurable_axis'] = true;
				}
			}
			unset($option);
		}

		return $product_option_data;
	}

	public function getProductDiscounts($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

		return $query->rows;
	}

	public function getProductImages($product_id, $language_id = null) {
		if ($language_id !== null) {
			// Check if language-specific images exist for the given language
			$specific = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "' ORDER BY sort_order ASC");
			if ($specific->num_rows > 0) {
				return $specific->rows;
			}

			// Fall back to global images
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' AND language_id IS NULL ORDER BY sort_order ASC");
			return $query->rows;
		}

		// No language specified: return all images (backward compatible)
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");
		return $query->rows;
	}

	public function getProductVideos($product_id, $language_id = null) {
		if ($language_id !== null) {
			$specific = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_video WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "' ORDER BY sort_order ASC");
			if ($specific->num_rows > 0) {
				return $specific->rows;
			}

			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_video WHERE product_id = '" . (int)$product_id . "' AND language_id IS NULL ORDER BY sort_order ASC");
			return $query->rows;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_video WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");
		return $query->rows;
	}

	public function getProductRelated($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_related pr LEFT JOIN " . DB_PREFIX . "product p ON (pr.related_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pr.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		foreach ($query->rows as $result) {
			$product_data[$result['related_id']] = $this->getProduct($result['related_id']);
		}

		return $product_data;
	}

	public function getProductUpsell($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_upsell pu LEFT JOIN " . DB_PREFIX . "product p ON (pu.upsell_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pu.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		foreach ($query->rows as $result) {
			$product_data[$result['upsell_id']] = $this->getProduct($result['upsell_id']);
		}

		return $product_data;
	}

	public function getProductAccessory($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_accessory pa LEFT JOIN " . DB_PREFIX . "product p ON (pa.accessory_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pa.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		foreach ($query->rows as $result) {
			$product_data[$result['accessory_id']] = $this->getProduct($result['accessory_id']);
		}

		return $product_data;
	}

	public function getProductFbt($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_fbt pf LEFT JOIN " . DB_PREFIX . "product p ON (pf.fbt_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pf.product_id = '" . (int)$product_id . "' AND pf.fbt_id <> '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY pf.fbt_id ASC");

		foreach ($query->rows as $result) {
			$product_data[$result['fbt_id']] = $this->getProduct($result['fbt_id']);
		}

		return $product_data;
	}

	public function getProductSimilar($product_id) {
		$product_data = array();

		// Manually assigned similar products
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_similar ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.similar_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE ps.product_id = '" . (int)$product_id . "' AND ps.similar_id <> '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY ps.similar_id ASC");

		foreach ($query->rows as $result) {
			$product_data[$result['similar_id']] = $this->getProduct($result['similar_id']);
		}

		// Top up with in-stock products from the same categories when not enough similar ones
		$limit = 12;

		if (count($product_data) < $limit) {
			$exclude_ids = array_merge(array_keys($product_data), array((int)$product_id));

			$sql = "SELECT DISTINCT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND (p.quantity > 0 OR p.preorder = '1') AND p2c.category_id IN (SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "')";

			if ($exclude_ids) {
				$sql .= " AND p.product_id NOT IN (" . implode(',', $exclude_ids) . ")";
			}

			$sql .= " ORDER BY p.viewed DESC LIMIT " . ($limit - count($product_data));

			$query = $this->db->query($sql);

			foreach ($query->rows as $result) {
				if (count($product_data) >= $limit) {
					break;
				}

				$product_info = $this->getProduct($result['product_id']);

				// Only in-stock (or pre-order) products make good substitutes
				if ($product_info && ((float)$product_info['quantity'] > 0 || !empty($product_info['preorder']))) {
					$product_data[$result['product_id']] = $product_info;
				}
			}
		}

		return $product_data;
	}

	public function getProductLayoutId($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getCategories($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

		return $query->rows;
	}

	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$tag_query = trim(html_entity_decode($data['filter_tag'], ENT_QUOTES, 'UTF-8'));

				if ($tag_query !== '') {
					$tag_phrase = "pd.tag LIKE '%" . $this->db->escape($tag_query) . "%'";

					$tag_tokens = preg_split('/[\s\p{P}\p{S}]+/u', $tag_query, -1, PREG_SPLIT_NO_EMPTY);
					$token_conditions = array();

					foreach ($tag_tokens as $token) {
						if (utf8_strlen($token) > 1 || is_numeric($token)) {
							$token_conditions[] = "pd.tag LIKE '%" . $this->db->escape($token) . "%'";
						}
					}

					if ($token_conditions) {
						$sql .= " (" . $tag_phrase . " OR (" . implode(" AND ", $token_conditions) . "))";
					} else {
						$sql .= " " . $tag_phrase;
					}
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalProductSpecials() {
		$query = $this->db->query("SELECT COUNT(DISTINCT ps.product_id) AS total FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

		if (isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function getNewArrivalProducts($data = array(), $days = 90) {
		$days = (int)$days;

		if ($days < 1) {
			$days = 90;
		}

		$sql = "SELECT DISTINCT p.product_id, (SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p.date_added >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY) AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY p.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'p.price',
			'rating',
			'p.sort_order',
			'p.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, LCASE(" . $data['sort'] . ")";
			} else {
				$sql .= " ORDER BY (p.quantity <= 0) ASC, " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY (p.quantity <= 0) ASC, p.date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'ASC')) {
			$sql .= " ASC, LCASE(pd.name) ASC";
		} else {
			$sql .= " DESC, LCASE(pd.name) ASC";
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

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getTotalNewArrivalProducts($days = 90) {
		$days = (int)$days;

		if ($days < 1) {
			$days = 90;
		}

		$query = $this->db->query("SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p.date_added >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY) AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if (isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function checkProductCategory($product_id, $category_ids) {

		$implode = array();

		foreach ($category_ids as $category_id) {
			$implode[] = (int)$category_id;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id IN(" . implode(',', $implode) . ")");
  	    return $query->row;
	}

	public function getProductGifts($product_id) {
		$query = $this->db->query("SELECT g.*, pd.name AS name, p.image, p.price FROM " . DB_PREFIX . "product_gift g LEFT JOIN " . DB_PREFIX . "product p ON (g.gift_product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (g.gift_product_id = pd.product_id) WHERE g.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND (g.date_start = '0000-00-00' OR g.date_start <= NOW()) AND (g.date_end = '0000-00-00' OR g.date_end >= NOW()) ORDER BY g.minimum_quantity ASC");

		return $query->rows;
	}

	public function getProductBxgy($product_id) {
		$query = $this->db->query("SELECT bx.*, pd.name AS reward_product_name FROM " . DB_PREFIX . "product_bxgy bx LEFT JOIN " . DB_PREFIX . "product_description pd ON (bx.reward_product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE bx.product_id = '" . (int)$product_id . "' AND (bx.date_start = '0000-00-00' OR bx.date_start <= NOW()) AND (bx.date_end = '0000-00-00' OR bx.date_end >= NOW()) ORDER BY bx.trigger_quantity ASC");

		return $query->rows;
	}

	private function autoRenewProductEntities() {
		static $done = [];

		$today = date('Y-m-d');
		$entities = array(
			'special' => array(
				'table'  => 'product_special',
				'insert' => "
					INSERT INTO " . DB_PREFIX . "product_special (product_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
					SELECT ps.product_id, ps.customer_group_id, ps.priority, ps.price,
						CURDATE(),
						DATE_ADD(CURDATE(), INTERVAL DATEDIFF(ps.date_end, ps.date_start) DAY),
						1
					FROM " . DB_PREFIX . "product_special ps
					WHERE ps.auto_renew = '1'
						AND ps.date_end < CURDATE()
						AND ps.date_end != '0000-00-00'
						AND NOT EXISTS (
							SELECT 1 FROM " . DB_PREFIX . "product_special ps2
							WHERE ps2.product_id = ps.product_id
								AND ps2.customer_group_id = ps.customer_group_id
								AND ps2.priority = ps.priority
								AND ps2.price = ps.price
								AND ps2.date_end > CURDATE()
						)",
			),
			'variant_special' => array(
				'table'  => 'dockercart_product_variant_special',
				'insert' => "
					INSERT INTO " . DB_PREFIX . "dockercart_product_variant_special (variant_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
					SELECT pvs.variant_id, pvs.customer_group_id, pvs.priority, pvs.price,
						CURDATE(),
						DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pvs.date_end, pvs.date_start) DAY),
						1
					FROM " . DB_PREFIX . "dockercart_product_variant_special pvs
					WHERE pvs.auto_renew = '1'
						AND pvs.date_end < CURDATE()
						AND pvs.date_end != '0000-00-00'
						AND NOT EXISTS (
							SELECT 1 FROM " . DB_PREFIX . "dockercart_product_variant_special pvs2
							WHERE pvs2.variant_id = pvs.variant_id
								AND pvs2.customer_group_id = pvs.customer_group_id
								AND pvs2.priority = pvs.priority
								AND pvs2.price = pvs.price
								AND pvs2.date_end > CURDATE()
						)",
			),
			'discount' => array(
				'table'  => 'product_discount',
				'insert' => "
					INSERT INTO " . DB_PREFIX . "product_discount (product_id, customer_group_id, quantity, priority, price, date_start, date_end, auto_renew)
					SELECT pd.product_id, pd.customer_group_id, pd.quantity, pd.priority, pd.price,
						CURDATE(),
						DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pd.date_end, pd.date_start) DAY),
						1
					FROM " . DB_PREFIX . "product_discount pd
					WHERE pd.auto_renew = '1'
						AND pd.date_end < CURDATE()
						AND pd.date_end != '0000-00-00'
						AND NOT EXISTS (
							SELECT 1 FROM " . DB_PREFIX . "product_discount pd2
							WHERE pd2.product_id = pd.product_id
								AND pd2.customer_group_id = pd.customer_group_id
								AND pd2.quantity = pd.quantity
								AND pd2.priority = pd.priority
								AND pd2.price = pd.price
								AND pd2.date_end > CURDATE()
						)",
			),
			'gift' => array(
				'table'  => 'product_gift',
				'insert' => "
					INSERT INTO " . DB_PREFIX . "product_gift (product_id, gift_product_id, minimum_quantity, date_start, date_end, auto_renew)
					SELECT pg.product_id, pg.gift_product_id, pg.minimum_quantity,
						CURDATE(),
						DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pg.date_end, pg.date_start) DAY),
						1
					FROM " . DB_PREFIX . "product_gift pg
					WHERE pg.auto_renew = '1'
						AND pg.date_end < CURDATE()
						AND pg.date_end != '0000-00-00'
						AND NOT EXISTS (
							SELECT 1 FROM " . DB_PREFIX . "product_gift pg2
							WHERE pg2.product_id = pg.product_id
								AND pg2.gift_product_id = pg.gift_product_id
								AND pg2.minimum_quantity = pg.minimum_quantity
								AND pg2.date_end > CURDATE()
						)",
			),
		);

		foreach ($entities as $entity => $config) {
			$key = $entity . '.' . $today;

			if (!empty($done[$key])) {
				continue;
			}

			$cache_key = 'auto_renew.product.' . $key;

			if ($this->cache->get($cache_key)) {
				$done[$key] = true;
				continue;
			}

			$this->db->query($config['insert']);

			$this->cache->set($cache_key, true, 86400);
			$done[$key] = true;
		}
	}
}
