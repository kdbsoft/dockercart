<?php
/**
 * DockerCart Blog - Latest Posts Module Controller
 * 
 * @package    DockerCart Blog
 * @version    1.0.0
 * @author     DockerCart Team
 * 
 * Description: Renders latest blog posts section matching index.html design
 */

class ControllerExtensionModuleDockercartBlogLatest extends Controller {

	/**
	 * Index method - render latest blog posts module
	 * 
	 * @param array $setting Module settings
	 * @return string Rendered HTML
	 */
	public function index($setting) {
		$this->load->language('extension/module/dockercart_blog_latest');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('extension/module/dockercart_blog_category');
		$this->load->model('tool/image');

		$data['show_author'] = (bool)$this->config->get('module_dockercart_blog_show_author');
		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');
		$data['show_views'] = (bool)$this->config->get('module_dockercart_blog_show_views');

		$data['heading_title'] = isset($setting['heading_title']) ? $setting['heading_title'] : $this->language->get('heading_title');
		$data['posts'] = array();

		// Get limit from settings, default to 3 for homepage display
		$limit = isset($setting['limit']) ? (int)$setting['limit'] : 3;
		if ($limit < 1) {
			$limit = 3;
		}

		// Fetch latest posts
		$filter_data = array(
			'sort'  => 'bp.date_published',
			'order' => 'DESC',
			'start' => 0,
			'limit' => $limit
		);

		if (!empty($setting['category_id'])) {
			$filter_data['filter_category_id'] = (int)$setting['category_id'];
		}

		$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);

		// Category colors palette
		$colors = array(
			'bg-blue-500',
			'bg-teal-500',
			'bg-indigo-500',
			'bg-purple-500',
			'bg-pink-500',
			'bg-rose-500',
			'bg-orange-500',
			'bg-amber-500',
			'bg-cyan-500',
			'bg-green-500'
		);

		foreach ($posts as $post) {
			// Get image - use 800x416 for blog cards
			if ($post['image']) {
				$image = $this->model_tool_image->resize($post['image'], 800, 416);
			} else {
				$image = $this->model_tool_image->resize('placeholder.png', 800, 416);
			}

			// Format date according to current language
			$date_published = date($this->language->get('date_format_short'), strtotime($post['date_published']));

			// Get category if available and assign consistent color
			$category           = '';
			$category_color     = $colors[0]; // Default to first color
			$category_info      = null;
			$primary_category_id = 0;
			
			$post_categories = $this->model_extension_module_dockercart_blog_post->getPostCategories($post['post_id']);

			if (!empty($post_categories)) {
				$primary_category_id = (int)$post_categories[0]['category_id'];
				$category_info = $this->model_extension_module_dockercart_blog_category->getCategory($primary_category_id);
				if ($category_info) {
					$category = $category_info['name'];
					// Assign color based on category_id (consistent across pages)
					$category_color = $colors[$primary_category_id % count($colors)];
				}
			}

			// Prepare description - use as short_description
			$description = isset($post['description']) ? $post['description'] : '';
			$description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
			$description = trim(strip_tags($description));

			if (utf8_strlen($description) > 150) {
				$description = utf8_substr($description, 0, 147) . '...';
			}

			if ($description === '') {
				$description = utf8_substr(trim(strip_tags(html_entity_decode($post['name'], ENT_QUOTES, 'UTF-8'))), 0, 150);
			}

			// Prepare post URL
			$post_url = $this->url->link('blog/post', 'blog_post_id=' . $post['post_id']);

			$data['posts'][] = array(
				'post_id'            => $post['post_id'],
				'name'               => $post['name'],
				'image'              => $image,
				'category'           => $category,
				'category_href'      => $category_info ? $this->url->link('blog/category', 'category_id=' . $primary_category_id) : '',
				'category_color'     => $category_color,
				'date_published'     => $data['show_date'] ? $date_published : '',
				'description'        => $description,
				'author'             => $data['show_author'] ? (!empty($post['author_name']) ? $post['author_name'] : 'Admin') : '',
				'author_url'         => $data['show_author'] && !empty($post['author_id']) ? $this->url->link('blog/author', 'author_id=' . (int)$post['author_id']) : '',
				'views'              => $data['show_views'] ? (int)$post['views'] : 0,
				'href'               => $post_url
			);
		}

		$data['blog_url'] = $this->url->link('blog/category');

		// Text strings
		$data['text_all_articles'] = $this->language->get('text_all_articles');
		$data['text_read_article'] = $this->language->get('text_read_article');
		$data['text_from_our_blog'] = $this->language->get('text_from_our_blog');

		if ($data['posts']) {
			return $this->load->view('extension/module/dockercart_blog_latest', $data);
		}

		return '';
	}
}
