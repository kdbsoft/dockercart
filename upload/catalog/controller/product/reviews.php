<?php
class ControllerProductReviews extends Controller {
	public function index() {
		$this->load->language('product/reviews');
		$this->load->language('product/product');

		if (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		} else {
			$product_id = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$sort = 'newest';

		if (isset($this->request->get['sort'])) {
			$sort = in_array($this->request->get['sort'], array('newest', 'highest', 'lowest'), true) ? $this->request->get['sort'] : 'newest';
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/review');
		$this->load->model('catalog/review_criteria');
		$this->load->model('tool/image');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			$data['product_id'] = $product_id;
			$data['heading_title'] = $product_info['name'];
			$data['heading_reviews'] = sprintf($this->language->get('heading_reviews'), $product_info['name']);
			$data['text_reviews_section'] = $this->language->get('text_reviews_section');
			$data['product_href'] = $this->url->link('product/product', 'product_id=' . $product_id);

			$page_url = $this->url->link('product/reviews', 'product_id=' . $product_id);

			// Rating summary + distribution
			$summary = $this->model_catalog_review->getProductRatingSummary($product_id);

			$this->load->helper('plural');

			$this->document->setTitle(sprintf($this->language->get('text_meta_title'), $product_info['name']));
			$this->document->setDescription(sprintf($this->language->get('text_meta_description'), $product_info['name'], ReviewRating::format($summary['rating']), $summary['review_count']));

			if ($page <= 1) {
				$this->document->addLink($page_url, 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/reviews', 'product_id=' . $product_id . '&page=' . ($page - 1)), 'prev');
				$this->document->addLink($this->url->link('product/reviews', 'product_id=' . $product_id . '&page=' . ($page + 1)), 'next');
			}

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $product_info['name'],
				'href' => $data['product_href']
			);

			$data['breadcrumbs'][] = array(
				'text' => review_count_label($summary['review_count'], $this->language->get('code')),
				'href' => $page_url
			);

			// Rating summary + distribution
			$data['rating_value'] = ReviewRating::format($summary['rating']);
			$data['rating'] = $summary['rating'];
			$data['review_count'] = $summary['review_count'];
			$data['rating_distribution'] = $summary['distribution'];
			$data['show_distribution'] = $this->config->get('config_review_show_distribution');

			$data['reviews'] = review_count_label($data['review_count'], $this->language->get('code'));
			$data['rating_stars'] = ReviewRating::starComponents($summary['rating']);

			$review_list = new ReviewList($this->registry);
			$fragment = $review_list->build($product_id, $page, $sort);

			$data['reviews_list'] = $fragment['reviews'];
			$data['has_more'] = $fragment['has_more'];
			$data['next_page'] = $fragment['next_page'];
			$data['show_more_label'] = $fragment['show_more_label'];
			$data['sort'] = $fragment['sort'];
			$data['sort_urls'] = $fragment['sort_urls'];
			$data['text_no_reviews'] = $fragment['text_no_reviews'];
			$data['text_be_first'] = $fragment['text_be_first'];
			$data['text_be_first_hint'] = $fragment['text_be_first_hint'];
			$data['text_leave_review'] = $fragment['text_leave_review'];
			$data['text_write'] = $fragment['text_write'];
			$data['review_total'] = $fragment['total'];
			$data['review_total_label'] = $fragment['total_label'];
			$data['vote_url'] = $fragment['vote_url'];
			$data['reply_url'] = $fragment['reply_url'];
			$data['reply_enabled'] = $fragment['reply_enabled'];
			$data['reply_min_length'] = $fragment['reply_min_length'];
			$data['reply_max_length'] = $fragment['reply_max_length'];
			$data['text_reply_count'] = $fragment['text_reply_count'];
			$data['text_replies_count'] = $fragment['text_replies_count'];
			$data['text_reply_admin_badge'] = $fragment['text_reply_admin_badge'];
			$data['text_reply_placeholder'] = $fragment['text_reply_placeholder'];
			$data['text_reply_submit'] = $fragment['text_reply_submit'];
			$data['text_reply_login_hint'] = $fragment['text_reply_login_hint'];
			$data['customer_logged'] = $this->customer->isLogged();
			$data['login_url'] = $this->url->link('account/login', '', true);

			// Write form data
			$data['review_status'] = $this->config->get('config_review_status');
			$data['review_guest'] = $this->config->get('config_review_guest') || $this->customer->isLogged();
			$data['text_login'] = sprintf($this->language->get('text_login'), $this->url->link('account/login', '', true), $this->url->link('account/register', '', true));
			$data['customer_name'] = $this->customer->isLogged() ? $this->customer->getFirstName() . '&nbsp;' . $this->customer->getLastName() : '';
			$data['customer_logged'] = $this->customer->isLogged();
			$data['review_write_url'] = $this->url->link('product/product/write', 'product_id=' . $product_id);
			$data['review_ajax_url'] = $this->url->link('product/product/review', 'product_id=' . $product_id);
			$data['reviews_url'] = $page_url;

			$criteria_group = $this->model_catalog_review_criteria->getProductCriteriaGroup($product_id);
			$criteria = $criteria_group['criteria'];

			$data['criteria'] = $criteria;
			$data['has_rating_criteria'] = ReviewCriteria::hasRatingCriteria($criteria);
			$data['review_images_enabled'] = $this->config->get('config_review_images_enabled');
			$data['review_max_images'] = (int)$this->config->get('config_review_max_images');
			$data['review_video_enabled'] = $this->config->get('config_review_video_enabled');

			$data['captcha'] = '';

			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
				$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'));
			}

			// Schema.org (Product + AggregateRating + Review list)
			$data['schema'] = array(
				'product_name'  => $product_info['name'],
				'image'         => $product_info['image'] ? $this->model_tool_image->resize($product_info['image'], 600, 600) : '',
				'sku'           => $product_info['sku'] ?? '',
				'model'         => $product_info['model'] ?? '',
				'rating'        => $data['rating_value'],
				'review_count'  => $data['review_count'],
				'url'           => $page_url,
				'reviews'       => $this->model_catalog_review->getReviewsForSchema($product_id, 10),
			);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/reviews', $data));
		} else {
			$this->load->language('product/product');

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/reviews', 'product_id=' . $product_id)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}
}
