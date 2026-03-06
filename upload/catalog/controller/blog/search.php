<?php
/**
 * DockerCart Blog - Search Catalog Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Frontend controller for blog search functionality.
 *              Handles search by tags and keywords.
 */

class ControllerBlogSearch extends Controller {

	/**
	 * Display blog search results
	 */
	public function index() {
		$this->load->language('blog/search');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_category');
		$this->load->model('tool/image');

		$data['show_author'] = (bool)$this->config->get('module_dockercart_blog_show_author');
		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');
		$data['show_views'] = (bool)$this->config->get('module_dockercart_blog_show_views');

		// Get search parameters
		$tag = '';
		$search = '';
		
		if (isset($this->request->get['tag'])) {
			$tag = trim($this->request->get['tag']);
		}
		
		if (isset($this->request->get['search'])) {
			$search = trim($this->request->get['search']);
		}

		// Pagination
		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = $this->config->get('module_dockercart_blog_posts_per_page') ? $this->config->get('module_dockercart_blog_posts_per_page') : 10;

		// Set page title
		if ($tag) {
			$this->document->setTitle(sprintf($this->language->get('heading_title_tag'), $tag));
			$heading_title = sprintf($this->language->get('heading_title_tag'), $tag);
		} elseif ($search) {
			$this->document->setTitle(sprintf($this->language->get('heading_title_search'), $search));
			$heading_title = sprintf($this->language->get('heading_title_search'), $search);
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
			$heading_title = $this->language->get('heading_title');
		}

		// Breadcrumbs
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_blog'),
			'href' => $this->url->link('blog/category')
		);
		$data['breadcrumbs'][] = array(
			'text' => $heading_title
		);

		$data['heading_title'] = $heading_title;

		// Build filter
		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		if ($tag) {
			$filter_data['filter_tag'] = $tag;
		}

		if ($search) {
			$filter_data['filter_search'] = $search;
		}

		// Get posts
		$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);
		$total_posts = $this->model_extension_module_dockercart_blog_post->getTotalPosts($filter_data);

		// Format posts
		$data['posts'] = array();

		foreach ($posts as $post) {
			if ($post['image']) {
				$image = $this->model_tool_image->resize($post['image'], 400, 300);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 400, 300);
			}

			$post_categories = $this->model_extension_module_dockercart_blog_post->getPostCategories($post['post_id']);
			$card_category      = '';
			$card_category_href = '';
			if (!empty($post_categories)) {
				$card_category      = $post_categories[0]['name'];
				$card_category_href = $this->url->link('blog/category', 'category_id=' . (int)$post_categories[0]['category_id']);
			}

			$data['posts'][] = array(
				'post_id'        => $post['post_id'],
				'name'           => $post['name'],
				'description'    => utf8_substr(strip_tags(html_entity_decode($post['description'], ENT_QUOTES, 'UTF-8')), 0, 200) . '...',
				'image'          => $image,
				'category'       => $card_category,
				'category_href'  => $card_category_href,
				'author'         => $data['show_author'] ? $post['author_name'] : '',
				'author_url'     => $data['show_author'] ? $this->url->link('blog/author', 'author_id=' . $post['author_id']) : '',
				'date_published' => $data['show_date'] ? date($this->language->get('date_format_short'), strtotime($post['date_published'])) : '',
				'views'          => $data['show_views'] ? (int)$post['views'] : 0,
				'href'           => $this->url->link('blog/post', 'blog_post_id=' . $post['post_id'])
			);
		}

		// Pagination
		$url = '';
		
		if ($tag) {
			$url .= '&tag=' . urlencode($tag);
		}
		
		if ($search) {
			$url .= '&search=' . urlencode($search);
		}

		$pagination = new Pagination();
		$pagination->total = $total_posts;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('blog/search', ltrim($url, '&') . '&page={page}');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($total_posts) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total_posts - $limit)) ? $total_posts : ((($page - 1) * $limit) + $limit), $total_posts, ceil($total_posts / $limit));

		// Blog categories for sidebar
		$data['blog_categories'] = array();
		$blog_categories = $this->model_extension_module_dockercart_blog_category->getCategories(0);
		foreach ($blog_categories as $blog_category) {
			$data['blog_categories'][] = array(
				'category_id' => (int)$blog_category['category_id'],
				'name'        => $blog_category['name'],
				'total'       => (int)$this->model_extension_module_dockercart_blog_category->getTotalPostsByCategory($blog_category['category_id']),
				'href'        => $this->url->link('blog/category', 'category_id=' . (int)$blog_category['category_id'])
			);
		}

		// Search form data
		$data['tag'] = $tag;
		$data['search'] = $search;
		$data['action'] = $this->url->link('blog/search');
		
		// Language variables
		$data['button_continue'] = $this->language->get('button_continue');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_search_placeholder'] = $this->language->get('text_search_placeholder');

		// Render template
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		// Text strings
		$data['text_read_article'] = $this->language->get('text_read_article');
		$data['text_blog'] = $this->language->get('text_blog');
		$data['text_search'] = $this->language->get('text_search');
		$data['text_categories'] = $this->language->get('text_categories');

		$this->response->setOutput($this->load->view('blog/search', $data));
	}
}
