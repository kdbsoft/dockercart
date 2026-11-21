<?php

use Dockercart\Layout\RouteConfig;

class ControllerDesignLayout extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('design/layout');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/layout');

		$this->getList();
	}

	public function add() {
		$this->load->language('design/layout');

		$this->load->model('design/layout');

		if (!$this->user->hasPermission('modify', 'design/layout')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true));
		}

		// No legacy form: create a layout with a default name and open it in the Builder.
		$language_id = (int)$this->config->get('config_language_id');

		$layout_id = $this->model_design_layout->addLayout(array(
			'layout_description' => array(
				$language_id => array('name' => $this->language->get('text_new_layout'))
			)
		));

		$this->response->redirect($this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . (int)$layout_id, true));
	}

	public function edit() {
		// Legacy editor removed: the Builder is the only editor.
		// Keep the route as a redirect so old bookmarks keep working.
		if (isset($this->request->get['layout_id'])) {
			$this->response->redirect($this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . (int)$this->request->get['layout_id'], true));
		}

		$this->response->redirect($this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function delete() {
		$this->load->language('design/layout');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/layout');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $layout_id) {
				$this->model_design_layout->deleteLayout($layout_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function builder() {
		$this->load->language('design/layout');

		if (!isset($this->request->get['layout_id'])) {
			$this->response->redirect($this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true));
		}

		$layout_id = (int)$this->request->get['layout_id'];

		$this->load->model('design/layout');

		// Handle AJAX save
		if ($this->request->server['REQUEST_METHOD'] === 'POST') {
			$json = array();

			if (!$this->user->hasPermission('modify', 'design/layout')) {
				$json['error'] = $this->language->get('error_permission');
			}

			if (!isset($json['error'])) {
				// Routes come from the inline Routes panel (the marker is always sent,
				// even when empty). Older clients may omit them — keep what is stored.
				if (!isset($this->request->post['layout_route']) && !isset($this->request->post['layout_routes_sent'])) {
					$this->request->post['layout_route'] = $this->model_design_layout->getLayoutRoutes($layout_id);
				}

				// The canvas only renders the positions of the layout's first route.
				// Modules in other positions are posted back as untouched hidden rows
				// — merge them in, otherwise editLayout() would delete them.
				if (!empty($this->request->post['hidden_module']) && is_array($this->request->post['hidden_module'])) {
					if (!isset($this->request->post['layout_module']) || !is_array($this->request->post['layout_module'])) {
						$this->request->post['layout_module'] = array();
					}

					foreach ($this->request->post['hidden_module'] as $hidden_module) {
						if (isset($hidden_module['code'], $hidden_module['position'])) {
							$this->request->post['layout_module'][] = array(
								'code'       => $hidden_module['code'],
								'position'   => $hidden_module['position'],
								'sort_order' => isset($hidden_module['sort_order']) ? $hidden_module['sort_order'] : 0
							);
						}
					}
				}

				unset($this->request->post['hidden_module']);

				$this->model_design_layout->editLayout($layout_id, $this->request->post);
				$json['success'] = $this->language->get('text_success');
			}

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$this->document->setTitle($this->language->get('heading_title_builder'));

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Current admin UI language — palette and card names are rendered in it.
		// Layout names themselves are edited inline for every language below.
		if (isset($this->session->data['language']) && $this->session->data['language']) {
			$data['current_language_code'] = (string)$this->session->data['language'];
		} else {
			$data['current_language_code'] = (string)$this->config->get('config_admin_language');
		}

		$data['current_language_id'] = 0;
		foreach ($data['languages'] as $code => $language) {
			if ($language['code'] === $data['current_language_code']) {
				$data['current_language_id'] = (int)$language['language_id'];
				break;
			}
		}

		if (!$data['current_language_id'] && $data['languages']) {
			$first = reset($data['languages']);
			$data['current_language_id'] = (int)$first['language_id'];
		}

		$data['layout_descriptions'] = $this->model_design_layout->getLayoutDescriptions($layout_id);

		$data['layout_routes'] = $this->model_design_layout->getLayoutRoutes($layout_id);

		$data['layout_id'] = $layout_id;
		$data['user_token'] = $this->session->data['user_token'];

		// Tab icon for the standalone page: the store's theme favicon master when
		// configured, otherwise the admin logo (same as the API docs pages).
		$data['favicon_url'] = $this->getBuilderFaviconUrl();

		// Cache-bust the builder assets: the web server serves static files
		// with long-lived immutable headers (same as design/landing/builder).
		$data['admin_css_version'] = (string)@filemtime(DIR_APPLICATION . 'view/stylesheet/dockercart_tokens.css');
		$data['builder_css_version'] = (string)@filemtime(DIR_APPLICATION . 'view/stylesheet/dockercart_layout_builder.css');
		$data['builder_js_version'] = (string)@filemtime(DIR_APPLICATION . 'view/javascript/dockercart_layout_builder.js');

		// Save URL for AJAX
		$data['action'] = $this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $layout_id, true);
		$data['back'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true);

		$this->load->model('setting/store');
		$data['stores'] = $this->model_setting_store->getStores();

		// Route-based layout config for template position filtering.
		// Uses the first route assigned to this layout.
		$first_route = !empty($data['layout_routes']) ? $data['layout_routes'][0]['route'] : 'common/home';
		$route_config = RouteConfig::forRoute($first_route);
		$data['active_positions'] = $route_config['positions'];
		$data['responsive_map'] = $route_config['responsive'];

		$data['extensions'] = $this->buildBuilderPalette((int)$data['current_language_id']);

		$data = array_merge($data, $this->buildBuilderModules(
			$layout_id,
			(int)$data['current_language_id'],
			$data['extensions'],
			$route_config['positions']
		));

		// Do NOT load admin chrome — standalone page
		$this->response->setOutput($this->load->view('design/layout_builder', $data));
	}

	/**
	 * Installed module types and their saved module instances for the palette.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function buildBuilderPalette(int $language_id): array {
		$this->load->model('setting/extension');
		$this->load->model('setting/module');

		$extensions = array();

		foreach ($this->model_setting_extension->getInstalled('module') as $code) {
			$this->load->language('extension/module/' . $code, 'extension');

			$module_data = array();
			foreach ($this->model_setting_module->getModulesByCode($code) as $module) {
				$module_info = $this->model_setting_module->getModule($module['module_id']);
				$module_data[] = array(
					'name' => strip_tags($this->moduleDisplayName($module_info ?: $module, $language_id) ?: $module['name']),
					'code' => $code . '.' . $module['module_id']
				);
			}

			if ($this->config->has('module_' . $code . '_status') || $module_data) {
				$extensions[] = array(
					'name'   => strip_tags($this->language->get('extension')->get('heading_title')),
					'code'   => $code,
					'icon'   => $this->moduleIcon($code),
					'module' => $module_data
				);
			}
		}

		return $extensions;
	}

	/**
	 * Assigned modules split into drawn zones and preserved hidden positions.
	 *
	 * @param array<int, array<string, mixed>> $extensions
	 * @param array<int, string>               $active_positions
	 *
	 * @return array<string, mixed>
	 */
	private function buildBuilderModules(int $layout_id, int $language_id, array $extensions, array $active_positions): array {
		$result = array(
			'layout_modules'      => array(),
			'hidden_modules'      => array(),
			'modules_by_position' => array(
				'content_top'    => array(),
				'column_left'    => array(),
				'column_right'   => array(),
				'content_bottom' => array(),
			),
			'hidden_modules_notice' => ''
		);

		$extension_names = array();
		foreach ($extensions as $ext) {
			$extension_names[$ext['code']] = $ext['name'];
		}

		$idx = 0;
		foreach ($this->model_design_layout->getLayoutModules($layout_id) as $layout_module) {
			$part = explode('.', $layout_module['code']);

			$module_name = $layout_module['code'];
			if (isset($part[1])) {
				$module_info = $this->model_setting_module->getModule($part[1]);
				$module_name = $this->moduleDisplayName($module_info ?: array(), $language_id);
				if (!$module_name) {
					$module_name = !empty($module_info['name']) ? (string)$module_info['name'] : $layout_module['code'];
				}
			} else {
				$this->load->language('extension/module/' . $part[0], 'extension');
				$module_name = $this->language->get('extension')->get('heading_title');
			}

			$display_name = isset($part[1], $extension_names[$part[0]]) ? $extension_names[$part[0]] . ' → ' . $module_name : $module_name;
			$edit_url = !isset($part[1])
				? $this->url->link('extension/module/' . $part[0], 'user_token=' . $this->session->data['user_token'], true)
				: $this->url->link('extension/module/' . $part[0], 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $part[1], true);

			$module_entry = array(
				'idx'          => $idx,
				'code'         => $layout_module['code'],
				'name'         => strip_tags($module_name),
				'display_name' => strip_tags($display_name),
				'edit'         => $edit_url,
				'position'     => $layout_module['position'],
				'sort_order'   => $layout_module['sort_order'],
				'icon'         => $this->moduleIcon($part[0])
			);

			$result['layout_modules'][] = $module_entry;

			// A position outside the canvas belongs to another route (or an
			// extension) — keep it out of the drawn zones and never remap it.
			if (isset($result['modules_by_position'][$layout_module['position']]) && in_array($layout_module['position'], $active_positions, true)) {
				$result['modules_by_position'][$layout_module['position']][] = $module_entry;
			} else {
				$result['hidden_modules'][] = $module_entry;
			}

			$idx++;
		}

		if ($result['hidden_modules']) {
			$position_labels = array(
				'content_top'    => 'text_content_top',
				'column_left'    => 'text_column_left',
				'column_right'   => 'text_column_right',
				'content_bottom' => 'text_content_bottom'
			);
			$hidden_positions = array();
			foreach ($result['hidden_modules'] as $hidden_module) {
				$position = $hidden_module['position'];
				$hidden_positions[$position] = isset($position_labels[$position]) ? $this->language->get($position_labels[$position]) : $position;
			}

			$result['hidden_modules_notice'] = sprintf($this->language->get('text_hidden_modules'), implode(', ', $hidden_positions));
		}

		return $result;
	}

	/**
	 * Display name of a module for the current admin language.
	 *
	 * Modules that support per-language names (e.g. slideshow) store them in
	 * the module setting as `name_translation` (language_id => name); the
	 * builder palette and cards should show the translation of the current
	 * language instead of the stored name.
	 */
	private function moduleDisplayName(array $module, $language_id) {
		$name = isset($module['name']) && $module['name'] !== null ? (string)$module['name'] : '';

		if ($language_id > 0
			&& isset($module['name_translation'])
			&& is_array($module['name_translation'])
			&& !empty($module['name_translation'][$language_id])
		) {
			$name = (string)$module['name_translation'][$language_id];
		}

		return $name;
	}

	/**
	 * Lucide icon for a palette group, resolved from the module extension
	 * code so every module type is visually distinct (Page Builder takes
	 * its icons from Dockercart\\Landing\\Blocks the same way).
	 *
	 * Unknown codes fall back to a few name heuristics and finally to
	 * the generic box.
	 */
	private function moduleIcon(string $code): string {
		static $map = array(
			'banner'       => 'panel-top',
			'carousel'     => 'gallery-horizontal-end',
			'slideshow'    => 'images',
			'category'     => 'folder-tree',
			'featured'     => 'star',
			'latest'       => 'clock',
			'bestseller'   => 'trophy',
			'special'      => 'badge-percent',
			'filter'       => 'sliders-horizontal',
			'menu'         => 'menu',
			'search'       => 'search',
			'cart'         => 'shopping-cart',
			'manufacturer' => 'factory',
			'information'  => 'file-text',
			'html'         => 'code',
			'content'      => 'file-text',
			'store'        => 'store',
		);

		if (isset($map[$code])) {
			return $map[$code];
		}

		foreach (array('blog' => 'newspaper', 'slide' => 'images', 'banner' => 'panel-top', 'menu' => 'menu', 'product' => 'package', 'manufactur' => 'factory') as $needle => $icon) {
			if (stripos($code, $needle) !== false) {
				return $icon;
			}
		}

		return 'box';
	}

	/**
	 * Build the tab-icon URL for the standalone builder page.
	 * with the pre-module theme_dockercart_favicon_master fallback) when the
	 * file exists in the shared image dir, and falls back to the admin logo.
	 */
	private function getBuilderFaviconUrl() {
		$admin_base = rtrim((string)HTTP_SERVER, '/');

		$favicon_url = $admin_base . '/view/image/logo.png';

		$favicon_master = (string)$this->config->get('dockercart_theme_favicon_master');

		if ($favicon_master === '') {
			// Backward compatibility for the previously stored value.
			$favicon_master = (string)$this->config->get('theme_dockercart_favicon_master');
		}

		if ($favicon_master && is_file(DIR_IMAGE . $favicon_master)) {
			$site_url = rtrim((string)$this->config->get('config_url'), '/');

			$favicon_url = ($site_url ?: $admin_base) . '/image/' . $favicon_master;
		}

		return $favicon_url;
	}

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['add'] = $this->url->link('design/layout/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('design/layout/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['layouts'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$layout_total = $this->model_design_layout->getTotalLayouts();

		$results = $this->model_design_layout->getLayouts($filter_data);

		$this->load->model('setting/store');

		$store_names = array();

		foreach ($this->model_setting_store->getStores() as $store) {
			$store_names[$store['store_id']] = $store['name'];
		}

		foreach ($results as $result) {
			$routes = array();

			foreach ($this->model_design_layout->getLayoutRoutes($result['layout_id']) as $layout_route) {
				$route_text = $layout_route['route'];

				if ($layout_route['store_id'] != 0 && isset($store_names[$layout_route['store_id']])) {
					$route_text .= ' (' . $store_names[$layout_route['store_id']] . ')';
				}

				$routes[] = $route_text;
			}

			$data['layouts'][] = array(
				'layout_id' => $result['layout_id'],
				'name'      => $result['name'],
				'routes'    => $routes,
				'edit'      => $this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $result['layout_id'], true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];

			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $layout_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/layout_list', $data));
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'design/layout')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$this->load->model('setting/store');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('catalog/information');

		foreach ($this->request->post['selected'] as $layout_id) {
			if ($this->config->get('config_layout_id') == $layout_id) {
				$this->error['warning'] = $this->language->get('error_default');
			}

			$store_total = $this->model_setting_store->getTotalStoresByLayoutId($layout_id);

			if ($store_total) {
				$this->error['warning'] = sprintf($this->language->get('error_store'), $store_total);
			}

			$product_total = $this->model_catalog_product->getTotalProductsByLayoutId($layout_id);

			if ($product_total) {
				$this->error['warning'] = sprintf($this->language->get('error_product'), $product_total);
			}

			$category_total = $this->model_catalog_category->getTotalCategoriesByLayoutId($layout_id);

			if ($category_total) {
				$this->error['warning'] = sprintf($this->language->get('error_category'), $category_total);
			}

			$information_total = $this->model_catalog_information->getTotalInformationsByLayoutId($layout_id);

			if ($information_total) {
				$this->error['warning'] = sprintf($this->language->get('error_information'), $information_total);
			}
		}

		return !$this->error;
	}
}
