<?php
class ControllerAccountWishList extends Controller {
	private function parseWishlistKey($key) {
		$parts = explode(':', (string)$key);

		return array(
			'product_id' => (int)($parts[0] ?? 0),
			'variant_id' => isset($parts[1]) ? (int)$parts[1] : 0
		);
	}

	private function buildWishlistKey($product_id, $variant_id) {
		$variant_id = (int)$variant_id;

		return $variant_id > 0 ? (int)$product_id . ':' . $variant_id : (string)(int)$product_id;
	}

	private function getWishlistRows() {
		$rows = array();

		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			foreach ($this->model_account_wishlist->getWishlist() as $row) {
				$rows[] = array(
					'product_id' => (int)$row['product_id'],
					'variant_id' => (int)$row['variant_id']
				);
			}
		} elseif (isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])) {
			foreach ($this->session->data['wishlist'] as $key) {
				$rows[] = $this->parseWishlistKey($key);
			}
		}

		return $rows;
	}

	private function removeWishlistRow($product_id, $variant_id) {
		$variant_id = (int)$variant_id;

		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			$this->model_account_wishlist->deleteWishlist($product_id, $variant_id);
		} elseif (isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])) {
			$key = $this->buildWishlistKey($product_id, $variant_id);

			foreach ($this->session->data['wishlist'] as $i => $wishlist_key) {
				if ((string)$wishlist_key === $key) {
					unset($this->session->data['wishlist'][$i]);
				}
			}

			$this->session->data['wishlist'] = array_values($this->session->data['wishlist']);
		}
	}

	private function isValidVariant($product_id, $variant_id, $product_info) {
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

	public function index() {
		$this->load->language('account/wishlist');
		$this->load->language('product/product');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->post['remove']) && $this->validateCsrf()) {
			$remove = $this->parseWishlistKey($this->request->post['remove']);

			if ($remove['product_id']) {
				$this->removeWishlistRow($remove['product_id'], $remove['variant_id']);

				$this->session->data['success'] = $this->language->get('text_remove');
			}

			$this->response->redirect($this->url->link('account/wishlist'));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if ($this->customer->isLogged()) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', '', true)
			);
		}

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/wishlist')
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['products'] = array();

		$results = $this->getWishlistRows();

		$products_info = $this->model_catalog_product->getProductsByIds(array_map('intval', array_column($results, 'product_id')));

		// Товары не в наличии — в конец списка
		$in_stock = array();
		$out_of_stock = array();

		foreach ($results as $result) {
			$product_info = isset($products_info[(int)$result['product_id']]) ? $products_info[(int)$result['product_id']] : array();

			if (ProductStockSorter::isOutOfStock($product_info)) {
				$out_of_stock[] = $result;
			} else {
				$in_stock[] = $result;
			}
		}

		$results = array_merge($in_stock, $out_of_stock);

		foreach ($results as $result) {
			$product_info = isset($products_info[(int)$result['product_id']]) ? $products_info[(int)$result['product_id']] : false;

			if ($product_info) {
				// Resolve the concrete variant for this wishlist row
				$variant = array();
				$variant_id = (int)$result['variant_id'];

				if ($variant_id > 0) {
					foreach (($product_info['variants'] ?? array()) as $v) {
						if ((int)$v['variant_id'] === $variant_id) {
							$variant = $v;
							break;
						}
					}
				}

				// A stored variant that no longer exists is a ghost row — purge it
				if ($variant_id > 0 && empty($variant)) {
					$this->removeWishlistRow((int)$result['product_id'], $variant_id);
					continue;
				}

				$image = $product_info['image'];
				if (!empty($variant['image'])) {
					$image = $variant['image'];
				}

				if ($image) {
					$image = $this->model_tool_image->resize($image, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
				} else {
					$image = false;
				}

				$quantity = (float)$product_info['quantity'];
				$preorder = !empty($product_info['preorder']);
				if (!empty($variant['quantity'])) {
					$quantity = (float)$variant['quantity'];
				}

				if ($quantity <= 0) {
					$stock = $preorder
						? $this->language->get('text_preorder')
						: $this->language->get('text_out_of_stock');
				} elseif ($this->config->get('config_stock_display')) {
					$stock = $quantity;
				} else {
					$stock = $this->language->get('text_instock');
				}

				// Specific variant price/special via the shared calculator
				$price_raw = (float)$product_info['price'];
				$special_raw = isset($product_info['special']) && $product_info['special'] !== null ? (float)$product_info['special'] : 0;

				if (!empty($variant['price']) && (float)$variant['price'] > 0) {
					$calculator = new ProductPricingCalculator($this->registry);
					$variant_pricing = $calculator->calculate((int)$product_info['product_id'], $variant_id, 1);
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

				// Correct stock flag: variant-specific, falls back to the product rule
				if (!empty($variant['quantity'])) {
					$is_in_stock = (float)$variant['quantity'] > 0 || $preorder;
				} else {
					$is_in_stock = !ProductStockSorter::isOutOfStock($product_info);
				}

				$product_url = 'product_id=' . (int)$product_info['product_id'];

				if ($variant_id > 0) {
					$product_url .= '&variant_id=' . $variant_id;
				}

				// Discount percent (same formula as listing pages)
				$discount_percent = 0;

				if ($special_raw > 0 && $price_raw > 0) {
					$discount_percent = (int)round((1 - ($special_raw / $price_raw)) * 100);

					if ($discount_percent < 0) {
						$discount_percent = 0;
					}
				}

				// Short description for the card (same truncation as listings)
				$description = '';

				if (!empty($product_info['description'])) {
					$description = utf8_substr(trim(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length'));
				}

				$minimum = !empty($variant['minimum']) ? (float)$variant['minimum'] : (float)$product_info['minimum'];

				if ($minimum <= 0) {
					$minimum = 1;
				}

				$data['products'][] = array(
					'product_id'         => (int)$product_info['product_id'],
					'variant_id'         => $variant_id,
					'variant_name'       => $variant_name,
					'thumb'              => $image,
					'name'               => $product_info['name'],
					'model'              => !empty($variant['model']) ? $variant['model'] : $product_info['model'],
					'description'        => $description,
					'stock'              => $stock,
					'is_in_stock'        => $is_in_stock,
					'is_preorder'        => $quantity <= 0 && $preorder,
					'price'              => $price,
					'special'            => $special,
					'discount'           => $discount_percent,
					'minimum'            => $minimum,
					'quantity_step'      => (float)($variant['quantity_step'] ?? $product_info['quantity_step']),
					'rating'             => (float)$product_info['rating'],
					'reviews'            => (int)$product_info['reviews'],
					'rating_distribution' => isset($product_info['rating_distribution']) ? $product_info['rating_distribution'] : array(),
					'reviews_url'        => $this->url->link('product/reviews', 'product_id=' . (int)$product_info['product_id']),
					'has_gift'           => !empty($product_info['has_gift']),
					'is_configurable'    => !empty($product_info['is_configurable']),
					'variant_swatches'   => isset($product_info['variant_swatches']) ? $product_info['variant_swatches'] : array(),
					'href'               => $this->url->link('product/product', $product_url),
					'remove'             => $this->url->link('account/wishlist', ''),
					'remove_key'         => $this->buildWishlistKey((int)$product_info['product_id'], $variant_id)
				);
			} else {
				$this->removeWishlistRow((int)$result['product_id'], (int)$result['variant_id']);
			}
		}

		if ($this->customer->isLogged()) {
			$data['continue'] = $this->url->link('account/account', '', true);
		} else {
			$data['continue'] = $this->url->link('common/home');
		}

		$data['button_cart'] = $this->language->get('button_cart');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['text_remove_wishlist'] = $this->language->get('text_remove_wishlist');
		$data['csrf_token'] = $this->csrfToken();
		$data['text_quick_view'] = $this->language->get('text_quick_view');
		$data['text_sale'] = $this->language->get('text_sale');
		$data['text_reviews'] = $this->language->get('text_reviews_word');

		$data['account_menu'] = $this->load->controller('common/account_menu');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/wishlist', $data));
	}

	public function add() {
		$this->load->language('account/wishlist');

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

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info && $this->isValidVariant($product_id, $variant_id, $product_info)) {
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');

				$this->model_account_wishlist->addWishlist($product_id, $variant_id);

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_id), $product_info['name'], $this->url->link('account/wishlist'));

				$json['total'] = (int)$this->model_account_wishlist->getTotalWishlist();
			} else {
				if (!isset($this->session->data['wishlist'])) {
					$this->session->data['wishlist'] = array();
				}

				$key = $this->buildWishlistKey($product_id, $variant_id);

				if (!in_array($key, $this->session->data['wishlist'])) {
					$this->session->data['wishlist'][] = $key;
				}

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_id), $product_info['name'], $this->url->link('account/wishlist'));

				$json['total'] = count($this->session->data['wishlist']);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('account/wishlist');

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

		if ($product_id) {
			$this->removeWishlistRow($product_id, $variant_id);

			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
				$json['total'] = (int)$this->model_account_wishlist->getTotalWishlist();
			} else {
				$json['total'] = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
			}

			$json['removed'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
