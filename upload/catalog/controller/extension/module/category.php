<?php
class ControllerExtensionModuleCategory extends Controller {
	public function index() {
		$this->load->language('extension/module/category');

		if (isset($this->request->get['path'])) {
			$parts = explode('_', (string)$this->request->get['path']);
		} else {
			$parts = array();
		}

		if (isset($parts[0])) {
			$data['category_id'] = $parts[0];
		} else {
			$data['category_id'] = 0;
		}

		if (isset($parts[1])) {
			$data['child_id'] = $parts[1];
		} else {
			$data['child_id'] = 0;
		}

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['categories'] = array();
		$data['all_categories_href'] = $this->url->link('product/categories');
		$cache_ttl = 1800;
		$cache_prefix = 'category.module.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_product_count');

		// Text strings
		$data['text_browse_by'] = $this->language->get('text_browse_by');
		$data['text_popular_categories'] = $this->language->get('text_popular_categories');
		$data['text_view_all'] = $this->language->get('text_view_all');

		$categories = $this->cache->get($cache_prefix . '.top');

		if (!is_array($categories)) {
			$categories = array();
			$top_categories = $this->model_catalog_category->getCategories(0);

			foreach ($top_categories as $category) {
				$filter_data = array(
					'filter_category_id'  => (int)$category['category_id'],
					'filter_sub_category' => true
				);

				$product_total = (int)$this->model_catalog_product->getTotalProducts($filter_data);

				if (!empty($category['image'])) {
					$image = $this->model_tool_image->resize($category['image'], 480, 640);
				} else {
					$first_product_image = $this->model_catalog_category->getFirstProductImageByCategoryId((int)$category['category_id']);
					if (!empty($first_product_image)) {
						$image = $this->model_tool_image->resize($first_product_image, 480, 640);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', 480, 640);
					}
				}

				$categories[] = array(
					'category_id' => (int)$category['category_id'],
					'name' => $category['name'] . ($this->config->get('config_product_count') ? ' (' . $product_total . ')' : ''),
					'name_raw' => $category['name'],
					'image' => $image,
					'product_total' => $product_total,
					'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
				);
			}

			$this->cache->set($cache_prefix . '.top', $categories, $cache_ttl);
		}

		$active_children = array();

		if ((int)$data['category_id'] > 0) {
			$children_cache_key = $cache_prefix . '.children.' . (int)$data['category_id'];
			$active_children = $this->cache->get($children_cache_key);

			if (!is_array($active_children)) {
				$active_children = array();
				$children = $this->model_catalog_category->getCategories((int)$data['category_id']);

				foreach ($children as $child) {
					$filter_data = array(
						'filter_category_id' => (int)$child['category_id'],
						'filter_sub_category' => true
					);

					$active_children[] = array(
						'category_id' => (int)$child['category_id'],
						'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . (int)$this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
						'href' => $this->url->link('product/category', 'path=' . (int)$data['category_id'] . '_' . (int)$child['category_id'])
					);
				}

				$this->cache->set($children_cache_key, $active_children, $cache_ttl);
			}
		}

		foreach ($categories as $category) {
			$category['children'] = ((int)$category['category_id'] === (int)$data['category_id']) ? $active_children : array();
			$data['categories'][] = $category;
		}

		return $this->load->view('extension/module/category', $data);
	}
}