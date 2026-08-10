<?php
class ControllerCommonCart extends Controller {
	public function index() {
		$this->load->language('common/cart');

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

		$data['text_items'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));

		// Drawer title
		$data['text_cart_drawer'] = $this->language->get('text_cart_drawer');

		$this->load->model('tool/image');
		$this->load->model('tool/upload');

		$data['products'] = array();

		$cart_products = $this->cart->getProducts();

		// BXGY per-item discounts (mirror checkout/cart so the drawer shows
		// the same discounted prices as the cart page).
		$bxgy_lib = new Bxgy($this->registry);
		$bxgy_discounts = $bxgy_lib->getPerProductDiscounts($cart_products);

		// Bulk lookup for file-option upload names (one query instead of per option)
		$upload_codes = array();

		foreach ($cart_products as $product) {
			foreach ($product['option'] as $option) {
				if ($option['type'] == 'file') {
					$upload_codes[$option['value']] = true;
				}
			}
		}

		$upload_names = array();

		if ($upload_codes) {
			$upload_names = $this->model_tool_upload->getUploadNamesByCodes(array_keys($upload_codes));
		}

		foreach ($cart_products as $product) {
			if ($product['image']) {
				$image = $this->model_tool_image->resize($product['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
			}

			$option_data = array();

			foreach ($product['option'] as $option) {
				if ($option['type'] != 'file') {
					$value = $option['value'];
				} else {
					$value = isset($upload_names[$option['value']]) ? $upload_names[$option['value']] : '';
				}

				$option_data[] = array(
					'name'  => $option['name'],
					'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value),
					'type'  => $option['type']
				);
			}

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));

				$bxgy_key = (int) $product['product_id'] . ':' . (int) ($product['variant_id'] ?? 0);
				$bxgy_original_price_fmt = false;
				$bxgy_discount_text = '';

				if (isset($bxgy_discounts[$bxgy_key])) {
					$per_unit_discount = $bxgy_discounts[$bxgy_key]['per_unit'];
					$bxgy_original_price_fmt = $bxgy_discounts[$bxgy_key]['original_price_formatted'];
					$bxgy_discount_text = $bxgy_discounts[$bxgy_key]['text'];
					$line_discount = $per_unit_discount * min((int)$bxgy_discounts[$bxgy_key]['units'], (int)$product['quantity']);
					$discounted_total = max(0, (float)$product['price'] * (int)$product['quantity'] - $line_discount);
					$discounted = (int)$product['quantity'] > 0 ? $discounted_total / (int)$product['quantity'] : 0;
					$unit_price = $this->tax->calculate($discounted, $product['tax_class_id'], $this->config->get('config_tax'));
				}

				$price = $this->currency->format($unit_price, $this->session->data['currency']);
				$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
			} else {
				$price = false;
				$total = false;
				$bxgy_original_price_fmt = false;
				$bxgy_discount_text = '';
			}

			$data['products'][] = array(
				'cart_id'   => $product['cart_id'],
				'thumb'     => $image,
				'name'      => $product['name'],
				'model'     => $product['model'],
				'option'    => $option_data,
				'quantity'  => $this->formatQuantityValue($product['quantity']),
				'minimum'   => $this->formatQuantityValue($product['minimum']),
				'quantity_step' => $this->formatQuantityValue(isset($product['quantity_step']) ? $product['quantity_step'] : 1),
				'price'     => $price,
				'total'     => $total,
				'bxgy_original_price' => $bxgy_original_price_fmt,
				'bxgy_discount_text'  => $bxgy_discount_text,
				'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			);
		}

		// Gift Voucher
		$data['vouchers'] = array();

		if (!empty($this->session->data['vouchers'])) {
			foreach ($this->session->data['vouchers'] as $key => $voucher) {
				$data['vouchers'][] = array(
					'key'         => $key,
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
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

		$data['totals'] = array();

		foreach ($totals as $total) {
			$data['totals'][] = array(
				'title' => $total['title'],
				'text'  => $this->currency->format($total['value'], $this->session->data['currency']),
			);
		}

		$data['cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);

		return $this->load->view('common/cart', $data);
	}

	private function formatQuantityValue($value) {
		$formatted = number_format((float)$value, 2, '.', '');

		return rtrim(rtrim($formatted, '0'), '.');
	}

	public function info() {
		$this->response->setOutput($this->index());
	}
}
