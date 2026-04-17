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

	public function delete() {
		$this->load->language('extension/module/dockercart_blog_category');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_category');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $category_id) {
				$this->model_extension_module_dockercart_blog_category->deleteCategory($category_id);
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		// Add link to Extensions (modules) list without overriding module heading
		$extension_lang = $this->load->language('extension/module/dockercart_blog');
		$data['breadcrumbs'][] = array(
			'text' => isset($extension_lang['text_extension']) ? $extension_lang['text_extension'] : $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		// Restore module-specific language to keep correct heading_title
		$this->load->language('extension/module/dockercart_blog_category');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('extension/module/dockercart_blog_category/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/dockercart_blog_category/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		$data['categories'] = array();

		$filter_data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$categories = $this->model_extension_module_dockercart_blog_category->getCategories($filter_data);

		foreach ($categories as $category) {
			$data['categories'][] = array(
				'category_id' => $category['category_id'],
				'name'        => $category['name'],
				'status'      => $category['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'edit'        => $this->url->link('extension/module/dockercart_blog_category/edit', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $category['category_id'] . $url, true)
			);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_category_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['category_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		// Add link to Extensions (modules) list without overriding module heading
		$extension_lang = $this->load->language('extension/module/dockercart_blog');
		$data['breadcrumbs'][] = array(
			'text' => isset($extension_lang['text_extension']) ? $extension_lang['text_extension'] : $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		// Restore module-specific language to keep correct heading_title
		$this->load->language('extension/module/dockercart_blog_category');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['category_id'])) {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_category/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_category/edit', 'user_token=' . $this->session->data['user_token'] . '&category_id=' . $this->request->get['category_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/module/dockercart_blog_category', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

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

		// Load categories for parent select
		$this->load->model('extension/module/dockercart_blog_category');
		$data['categories'] = $this->model_extension_module_dockercart_blog_category->getCategories();

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

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_category')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
