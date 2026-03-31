<?php
class ControllerProductCategories extends Controller {
	public function index() {
		$this->load->language('product/categories');

		// Also load category module strings (shared labels)
		$this->load->language('extension/module/category');

		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('product/categories')
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['text_items'] = $this->language->get('text_items');
		$data['text_show'] = $this->language->get('text_show');
		$data['text_empty'] = $this->language->get('text_empty');

		// Shared label
		$data['text_browse_by'] = $this->language->get('text_browse_by');

		$cache_prefix = 'category.page.all.' . (int)$this->config->get('config_store_id') . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_product_count');

		$popular_categories = $this->cache->get($cache_prefix . '.popular');
		if (!is_array($popular_categories)) {
			$popular_categories = array();
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
					$first_product_image = $this->model_catalog_category->getFirstProductImageByCategoryId($category['category_id']);
					if (!empty($first_product_image)) {
						$image = $this->model_tool_image->resize($first_product_image, 480, 640);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', 480, 640);
					}
				}

				$popular_categories[] = array(
					'category_id' => (int)$category['category_id'],
					'name' => $category['name'],
					'image' => $image,
					'total' => $product_total,
					'href' => $this->url->link('product/category', 'path=' . (int)$category['category_id'])
				);
			}

			$this->cache->set($cache_prefix . '.popular', $popular_categories, 1800);
		}
		$data['popular_categories'] = $popular_categories;

		$categories_tree = $this->cache->get($cache_prefix . '.tree');
		if (!is_array($categories_tree)) {
			$categories_tree = $this->buildTree(0, '');
			$this->cache->set($cache_prefix . '.tree', $categories_tree, 1800);
		}
		$data['categories_tree'] = $categories_tree;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/categories', $data));
	}

	private function buildTree($parent_id = 0, $path = '') {
		$tree = array();
		$categories = $this->model_catalog_category->getCategories((int)$parent_id);

		foreach ($categories as $category) {
			$current_path = $path ? $path . '_' . (int)$category['category_id'] : (string)(int)$category['category_id'];

			$filter_data = array(
				'filter_category_id'  => (int)$category['category_id'],
				'filter_sub_category' => true
			);

			$tree[] = array(
				'category_id' => (int)$category['category_id'],
				'name' => $category['name'],
				'total' => (int)$this->model_catalog_product->getTotalProducts($filter_data),
				'href' => $this->url->link('product/category', 'path=' . $current_path),
				'children' => $this->buildTree((int)$category['category_id'], $current_path)
			);
		}

		return $tree;
	}
}
