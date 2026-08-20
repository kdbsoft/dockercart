<?php
class ControllerProductCompare extends Controller {
	private function parseCompareKey($key) {
		$parts = explode(':', (string)$key);

		return array(
			'product_id' => (int)($parts[0] ?? 0),
			'variant_id' => isset($parts[1]) ? (int)$parts[1] : 0
		);
	}

	private function buildCompareKey($product_id, $variant_id) {
		$variant_id = (int)$variant_id;

		return $variant_id > 0 ? (int)$product_id . ':' . $variant_id : (string)(int)$product_id;
	}

	public function index() {
		$this->load->language('product/compare');
		$this->load->helper('plural');

		$this->load->model('catalog/product');

		$this->load->model('catalog/category');

		$this->load->model('tool/image');

		if (!isset($this->session->data['compare'])) {
			$this->session->data['compare'] = array();
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['remove']) && $this->validateCsrf()) {
			$remove = $this->parseCompareKey($this->request->post['remove']);

			$key = array_search($this->buildCompareKey($remove['product_id'], $remove['variant_id']), $this->session->data['compare']);

			if ($key !== false) {
				unset($this->session->data['compare'][$key]);

				$product_info = $this->model_catalog_product->getProduct($remove['product_id']);

				if ($product_info) {
					$this->session->data['success'] = sprintf($this->language->get('text_remove'), $this->url->link('product/product', 'product_id=' . $remove['product_id']), $product_info['name'], $this->url->link('product/compare'));
				}
			}

			$this->response->redirect($this->url->link('product/compare'));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/compare')
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['review_status'] = $this->config->get('config_review_status');

		$data['products'] = array();

		$data['attribute_groups'] = array();

		$compare_keys = array_values($this->session->data['compare']);

		$product_ids = array();

		foreach ($compare_keys as $key) {
			$parsed = $this->parseCompareKey($key);
			$product_ids[] = $parsed['product_id'];
		}

		$products_info = $this->model_catalog_product->getProductsByIds(array_map('intval', $product_ids));

		$attributes_by_product = array();
		if ($products_info) {
			$attributes_by_product = $this->model_catalog_product->getProductAttributesByIds(array_keys($products_info));
		}

		foreach ($compare_keys as $key) {
			$parsed = $this->parseCompareKey($key);
			$product_id = $parsed['product_id'];
			$variant_id = $parsed['variant_id'];

			$product_info = isset($products_info[$product_id]) ? $products_info[$product_id] : false;

			if ($product_info) {
				// Resolve the concrete variant for this compare column
				$variant = array();

				if ($variant_id > 0) {
					foreach (($product_info['variants'] ?? array()) as $v) {
						if ((int)$v['variant_id'] === $variant_id) {
							$variant = $v;
							break;
						}
					}
				}

				// A stored variant that no longer exists is a ghost column — drop it
				if ($variant_id > 0 && empty($variant)) {
					unset($this->session->data['compare'][array_search($key, $this->session->data['compare'])]);
					continue;
				}

				$image = $product_info['image'];
				if (!empty($variant['image'])) {
					$image = $variant['image'];
				}

				if ($image) {
					$image = $this->model_tool_image->resize($image, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_compare_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_compare_height'));
				} else {
					$image = false;
				}

				// Specific variant price/special via the shared calculator
				$price_raw = (float)$product_info['price'];
				$special_raw = isset($product_info['special']) && $product_info['special'] !== null ? (float)$product_info['special'] : 0;

				if (!empty($variant['price']) && (float)$variant['price'] > 0) {
					$calculator = new ProductPricingCalculator($this->registry);
					$variant_pricing = $calculator->calculate($product_id, $variant_id, 1);
					$currency_id = isset($product_info['currency_id']) ? (int)$product_info['currency_id'] : 0;

					if ($variant_pricing['special'] !== null) {
						$price_raw = (float)$this->currency->convertProductPrice($variant_pricing['base_price'], $currency_id);
						$special_raw = (float)$this->currency->convertProductPrice($variant_pricing['special'], $currency_id);
					} else {
						$price_raw = (float)$this->currency->convertProductPrice($variant_pricing['price'], $currency_id);
						$special_raw = 0;
					}
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($price_raw, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$special_raw > 0) {
					$special = $this->currency->format($this->tax->calculate($special_raw, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				$quantity = (float)$product_info['quantity'];
				if (!empty($variant['quantity'])) {
					$quantity = (float)$variant['quantity'];
				}

				if ($quantity <= 0) {
					$availability = $product_info['preorder']
						? $this->language->get('text_preorder')
						: $this->language->get('text_out_of_stock');
				} elseif ($this->config->get('config_stock_display')) {
					$availability = $quantity;
				} else {
					$availability = $this->language->get('text_instock');
				}

				// Correct stock flag for the add-to-cart button
				if (!empty($variant['quantity'])) {
					$is_in_stock = (float)$variant['quantity'] > 0 || !empty($product_info['preorder']);
				} else {
					$is_in_stock = !ProductStockSorter::isOutOfStock($product_info);
				}

				// Variant label: "Value1 / Value2" from the variant's option values
				$variant_name = '';
				if (!empty($variant['values'])) {
					$names = array();
					foreach ($variant['values'] as $vv) {
						if (!empty($vv['name'])) {
							$names[] = $vv['name'];
						}
					}
					$variant_name = implode(' / ', $names);
				}

				$attribute_data = array();

				$attribute_groups = isset($attributes_by_product[$product_id]) ? $attributes_by_product[$product_id] : array();

				foreach ($attribute_groups as $attribute_group) {
					foreach ($attribute_group['attribute'] as $attribute) {
						$attribute_data[$attribute['attribute_id']] = $attribute['text'];
					}
				}

				$product_url = 'product_id=' . $product_id;

				if ($variant_id > 0) {
					$product_url .= '&variant_id=' . $variant_id;
				}

				// Main category for grouping compare sections
				$category_id = !empty($product_info['main_category_id']) ? (int)$product_info['main_category_id'] : 0;
				$category_name = '';

				if ($category_id > 0) {
					$category_info = $this->model_catalog_category->getCategory($category_id);

					if ($category_info) {
						$category_name = $category_info['name'];
					}
				}

				$data['products'][$key] = array(
					'product_id'    => $product_id,
					'variant_id'    => $variant_id,
					'variant_name'  => $variant_name,
					'name'          => $product_info['name'],
					'thumb'         => $image,
					'price'         => $price,
					'special'       => $special,
					'description'   => utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, 200) . '..',
					'model'         => !empty($variant['model']) ? $variant['model'] : $product_info['model'],
					'manufacturer'  => $product_info['manufacturer'],
					'availability'  => $availability,
					'is_in_stock'   => $is_in_stock,
					'minimum'       => $product_info['minimum'] > 0 ? $product_info['minimum'] : 1,
					'rating'        => (float)$product_info['rating'],
					'reviews'       => review_count_label((int)$product_info['reviews'], $this->language->get('code')),
					'weight'        => !empty($variant['weight']) && (float)$variant['weight'] > 0 ? $this->weight->format($variant['weight'], !empty($variant['weight_class_id']) ? $variant['weight_class_id'] : $product_info['weight_class_id']) : $this->weight->format($product_info['weight'], $product_info['weight_class_id']),
					'length'        => $this->length->format($product_info['length'], $product_info['length_class_id']),
					'width'         => $this->length->format($product_info['width'], $product_info['length_class_id']),
					'height'        => $this->length->format($product_info['height'], $product_info['length_class_id']),
					'attribute'     => $attribute_data,
					'category_id'   => $category_id,
					'category_name' => $category_name,
					'href'          => $this->url->link('product/product', $product_url),
					'remove'        => $this->url->link('product/compare', ''),
					'remove_key'    => $key
				);

				foreach ($attribute_groups as $attribute_group) {
					$data['attribute_groups'][$attribute_group['attribute_group_id']]['name'] = $attribute_group['name'];

					foreach ($attribute_group['attribute'] as $attribute) {
						$data['attribute_groups'][$attribute_group['attribute_group_id']]['attribute'][$attribute['attribute_id']]['name'] = $attribute['name'];
					}
				}
			} else {
				unset($this->session->data['compare'][array_search($key, $this->session->data['compare'])]);
			}
		}

		// Group compare columns by the product's main category so attributes
		// are only rendered for products that actually share a category.
		$data['compare_sections'] = array();

		foreach ($data['products'] as $key => $product) {
			$cat_id = $product['category_id'];

			if (!isset($data['compare_sections'][$cat_id])) {
				$data['compare_sections'][$cat_id] = array(
					'category_id'   => $cat_id,
					'category_name' => $product['category_name'],
					'products'      => array()
				);
			}

			$data['compare_sections'][$cat_id]['products'][$key] = $product;
		}

		// Flatten attribute name map (attribute_id => name) for the template
		$data['attribute_names'] = array();

		foreach ($data['attribute_groups'] as $attribute_group) {
			foreach ($attribute_group['attribute'] as $attribute_id => $attribute) {
				$data['attribute_names'][$attribute_id] = $attribute['name'];
			}
		}

		// Base-column labels that must not repeat as attribute rows
		// (brand/model/weight, in all supported languages).
		$base_labels = array(
			'brand', 'manufacturer', 'model', 'product code', 'weight',
			'бренд', 'производитель', 'модель', 'код товара', 'артикул', 'вес',
			'бренд', 'виробник', 'модель', 'код товару', 'артикул', 'вага'
		);

		$base_labels[] = strtolower($this->language->get('text_manufacturer'));
		$base_labels[] = strtolower($this->language->get('text_model'));
		$base_labels[] = strtolower($this->language->get('text_weight'));

		// Build per-section attribute rows: attribute_id, name and per-product
		// values. Only attributes present (non-empty) in at least one product
		// of the section are included.
		foreach ($data['compare_sections'] as &$section) {
			$attr_map = array();

			foreach ($section['products'] as $product) {
				$pid = (int)$product['product_id'];

				foreach ($product['attribute'] as $attr_id => $text) {
					$label = isset($data['attribute_names'][$attr_id]) ? $data['attribute_names'][$attr_id] : (string)$attr_id;

					if (in_array(strtolower($label), $base_labels, true)) {
						continue;
					}

					if (!isset($attr_map[$attr_id])) {
						$attr_map[$attr_id] = array(
							'attribute_id' => $attr_id,
							'name'         => $label,
							'values'       => array()
						);
					}

					$attr_map[$attr_id]['values'][$pid] = $text;
				}
			}

			// Drop attributes that are empty for every product in the section
			foreach ($attr_map as $attr_id => $attr) {
				$has_value = false;

				foreach ($section['products'] as $product) {
					if (!empty($attr['values'][(int)$product['product_id']])) {
						$has_value = true;
						break;
					}
				}

				if (!$has_value) {
					unset($attr_map[$attr_id]);
				}
			}

			$section['attributes'] = array_values($attr_map);
		}
		unset($section);

		$data['continue'] = '/';

		$data['csrf_token'] = $this->csrfToken();

		$data['compare_url'] = $this->url->link('product/compare');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/compare', $data));
	}

	public function add() {
		$this->load->language('product/compare');
		$this->load->helper('plural');

		$json = array();

		if (!$this->validateCsrf()) {
			$json['error'] = $this->language->get('error_csrf');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (!isset($this->session->data['compare'])) {
			$this->session->data['compare'] = array();
		}

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->post['variant_id'])) {
			$variant_id = (int)$this->request->post['variant_id'];
		} else {
			$variant_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info && $this->isValidVariant($product_id, $variant_id)) {
			$key = $this->buildCompareKey($product_id, $variant_id);

			if (!in_array($key, $this->session->data['compare'])) {
				if (count($this->session->data['compare']) >= 4) {
					array_shift($this->session->data['compare']);
				}

				$this->session->data['compare'][] = $key;
			}

			$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_id), $product_info['name'], $this->url->link('product/compare'));

			$json['total'] = count($this->session->data['compare']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('product/compare');
		$this->load->helper('plural');

		$json = array();

		if (!$this->validateCsrf()) {
			$json['error'] = $this->language->get('error_csrf');

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->post['variant_id'])) {
			$variant_id = (int)$this->request->post['variant_id'];
		} else {
			$variant_id = 0;
		}

		if (!isset($this->session->data['compare'])) {
			$this->session->data['compare'] = array();
		}

		$key = $this->buildCompareKey($product_id, $variant_id);

		$found_key = array_search($key, $this->session->data['compare']);

		if ($found_key !== false) {
			unset($this->session->data['compare'][$found_key]);

			$this->load->model('catalog/product');
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				$json['success'] = sprintf($this->language->get('text_remove'), $this->url->link('product/product', 'product_id=' . $product_id), $product_info['name'], $this->url->link('product/compare'));
			}
		}

		$json['total'] = count($this->session->data['compare']);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function isValidVariant($product_id, $variant_id) {
		$variant_id = (int)$variant_id;

		if ($variant_id <= 0) {
			return true;
		}

		$pc = new ProductConfigurable($this->registry);
		$variant = $pc->getVariant($variant_id);

		if (empty($variant) || (int)$variant['status'] !== 1) {
			return false;
		}

		return (int)$variant['product_id'] === (int)$product_id;
	}
}
