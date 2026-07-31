<?php
/**
 * DockerCart Blog - Category Admin Controller
 */

class ControllerExtensionModuleDockercartBlogCategory extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_dockercart_blog_category->addCategory($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_dockercart_blog_category->editCategory($this->request->get['category_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function copy() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		$category_ids = [];

		if (isset($this->request->post['selected'])) {
			$category_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['category_id'])) {
			$category_ids = [(int) $this->request->get['category_id']];
		}

		if ($category_ids && $this->validateCopy()) {
			foreach ($category_ids as $category_id) {
				$this->model_extension_module_dockercart_blog_category->copyCategory((int) $category_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		$category_ids = [];

		if (isset($this->request->post['selected'])) {
			$category_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['category_id'])) {
			$category_ids = [(int) $this->request->get['category_id']];
		}

		if ($category_ids && $this->validateDelete()) {
			foreach ($category_ids as $category_id) {
				$this->model_extension_module_dockercart_blog_category->deleteCategory((int) $category_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		// Add link to Extensions (modules) list without overriding module heading
		$extension_lang = $this->load->language('extension/module/dockercart_blog');
		// Restore module-specific language to keep correct heading_title
		$this->load->language('extension/module/dockercart_blog_category');
		$data['add'] = $this->url->link('extension/module/dockercart_blog_category/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('extension/module/dockercart_blog_category/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/dockercart_blog_category/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		$data['categories'] = array();

		$filter_data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$category_total = $this->model_extension_module_dockercart_blog_category->getTotalCategories($filter_data);

		$categories = $this->model_extension_module_dockercart_blog_category->getCategories($filter_data);

		foreach ($categories as $category) {
			$data['categories'][] = array(
				'category_id' => $category['category_id'],
				'name'        => $category['name'],
				'name_raw'    => $category['name'],
				'status'      => $category['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'status_raw'  => $category['status'],
				'sort_order'  => $category['sort_order'],
				'sort_order_raw' => $category['sort_order'],
				'selected'    => isset($this->request->post['selected']) && in_array($category['category_id'], $this->request->post['selected']),
				'edit'        => $this->url->link('extension/module/dockercart_blog_category/edit', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $category['category_id'] . $url, true),
				'copy'        => $this->url->link('extension/module/dockercart_blog_category/copy', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $category['category_id'] . $url, true),
				'delete'      => $this->url->link('extension/module/dockercart_blog_category/delete', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $category['category_id'] . $url, true)
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

		$pagination = new Pagination();
		$pagination->total = $category_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($category_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($category_total - $this->config->get('config_limit_admin'))) ? $category_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $category_total, ceil($category_total / $this->config->get('config_limit_admin')));

		$data['user_token'] = $this->session->data['user_token'];
		$data['config_language_id'] = $this->config->get('config_language_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_category_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['category_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['category_id']) ? $this->language->get('text_add_category_subtitle') : $this->language->get('text_edit_category_subtitle');

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		// Add link to Extensions (modules) list without overriding module heading
		$extension_lang = $this->load->language('extension/module/dockercart_blog');
		// Restore module-specific language to keep correct heading_title
		$this->load->language('extension/module/dockercart_blog_category');
		if (!isset($this->request->get['category_id'])) {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_category/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_category/edit', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $this->request->get['category_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['category_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$category_info = $this->model_extension_module_dockercart_blog_category->getCategory($this->request->get['category_id']);
		}

		$this->load->model('localisation/language');
		// Languages (use same structure as core controllers)
		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['category_description'])) {
			$data['category_description'] = $this->request->post['category_description'];
		} elseif (isset($this->request->get['category_id'])) {
			$data['category_description'] = $this->model_extension_module_dockercart_blog_category->getCategoryDescriptions($this->request->get['category_id']);
		} else {
			$data['category_description'] = array();
		}

		$data['category_description'] = $this->decodeDescriptionFields($data['category_description'], array('name', 'meta_title'));

		// Category name for page header (use admin language)
		$data['category_name'] = '';
		if (!empty($data['category_description'])) {
			$language_id = (int)$this->config->get('config_language_id');
			$data['category_name'] = $data['category_description'][$language_id]['name']
				?? reset($data['category_description'])['name']
				?? '';
		}

		// Errors for template
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		// Per-language errors
		$data['error_name'] = array();
		$data['error_meta_title'] = array();
		if (isset($this->error['name']) && is_array($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		}
		if (isset($this->error['meta_title']) && is_array($this->error['meta_title'])) {
			$data['error_meta_title'] = $this->error['meta_title'];
		}

		if (isset($this->request->post['parent_id'])) {
			$data['parent_id'] = $this->request->post['parent_id'];
		} elseif (!empty($category_info)) {
			$data['parent_id'] = $category_info['parent_id'];
		} else {
			$data['parent_id'] = 0;
		}

		// Path display for tree selector
		$data['path'] = '';
		if (isset($this->request->post['path'])) {
			$data['path'] = $this->request->post['path'];
		} elseif (!empty($category_info) && $category_info['parent_id']) {
			$tree = $this->model_extension_module_dockercart_blog_category->getTreeCategories();
			foreach ($tree as $node) {
				if ($node['category_id'] == $category_info['parent_id']) {
					$data['path'] = $node['path'];
					break;
				}
			}
		}
		$data['path'] = $this->decodeHtmlEntitiesForDisplay($data['path']);

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($category_info)) {
			$data['image'] = $category_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
		} elseif (!empty($category_info) && is_file(DIR_IMAGE . $category_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($category_info['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($category_info)) {
			$data['status'] = $category_info['status'];
		} else {
			$data['status'] = 1;
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($category_info)) {
			$data['sort_order'] = $category_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		$this->load->model('setting/store');
		// Include default store (store_id = 0) like core controllers
		$data['stores'] = array();
		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);
		$stores = $this->model_setting_store->getStores();
		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		// SEO URLs per store/language (use blog-specific table)
		if (isset($this->request->post['category_seo_url'])) {
			$data['category_seo_url'] = $this->request->post['category_seo_url'];
		} elseif (isset($this->request->get['category_id'])) {
			$data['category_seo_url'] = $this->model_extension_module_dockercart_blog_category->getCategorySeoUrls($this->request->get['category_id']);
		} else {
			$data['category_seo_url'] = array();
		}

		if (isset($this->request->post['category_store'])) {
			$data['category_store'] = $this->request->post['category_store'];
		} elseif (isset($this->request->get['category_id'])) {
			$data['category_store'] = $this->model_extension_module_dockercart_blog_category->getCategoryStores($this->request->get['category_id']);
		} else {
			$data['category_store'] = array(0);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_category_form', $data));
	}

	private function decodeDescriptionFields($descriptions, $fields = array()) {
		if (!is_array($descriptions)) {
			return array();
		}

		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			foreach ($fields as $field) {
				if (isset($description[$field])) {
					$descriptions[$language_id][$field] = $this->decodeHtmlEntitiesForDisplay($description[$field]);
				}
			}
		}

		return $descriptions;
	}

	private function decodeHtmlEntitiesForDisplay($value) {
		if (!is_scalar($value)) {
			return '';
		}

		$decoded = (string)$value;

		for ($i = 0; $i < 2; $i++) {
			$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($next === $decoded) {
				break;
			}

			$decoded = $next;
		}

		return $decoded;
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Ensure category descriptions exist
		if (!isset($this->request->post['category_description']) || !is_array($this->request->post['category_description'])) {
			$this->error['warning'] = $this->language->get('error_description');
		} else {
			foreach ($this->request->post['category_description'] as $language_id => $value) {
				if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
					$this->error['name'][$language_id] = $this->language->get('error_name');
				}

				if ((utf8_strlen($value['meta_title']) < 1) || (utf8_strlen($value['meta_title']) > 255)) {
					$this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
				}
			}
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function tree() {
		$this->load->model('extension/module/dockercart_blog_category');

		$results = $this->model_extension_module_dockercart_blog_category->getTreeCategories();

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($results));
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['category_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$category_id = (int)$this->request->post['category_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('extension/module/dockercart_blog_category');

			if ($field === 'sort_order') {
				$val = (int)$value;

				if ($val < 0) {
					$json['error'] = $this->language->get('error_invalid_sort_order');
				} else {
					$this->model_extension_module_dockercart_blog_category->updateCategoryField($category_id, array('sort_order' => $val));
					$json['success'] = true;
					$json['value_html'] = (string)$val;
				}
			} elseif ($field === 'status') {
				$val = (int)$value;
				$this->model_extension_module_dockercart_blog_category->updateCategoryField($category_id, array('status' => $val));

				if ($val) {
					$json['value_html'] = '<span class="label label-success">' . $this->language->get('text_enabled') . '</span>';
				} else {
					$json['value_html'] = '<span class="label label-danger">' . $this->language->get('text_disabled') . '</span>';
				}
				$json['success'] = true;
			} else {
				$json['error'] = 'Invalid field';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getName() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->get['category_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$category_id = (int)$this->request->get['category_id'];

			$this->load->model('extension/module/dockercart_blog_category');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$descriptions = $this->model_extension_module_dockercart_blog_category->getCategoryDescriptions($category_id);

			$names = array();
			foreach ($languages as $language) {
				$lid = $language['language_id'];
				$names[$lid] = isset($descriptions[$lid]) ? $descriptions[$lid]['name'] : '';
			}

			$json['success'] = true;
			$json['languages'] = array_values($languages);
			$json['names'] = $names;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateNames() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$json['error'] = $this->language->get('error_permission');
		} elseif (!isset($this->request->post['category_id']) || !isset($this->request->post['names'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$category_id = (int)$this->request->post['category_id'];
			$names = $this->request->post['names'];

			$this->load->model('extension/module/dockercart_blog_category');
			$this->load->model('localisation/language');

			$languages = $this->model_localisation_language->getLanguages();
			$error_names = array();

			foreach ($languages as $language) {
				$lid = $language['language_id'];
				if (isset($names[$lid])) {
					$name = trim((string)$names[$lid]);
					if (utf8_strlen($name) < 1 || utf8_strlen($name) > 255) {
						$error_names[$lid] = $this->language->get('error_name');
					}
				}
			}

			if (!empty($error_names)) {
				$json['error'] = $this->language->get('error_name');
				$json['error_names'] = $error_names;
			} else {
				$this->model_extension_module_dockercart_blog_category->updateCategoryNames($category_id, $names);
				$json['success'] = true;
				$first = reset($names);
				$json['value_html'] = htmlspecialchars((string)$first, ENT_QUOTES, 'UTF-8');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
