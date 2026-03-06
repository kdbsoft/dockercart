<?php
class ControllerCommonSearch extends Controller {
	public function index() {
		$this->load->language('common/search');
		$this->load->model('catalog/category');

		// Detect category context for scoped search
		$search_category_id   = 0;
		$search_category_name = '';

		if (isset($this->request->get['route']) && $this->request->get['route'] === 'product/category' && !empty($this->request->get['path'])) {
			$path_parts = array_values(array_filter(array_map('intval', explode('_', (string)$this->request->get['path']))));

			if ($path_parts) {
				$current_cat_id = (int)end($path_parts);
				$cat_info = $this->model_catalog_category->getCategory($current_cat_id);

				if ($cat_info) {
					$search_category_id   = $current_cat_id;
					$search_category_name = $cat_info['name'];
				}
			}
		}

		$data['search_category_id']   = $search_category_id;
		$data['search_category_name'] = $search_category_name;

		if ($search_category_name) {
			$data['text_search'] = sprintf($this->language->get('text_search_scoped'), $search_category_name);
		} else {
			$data['text_search'] = $this->language->get('text_search');
		}

		$data['text_all_categories'] = $this->language->get('text_all_categories');

		if (isset($this->request->get['search'])) {
			$data['search'] = $this->request->get['search'];
		} else {
			$data['search'] = '';
		}

		if (isset($this->request->get['category_id'])) {
			$data['category_id'] = (int)$this->request->get['category_id'];
		} else {
			$data['category_id'] = 0;
		}

		$data['action'] = $this->url->link('product/search');
		$data['categories'] = $this->model_catalog_category->getCategories(0);

		return $this->load->view('common/search', $data);
	}
}