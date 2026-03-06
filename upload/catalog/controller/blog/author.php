<?php
/**
 * DockerCart Blog - Author Page Controller
 */

class ControllerBlogAuthor extends Controller {

	public function index() {
		$this->load->language('blog/author');
		$this->load->model('extension/module/dockercart_blog_author');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('tool/image');

		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');

		if (isset($this->request->get['author_id'])) {
			$author_id = (int)$this->request->get['author_id'];
		} else {
			$author_id = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = (int)$this->config->get('config_limit');
		if ($limit < 1) {
			$limit = 10;
		}

		$author_info = $this->model_extension_module_dockercart_blog_author->getAuthor($author_id);

		if ($author_info) {
			$this->document->setTitle($author_info['name']);
			$this->document->setDescription($author_info['bio']);

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
				'text' => $author_info['name']
			);

			$data['heading_title'] = $author_info['name'];
			$data['description'] = html_entity_decode($author_info['bio'], ENT_QUOTES, 'UTF-8');

			if ($author_info['image']) {
				$data['image'] = $this->model_tool_image->resize($author_info['image'], 200, 200);
			} else {
				$data['image'] = '';
			}

			$data['email'] = $author_info['email'];

			// Get posts by this author
			$filter_data = array(
				'filter_author_id' => $author_id,
				'start'            => ($page - 1) * $limit,
				'limit'            => $limit
			);

			$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);
			$post_total = $this->model_extension_module_dockercart_blog_author->getTotalPostsByAuthor($author_id);

			$data['posts'] = array();

			foreach ($posts as $post) {
				if ($post['image']) {
					$image = $this->model_tool_image->resize($post['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_related_height'));
				}

				$data['posts'][] = array(
					'post_id'        => $post['post_id'],
					'name'           => $post['name'],
					'description'    => utf8_substr(strip_tags(html_entity_decode($post['description'], ENT_QUOTES, 'UTF-8')), 0, 200) . '...',
					'image'          => $image,
					'date_published' => $data['show_date'] ? date($this->language->get('date_format_short'), strtotime($post['date_published'])) : '',
					'href'           => $this->url->link('blog/post', 'post_id=' . $post['post_id'])
				);
			}

			$pagination = new Pagination();
			$pagination->total = $post_total;
			$pagination->page = $page;
			$pagination->limit = $limit;
			$pagination->url = $this->url->link('blog/author', 'author_id=' . $author_id . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($post_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($post_total - $limit)) ? $post_total : ((($page - 1) * $limit) + $limit), $post_total, ceil($post_total / $limit));

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			// Text strings
			$data['text_read_article'] = $this->language->get('text_read_article');
			$data['text_blog'] = $this->language->get('text_blog');
			$data['text_categories'] = $this->language->get('text_categories');

			$this->response->setOutput($this->load->view('blog/author', $data));
		} else {
			$this->document->setTitle($this->language->get('text_error'));

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('blog/author', 'author_id=' . $author_id)
			);

			$data['heading_title'] = $this->language->get('text_error');
			$data['text_error'] = $this->language->get('text_error');
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
}
