<?php
class ControllerExtensionModuleDockercartViewed extends Controller {
	public function index() {
		if (!(int)$this->config->get('module_dockercart_viewed_status')) {
			return '';
		}

		$this->load->language('extension/module/dockercart_viewed');
		$this->load->model('account/viewed');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_view_all'] = $this->language->get('text_view_all');
		$data['viewed_link'] = $this->url->link('account/viewed', '', true);
		$data['products'] = array();

		$product_ids = $this->model_account_viewed->getViewedProductIds(10);

		foreach ($product_ids as $product_id) {
			$product_info = $this->model_catalog_product->getProduct((int)$product_id);

			if (!$product_info) {
				continue;
			}

			if ($product_info['image']) {
				$thumb = $this->model_tool_image->resize($product_info['image'], 60, 60);
			} else {
				$thumb = $this->model_tool_image->resize('placeholder.png', 60, 60);
			}

			$data['products'][] = array(
				'product_id' => (int)$product_info['product_id'],
				'name' => $product_info['name'],
				'thumb' => $thumb,
				'href' => $this->url->link('product/product', 'product_id=' . (int)$product_info['product_id'])
			);
		}

		if (!$data['products']) {
			return '';
		}

		return $this->load->view('extension/module/dockercart_viewed', $data);
	}
}
