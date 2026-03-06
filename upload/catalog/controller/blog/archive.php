<?php
/**
 * DockerCart Blog - Archive Controller
 */

class ControllerBlogArchive extends Controller {

	public function index() {
		$this->load->language('blog/archive');
		$this->load->model('extension/module/dockercart_blog_post');
		$this->load->model('tool/image');

		$data['show_author'] = (bool)$this->config->get('module_dockercart_blog_show_author');
		$data['show_date'] = (bool)$this->config->get('module_dockercart_blog_show_date');

		if (isset($this->request->get['year'])) {
			$year = (int)$this->request->get['year'];
		} else {
			$year = date('Y');
		}

		if (isset($this->request->get['month'])) {
			$month = (int)$this->request->get['month'];
		} else {
			$month = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->document->setTitle(sprintf($this->language->get('heading_title'), $year, $month));

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
			'text' => sprintf($this->language->get('heading_title'), $year, $month)
		);

		$data['heading_title'] = sprintf($this->language->get('heading_title'), $year, $month);

		// Get posts for specific year/month
		$filter_data = array(
			'filter_year'  => $year,
			'filter_month' => $month,
			'start'        => ($page - 1) * $this->config->get('config_limit'),
			'limit'        => $this->config->get('config_limit')
		);

		$posts = $this->model_extension_module_dockercart_blog_post->getPosts($filter_data);

		$post_total_query = $this->db->query("SELECT COUNT(*) AS total 
			FROM `" . DB_PREFIX . "blog_post` 
			WHERE YEAR(date_published) = '" . (int)$year . "' 
			AND " . ($month ? "MONTH(date_published) = '" . (int)$month . "' AND " : "") . "
			status = '1' 
			AND date_published <= NOW()");

		$post_total = $post_total_query->row['total'];

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
				'author'         => $data['show_author'] && !empty($post['author_name']) ? $post['author_name'] : '',
				'date_published' => $data['show_date'] ? date($this->language->get('date_format_short'), strtotime($post['date_published'])) : '',
				'href'           => $this->url->link('blog/post', 'post_id=' . $post['post_id'])
			);
		}

		$url = '';

		if (isset($this->request->get['month'])) {
			$url .= '&month=' . $this->request->get['month'];
		}

		$pagination = new Pagination();
		$pagination->total = $post_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit');
		$pagination->url = $this->url->link('blog/archive', 'year=' . $year . $url . '&page={page}');

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($post_total) ? (($page - 1) * $this->config->get('config_limit')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit')) > ($post_total - $this->config->get('config_limit'))) ? $post_total : ((($page - 1) * $this->config->get('config_limit')) + $this->config->get('config_limit')), $post_total, ceil($post_total / $this->config->get('config_limit')));

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

		$this->response->setOutput($this->load->view('blog/archive', $data));
	}
}
