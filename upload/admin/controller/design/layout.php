<?php
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

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/layout');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_design_layout->addLayout($this->request->post);

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

		$this->getForm();
	}

	public function edit() {
		$this->load->language('design/layout');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/layout');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_design_layout->editLayout($this->request->get['layout_id'], $this->request->post);

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

		$this->getForm();
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
				// Preserve existing routes if not sent in POST
				if (!isset($this->request->post['layout_route'])) {
					$this->request->post['layout_route'] = $this->model_design_layout->getLayoutRoutes($layout_id);
				}

				$this->model_design_layout->editLayout($layout_id, $this->request->post);
				$json['success'] = $this->language->get('text_success');
			}

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));

			return;
		}

		$this->document->setTitle($this->language->get('heading_title_builder'));

		$layout_info = $this->model_design_layout->getLayout($layout_id);

		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['layout_descriptions'] = $this->model_design_layout->getLayoutDescriptions($layout_id);

		$data['layout_routes'] = $this->model_design_layout->getLayoutRoutes($layout_id);

		$data['layout_id'] = $layout_id;
		$data['user_token'] = $this->session->data['user_token'];

		// Save URL for AJAX
		$data['action'] = $this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $layout_id, true);
		$data['back'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'], true);
		$data['classic_url'] = $this->url->link('design/layout/edit', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $layout_id, true);

		// Preview URL — use the first route assigned to this layout
		$first_route = !empty($data['layout_routes']) ? $data['layout_routes'][0]['route'] : 'common/home';
		$data['preview_url'] = HTTP_CATALOG . 'index.php?route=' . $first_route;

		// Route-based layout config for template position filtering
		$route_config = $this->getRouteLayoutConfig($first_route);
		$data['active_positions'] = $route_config['positions'];
		$data['responsive_map'] = $route_config['responsive'];

		// Get installed extensions for the module palette
		$this->load->model('setting/extension');
		$this->load->model('setting/module');

		$data['extensions'] = array();
		$extensions = $this->model_setting_extension->getInstalled('module');

		foreach ($extensions as $code) {
			$this->load->language('extension/module/' . $code, 'extension');

			$module_data = array();

			$modules = $this->model_setting_module->getModulesByCode($code);

			foreach ($modules as $module) {
				$module_data[] = array(
					'name' => strip_tags($module['name']),
					'code' => $code . '.' . $module['module_id']
				);
			}

			if ($this->config->has('module_' . $code . '_status') || $module_data) {
				$data['extensions'][] = array(
					'name'   => strip_tags($this->language->get('extension')->get('heading_title')),
					'code'   => $code,
					'module' => $module_data
				);
			}
		}

		// Get assigned modules for this layout
		$layout_modules = $this->model_design_layout->getLayoutModules($layout_id);

		$data['layout_modules'] = array();
		$data['modules_by_position'] = array(
			'content_top'    => array(),
			'column_left'    => array(),
			'column_right'   => array(),
			'content_bottom' => array(),
		);

		$idx = 0;
		// Build extension name lookup for display names
		$extension_names = array();
		foreach ($data['extensions'] as $ext) {
			$extension_names[$ext['code']] = $ext['name'];
		}

		foreach ($layout_modules as $layout_module) {
			$part = explode('.', $layout_module['code']);

			$module_name = $layout_module['code'];
			if (isset($part[1])) {
				$module_info = $this->model_setting_module->getModule($part[1]);
				$module_name = $module_info ? $module_info['name'] : $layout_module['code'];
			} else {
				$this->load->language('extension/module/' . $part[0], 'extension');
				$module_name = $this->language->get('extension')->get('heading_title');
			}

			$display_name = $module_name;
			if (isset($part[1], $extension_names[$part[0]])) {
				$display_name = $extension_names[$part[0]] . ' → ' . $module_name;
			}

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
				'sort_order'   => $layout_module['sort_order']
			);

			$data['layout_modules'][] = $module_entry;

			if (isset($data['modules_by_position'][$layout_module['position']])) {
				$data['modules_by_position'][$layout_module['position']][] = $module_entry;
			} else {
				$data['modules_by_position']['content_top'][] = $module_entry;
			}

			$idx++;
		}

		// Do NOT load admin chrome — standalone page
		$this->response->setOutput($this->load->view('design/layout_builder', $data));
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

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

		foreach ($results as $result) {
			$data['layouts'][] = array(
				'layout_id' => $result['layout_id'],
				'name'      => $result['name'],
				'edit'      => $this->url->link('design/layout/edit', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $result['layout_id'] . $url, true),
				'builder'   => $this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $result['layout_id'], true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
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

		$data['results'] = sprintf($this->language->get('text_pagination'), ($layout_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($layout_total - $this->config->get('config_limit_admin'))) ? $layout_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $layout_total, ceil($layout_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/layout_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['layout_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['layout_id'])
		    ? $this->language->get('text_add_layout_subtitle')
		    : $this->language->get('text_edit_layout_subtitle');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = array();
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['layout_id'])) {
			$data['action'] = $this->url->link('design/layout/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('design/layout/edit', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $this->request->get['layout_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('design/layout', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['layout_id'])) {
			$data['builder_url'] = $this->url->link('design/layout/builder', 'user_token=' . $this->session->data['user_token'] . '&layout_id=' . $this->request->get['layout_id'], true);
		} else {
			$data['builder_url'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->get['layout_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$layout_info = $this->model_design_layout->getLayout($this->request->get['layout_id']);
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['layout_description'])) {
			$data['layout_descriptions'] = $this->request->post['layout_description'];
		} elseif (!empty($layout_info)) {
			$data['layout_descriptions'] = $this->model_design_layout->getLayoutDescriptions($this->request->get['layout_id']);
		} else {
			$data['layout_descriptions'] = array();
		}

		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		if (isset($this->request->post['layout_route'])) {
			$data['layout_routes'] = $this->request->post['layout_route'];
		} elseif (isset($this->request->get['layout_id'])) {
			$data['layout_routes'] = $this->model_design_layout->getLayoutRoutes($this->request->get['layout_id']);
		} else {
			$data['layout_routes'] = array();
		}

		$this->load->model('setting/extension');

		$this->load->model('setting/module');

		$data['extensions'] = array();

		// Get a list of installed modules
		$extensions = $this->model_setting_extension->getInstalled('module');

		// Add all the modules which have multiple settings for each module
		foreach ($extensions as $code) {
			$this->load->language('extension/module/' . $code, 'extension');

			$module_data = array();

			$modules = $this->model_setting_module->getModulesByCode($code);

			foreach ($modules as $module) {
				$module_data[] = array(
					'name' => strip_tags($module['name']),
					'code' => $code . '.' .  $module['module_id']
				);
			}

			if ($this->config->has('module_' . $code . '_status') || $module_data) {
				$data['extensions'][] = array(
					'name'   => strip_tags($this->language->get('extension')->get('heading_title')),
					'code'   => $code,
					'module' => $module_data
				);
			}
		}

		// Modules layout
		if (isset($this->request->post['layout_module'])) {
			$layout_modules = $this->request->post['layout_module'];
		} elseif (isset($this->request->get['layout_id'])) {
			$layout_modules = $this->model_design_layout->getLayoutModules($this->request->get['layout_id']);
		} else {
			$layout_modules = array();
		}

		$data['layout_modules'] = array();

		// Add all the modules which have multiple settings for each module
		foreach ($layout_modules as $layout_module) {
			$part = explode('.', $layout_module['code']);

			if (!isset($part[1])) {
				$data['layout_modules'][] = array(
					'code'       => $layout_module['code'],
					'edit'       => $this->url->link('extension/module/' . $part[0], 'user_token=' . $this->session->data['user_token'], true),
					'position'   => $layout_module['position'],
					'sort_order' => $layout_module['sort_order']
				);
			} else {
				$module_info = $this->model_setting_module->getModule($part[1]);

				if ($module_info) {
					$data['layout_modules'][] = array(
						'code'       => $layout_module['code'],
						'edit'       => $this->url->link('extension/module/' . $part[0], 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $part[1], true),
						'position'   => $layout_module['position'],
						'sort_order' => $layout_module['sort_order']
					);
				}
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/layout_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'design/layout')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['layout_description'])) {
			foreach ($this->request->post['layout_description'] as $language_id => $value) {
				if ((utf8_strlen($value['name']) < 3) || (utf8_strlen($value['name']) > 64)) {
					$this->error['name'][$language_id] = $this->language->get('error_name');
				}
			}
		}

		return !$this->error;
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

	private function getRouteLayoutConfig($route) {
		$configs = array(
			// Pattern A — Modern Tailwind Grid (columns: hidden lg:block)
			'product/category' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/special' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/search' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/manufacturer/info' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/manufacturer' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/new_arrivals' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'blog/category' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			// Pattern B — Legacy Bootstrap (columns visible on all breakpoints)
			'common/home' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'checkout/checkout' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'affiliate/login' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'affiliate/register' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			// Pattern C — No sidebars, various
			'common/success' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/information' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/contact' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/sitemap' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'checkout/success' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'error/not_found' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'product/product' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'product/compare' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'checkout/cart' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/post' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/author' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/archive' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/search' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
		);

		// Exact match
		if (isset($configs[$route])) {
			return $configs[$route];
		}

		// Wildcard match for account/* and affiliate/* (excluding login/register already matched above)
		if (strpos($route, 'account/') === 0 || (strpos($route, 'affiliate/') === 0 && !isset($configs[$route]))) {
			return array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			);
		}

		// Default for unknown routes: all 4 positions, lg_only columns
		return array(
			'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
			'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
		);
	}
}
