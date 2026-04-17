<?php
/**
 * DockerCart Blog - Post Admin Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Admin controller for blog post management.
 *              Handles CRUD operations, validation, filtering, and pagination.
 */

class ControllerExtensionModuleDockercartBlogPost extends Controller {
	
	private $error = array();

	/**
	 * Post list page
	 */
	public function index() {
		$this->load->language('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_post');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->getList();
	}

	/**
	 * Add new post page
	 */
	public function add() {
		$this->load->language('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_post');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			// Prepare data for model
			$post_data = $this->request->post;
			
			// Convert single category_id to post_category array
			if (isset($post_data['category_id']) && $post_data['category_id']) {
				$post_data['post_category'] = array($post_data['category_id']);
			} else {
				$post_data['post_category'] = array();
			}

			// Ensure post_store is set; default to all stores if not submitted
			if (empty($post_data['post_store'])) {
				$post_data['post_store'] = $this->getAllStoreIds();
			}
			
			$this->model_extension_module_dockercart_blog_post->addPost($post_data);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	/**
	 * Edit post page
	 */
	public function edit() {
		$this->load->language('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_post');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			// Prepare data for model
			$post_data = $this->request->post;
			
			// Convert single category_id to post_category array
			if (isset($post_data['category_id']) && $post_data['category_id']) {
				$post_data['post_category'] = array($post_data['category_id']);
			} else {
				$post_data['post_category'] = array();
			}

			// Ensure post_store is set; default to all stores if not submitted
			if (empty($post_data['post_store'])) {
				$post_data['post_store'] = $this->getAllStoreIds();
			}
			
			$this->model_extension_module_dockercart_blog_post->editPost($this->request->get['post_id'], $post_data);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	/**
	 * Delete post(s)
	 */
	public function delete() {
		$this->load->language('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_post');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $post_id) {
				$this->model_extension_module_dockercart_blog_post->deletePost($post_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getList();
	}

	/**
	 * Get posts list
	 */
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
		$this->load->language('extension/module/dockercart_blog_post');

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['add'] = $this->url->link('extension/module/dockercart_blog_post/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/dockercart_blog_post/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		$data['posts'] = array();

		$filter_data = array(
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);

		$post_total = $this->model_extension_module_dockercart_blog_post->getTotalPosts($filter_data);

		$results = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);

		// Load category model for getting categories
		$this->load->model('extension/module/dockercart_blog_category');

		foreach ($results as $result) {
			// Get post categories
			$post_categories = $this->db->query("SELECT GROUP_CONCAT(bcd.name) as categories FROM `" . DB_PREFIX . "blog_post_to_category` bpc
				LEFT JOIN `" . DB_PREFIX . "blog_category` bc ON (bpc.category_id = bc.category_id)
				LEFT JOIN `" . DB_PREFIX . "blog_category_description` bcd ON (bc.category_id = bcd.category_id)
				WHERE bpc.post_id = '" . (int)$result['post_id'] . "' AND bcd.language_id = '" . (int)$this->config->get('config_language_id') . "'")->row;
			
			$category_text = !empty($post_categories['categories']) ? $post_categories['categories'] : '—';

			$data['posts'][] = array(
				'post_id'      => $result['post_id'],
				'name'         => $result['name'],
				'category'     => $category_text,
				'author'       => $result['author_name'],
				'views'        => isset($result['views']) ? $result['views'] : 0,
				'comments'     => isset($result['comment_count']) ? $result['comment_count'] : 0,
				'status'       => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'date_created' => isset($result['date_published']) && $result['date_published'] ? date('Y-m-d H:i:s', strtotime($result['date_published'])) : '—',
				'featured'     => $result['featured'] ? $this->language->get('text_yes') : $this->language->get('text_no'),
				'edit'         => $this->url->link('extension/module/dockercart_blog_post/edit', 'user_token=' . $this->session->data['user_token'] . '&post_id=' . $result['post_id'] . $url, true)
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

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_post_list', $data));
	}

	/**
	 * Get post form
	 */
	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['post_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

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
		$this->load->language('extension/module/dockercart_blog_post');
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true)
		);

		if (!isset($this->request->get['post_id'])) {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_post/add', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/dockercart_blog_post/edit', 'user_token=' . $this->session->data['user_token'] . '&post_id=' . $this->request->get['post_id'], true);
		}

		$data['cancel'] = $this->url->link('extension/module/dockercart_blog_post', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('extension/module/dockercart_blog', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->get['post_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$post_info = $this->model_extension_module_dockercart_blog_post->getPost($this->request->get['post_id']);
		}

		// Load additional models for form data
		$this->load->model('extension/module/dockercart_blog_category');
		$this->load->model('extension/module/dockercart_blog_author');
		$this->load->model('localisation/language');
		$this->load->model('setting/store');
		$this->load->model('tool/image');

		// Title/Name
		if (isset($this->request->post['post_description'])) {
			$data['post_description'] = $this->request->post['post_description'];
		} elseif (!empty($post_info)) {
			$data['post_description'] = $this->model_extension_module_dockercart_blog_post->getPostDescriptions($post_info['post_id']);
		} else {
			$data['post_description'] = array();
		}

		$data['post_description'] = $this->decodeDescriptionFields($data['post_description'], array('title', 'meta_title', 'tags'));

		// Status
		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($post_info)) {
			$data['status'] = $post_info['status'];
		} else {
			$data['status'] = 1;
		}

		// Featured
		if (isset($this->request->post['featured'])) {
			$data['featured'] = $this->request->post['featured'];
		} elseif (!empty($post_info)) {
			$data['featured'] = $post_info['featured'];
		} else {
			$data['featured'] = 0;
		}

		// Category ID
		if (isset($this->request->post['category_id'])) {
			$data['category_id'] = $this->request->post['category_id'];
		} elseif (!empty($post_info)) {
			// Post categories are stored in a separate table; fetch them and use the first one as the primary
			$post_categories = $this->model_extension_module_dockercart_blog_post->getPostCategories($post_info['post_id']);
			$data['category_id'] = !empty($post_categories) ? (int)$post_categories[0] : 0;
		} else {
			$data['category_id'] = 0;
		}

		// Author
		if (isset($this->request->post['author_id'])) {
			$data['author_id'] = $this->request->post['author_id'];
		} elseif (!empty($post_info)) {
			$data['author_id'] = $post_info['author_id'];
		} else {
			$data['author_id'] = 0;
		}

		// Image
		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($post_info)) {
			$data['image'] = $post_info['image'];
		} else {
			$data['image'] = '';
		}

		// Published date
		if (isset($this->request->post['date_published'])) {
			$data['date_published'] = $this->request->post['date_published'];
		} elseif (!empty($post_info)) {
			$data['date_published'] = $post_info['date_published'];
		} else {
			$data['date_published'] = date('Y-m-d H:i:s');
		}

		// Sort order
		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($post_info)) {
			$data['sort_order'] = $post_info['sort_order'];
		} else {
			$data['sort_order'] = 0;
		}

		// Allow comments
		if (isset($this->request->post['allow_comments'])) {
			$data['allow_comments'] = $this->request->post['allow_comments'];
		} elseif (!empty($post_info)) {
			$data['allow_comments'] = $post_info['allow_comments'];
		} else {
			$data['allow_comments'] = 1;
		}

		// Load categories for dropdown
		$data['categories'] = $this->model_extension_module_dockercart_blog_category->getCategories();

		// Load authors for dropdown
		$data['authors'] = $this->model_extension_module_dockercart_blog_author->getAuthors();

		// Load languages for tabs
		$data['languages'] = $this->model_localisation_language->getLanguages();

		// Load stores for SEO URLs
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

		if (isset($this->request->post['post_store'])) {
			$data['post_store'] = $this->request->post['post_store'];
		} elseif (!empty($post_info)) {
			$data['post_store'] = $this->model_extension_module_dockercart_blog_post->getPostStores($post_info['post_id']);
		} else {
			$data['post_store'] = $this->getAllStoreIds();
		}

		// Set placeholder for image
		$data['placeholder'] = $this->model_tool_image->resize('placeholder.png', 200, 200);

		// Load error messages for form fields
		if (isset($this->error['category'])) {
			$data['error_category'] = $this->error['category'];
		} else {
			$data['error_category'] = '';
		}

		if (isset($this->error['author'])) {
			$data['error_author'] = $this->error['author'];
		} else {
			$data['error_author'] = '';
		}

		if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = array();
		}

		if (isset($this->error['content'])) {
			$data['error_content'] = $this->error['content'];
		} else {
			$data['error_content'] = array();
		}

		// Get image thumbnail if exists
		if ($data['image']) {
			$data['thumb'] = $this->model_tool_image->resize($data['image'], 200, 200);
		} else {
			$data['thumb'] = $this->model_tool_image->resize('placeholder.png', 200, 200);
		}

		// Load SEO URLs from post_info if available
		if (isset($this->request->post['post_seo_url'])) {
			$data['post_seo_url'] = $this->request->post['post_seo_url'];
		} elseif (!empty($post_info)) {
			$data['post_seo_url'] = $this->model_extension_module_dockercart_blog_post->getPostSeoUrls($post_info['post_id']);
		} else {
			$data['post_seo_url'] = array();
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/dockercart_blog_post_form', $data));
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

	/**
	 * Validate form data
	 * 
	 * @return bool
	 */
	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_post')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// Validate post descriptions (title and content)
		if (!isset($this->request->post['post_description']) || !is_array($this->request->post['post_description']) || !count($this->request->post['post_description'])) {
			$this->error['warning'] = $this->language->get('error_description');
		} else {
			// Check individual language fields
			foreach ($this->request->post['post_description'] as $language_id => $description) {
				// Validate title
				if (empty($description['title'])) {
					if (!isset($this->error['title'])) {
						$this->error['title'] = array();
					}
					$this->error['title'][$language_id] = $this->language->get('error_title');
				} elseif (strlen($description['title']) < 1 || strlen($description['title']) > 255) {
					if (!isset($this->error['title'])) {
						$this->error['title'] = array();
					}
					$this->error['title'][$language_id] = $this->language->get('error_title');
				}

				// Validate content
				if (empty($description['content'])) {
					if (!isset($this->error['content'])) {
						$this->error['content'] = array();
					}
					$this->error['content'][$language_id] = $this->language->get('error_content');
				}
			}
		}

		// Validate category (only if form was submitted with POST data)
		if (!isset($this->request->post['category_id']) || $this->request->post['category_id'] === '' || $this->request->post['category_id'] === 0) {
			$this->error['category'] = $this->language->get('error_category');
		}

		// Validate author (only if form was submitted with POST data)
		if (!isset($this->request->post['author_id']) || $this->request->post['author_id'] === '' || $this->request->post['author_id'] === 0) {
			$this->error['author'] = $this->language->get('error_author');
		}

		return !$this->error;
	}

	/**
	 * Validate delete operation
	 * 
	 * @return bool
	 */
	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'extension/module/dockercart_blog_post')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Get all available store IDs including default store (0)
	 *
	 * @return array
	 */
	private function getAllStoreIds() {
		$this->load->model('setting/store');

		$store_ids = array(0);
		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$store_ids[] = (int)$store['store_id'];
		}

		return array_values(array_unique($store_ids));
	}
}
