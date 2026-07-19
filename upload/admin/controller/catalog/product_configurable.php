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

			$pairs = array();

			foreach ($values as $value) {
				$pairs[] = (int)$value['option_id'] . ':' . (int)$value['option_value_id'];
			}

			sort($pairs);
			$combo_key = implode('|', $pairs);

			$existing_variants = $this->model_catalog_product_configurable->getVariants($product_id);
			$current_variant_id = !empty($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;

			foreach ($existing_variants as $v) {
				if ((int)$v['variant_id'] === $current_variant_id) {
					continue;
				}

				if (!empty($v['values'])) {
					$existing_pairs = array();

					foreach ($v['values'] as $vv) {
						$existing_pairs[] = (int)$vv['option_id'] . ':' . (int)$vv['option_value_id'];
					}

					sort($existing_pairs);

					if (implode('|', $existing_pairs) === $combo_key) {
						$json['error'] = $this->language->get('error_variant_duplicate');

						break;
					}
				}
			}
		}

		if (!$json) {
			$product_id = (int)$this->request->post['product_id'];
			$data = $this->request->post;

			if (!empty($data['variant_id'])) {
				$this->model_catalog_product_configurable->updateVariant((int)$data['variant_id'], $data);
				$json['variant_id'] = (int)$data['variant_id'];
			} else {
				$json['variant_id'] = $this->model_catalog_product_configurable->addVariant($product_id, $data);
			}

			$json['success'] = $this->language->get('text_success_variant');
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
			$this->model_catalog_product_configurable->deleteVariant((int)$this->request->post['variant_id']);
			$json['success'] = $this->language->get('text_success_variant');
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

			if (!empty($option_ids)) {
				$existing_axes = array();
				$axes_query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

				foreach ($axes_query->rows as $row) {
					$existing_axes[] = (int)$row['option_id'];
				}

				$new_axes = array_diff($option_ids, $existing_axes);

				if (!empty($new_axes)) {
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
				$this->model_catalog_product_configurable->setConfigurableOptions((int)$this->request->post['product_id'], $option_ids);
				$json['success'] = $this->language->get('text_success_axes');
			}
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
				$this->model_catalog_product_configurable->deleteAllVariants($product_id);
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
				$existing_map = array();

				foreach ($existing_variants as $v) {
					if (!empty($v['values'])) {
						$pairs = array();
						foreach ($v['values'] as $vv) {
							$pairs[] = $vv['option_id'] . ':' . $vv['option_value_id'];
						}
						sort($pairs);
						$existing_map[implode('|', $pairs)] = true;
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
						'is_default' => 0,
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
