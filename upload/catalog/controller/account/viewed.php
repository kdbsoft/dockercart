<?php
class ControllerAccountViewed extends Controller {
	public function index() {
		$this->load->language('account/viewed');

		$this->load->model('account/viewed');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

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
			'href' => $this->url->link('account/viewed', '', true)
		);

		$data['products'] = array();

		$product_ids = $this->model_account_viewed->getViewedProductIds();

		foreach ($product_ids as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if (!$product_info) {
				continue;
			}

			if ($product_info['image']) {
				$image = $this->model_tool_image->resize(
					$product_info['image'],
					$this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'),
					$this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height')
				);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 100, 100);
			}

			if ($product_info['quantity'] <= 0) {
				$stock = $product_info['stock_status'];
			} elseif ($this->config->get('config_stock_display')) {
				$stock = $product_info['quantity'];
			} else {
				$stock = $this->language->get('text_instock');
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format(
					$this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			} else {
				$price = false;
			}

			if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
				$special = $this->currency->format(
					$this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			} else {
				$special = false;
			}

			$data['products'][] = array(
				'product_id' => (int)$product_info['product_id'],
				'thumb'      => $image,
				'name'       => $product_info['name'],
				'model'      => $product_info['model'],
				'stock'      => $stock,
				'price'      => $price,
				'special'    => $special,
				'minimum'    => ($product_info['minimum'] > 0 ? (float)$product_info['minimum'] : 1),
				'href'       => $this->url->link('product/product', 'product_id=' . (int)$product_info['product_id'])
			);
		}

		$data['continue'] = $this->customer->isLogged() ? $this->url->link('account/account', '', true) : $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/viewed', $data));
	}
}
