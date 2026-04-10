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

		// Links for accent pages
		$data['new_arrivals'] = $this->url->link('product/new_arrivals');
		$data['special'] = $this->url->link('product/special');

		// Information links marked as top=1 — shown in header nav (vertical top bar / horizontal micro bar)
		$this->load->model('catalog/information');
		$data['top_informations'] = array();
		foreach ($this->model_catalog_information->getInformations() as $info) {
			if (!empty($info['top'])) {
				$data['top_informations'][] = array(
					'title' => $info['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . (int)$info['information_id'])
				);
			}
		}
		
		// Add Contacts link after information links
		$data['top_informations'][] = array(
			'title' => $this->language->get('text_contact'),
			'href'  => $this->url->link('information/contact')
		);

		// Determine active category
		// Works for both product/category and product/product (when opened from category)
		if (!empty($this->request->get['path'])) {
			$path_parts = array_values(array_filter(array_map('intval', explode('_', (string)$this->request->get['path']))));

			if ($path_parts) {
				$data['active_category_id'] = (int)$path_parts[0];
			}
		}

		$language_context = $this->resolveLanguageContext();

		$cache_key = 'category.menu.tree.v2.'
			. (int)$this->config->get('config_store_id')
			. '.' . (int)$language_context['language_id']
			. '.' . (string)$language_context['language_code']
			. '.' . (int)$this->config->get('config_product_count');
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
				$grandchild_total = 0;

				if ($this->config->get('config_product_count')) {
					$grandchild_total = $this->getCategoryProductTotal((int)$grandchild['category_id']);
				}

				$grandchildren_data[] = array(
					'name' => $grandchild['name'] . ($this->config->get('config_product_count') ? ' (' . $grandchild_total . ')' : ''),
					'href' => $this->url->link('product/category', 'path=' . $path . '_' . (int)$child['category_id'] . '_' . (int)$grandchild['category_id'])
				);
			}

			$child_total = 0;

			if ($this->config->get('config_product_count')) {
				$child_total = $this->getCategoryProductTotal((int)$child['category_id']);
			}

			$children_data[] = array(
				'name'  => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $child_total . ')' : ''),
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

	private function resolveLanguageContext() {
		$language_code = '';

		if (!empty($this->session->data['language'])) {
			$language_code = (string)$this->session->data['language'];
		} elseif (!empty($this->request->cookie['language'])) {
			$language_code = (string)$this->request->cookie['language'];
		} else {
			$language_code = (string)$this->language->get('code');
		}

		$language_code = strtolower(preg_replace('/[^a-z0-9\-]/i', '', $language_code));
		if ($language_code === '') {
			$language_code = 'default';
		}

		$language_id = (int)$this->config->get('config_language_id');

		if ($language_code !== 'default') {
			$this->load->model('localisation/language');
			$languages = $this->model_localisation_language->getLanguages();

			if (isset($languages[$language_code]) && isset($languages[$language_code]['language_id'])) {
				$resolved_language_id = (int)$languages[$language_code]['language_id'];

				if ($resolved_language_id > 0 && $resolved_language_id !== $language_id) {
					$language_id = $resolved_language_id;
					$this->config->set('config_language_id', $language_id);
				}
			}
		}

		return array(
			'language_id' => $language_id,
			'language_code' => $language_code
		);
	}

	private function getCategoryProductTotal($category_id) {
		static $request_cache = array();

		$store_id = (int)$this->config->get('config_store_id');
		$category_id = (int)$category_id;
		$cache_key = 'category.menu.count.' . $store_id . '.' . $category_id;

		if (isset($request_cache[$cache_key])) {
			return $request_cache[$cache_key];
		}

		$total = $this->cache->get($cache_key);

		if ($total === null || $total === false) {
			$query = $this->db->query("SELECT COUNT(DISTINCT p.product_id) AS total
				FROM " . DB_PREFIX . "category_path cp
				LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)
				LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
				LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
				WHERE cp.path_id = '" . $category_id . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . $store_id . "'");

			$total = isset($query->row['total']) ? (int)$query->row['total'] : 0;
			$this->cache->set($cache_key, $total, 900);
		}

		$request_cache[$cache_key] = (int)$total;

		return $request_cache[$cache_key];
	}
}
