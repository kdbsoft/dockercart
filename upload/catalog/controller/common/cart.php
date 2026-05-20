<?php
class ControllerCommonCart extends Controller {
	public function index() {
		$this->load->language('common/cart');

		// 1-Click Checkout module data
		$data['oneclickcheckout_enabled'] = false;
		$data['oneclickcheckout_modal'] = '';
		$data['button_oneclickcheckout_cart'] = '';
		$data['dockercart_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : '';

		if ($this->config->get('module_dockercart_oneclickcheckout_status')) {
			$this->load->language('extension/module/dockercart_oneclickcheckout');
			$data['button_oneclickcheckout_cart'] = $this->language->get('button_oneclickcheckout_cart');

			$modal_html = $this->load->controller('extension/module/dockercart_oneclickcheckout/getModalHtml');
			if ($modal_html) {
				$data['oneclickcheckout_modal'] = $modal_html;
				$data['oneclickcheckout_enabled'] = true;
			}
		}

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

		foreach ($this->cart->getProducts() as $product) {
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
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

					if ($upload_info) {
						$value = $upload_info['name'];
					} else {
						$value = '';
					}
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

				$price = $this->currency->format($unit_price, $this->session->data['currency']);
				$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
			} else {
				$price = false;
				$total = false;
			}

			$data['products'][] = array(
				'cart_id'   => $product['cart_id'],
				'thumb'     => $image,
				'name'      => $product['name'],
				'model'     => $product['model'],
				'option'    => $option_data,
				'recurring' => ($product['recurring'] ? $product['recurring']['name'] : ''),
				'quantity'  => $this->formatQuantityValue($product['quantity']),
				'minimum'   => $this->formatQuantityValue($product['minimum']),
				'quantity_step' => $this->formatQuantityValue(isset($product['quantity_step']) ? $product['quantity_step'] : 1),
				'price'     => $price,
				'total'     => $total,
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

		foreach ($this->cart->getProducts() as $product) {
			$gifts = $this->model_catalog_product->getProductGifts($product['product_id']);

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
