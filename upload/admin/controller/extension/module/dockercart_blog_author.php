<?php
/**
 * DockerCart Blog - Author Admin Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 */

class ControllerExtensionModuleDockercartBlogAuthor extends Controller {
	
	private $error = array();

	public function index() {
		$this->load->language('extension/module/dockercart_blog_author');
		$this->load->model('extension/module/dockercart_blog_author');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->getList();
	}

	public function add() {
		$this->load->language('extension/module/dockercart_blog_author');
		$this->load->model('extension/module/dockercart_blog_author');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_dockercart_blog_author->addAuthor($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/dockercart_blog_author');
		$this->load->model('extension/module/dockercart_blog_author');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_dockercart_blog_author->editAuthor($this->request->get['author_id'], $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/dockercart_blog_author');
		$this->load->model('extension/module/dockercart_blog_author');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $author_id) {
				$this->model_extension_module_dockercart_blog_author->deleteAuthor($author_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	protected function getList() {
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
		$this->load->language('extension/module/dockercart_blog_author');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('extension/module/dockercart_blog_author/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('extension/module/dockercart_blog_author/delete', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		$data['authors'] = array();

		$results = $this->model_extension_module_dockercart_blog_author->getAuthors();

		foreach ($results as $result) {
			$data['authors'][] = array(
				'author_id'  => $result['author_id'],
				'name'       => $result['name'],
				'email'      => $result['email'],
				'status'     => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'sort_order' => $result['sort_order'],
				'edit'       => $this->url->link('extension/module/dockercart_blog_author/edit', 'user_token=' . $this->session->data['user_token'] . '&author_id=' . $result['author_id'], true)
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

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_author_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['author_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['email'])) {
			$data['error_email'] = $this->error['email'];
		} else {
			$data['error_email'] = '';
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
		$this->load->language('extension/module/dockercart_blog_author');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true)
		);

		if (!isset($this->request->get['author_id'])) {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_author/add', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_author/edit', 'user_token=' . $this->session->data['user_token'] . '&author_id=' . $this->request->get['author_id'], true);
		}

		$data['cancel'] = $this->url->link('extension/module/dockercart_blog_author', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->get['author_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$author_info = $this->model_extension_module_dockercart_blog_author->getAuthor($this->request->get['author_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($author_info)) {
			$data['name'] = $author_info['name'];
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} elseif (!empty($author_info)) {
			$data['email'] = $author_info['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['bio'])) {
			$data['bio'] = $this->request->post['bio'];
		} elseif (!empty($author_info)) {
			$data['bio'] = $author_info['bio'];
		} else {
			$data['bio'] = '';
		}

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($author_info)) {
			$data['image'] = $author_info['image'];
		} else {
			$data['image'] = '';
		}

		// Image thumbnail / placeholder
		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($this->request->post['image'], 100, 100);
		} elseif (!empty($author_info) && is_file(DIR_IMAGE . $author_info['image'])) {
			$data['thumb'] = $this->model_tool_image->resize($author_info['image'], 100, 100);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($author_info)) {
			$data['status'] = $author_info['status'];
		} else {
			$data['status'] = 1;
		}

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($author_info)) {
			$data['sort_order'] = $author_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_author_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_author')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 1) || (utf8_strlen($this->request->post['name']) > 255)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$this->error['email'] = $this->language->get('error_email');
		}

		// Prevent duplicate authors by name or email
		$existing = $this->model_extension_module_dockercart_blog_author->getAuthorByNameOrEmail($this->request->post['name'], $this->request->post['email']);
		if ($existing) {
			// If adding new author or existing author belongs to different id => duplicate
			if (!isset($this->request->get['author_id']) || ((int)$existing['author_id'] !== (int)$this->request->get['author_id'])) {
				$this->error['warning'] = $this->language->get('error_duplicate');
			}
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_author')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
