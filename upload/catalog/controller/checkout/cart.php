<?php
class ControllerCheckoutCart extends Controller {
	public function index() {
		$this->load->language('checkout/cart');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('common/home'),
			'text' => $this->language->get('text_home')
		);

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('checkout/cart'),
			'text' => $this->language->get('heading_title')
		);

		if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
			if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
				$data['error_warning'] = $this->language->get('error_stock');
			} elseif (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];

				unset($this->session->data['error']);
			} else {
				$data['error_warning'] = '';
			}

			if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
				$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
			} else {
				$data['attention'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			if (isset($this->session->data['abandoned_restored'])) {
				$data['abandoned_restored'] = true;

				unset($this->session->data['abandoned_restored']);
			} else {
				$data['abandoned_restored'] = false;
			}

			$data['action'] = $this->url->link('checkout/cart/edit', '', true);

			if ($this->config->get('config_cart_weight')) {
				$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
			} else {
				$data['weight'] = '';
			}

			$this->load->model('tool/image');
			$this->load->model('tool/upload');

			$products = $this->cart->getProducts();

			$data['products'] = array();

			foreach ($products as $product) {
				$product_total = 0;

				foreach ($products as $product_2) {
					if ($product_2['product_id'] == $product['product_id']) {
						$product_total += $product_2['quantity'];
					}
				}

				if ($product['minimum'] > $product_total) {
					$data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $this->formatQuantity($product['minimum']));
				}

				if ($product['image']) {
					$image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
				} else {
					$image = '';
				}

				$option_data = array();

				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

					// BXGY already applied to the cart line price by Cart::getProducts();
					// here we only surface the original price / discount text.
					$original_price = $price = $unit_price;
					$original_total = $total = $unit_price * $product['quantity'];
					$bxgy_original_price_fmt = !empty($product['bxgy_applied']) ? $product['bxgy_original_price'] : false;
					$bxgy_discount_text = !empty($product['bxgy_applied']) ? $product['bxgy_text'] : '';

					$price = $this->currency->format($price, $this->session->data['currency']);
					$total = $this->currency->format($total, $this->session->data['currency']);
				} else {
					$price = false;
					$total = false;
					$bxgy_original_price_fmt = false;
					$bxgy_discount_text = '';
				}

				$product_data = array(
					'cart_id'   => $product['cart_id'],
					'thumb'     => $image,
					'name'      => $product['name'],
					'model'     => $product['model'],
					'option'    => $option_data,
					'quantity'  => $this->formatQuantity($product['quantity']),
					'minimum'   => $this->formatQuantity($product['minimum']),
					'quantity_step' => $this->formatQuantity(isset($product['quantity_step']) ? $product['quantity_step'] : 1),
					'stock'     => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
					'preorder'  => !empty($product['preorder']),
					'reward'    => ($product['reward'] ? sprintf($this->language->get('text_points'), $product['reward']) : ''),
					'price'     => $price,
					'total'     => $total,
					'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
				);

				if ($bxgy_original_price_fmt) {
					$product_data['bxgy_original_price'] = $bxgy_original_price_fmt;
					$product_data['bxgy_discount_text'] = $bxgy_discount_text;
				}

				$data['products'][] = $product_data;
			}

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $key => $voucher) {
					$data['vouchers'][] = array(
						'key'         => $key,
						'description' => $voucher['description'],
						'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency']),
						'remove'      => $this->url->link('checkout/cart', 'remove=' . $key)
					);
				}
			}

			// Product Gifts
			$this->load->model('catalog/product');
			$this->load->language('product/product');

			$data['gifts'] = array();
			$cart_products = $this->cart->getProducts();

			$gifts_map = $this->model_catalog_product->getProductGiftsByIds(array_column($cart_products, 'product_id'));
			$gifts_map = $this->model_catalog_product->hydrateGiftVariants($gifts_map ? array_merge(...array_values($gifts_map)) : array());
			$gifts_by_pid = array();

			foreach ($gifts_map as $gift) {
				$gifts_by_pid[(int)$gift['product_id']][] = $gift;
			}

			foreach ($cart_products as $product) {
				$gifts = isset($gifts_by_pid[(int)$product['product_id']]) ? $gifts_by_pid[(int)$product['product_id']] : array();

				foreach ($gifts as $gift) {
					if ($product['quantity'] >= (int)$gift['minimum_quantity']) {
						if ($gift['image']) {
							$gift_image = $this->model_tool_image->resize($gift['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
						} else {
							$gift_image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
						}

						$data['gifts'][] = array(
							'name'  => $gift['name'],
							'image' => $gift_image,
							'price' => $this->currency->format($this->tax->calculate($gift['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
							'href'  => $this->url->link('product/product', 'product_id=' . $gift['gift_product_id'])
						);
					}
				}
			}

			$data['text_gift'] = $this->language->get('text_gift');
			$data['text_free'] = $this->language->get('text_free');

			// Reward points block strings (defined in checkout language file)
			$this->load->language('checkout/dockercart_checkout');

			$data['text_reward_points'] = $this->language->get('text_reward_points');
			$data['button_apply_reward'] = $this->language->get('button_apply_reward');
			$data['text_reward_applied'] = $this->language->get('text_reward_applied');
			$data['button_remove'] = $this->language->get('button_remove');

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array.
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);

						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$data['totals'] = array();

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $this->session->data['currency'])
				);
			}

			$data['continue'] = '/';

			// Total reward points the customer will earn for this order
			$total_reward = 0;

			foreach ($products as $product) {
				$total_reward += (int)$product['reward'];
			}

			$data['total_reward'] = $total_reward;

			// Reward points the customer can spend on this order
			if ($this->customer->isLogged()) {
				$reward_max = $this->customer->getRewardPoints();
				$points_total = 0;

				foreach ($products as $product) {
					$points_total += (int)$product['points'];
				}

				if ($points_total > 0 && $reward_max > $points_total) {
					$reward_max = $points_total;
				}

				$data['reward_max'] = $reward_max;
				$data['reward_applied'] = isset($this->session->data['reward']) ? (int)$this->session->data['reward'] : 0;
			} else {
				$data['reward_max'] = 0;
				$data['reward_applied'] = 0;
			}

			$data['checkout'] = $this->url->link('checkout/checkout', '', true);

			$this->load->model('setting/extension');

			$data['modules'] = array();

			$files = glob(DIR_APPLICATION . '/controller/extension/total/*.php');

			if ($files) {
				foreach ($files as $file) {
					$result = $this->load->controller('extension/total/' . basename($file, '.php'));

					if ($result) {
						$data['modules'][] = $result;
					}
				}
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('checkout/cart', $data));
		} else {
			$data['text_error'] = $this->language->get('text_empty');

			$data['continue'] = '/';

			unset($this->session->data['success']);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	private function normalizeQuantity($value, $default = 1.0) {
		$normalized = str_replace(',', '.', trim((string)$value));

		if (!is_numeric($normalized)) {
			return (float)$default;
		}

		return round((float)$normalized, 2);
	}

	private function getMinimumQuantity($product_info) {
		$minimum = isset($product_info['minimum']) ? (float)$product_info['minimum'] : 1.0;

		if ($minimum <= 0) {
			$minimum = 1.0;
		}

		return round($minimum, 2);
	}

	private function getQuantityStep($product_info) {
		$step = isset($product_info['quantity_step']) ? (float)$product_info['quantity_step'] : 1.0;

		if ($step <= 0) {
			$step = 1.0;
		}

		return round($step, 2);
	}

	private function isQuantityByStep($quantity, $step) {
		$quantity_cents = (int)round((float)$quantity * 100);
		$step_cents = (int)round((float)$step * 100);

		if ($step_cents <= 0) {
			return false;
		}

		return ($quantity_cents % $step_cents) === 0;
	}

	private function formatQuantity($quantity) {
		$formatted = number_format((float)$quantity, 2, '.', '');

		return rtrim(rtrim($formatted, '0'), '.');
	}

	private function validateRequestedQuantity($product_info, $quantity, &$json, $error_key = 'quantity') {
		$minimum = $this->getMinimumQuantity($product_info);
		$step = $this->getQuantityStep($product_info);

		if ($quantity < $minimum || !$this->isQuantityByStep($quantity, $step)) {
			$json['error'][$error_key] = sprintf(
				$this->language->get('error_quantity_step'),
				$product_info['name'],
				$this->formatQuantity($minimum),
				$this->formatQuantity($step)
			);

			return false;
		}

		return true;
	}

	public function add() {
		$this->load->language('checkout/cart');

		$json = array();

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			$cfp_by_theme = $this->config->get('dockercart_theme_call_for_price_status') && ((float)$product_info['price'] <= 0);

			if (!empty($product_info['call_for_price']) || $cfp_by_theme) {
				$json['error']['call_for_price'] = $this->language->get('error_call_for_price');
			}

			if ((float)$product_info['quantity'] <= 0 && empty($product_info['preorder']) && !$this->config->get('config_stock_checkout') && empty($product_info['is_configurable'])) {
				$json['error']['stock'] = $this->language->get('error_stock');
			}

			$default_quantity = $this->getMinimumQuantity($product_info);

			if (isset($this->request->post['quantity'])) {
				$quantity = $this->normalizeQuantity($this->request->post['quantity'], $default_quantity);
			} else {
				$quantity = $default_quantity;
			}

			if (isset($this->request->post['option'])) {
				$option = array_filter($this->request->post['option']);
			} else {
				$option = array();
			}

			$product_options = $this->model_catalog_product->getProductOptions($this->request->post['product_id']);

			foreach ($product_options as $product_option) {
				// Configurable-product axes are required only until a valid
				// variant_id is supplied — a variant already pins the exact
				// option combination, so the axis values are implied.
				$is_axis = !empty($product_option['is_configurable_axis']);

				if ($is_axis && !empty($option['variant_id'])) {
					continue;
				}

				if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
					$json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
				}
			}

			if (!empty($product_info['is_configurable'])) {
				if (empty($option['variant_id'])) {
					$json['error']['variant'] = $this->language->get('error_variant_required');
				} else {
					$pc = new ProductConfigurable($this->registry);
					$variant_info = $pc->getVariant((int)$option['variant_id']);

					if (!$variant_info || empty($variant_info['status']) || (int)$variant_info['product_id'] !== (int)$product_id) {
						$json['error']['variant'] = $this->language->get('error_variant_invalid');
					} elseif ((float)$variant_info['quantity'] <= 0 && empty($product_info['preorder']) && !$this->config->get('config_stock_checkout')) {
						$json['error']['stock'] = $this->language->get('error_stock');
					}
				}
			}

			if (!$json && $this->validateRequestedQuantity($product_info, $quantity, $json)) {
				$this->cart->add($this->request->post['product_id'], $quantity, $option);

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']), $product_info['name'], $this->url->link('checkout/cart'));

				// Unset all shipping and payment methods
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);

				// Totals
				$this->load->model('setting/extension');

				$totals = array();
				$taxes = $this->cart->getTaxes();
				$total = 0;

				// Because __call can not keep var references so we put them into an array.
				$total_data = array(
					'totals' => &$totals,
					'taxes'  => &$taxes,
					'total'  => &$total
				);

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$sort_order = array();

					$results = $this->model_setting_extension->getExtensions('total');

					foreach ($results as $key => $value) {
						$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
					}

					array_multisort($sort_order, SORT_ASC, $results);

					foreach ($results as $result) {
						if ($this->config->get('total_' . $result['code'] . '_status')) {
							$this->load->model('extension/total/' . $result['code']);

							// We have to put the totals in an array so that they pass by reference.
							$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
						}
					}

					$sort_order = array();

					foreach ($totals as $key => $value) {
						$sort_order[$key] = $value['sort_order'];
					}

					array_multisort($sort_order, SORT_ASC, $totals);
				}

				$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
			} else {
				if (isset($json['error']['option'])) {
					$json['redirect'] = str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']));
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addBundle() {
		$this->load->language('checkout/cart');

		$json = array();

		if (isset($this->request->post['bundle_id'])) {
			$bundle_id = (int)$this->request->post['bundle_id'];
		} else {
			$bundle_id = 0;
		}

		if ($bundle_id) {
			$bundle_lib = new ProductBundle($this->registry);
			$bundle_products = $bundle_lib->getBundleProducts($bundle_id);

			if (count($bundle_products) >= 2) {
				$this->load->model('catalog/product');

				$bundle_products_info = $this->model_catalog_product->getProductsByIds(array_map('intval', array_column($bundle_products, 'product_id')));

				foreach ($bundle_products as $bp) {
					$product_info = isset($bundle_products_info[(int)$bp['product_id']]) ? $bundle_products_info[(int)$bp['product_id']] : false;

					if ($product_info) {
						if (!empty($product_info['call_for_price'])) {
							$json['error'] = $this->language->get('error_call_for_price');
							break;
						}

						if ((float)$product_info['quantity'] <= 0 && empty($product_info['preorder']) && !$this->config->get('config_stock_checkout')) {
							$json['error'] = $this->language->get('error_stock');
							break;
						}

						$default_quantity = $this->getMinimumQuantity($product_info);
						$this->cart->add($bp['product_id'], $default_quantity, array(), 0);
					}
				}

				if (!isset($json['error'])) {
						$json['success'] = sprintf($this->language->get('text_success_bundle'), $this->url->link('checkout/cart'));

						unset($this->session->data['shipping_method']);
						unset($this->session->data['shipping_methods']);
						unset($this->session->data['payment_method']);
						unset($this->session->data['payment_methods']);

						$this->load->model('setting/extension');

						$totals = array();
						$taxes = $this->cart->getTaxes();
						$total = 0;

						$total_data = array(
							'totals' => &$totals,
							'taxes'  => &$taxes,
							'total'  => &$total
						);

						if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
							$sort_order = array();

							$results = $this->model_setting_extension->getExtensions('total');

							foreach ($results as $key => $value) {
								$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
							}

							array_multisort($sort_order, SORT_ASC, $results);

							foreach ($results as $result) {
								if ($this->config->get('total_' . $result['code'] . '_status')) {
									$this->load->model('extension/total/' . $result['code']);

									$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
								}
							}

							$sort_order = array();

							foreach ($totals as $key => $value) {
								$sort_order[$key] = $value['sort_order'];
							}

							array_multisort($sort_order, SORT_ASC, $totals);
						}

					$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
				}
			} else {
				$json['error'] = $this->language->get('error_bundle_invalid');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function edit() {
		$this->load->language('checkout/cart');

		$json = array();

		$is_ajax = (isset($this->request->server['HTTP_X_REQUESTED_WITH'])
			&& strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

		// Support single-key AJAX call: key=<cart_id>&quantity=<qty>
		if (isset($this->request->post['key']) && isset($this->request->post['quantity'])) {
			$quantities = array((int)$this->request->post['key'] => $this->request->post['quantity']);
		} elseif (!empty($this->request->post['quantity']) && is_array($this->request->post['quantity'])) {
			// quantity[cart_id]=qty (array form from AJAX or full-page form)
			$quantities = $this->request->post['quantity'];
		} else {
			$quantities = array();
		}

		$cart_products = array();

		foreach ($this->cart->getProducts() as $cart_product) {
			$cart_products[$cart_product['cart_id']] = $cart_product;
		}

		if ($quantities) {
			$normalized_quantities = array();

			foreach ($quantities as $key => $value) {
				$cart_id = (int)$key;
				$quantity = $this->normalizeQuantity($value, 0);

				if ($quantity <= 0) {
					$normalized_quantities[$cart_id] = 0;
					continue;
				}

				if (isset($cart_products[$cart_id])) {
					$this->validateRequestedQuantity($cart_products[$cart_id], $quantity, $json, $cart_id);
				}

				$normalized_quantities[$cart_id] = $quantity;
			}

			if (isset($json['error'])) {
				if (!$is_ajax) {
					$this->session->data['error'] = reset($json['error']);
					$this->response->redirect($this->url->link('checkout/cart'));
					return;
				}

				$this->response->addHeader('Content-Type: application/json');
				$this->response->setOutput(json_encode($json));
				return;
			}

			foreach ($normalized_quantities as $cart_id => $quantity) {
				$this->cart->update($cart_id, $quantity);
			}

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			if (!$is_ajax) {
				// Full-page form submit — redirect back as before
				$this->session->data['success'] = $this->language->get('text_remove');
				$this->response->redirect($this->url->link('checkout/cart'));
				return;
			}

			$json['success'] = true;

			// Calculate and return totals for AJAX
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array.
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);

						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$json['totals'] = array();

			foreach ($totals as $total) {
				$json['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $this->session->data['currency'])
				);
			}

			$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
		} else {
			$json['error'] = 'No quantity data provided';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('checkout/cart');

		$json = array();

		// Remove
		if (isset($this->request->post['key'])) {
			$this->cart->remove($this->request->post['key']);

			unset($this->session->data['vouchers'][$this->request->post['key']]);

			$json['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array.
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);

						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$json['totals'] = array();

			foreach ($totals as $total) {
				$json['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $this->session->data['currency'])
				);
			}

			$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
