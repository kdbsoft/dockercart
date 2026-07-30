<?php
class ControllerCatalogProductConfigurable extends Controller {
	private $error = array();

	public function getMatrix() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

		if (!$product_id) {
			$json = array('error' => 'Product ID is required');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$json = array(
			'is_configurable' => $this->model_catalog_product_configurable->isConfigurable($product_id),
			'configurable_options' => $this->model_catalog_product_configurable->getConfigurableOptions($product_id),
			'variants' => $this->model_catalog_product_configurable->getVariants($product_id),
			'default_variant_id' => 0,
		);

		$configurable = $this->model_catalog_product_configurable->getConfigurable($product_id);

		if ($configurable) {
			$json['default_variant_id'] = (int)$configurable['default_variant_id'];
		}

		$this->load->model('tool/image');

		foreach ($json['variants'] as &$v) {
			if (!empty($v['image'])) {
				$v['thumb'] = $this->model_tool_image->resize($v['image'], 60, 60);
			} else {
				$v['thumb'] = $this->model_tool_image->resize('no_image.png', 60, 60);
			}
		}

		$cg_prices = $this->model_catalog_product_configurable->getVariantCustomerGroupPrices($product_id);
		$variant_specials = $this->model_catalog_product_configurable->getVariantsSpecials($product_id);
		$variant_discounts = $this->model_catalog_product_configurable->getVariantsDiscounts($product_id);

		foreach ($json['variants'] as &$v) {
			$v['customer_group_prices'] = isset($cg_prices[(int)$v['variant_id']]) ? $cg_prices[(int)$v['variant_id']] : array();
			$v['variant_specials'] = isset($variant_specials[(int)$v['variant_id']]) ? $variant_specials[(int)$v['variant_id']] : array();
			$v['variant_discounts'] = isset($variant_discounts[(int)$v['variant_id']]) ? $variant_discounts[(int)$v['variant_id']] : array();
		}

		unset($v);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariant() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id'])) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!isset($this->request->post['values']) || empty($this->request->post['values'])) {
			$json['error'] = $this->language->get('error_variant_values');
		}

		if (!$json) {
			$price = isset($this->request->post['price']) ? $this->request->post['price'] : '0';

			if ($price !== '' && !is_numeric($price)) {
				$json['error'] = $this->language->get('error_variant_price_numeric');
			}

			$quantity = isset($this->request->post['quantity']) ? $this->request->post['quantity'] : '0';

			if ($quantity !== '' && !is_numeric($quantity)) {
				$json['error'] = $this->language->get('error_variant_quantity_numeric');
			}
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$axes = $this->model_catalog_product_configurable->getConfigurableOptions($product_id);
			$values = isset($this->request->post['values']) ? $this->request->post['values'] : array();

			if (count($axes) !== count($values)) {
				$json['error'] = $this->language->get('error_variant_axes_mismatch');
			} else {
				$axis_ids = array();

				foreach ($axes as $axis) {
					$axis_ids[] = (int)$axis['option_id'];
				}

				foreach ($values as $value) {
					if (!in_array((int)$value['option_id'], $axis_ids)) {
						$json['error'] = $this->language->get('error_variant_value_unknown_axis');

						break;
					}
				}
			}
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$values = $this->request->post['values'];
			$current_variant_id = !empty($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;

			$hash_parts = array();
			foreach ($values as $value) {
				$hash_parts[(int)$value['option_id']] = (int)$value['option_value_id'];
			}
			ksort($hash_parts);
			$variant_hash = implode('-', $hash_parts);

			$duplicate_id = $this->model_catalog_product_configurable->findDuplicateVariant($product_id, $variant_hash, $current_variant_id);

			if ($duplicate_id) {
				$json['error'] = $this->language->get('error_variant_duplicate');
			}
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$data = $this->request->post;

			try {
				if (!empty($data['variant_id'])) {
					$this->model_catalog_product_configurable->updateVariant((int)$data['variant_id'], $data);
					$json['variant_id'] = (int)$data['variant_id'];
				} else {
					$json['variant_id'] = $this->model_catalog_product_configurable->addVariant($product_id, $data);
				}

				$variant_id = $json['variant_id'];
				$this->model_catalog_product_configurable->deleteAllVariantCustomerGroupPrices($variant_id);
				$cg_prices = isset($this->request->post['customer_group_prices']) ? $this->request->post['customer_group_prices'] : [];

				foreach ($cg_prices as $cg) {
					if (!empty($cg['customer_group_id']) && isset($cg['price']) && $cg['price'] !== '') {
						$this->model_catalog_product_configurable->setVariantCustomerGroupPrice(
							$variant_id, (int)$cg['customer_group_id'], (float)$cg['price']
						);
					}
				}

				$specials = isset($this->request->post['variant_specials']) ? $this->request->post['variant_specials'] : [];
				$this->model_catalog_product_configurable->setVariantSpecials($variant_id, $specials);

				$discounts = isset($this->request->post['variant_discounts']) ? $this->request->post['variant_discounts'] : [];
				$this->model_catalog_product_configurable->setVariantDiscounts($variant_id, $discounts);

				$json['success'] = $this->language->get('text_success_variant');
			} catch (\RuntimeException $e) {
				$json['error'] = $this->language->get('error_variant_duplicate');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteVariant() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['variant_id'])) {
			$json['error'] = $this->language->get('error_variant_id');
		}

	if (!$json) {
		$variant_query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$this->request->post['variant_id'] . "'");
		$deleted_product_id = $variant_query->num_rows ? (int)$variant_query->row['product_id'] : 0;

		$this->model_catalog_product_configurable->deleteVariant((int)$this->request->post['variant_id']);
		$json['success'] = $this->language->get('text_success_variant');

		if ($deleted_product_id) {
			$configurable = $this->model_catalog_product_configurable->getConfigurable($deleted_product_id);
			$json['default_variant_id'] = $configurable ? (int)$configurable['default_variant_id'] : 0;
		}
	}

	$this->response->addHeader('Content-Type: application/json');
	$this->response->setOutput(json_encode($json));
	}

	public function setDefault() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['variant_id'])) {
			$json['error'] = $this->language->get('error_variant_id');
		}

	if (!$json) {
		$this->model_catalog_product_configurable->setDefaultVariant((int)$this->request->post['variant_id']);
		$json['success'] = $this->language->get('text_success_default');
		$json['default_variant_id'] = (int)$this->request->post['variant_id'];
	}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function setAxes() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id'])) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$option_ids = isset($this->request->post['option_ids']) ? $this->request->post['option_ids'] : array();

			if (count($option_ids) > 3) {
				$json['error'] = $this->language->get('error_max_axes');
			}
		}

		if (!$json && !empty($option_ids)) {
			$product_id = (int)$this->request->post['product_id'];
			$existing_axes = array();
			$axes_query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

			foreach ($axes_query->rows as $row) {
				$existing_axes[] = (int)$row['option_id'];
			}

			$new_axes = array_diff($option_ids, $existing_axes);

			if (!empty($new_axes)) {
				$new_axes_list = implode(',', $new_axes);

				$empty_query = $this->db->query("
					SELECT o.option_id
					FROM " . DB_PREFIX . "option o
					LEFT JOIN " . DB_PREFIX . "option_value ov ON (o.option_id = ov.option_id)
					WHERE o.option_id IN (" . $new_axes_list . ")
					GROUP BY o.option_id
					HAVING COUNT(ov.option_value_id) = 0
				");

				if ($empty_query->num_rows) {
					$json['error'] = $this->language->get('error_axis_no_values');
				}
			}

			if (!$json && !empty($new_axes)) {
				$new_axes_list = implode(',', $new_axes);
				$conflict_query = $this->db->query("
					SELECT DISTINCT pov.option_id
					FROM " . DB_PREFIX . "product_option_value pov
					INNER JOIN " . DB_PREFIX . "product_option po ON (pov.product_option_id = po.product_option_id)
					WHERE po.product_id = '" . (int)$product_id . "'
					AND pov.option_id IN (" . $new_axes_list . ")
					AND (pov.price != '0')
				");

				if ($conflict_query->num_rows) {
					$json['error'] = $this->language->get('error_axis_is_simple_option');
				}
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->setConfigurableOptions((int)$this->request->post['product_id'], isset($option_ids) ? $option_ids : array());
			$json['success'] = $this->language->get('text_success_axes');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function setMode() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id'])) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!isset($this->request->post['mode']) || !in_array($this->request->post['mode'], array('simple', 'combined'))) {
			$json['error'] = $this->language->get('error_invalid_mode');
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$mode = $this->request->post['mode'];

			if ($mode === 'simple') {
				$this->model_catalog_product_configurable->disableConfigurable($product_id);
			} else {
				$this->model_catalog_product_configurable->setConfigurable($product_id, 1);
			}

			$json['success'] = $this->language->get('text_success_mode');
			$json['is_configurable'] = ($mode === 'combined');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function generate() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['product_id'])) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$axes = $this->model_catalog_product_configurable->getConfigurableOptions($product_id);

			if (empty($axes)) {
				$json['error'] = $this->language->get('error_no_axes');
			} else {
				$option_values = array();

				foreach ($axes as $axis) {
					$option_id = (int)$axis['option_id'];
					$value_query = $this->db->query("SELECT ov.option_value_id FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

					$option_values[$option_id] = array();

					foreach ($value_query->rows as $row) {
						$option_values[$option_id][] = (int)$row['option_value_id'];
					}
				}

				$combinations = $this->cartesianProduct($option_values);
				$generated = 0;

				$existing_variants = $this->model_catalog_product_configurable->getVariants($product_id);
				$axis_count = count($axes);
				$existing_map = array();

				foreach ($existing_variants as $v) {
					if (!empty($v['values']) && count($v['values']) === $axis_count) {
						$pairs = array();
						foreach ($v['values'] as $vv) {
							$pairs[] = $vv['option_id'] . ':' . $vv['option_value_id'];
						}
						sort($pairs);
						$existing_map[implode('|', $pairs)] = (int)$v['variant_id'];
					} elseif (!empty($v['variant_id'])) {
						$this->model_catalog_product_configurable->deleteVariant((int)$v['variant_id']);
					}
				}

				foreach ($combinations as $combination) {
					$pairs = array();
					foreach ($combination as $oid => $ovid) {
						$pairs[] = $oid . ':' . $ovid;
					}
					sort($pairs);
					$key = implode('|', $pairs);

					if (isset($existing_map[$key])) {
						continue;
					}

					$data = array(
						'model' => '',
						'sku' => '',
						'upc' => '',
						'ean' => '',
						'mpn' => '',
						'price' => 0,
						'quantity' => 0,
						'subtract' => 1,
						'weight' => 0,
						'weight_class_id' => 0,
						'image' => '',
						'sort_order' => 0,
						'status' => 1,
						'values' => array(),
					);

					foreach ($combination as $option_id => $option_value_id) {
						$data['values'][] = array(
							'option_id' => $option_id,
							'option_value_id' => $option_value_id,
						);
					}

					$this->model_catalog_product_configurable->addVariant($product_id, $data);
					$generated++;
				}

				$json['success'] = sprintf($this->language->get('text_success_generate'), $generated);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function cartesianProduct($arrays) {
		$result = array(array());

		foreach ($arrays as $option_id => $values) {
			$append = array();

			foreach ($result as $product) {
				foreach ($values as $item) {
					$product[$option_id] = $item;
					$append[] = $product;
				}
			}

			$result = $append;
		}

		return $result;
	}
}
