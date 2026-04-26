<?php
/**
 * DockerCart Blog - Comment Admin Controller
 */

class ControllerExtensionModuleDockercartBlogComment extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/dockercart_blog_comment');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_comment');

		$this->getList();
	}

	public function edit() {
		$this->load->language('extension/module/dockercart_blog_comment');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_comment');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_dockercart_blog_comment->editComment($this->request->get['comment_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/dockercart_blog_comment');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_blog_comment');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $comment_id) {
				$this->model_extension_module_dockercart_blog_comment->deleteComment($comment_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function approve() {
		$this->load->language('extension/module/dockercart_blog_comment');

		$this->load->model('extension/module/dockercart_blog_comment');

		if (isset($this->request->post['selected']) && $this->validateApprove()) {
			foreach ($this->request->post['selected'] as $comment_id) {
				$comment_info = $this->model_extension_module_dockercart_blog_comment->getComment($comment_id);

				if ($comment_info) {
					$this->model_extension_module_dockercart_blog_comment->editComment($comment_id, array_merge($comment_info, array('status' => 1)));
				}
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		// Additional filters
		if (isset($this->request->get['filter_post'])) {
			$filter_post = $this->request->get['filter_post'];
		} else {
			$filter_post = '';
		}

		if (isset($this->request->get['filter_author'])) {
			$filter_author = $this->request->get['filter_author'];
		} else {
			$filter_author = '';
		}

		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = '';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_post'])) {
			$url .= '&filter_post=' . urlencode($this->request->get['filter_post']);
		}

		if (isset($this->request->get['filter_author'])) {
			$url .= '&filter_author=' . urlencode($this->request->get['filter_author']);
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode($this->request->get['filter_email']);
		}

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
		$this->load->language('extension/module/dockercart_blog_comment');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['approve'] = $this->url->link('extension/module/dockercart_blog_comment/approve', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/dockercart_blog_comment/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		$data['comments'] = array();

		$filter_data = array(
			'filter_status' => $filter_status,
			'filter_post'   => $filter_post,
			'filter_author' => $filter_author,
			'filter_email'  => $filter_email,
			'start'         => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'         => $this->config->get('config_limit_admin')
		);

		$comments = $this->model_extension_module_dockercart_blog_comment->getComments($filter_data);
		$comment_total = $this->model_extension_module_dockercart_blog_comment->getTotalComments($filter_data);

		foreach ($comments as $comment) {
			$data['comments'][] = array(
				'comment_id'  => $comment['comment_id'],
				'post_name'   => $comment['post_name'],
				'author'      => $comment['author'],
				'rating'      => $comment['rating'],
				'status'      => $comment['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'date_added'  => date($this->language->get('date_format_short'), strtotime($comment['date_added'])),
				'edit'        => $this->url->link('extension/module/dockercart_blog_comment/edit', 'user_token=' . $this->session->data['user_token'] . '&comment_id=' . $comment['comment_id'] . $url, true)
			);
		}

		// Pass filters back to template for form prefilling
		$data['filter_status'] = $filter_status;
		$data['filter_post'] = $filter_post;
		$data['filter_author'] = $filter_author;
		$data['filter_email'] = $filter_email;

		// Base URL for filter actions in JS
		$data['filter_url'] = $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'], true);

		$pagination = new Pagination();
		$pagination->total = $comment_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($comment_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($comment_total - $this->config->get('config_limit_admin'))) ? $comment_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $comment_total, ceil($comment_total / $this->config->get('config_limit_admin')));

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_comment_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = $this->language->get('text_edit');

		$url = '';

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

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
		$this->load->language('extension/module/dockercart_blog_comment');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['action'] = $this->url->link('extension/module/dockercart_blog_comment/edit', 'user_token=' . $this->session->data['user_token'] . '&comment_id=' . $this->request->get['comment_id'] . $url, true);
		$data['cancel'] = $this->url->link('extension/module/dockercart_blog_comment', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->get['comment_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$comment_info = $this->model_extension_module_dockercart_blog_comment->getComment($this->request->get['comment_id']);
		}

		if (isset($this->request->post['author'])) {
			$data['author'] = $this->request->post['author'];
		} elseif (!empty($comment_info)) {
			$data['author'] = $comment_info['author'];
		} else {
			$data['author'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} elseif (!empty($comment_info)) {
			$data['email'] = $comment_info['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['text'])) {
			$data['text'] = $this->request->post['text'];
		} elseif (!empty($comment_info)) {
			$data['text'] = $comment_info['text'];
		} else {
			$data['text'] = '';
		}

		if (isset($this->request->post['rating'])) {
			$data['rating'] = $this->request->post['rating'];
		} elseif (!empty($comment_info)) {
			$data['rating'] = $comment_info['rating'];
		} else {
			$data['rating'] = 5;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($comment_info)) {
			$data['status'] = $comment_info['status'];
		} else {
			$data['status'] = 0;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_comment_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_comment')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['author']) < 3) || (utf8_strlen($this->request->post['author']) > 64)) {
			$this->error['author'] = $this->language->get('error_author');
		}

		if ((utf8_strlen($this->request->post['text']) < 1)) {
			$this->error['text'] = $this->language->get('error_text');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_comment')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateApprove() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_comment')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
