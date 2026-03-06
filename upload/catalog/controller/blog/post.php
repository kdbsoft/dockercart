<?php
/**
 * DockerCart Blog - Post Catalog Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Frontend controller for displaying individual blog posts.
 *              Handles post viewing, comment submission, view counting, SEO.
 */

class ControllerBlogPost extends Controller {

	/**
	 * Display single blog post
	 */
	public function index() {
		$this->load->language('blog/post');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_comment');
		$this->load->model('tool/image');

		$data['show_author'] = (bool)$this->config->get('module_dockercart_blog_show_author');
		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');
		$data['show_views'] = (bool)$this->config->get('module_dockercart_blog_show_views');

		// Get post ID from request
		$post_id = 0;
		
		if (isset($this->request->get['blog_post_id'])) {
			$post_id = (int)$this->request->get['blog_post_id'];
		} elseif (isset($this->request->get['post_id'])) {
			$post_id = (int)$this->request->get['post_id'];
		}

		// Get post data
		$post_info = $this->model_extension_module_dockercart_blog_post->getPost($post_id);

		if ($post_info && $post_info['status']) {
			// Increment view counter
			$this->model_extension_module_dockercart_blog_post->incrementViews($post_id);
			
			// Update views in current data (for display)
			$post_info['views']++;

			// Set page title and meta
			$this->document->setTitle($post_info['meta_title'] ? $post_info['meta_title'] : $post_info['name']);
			$this->document->setDescription($post_info['meta_description']);
			$this->document->setKeywords($post_info['meta_keyword']);
			$this->document->addLink($this->url->link('blog/post', 'blog_post_id=' . $post_id), 'canonical');

			// Breadcrumbs
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				// Use root path to match product category behaviour and avoid duplicate insertion by header JS
				'href' => $this->url->link('common/home')
			);
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_blog'),
				'href' => $this->url->link('blog/category')
			);
			$data['breadcrumbs'][] = array(
				'text' => $post_info['name']
			);

			// Prepare post data
			$data['post_id'] = $post_info['post_id'];
			$data['heading_title'] = $post_info['name'];
			$data['name'] = $post_info['name'];
			$data['description'] = html_entity_decode($post_info['description'], ENT_QUOTES, 'UTF-8');
			$data['content'] = html_entity_decode($post_info['content'], ENT_QUOTES, 'UTF-8');
			$data['date_published'] = $data['show_date'] ? date($this->language->get('date_format_short'), strtotime($post_info['date_published'])) : '';
			$data['date_published_iso'] = !empty($post_info['date_published']) ? date('c', strtotime($post_info['date_published'])) : '';
			$data['date_modified_iso'] = !empty($post_info['date_modified']) ? date('c', strtotime($post_info['date_modified'])) : $data['date_published_iso'];
			$data['views'] = $data['show_views'] ? (int)$post_info['views'] : 0;
			$data['schema_post_url'] = $this->url->link('blog/post', 'blog_post_id=' . $post_id);

			// Author
			if ($data['show_author']) {
				$data['author'] = $post_info['author_name'];
				$data['author_url'] = $this->url->link('blog/author', 'author_id=' . $post_info['author_id']);
			} else {
				$data['author'] = '';
				$data['author_url'] = '';
			}

			// Image
			if ($post_info['image']) {
				$data['image'] = $this->model_tool_image->resize($post_info['image'], 800, 600);
				$data['thumb'] = $this->model_tool_image->resize($post_info['image'], 400, 300);
			} else {
				$data['image'] = '';
				$data['thumb'] = '';
			}

			// Categories
			$data['categories'] = array();
			$categories = $this->model_extension_module_dockercart_blog_post->getPostCategories($post_id);
			foreach ($categories as $category) {
				$data['categories'][] = array(
					'name' => $category['name'],
					'href' => $this->url->link('blog/category', 'category_id=' . $category['category_id'])
				);
			}

			// Tags
			$data['tags'] = array();
			if ($post_info['tags']) {
				$tags = explode(',', $post_info['tags']);
				foreach ($tags as $tag) {
					$data['tags'][] = array(
						'name' => trim($tag),
						'href' => $this->url->link('blog/search', 'tag=' . trim($tag))
					);
				}
			}

			// Comments
			$data['allow_comments'] = $post_info['allow_comments'] && $this->config->get('module_dockercart_blog_allow_comments');
			
			if ($data['allow_comments']) {
				$data['comments'] = array();
				
				$results = $this->model_extension_module_dockercart_blog_comment->getCommentsByPostId($post_id);
				
				foreach ($results as $result) {
					$data['comments'][] = array(
						'author'     => $result['author'],
						'text'       => nl2br($result['text']),
						'rating'     => $result['rating'],
						'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
					);
				}

				// Comment form
				$data['comment_form'] = $this->url->link('blog/post/addComment', 'blog_post_id=' . $post_id);
				$data['captcha'] = $this->config->get('module_dockercart_blog_captcha');
			}

			// Render template
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			// Text strings
			$data['text_blog_post'] = $this->language->get('text_blog_post');
			$data['text_tags'] = $this->language->get('text_tags');
			$data['text_blog'] = $this->language->get('text_blog');
			$data['text_categories'] = $this->language->get('text_categories');

			$this->response->setOutput($this->load->view('blog/post', $data));
		} else {
			// Post not found - show 404
			$this->load->language('error/not_found');

			$this->document->setTitle($this->language->get('heading_title'));

			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('error/not_found')
			);

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	/**
	 * Add comment to post (AJAX endpoint)
	 */
	public function addComment() {
		$this->load->language('extension/module/dockercart_blog');
		$this->load->model('extension/module/dockercart_blog_comment');

		$json = array();

		// Validate request
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			// Validate CAPTCHA if enabled
			if ($this->config->get('module_dockercart_blog_captcha')) {
				// Implement CAPTCHA validation here
			}

			// Validate required fields
			if (!isset($this->request->post['name']) || (utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
				$json['error'] = $this->language->get('error_name');
			}

			if (!isset($this->request->post['email']) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$json['error'] = $this->language->get('error_email');
			}

			if (!isset($this->request->post['text']) || (utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
				$json['error'] = $this->language->get('error_text');
			}

			if (!isset($json['error'])) {
				// Add comment
				$comment_data = array(
					'post_id'     => (int)$this->request->get['blog_post_id'],
					'customer_id' => $this->customer->isLogged() ? $this->customer->getId() : 0,
					'author'      => $this->request->post['name'],
					'email'       => $this->request->post['email'],
					'text'        => $this->request->post['text'],
					'rating'      => isset($this->request->post['rating']) ? (int)$this->request->post['rating'] : 0,
					'status'      => $this->config->get('module_dockercart_blog_moderate_comments') ? 0 : 1,
					'ip'          => $this->request->server['REMOTE_ADDR']
				);

				$this->model_extension_module_dockercart_blog_comment->addComment($comment_data);

				if ($this->config->get('module_dockercart_blog_moderate_comments')) {
					$json['success'] = $this->language->get('text_comment_awaiting_moderation');
				} else {
					$json['success'] = $this->language->get('text_comment_added');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
