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

		$products_info = $this->model_catalog_product->getProductsByIds(array_map('intval', $product_ids));

		// Товары не в наличии — в конец списка
		$in_stock = array();
		$out_of_stock = array();

		foreach ($product_ids as $product_id) {
			$product_info = isset($products_info[(int)$product_id]) ? $products_info[(int)$product_id] : array();

			if (ProductStockSorter::isOutOfStock($product_info)) {
				$out_of_stock[] = $product_id;
			} else {
				$in_stock[] = $product_id;
			}
		}

		$product_ids = array_merge($in_stock, $out_of_stock);

		foreach ($product_ids as $product_id) {
			$product_info = isset($products_info[(int)$product_id]) ? $products_info[(int)$product_id] : false;

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
