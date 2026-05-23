<?php
class ControllerExtensionModuleDockercartCategoryTree extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/dockercart_category_tree');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['categories_tree'] = array();

		$cache_key = 'category_tree.'
			. (int)$this->config->get('config_store_id') . '.'
			. (int)$this->config->get('config_language_id') . '.'
			. md5(serialize($setting));

		$data['categories_tree'] = $this->cache->get($cache_key . '.tree');

		if (!is_array($data['categories_tree'])) {
			$max_depth = !empty($setting['max_depth']) ? (int)$setting['max_depth'] : 0;
			$show_count = !empty($setting['show_product_count']);

			$data['categories_tree'] = $this->buildTree(0, '', 0, $max_depth, $show_count);

			$this->cache->set($cache_key . '.tree', $data['categories_tree'], 1800);
		}

		if (!$data['categories_tree']) {
			return '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_subtitle'] = $this->language->get('text_subtitle');
		$data['text_items'] = $this->language->get('text_items');
		$data['text_view_all'] = $this->language->get('text_view_all');
		$data['text_shop_all'] = $this->language->get('text_shop_all');
		$data['all_categories_href'] = $this->url->link('product/categories');

		return $this->load->view('extension/module/dockercart_category_tree', $data);
	}

	private function buildTree($parent_id, $path_prefix, $depth, $max_depth, $show_count) {
		$tree = array();

		$categories = $this->model_catalog_category->getCategories((int)$parent_id);

		foreach ($categories as $category) {
			$cat_id = (int)$category['category_id'];
			$path = $path_prefix ? $path_prefix . '_' . $cat_id : (string)$cat_id;

			$total = 0;

			if ($show_count) {
				$filter_data = array(
					'filter_category_id'  => $cat_id,
					'filter_sub_category' => true
				);

				$total = (int)$this->model_catalog_product->getTotalProducts($filter_data);
			}

			$children = array();

			if ($max_depth === 0 || $depth + 1 < $max_depth) {
				$children = $this->buildTree($cat_id, $path, $depth + 1, $max_depth, $show_count);
			}

				$thumb = '';

			if (!empty($category['image'])) {
				$thumb = $this->model_tool_image->resize($category['image'], 400, 400);
			} else {
				$first_product_image = $this->model_catalog_category->getFirstProductImageByCategoryId($cat_id);

				if (!empty($first_product_image)) {
					$thumb = $this->model_tool_image->resize($first_product_image, 400, 400);
				}
			}

			$tree[] = array(
				'category_id' => $cat_id,
				'name'        => $category['name'],
				'thumb'       => $thumb,
				'hue'         => ($cat_id * 53 + 180) % 360,
				'href'        => $this->url->link('product/category', 'path=' . $path),
				'total'       => $total,
				'children'    => $children
			);
		}

		return $tree;
	}
}
