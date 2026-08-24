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

			foreach (array('length', 'width', 'height', 'weight') as $dim_field) {
				if (isset($v[$dim_field])) {
					$v[$dim_field] = \ProductConfigurable::formatDecimal($v[$dim_field]);
				}
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

		$this->attachPricingRanges($json, $product_id);
		$this->attachDimensionRanges($json, $product_id);

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

			$weight = isset($this->request->post['weight']) ? $this->request->post['weight'] : '0';

			if ($weight !== '' && !is_numeric($weight)) {
				$json['error'] = $this->language->get('error_variant_weight_numeric');
			}

			$length = isset($this->request->post['length']) ? $this->request->post['length'] : '0';

			if ($length !== '' && !is_numeric($length)) {
				$json['error'] = $this->language->get('error_variant_dimension_numeric');
			}

			$width = isset($this->request->post['width']) ? $this->request->post['width'] : '0';

			if ($width !== '' && !is_numeric($width)) {
				$json['error'] = $this->language->get('error_variant_dimension_numeric');
			}

			$height = isset($this->request->post['height']) ? $this->request->post['height'] : '0';

			if ($height !== '' && !is_numeric($height)) {
				$json['error'] = $this->language->get('error_variant_dimension_numeric');
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

				// Variant specials/discounts are managed in the product form
				// Promotions panel (catalog/product_form) and saved with the
				// form submit — not here.

				$json['success'] = $this->language->get('text_success_variant');
			} catch (\RuntimeException $e) {
				$json['error'] = $this->language->get('error_variant_duplicate');
			}
		}

		if (isset($json['success'])) {
			$pid_for_pricing = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
			$this->attachPricingRanges($json, $pid_for_pricing);
			$this->attachDimensionRanges($json, $pid_for_pricing);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariantDimensions() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;
		$length = isset($this->request->post['length']) ? $this->request->post['length'] : '0';
		$width = isset($this->request->post['width']) ? $this->request->post['width'] : '0';
		$height = isset($this->request->post['height']) ? $this->request->post['height'] : '0';
		$weight = isset($this->request->post['weight']) ? $this->request->post['weight'] : '0';

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$product_id) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json && !$variant_id) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json && $length !== '' && !is_numeric($length)) {
			$json['error'] = $this->language->get('error_variant_dimension_numeric');
		}

		if (!$json && $width !== '' && !is_numeric($width)) {
			$json['error'] = $this->language->get('error_variant_dimension_numeric');
		}

		if (!$json && $height !== '' && !is_numeric($height)) {
			$json['error'] = $this->language->get('error_variant_dimension_numeric');
		}

		if (!$json && $weight !== '' && !is_numeric($weight)) {
			$json['error'] = $this->language->get('error_variant_weight_numeric');
		}

		if (!$json) {
			$variant = $this->model_catalog_product_configurable->getVariant($variant_id);

			if (!$variant || (int)$variant['product_id'] !== $product_id) {
				$json['error'] = $this->language->get('error_variant_not_found');
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->updateVariantDimensions($variant_id, $length !== '' ? $length : 0, $width !== '' ? $width : 0, $height !== '' ? $height : 0, $weight !== '' ? $weight : 0);
			$json['success'] = $this->language->get('text_saved');
		}

		if (!$json || isset($json['success'])) {
			$this->attachPricingRanges($json, $product_id);
			$this->attachDimensionRanges($json, $product_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariantCodes() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;

		$codes = array();

		foreach (array('sku', 'upc', 'ean', 'jan', 'isbn', 'mpn') as $code_field) {
			if (isset($this->request->post[$code_field])) {
				$codes[$code_field] = (string)$this->request->post[$code_field];
			}
		}

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$product_id) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json && !$variant_id) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json && !$codes) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json) {
			$variant = $this->model_catalog_product_configurable->getVariant($variant_id);

			if (!$variant || (int)$variant['product_id'] !== $product_id) {
				$json['error'] = $this->language->get('error_variant_not_found');
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->updateVariantCodes($variant_id, $codes);
			$json['success'] = $this->language->get('text_saved');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariantImage() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');
		$this->load->model('tool/image');

		$json = array();

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;
		$image = isset($this->request->post['image']) ? (string)$this->request->post['image'] : '';

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$product_id) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json && !$variant_id) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json) {
			$variant = $this->model_catalog_product_configurable->getVariant($variant_id);

			if (!$variant || (int)$variant['product_id'] !== $product_id) {
				$json['error'] = $this->language->get('error_variant_not_found');
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->updateVariantImage($variant_id, $image);

			$json['success'] = $this->language->get('text_saved');
			$json['thumb'] = $this->model_tool_image->resize($image !== '' ? $image : 'no_image.png', 60, 60);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariantQuantity() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;
		$quantity = isset($this->request->post['quantity']) ? $this->request->post['quantity'] : '0';

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$product_id) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json && !$variant_id) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json && $quantity !== '' && !is_numeric($quantity)) {
			$json['error'] = $this->language->get('error_variant_quantity_numeric');
		}

		if (!$json) {
			$variant = $this->model_catalog_product_configurable->getVariant($variant_id);

			if (!$variant || (int)$variant['product_id'] !== $product_id) {
				$json['error'] = $this->language->get('error_variant_not_found');
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->updateVariantQuantity($variant_id, $quantity !== '' ? $quantity : 0);
			$json['success'] = $this->language->get('text_saved');
		}

		if (!$json || isset($json['success'])) {
			$this->attachPricingRanges($json, $product_id);
			$this->attachDimensionRanges($json, $product_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveVariantPricing() {
		$this->load->language('catalog/product_configurable');
		$this->load->model('catalog/product_configurable');

		$json = array();

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;
		$price = isset($this->request->post['price']) ? $this->request->post['price'] : '0';
		$cg_prices = isset($this->request->post['customer_group_prices']) ? $this->request->post['customer_group_prices'] : array();

		if (!$this->user->hasPermission('modify', 'catalog/product_configurable')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$product_id) {
			$json['error'] = $this->language->get('error_product_id');
		}

		if (!$json && !$variant_id) {
			$json['error'] = $this->language->get('error_variant_id');
		}

		if (!$json && $price !== '' && $price !== null && !is_numeric($price)) {
			$json['error'] = $this->language->get('error_variant_price_numeric');
		}

		if (!$json && is_array($cg_prices)) {
			foreach ($cg_prices as $cg) {
				if (isset($cg['price']) && $cg['price'] !== '' && $cg['price'] !== null && !is_numeric($cg['price'])) {
					$json['error'] = $this->language->get('error_variant_price_numeric');
					break;
				}
			}
		}

		if (!$json) {
			$variant = $this->model_catalog_product_configurable->getVariant($variant_id);

			if (!$variant || (int)$variant['product_id'] !== $product_id) {
				$json['error'] = $this->language->get('error_variant_not_found');
			}
		}

		if (!$json) {
			$this->model_catalog_product_configurable->updateVariantPricing($variant_id, $price !== '' && $price !== null ? $price : 0, is_array($cg_prices) ? $cg_prices : array());
			$json['success'] = $this->language->get('text_saved');
		}

		if (!$json || isset($json['success'])) {
			$this->attachPricingRanges($json, $product_id);
			$this->attachDimensionRanges($json, $product_id);
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

		if (isset($json['success'])) {
			$this->attachPricingRanges($json, (int)$deleted_product_id);
			$this->attachDimensionRanges($json, (int)$deleted_product_id);
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

		if (isset($json['success'])) {
			$this->attachPricingRanges($json, (int)$product_id);
			$this->attachDimensionRanges($json, (int)$product_id);
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

		if (isset($json['success'])) {
			$this->attachPricingRanges($json, (int)$product_id);
			$this->attachDimensionRanges($json, (int)$product_id);
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

		if (isset($json['success'])) {
			$this->attachPricingRanges($json, (int)$product_id);
			$this->attachDimensionRanges($json, (int)$product_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function attachPricingRanges(&$json, $product_id) {
		$product_id = (int)$product_id;

		if (!$product_id) {
			return;
		}

		$product_row = $this->db->query("SELECT currency_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'")->row;
		$currency_id = isset($product_row['currency_id']) ? (int)$product_row['currency_id'] : 0;
		$format_currency = $this->config->get('config_currency');
		$pc_lib = new \ProductConfigurable($this->registry);
		$price_range = $pc_lib->getAggregatedPriceRange($product_id, null);
		$price_range_text = '';

		if (isset($price_range['min']) && isset($price_range['max']) && ((float)$price_range['min'] > 0 || (float)$price_range['max'] > 0)) {
			$p_min = (float)$this->currency->convertProductPrice((float)$price_range['min'], $currency_id);
			$p_max = (float)$this->currency->convertProductPrice((float)$price_range['max'], $currency_id);
			$price_range_text = $this->currency->format($p_min, $format_currency);

			if ($p_max > $p_min) {
				$price_range_text .= ' – ' . $this->currency->format($p_max, $format_currency);
			}
		}

		$json['price_range'] = $price_range;
		$json['price_range_text'] = $price_range_text;
	}

	private function attachDimensionRanges(&$json, $product_id) {
		$product_id = (int)$product_id;

		if (!$product_id) {
			return;
		}

		$this->load->model('localisation/weight_class');
		$this->load->model('localisation/length_class');

		$pc_lib = new \ProductConfigurable($this->registry);
		$product_row = $this->db->query("SELECT weight_class_id, length_class_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'")->row;
		$weight_class_id = isset($product_row['weight_class_id']) ? (int)$product_row['weight_class_id'] : (int)$this->config->get('config_weight_class_id');
		$length_class_id = isset($product_row['length_class_id']) ? (int)$product_row['length_class_id'] : (int)$this->config->get('config_length_class_id');

		$weight_range = $pc_lib->getAggregatedWeightRange($product_id);
		$dim_range = $pc_lib->getAggregatedDimensionsRange($product_id);

		$weight_range_text = '';
		$weight_unit = '';

		if ($weight_class_id) {
			$wc = $this->model_localisation_weight_class->getWeightClass($weight_class_id);
			if ($wc && !empty($wc['unit'])) {
				$weight_unit = $wc['unit'];
			}
		}

		if (isset($weight_range['min'], $weight_range['max']) && ((float)$weight_range['min'] > 0 || (float)$weight_range['max'] > 0)) {
			$w_min_num = (float)$weight_range['min'];
			$w_max_num = (float)$weight_range['max'];

			$w_min_str = rtrim(rtrim(number_format($w_min_num, 4, '.', ''), '0'), '.');
			$w_max_str = rtrim(rtrim(number_format($w_max_num, 4, '.', ''), '0'), '.');

			if ($w_min_str === '') {
				$w_min_str = '0';
			}

			if ($w_max_str === '') {
				$w_max_str = '0';
			}

			if ($w_max_num > $w_min_num) {
				$weight_range_text = $w_min_str . ' – ' . $w_max_str;
			} else {
				$weight_range_text = $w_min_str;
			}

			if ($weight_unit) {
				$weight_range_text .= ' ' . $weight_unit;
			}
		}

		$length_unit = '';

		if ($length_class_id) {
			$lc = $this->model_localisation_length_class->getLengthClass($length_class_id);
			if ($lc && !empty($lc['unit'])) {
				$length_unit = $lc['unit'];
			}
		}

		$format_dim = function($val) {
			$s = rtrim(rtrim(number_format((float)$val, 4, '.', ''), '0'), '.');
			return $s === '' ? '0' : $s;
		};

		$dim_texts = array();
		$dim_has_data = false;

		foreach (array('length', 'width', 'height') as $k) {
			$min = isset($dim_range[$k]['min']) ? (float)$dim_range[$k]['min'] : 0;
			$max = isset($dim_range[$k]['max']) ? (float)$dim_range[$k]['max'] : 0;

			if ($min > 0 || $max > 0) {
				$dim_has_data = true;
			}

			if ($max > $min) {
				$dim_texts[$k] = $format_dim($min) . ' – ' . $format_dim($max);
			} else {
				$dim_texts[$k] = $format_dim($min);
			}
		}

		$dimensions_range_text = '';

		if ($dim_has_data) {
			$dimensions_range_text = $dim_texts['length'] . ' × ' . $dim_texts['width'] . ' × ' . $dim_texts['height'];

			if ($length_unit) {
				$dimensions_range_text .= ' ' . $length_unit;
			}
		}

		$json['weight_range'] = $weight_range;
		$json['weight_range_text'] = $weight_range_text;
		$json['dimensions_range'] = $dim_range;
		$json['dimensions_range_text'] = $dimensions_range_text;
		$json['length_range_text'] = $dim_texts['length'] . ($length_unit ? ' ' . $length_unit : '');
		$json['width_range_text'] = $dim_texts['width'] . ($length_unit ? ' ' . $length_unit : '');
		$json['height_range_text'] = $dim_texts['height'] . ($length_unit ? ' ' . $length_unit : '');
		$json['weight_class_id'] = $weight_class_id;
		$json['length_class_id'] = $length_class_id;
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
