<?php
class ControllerCommonMenu extends Controller {
	public function index() {
		$this->load->language('common/menu');

		// Menu
		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$data['categories'] = array();
		$data['active_category_id'] = 0;
		$data['menu_type'] = (string)$this->config->get('dockercart_theme_menu_type');
		if ($data['menu_type'] !== 'vertical') {
			$data['menu_type'] = 'horizontal';
		}
		$data['text_catalog']      = $this->language->get('text_catalog');
		$data['text_all']          = $this->language->get('text_all');
		$data['text_new_arrivals'] = $this->language->get('text_new_arrivals');
		$data['text_sale']         = $this->language->get('text_sale');

		// Determine active category
		// Works for both product/category and product/product (when opened from category)
		if (!empty($this->request->get['path'])) {
			$path_parts = array_values(array_filter(array_map('intval', explode('_', (string)$this->request->get['path']))));

			if ($path_parts) {
				$data['active_category_id'] = (int)$path_parts[0];
			}
		}

		$cache_key = 'category.menu.tree.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_product_count');
		$menu_tree = $this->cache->get($cache_key);

		if (!is_array($menu_tree)) {
			$menu_tree = array();
			$categories = $this->model_catalog_category->getCategories(0);

			foreach ($categories as $category) {
				if ($category['top']) {
					$menu_tree[] = $this->buildMenuCategory($category, (string)(int)$category['category_id']);
				}
			}

			$this->cache->set($cache_key, $menu_tree, 1800);
		}

		foreach ($menu_tree as $built_category) {
			$built_category['is_active'] = ($data['active_category_id'] === (int)$built_category['category_id']);
			$data['categories'][] = $built_category;
		}

		return $this->load->view('common/menu', $data);
	}

	private function buildMenuCategory(array $category, $path) {
		$children_data = array();

		$children = $this->model_catalog_category->getCategories((int)$category['category_id']);

		foreach ($children as $child) {
			$grandchildren_data = array();

			$grandchildren = $this->model_catalog_category->getCategories((int)$child['category_id']);

			foreach ($grandchildren as $grandchild) {
				$grandchild_filter_data = array(
					'filter_category_id'  => (int)$grandchild['category_id'],
					'filter_sub_category' => true
				);

				$grandchildren_data[] = array(
					'name' => $grandchild['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($grandchild_filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'path=' . $path . '_' . (int)$child['category_id'] . '_' . (int)$grandchild['category_id'])
				);
			}

			$filter_data = array(
				'filter_category_id'  => (int)$child['category_id'],
				'filter_sub_category' => true
			);

			$children_data[] = array(
				'name'  => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
				'children' => $grandchildren_data,
				'href'  => $this->url->link('product/category', 'path=' . $path . '_' . (int)$child['category_id'])
			);
		}

		return array(
			'category_id' => (int)$category['category_id'],
			'name'     => $category['name'],
			'children' => $children_data,
			'column'   => !empty($category['column']) ? $category['column'] : 1,
			'href'     => $this->url->link('product/category', 'path=' . $path)
		);
	}
}
