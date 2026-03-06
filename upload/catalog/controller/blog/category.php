<?php
/**
 * DockerCart Blog - Category Catalog Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Frontend controller for displaying blog categories and post listings.
 */

class ControllerBlogCategory extends Controller {

	/**
	 * Display blog category with posts
	 */
	public function index() {
		$this->load->language('blog/category');
		$this->load->model('extension/module/dockercart_blog_category');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('tool/image');

		$data['show_author'] = (bool)$this->config->get('module_dockercart_blog_show_author');
		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');
		$data['show_views'] = (bool)$this->config->get('module_dockercart_blog_show_views');

		// Get category ID
		$category_id = 0;
		
		if (isset($this->request->get['blog_category_id'])) {
			$category_id = (int)$this->request->get['blog_category_id'];
		} elseif (isset($this->request->get['category_id'])) {
			$category_id = (int)$this->request->get['category_id'];
		}

		// Pagination
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = $this->config->get('module_dockercart_blog_posts_per_page') ? $this->config->get('module_dockercart_blog_posts_per_page') : 10;

		// Category or main blog page
		if ($category_id) {
			$category_info = $this->model_extension_module_dockercart_blog_category->getCategory($category_id);

			if ($category_info && $category_info['status']) {
				// Set page title and meta
				$this->document->setTitle($category_info['meta_title'] ? $category_info['meta_title'] : $category_info['name']);
				$this->document->setDescription($category_info['meta_description']);
				$this->document->setKeywords($category_info['meta_keyword']);

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

				// Parent categories
				$url = '';
				$category_path_raw = !empty($category_info['path']) ? $category_info['path'] : (string)$category_id;
				$path_parts = explode('_', $category_path_raw);
				foreach ($path_parts as $path_id) {
					if ($path_id != $category_id) {
						$category_path = $this->model_extension_module_dockercart_blog_category->getCategory($path_id);
						if ($category_path) {
							$url .= ($url ? '_' : '') . $path_id;
							$data['breadcrumbs'][] = array(
								'text' => $category_path['name'],
								'href' => $this->url->link('blog/category', 'path=' . $url)
							);
						}
					}
				}

				$data['breadcrumbs'][] = array(
					'text' => $category_info['name']
				);

				$data['heading_title'] = $category_info['name'];
				$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');

				// Category image
				if ($category_info['image']) {
					$data['image'] = $this->model_tool_image->resize($category_info['image'], 800, 400);
				} else {
					$data['image'] = '';
				}

				// Get posts for this category
				$filter_data = array(
					'filter_category_id' => $category_id,
					'start'              => ($page - 1) * $limit,
					'limit'              => $limit
				);

				$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);
				$total_posts = $this->model_extension_module_dockercart_blog_post->getTotalPosts($filter_data);
			} else {
				// Category not found
				$this->response->redirect($this->url->link('blog/category'));
				return;
			}
		} else {
			// Main blog page - all posts
			$this->document->setTitle($this->language->get('text_blog'));

			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_blog')
			);

			$data['heading_title'] = $this->language->get('text_blog');
			$data['description'] = '';
			$data['image'] = '';

			$filter_data = array(
				'start' => ($page - 1) * $limit,
				'limit' => $limit
			);

			$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);
			$total_posts = $this->model_extension_module_dockercart_blog_post->getTotalPosts($filter_data);
		}

		// Format posts
		$data['posts'] = array();

		foreach ($posts as $post) {
			if ($post['image']) {
				$image = $this->model_tool_image->resize($post['image'], 400, 300);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 400, 300);
			}

			$post_categories = $this->model_extension_module_dockercart_blog_post->getPostCategories($post['post_id']);
			$card_category = '';
			$card_category_href = '';

			if (!empty($post_categories)) {
				$card_category = $post_categories[0]['name'];
				$card_category_href = $this->url->link('blog/category', 'category_id=' . (int)$post_categories[0]['category_id']);
			}

			$data['posts'][] = array(
				'post_id'        => $post['post_id'],
				'name'           => $post['name'],
				'description'    => utf8_substr(strip_tags(html_entity_decode($post['description'], ENT_QUOTES, 'UTF-8')), 0, 200) . '...',
				'image'          => $image,
				'category'       => $card_category,
				'category_href'  => $card_category_href,
				'author'         => $data['show_author'] && !empty($post['author_name']) ? $post['author_name'] : '',
				'author_url'     => $data['show_author'] && !empty($post['author_id']) ? $this->url->link('blog/author', 'author_id=' . (int)$post['author_id']) : '',
				'date_published' => $data['show_date'] ? date($this->language->get('date_format_short'), strtotime($post['date_published'])) : '',
				'views'          => $data['show_views'] ? (int)$post['views'] : 0,
				'href'           => $this->url->link('blog/post', 'blog_post_id=' . $post['post_id'])
			);
		}

		$data['blog_categories'] = array();
		$data['current_category_id'] = (int)$category_id;

		$blog_categories = $this->model_extension_module_dockercart_blog_category->getCategories(0);

		foreach ($blog_categories as $blog_category) {
			$data['blog_categories'][] = array(
				'category_id' => (int)$blog_category['category_id'],
				'name'        => $blog_category['name'],
				'total'       => (int)$this->model_extension_module_dockercart_blog_category->getTotalPostsByCategory($blog_category['category_id']),
				'href'        => $this->url->link('blog/category', 'category_id=' . (int)$blog_category['category_id'])
			);
		}

		// Pagination
		$pagination = new Pagination();
		$pagination->total = $total_posts;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('blog/category', ($category_id ? 'category_id=' . $category_id . '&' : '') . 'page={page}');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($total_posts) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total_posts - $limit)) ? $total_posts : ((($page - 1) * $limit) + $limit), $total_posts, ceil($total_posts / $limit));

		// Render template
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// Text strings
		$data['text_blog'] = $this->language->get('text_blog');
		$data['text_categories'] = $this->language->get('text_categories');

		// Text strings
		$data['text_read_article'] = $this->language->get('text_read_article');

		$this->response->setOutput($this->load->view('blog/category', $data));
	}
}
