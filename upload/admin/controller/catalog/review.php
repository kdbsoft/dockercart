<?php
class ControllerCatalogReview extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review');

		$this->getList();
	}

	public function add() {
		$this->load->language('catalog/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_review->addReview($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_author'])) {
				$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('catalog/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_catalog_review->editReview($this->request->get['review_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_author'])) {
				$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function copy() {
		$this->load->language('catalog/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review');

		$review_ids = [];

		if (isset($this->request->post['selected'])) {
			$review_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['review_id'])) {
			$review_ids = [(int) $this->request->get['review_id']];
		}

		if ($review_ids && $this->validateCopy()) {
			foreach ($review_ids as $review_id) {
				$this->model_catalog_review->copyReview((int) $review_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_author'])) {
				$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	public function delete() {
		$this->load->language('catalog/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/review');

		$review_ids = [];

		if (isset($this->request->post['selected'])) {
			$review_ids = $this->request->post['selected'];
		} elseif (isset($this->request->get['review_id'])) {
			$review_ids = [(int) $this->request->get['review_id']];
		}

		if ($review_ids && $this->validateDelete()) {
			foreach ($review_ids as $review_id) {
				$this->model_catalog_review->deleteReview((int) $review_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['filter_product'])) {
				$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_author'])) {
				$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
		if (isset($this->request->get['filter_product'])) {
			$filter_product = $this->request->get['filter_product'];
		} else {
			$filter_product = '';
		}

		if (isset($this->request->get['filter_author'])) {
			$filter_author = $this->request->get['filter_author'];
		} else {
			$filter_author = '';
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		if (isset($this->request->get['filter_replies'])) {
			$filter_replies = $this->request->get['filter_replies'];
		} else {
			$filter_replies = '';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'r.date_added';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_author'])) {
			$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_replies'])) {
			$url .= '&filter_replies=' . $this->request->get['filter_replies'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['add'] = $this->url->link('catalog/review/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['copy'] = $this->url->link('catalog/review/copy', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('catalog/review/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		// Per-admin saved filters (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('review');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'review');

		$tab_counts = array(
			'all' => $this->model_catalog_review->getTotalReviews(array())
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_catalog_review->getTotalReviews($this->buildReviewFilterData($saved['conditions']));
		}

		$data['user_filter'] = $this->renderUserFilter('review', 'catalog/review', array(
			array('key' => 'product', 'label' => $this->language->get('entry_product'), 'type' => 'text'),
			array('key' => 'author', 'label' => $this->language->get('entry_author'), 'type' => 'text'),
			array('key' => 'status', 'label' => $this->language->get('entry_status'), 'type' => 'status'),
			array('key' => 'date_added', 'label' => $this->language->get('entry_date_added'), 'type' => 'date'),
			array('key' => 'replies', 'label' => $this->language->get('filter_replies'), 'type' => 'select', 'options' => array(
				''        => $this->language->get('text_filter_replies_all'),
				'any'     => $this->language->get('text_filter_replies_any'),
				'pending' => $this->language->get('text_filter_replies_pending'),
			)),
		), $tab_counts);

		$data['active_filter'] = $active_filter;

		$data['reviews'] = array();

		$filter_data = array(
			'filter_product'    => $filter_product,
			'filter_author'     => $filter_author,
			'filter_status'     => $filter_status,
			'filter_date_added' => $filter_date_added,
			'filter_replies'    => $filter_replies,
			'sort'              => $sort,
			'order'             => $order,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin')
		);

		if ($active_filter) {
			foreach ($this->buildReviewFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$review_total = $this->model_catalog_review->getTotalReviews($filter_data);

		$results = $this->model_catalog_review->getReviews($filter_data);

		foreach ($results as $result) {
			$data['reviews'][] = array(
				'review_id'       => $result['review_id'],
				'name'            => $result['name'],
				'author'          => $result['author'],
				'author_raw'      => $result['author'],
				'rating'          => ReviewRating::format((float)$result['rating']),
				'rating_raw'      => (string)(int)$result['rating'],
				'verified'        => (int)$result['verified'],
				'has_media'       => ((int)$result['image_count'] + (int)$result['video_count']) > 0,
				'likes'           => (int)$result['likes'],
				'dislikes'        => (int)$result['dislikes'],
				'reply_count'     => (int)$result['reply_count'],
				'reply_pending_count' => (int)$result['reply_pending_count'],
				'status'          => ($result['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'status_raw'      => $result['status'],
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'date_added_raw'  => $result['date_added'],
				'edit'            => $this->url->link('catalog/review/edit', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . $result['review_id'] . $url, true),
				'copy'            => $this->url->link('catalog/review/copy', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . $result['review_id'] . $url, true),
				'delete'          => $this->url->link('catalog/review/delete', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . $result['review_id'] . $url, true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

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

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_author'])) {
			$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_product'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . '&sort=pd.name' . $url, true);
		$data['sort_author'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . '&sort=r.author' . $url, true);
		$data['sort_rating'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . '&sort=r.rating' . $url, true);
		$data['sort_status'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . '&sort=r.status' . $url, true);
		$data['sort_date_added'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . '&sort=r.date_added' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_author'])) {
			$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $review_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($review_total - $this->config->get('config_limit_admin'))) ? $review_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $review_total, ceil($review_total / $this->config->get('config_limit_admin')));

		$data['filter_product'] = $filter_product;
		$data['filter_author'] = $filter_author;
		$data['filter_status'] = $filter_status;
		$data['filter_date_added'] = $filter_date_added;
		$data['filter_replies'] = $filter_replies;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['review_settings'] = $this->url->link('catalog/review_setting', 'user_token=' . $this->session->data['user_token'], true);

		// Replies
		$data['reply_url'] = $this->url->link('catalog/review/reply', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete_reply_url'] = $this->url->link('catalog/review/deleteReply', 'user_token=' . $this->session->data['user_token'], true);
		$data['update_reply_field_url'] = $this->url->link('catalog/review/updateReplyField', 'user_token=' . $this->session->data['user_token'], true);
		$data['text_reply_admin_badge'] = $this->language->get('text_reply_admin_badge');
		$data['text_reply_pending'] = $this->language->get('text_reply_pending');
		$data['entry_reply_text'] = $this->language->get('entry_reply_text');
		$data['button_reply'] = $this->language->get('button_reply');
		$data['text_confirm_delete_reply'] = $this->language->get('text_confirm_delete_reply');
		$data['column_replies'] = $this->language->get('column_replies');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/review_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['review_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		$data['text_form_subtitle'] = !isset($this->request->get['review_id'])
		    ? $this->language->get('text_add_review_subtitle')
		    : $this->language->get('text_edit_review_subtitle');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['product'])) {
			$data['error_product'] = $this->error['product'];
		} else {
			$data['error_product'] = '';
		}

		if (isset($this->error['author'])) {
			$data['error_author'] = $this->error['author'];
		} else {
			$data['error_author'] = '';
		}

		if (isset($this->error['text'])) {
			$data['error_text'] = $this->error['text'];
		} else {
			$data['error_text'] = '';
		}

		if (isset($this->error['rating'])) {
			$data['error_rating'] = $this->error['rating'];
		} else {
			$data['error_rating'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_product'])) {
			$url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_author'])) {
			$url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		if (!isset($this->request->get['review_id'])) {
			$data['action'] = $this->url->link('catalog/review/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('catalog/review/edit', 'user_token=' . $this->session->data['user_token'] . '&review_id=' . $this->request->get['review_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('catalog/review', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['review_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$review_info = $this->model_catalog_review->getReview($this->request->get['review_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('catalog/product');

		if (isset($this->request->post['product_id'])) {
			$data['product_id'] = $this->request->post['product_id'];
		} elseif (!empty($review_info)) {
			$data['product_id'] = $review_info['product_id'];
		} else {
			$data['product_id'] = '';
		}

		if (isset($this->request->post['product'])) {
			$data['product'] = $this->request->post['product'];
		} elseif (!empty($review_info)) {
			$data['product'] = $review_info['product'];
		} else {
			$data['product'] = '';
		}

		if (isset($this->request->post['author'])) {
			$data['author'] = $this->request->post['author'];
		} elseif (!empty($review_info)) {
			$data['author'] = $review_info['author'];
		} else {
			$data['author'] = '';
		}

		if (isset($this->request->post['text'])) {
			$data['text'] = $this->request->post['text'];
		} elseif (!empty($review_info)) {
			$data['text'] = $review_info['text'];
		} else {
			$data['text'] = '';
		}

		if (isset($this->request->post['rating'])) {
			$data['rating'] = $this->request->post['rating'];
		} elseif (!empty($review_info)) {
			// oc_review.rating is decimal(3,1); whole ratings arrive as "5.0" — cast to int
			$data['rating'] = (string)(int)$review_info['rating'];
		} else {
			$data['rating'] = '';
		}

		if (isset($this->request->post['date_added'])) {
			$data['date_added'] = $this->request->post['date_added'];
		} elseif (!empty($review_info)) {
			$data['date_added'] = ($review_info['date_added'] != '0000-00-00 00:00' ? $review_info['date_added'] : '');
		} else {
			$data['date_added'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($review_info)) {
			$data['status'] = $review_info['status'];
		} else {
			$data['status'] = '';
		}

		if (isset($this->request->post['verified'])) {
			$data['verified'] = $this->request->post['verified'];
		} elseif (!empty($review_info)) {
			$data['verified'] = $review_info['verified'];
		} else {
			$data['verified'] = 0;
		}

		// Criteria (resolved from the product's category chain or the review's group)
		$this->load->model('catalog/review_criteria');
		$this->load->model('tool/image');

		$criteria_group_id = 0;

		if (!empty($review_info['criteria_group_id'])) {
			$criteria_group_id = (int)$review_info['criteria_group_id'];
		}

		if ($criteria_group_id <= 0 && !empty($data['product_id'])) {
			$criteria_group_id = $this->model_catalog_review_criteria->getProductCriteriaGroupId((int)$data['product_id']);
		}

		if ($criteria_group_id <= 0) {
			$criteria_group_id = $this->model_catalog_review_criteria->getDefaultGroupId();
		}

		$data['criteria_group_id'] = $criteria_group_id;
		$data['criteria'] = array();

		$criteria_items = $this->model_catalog_review_criteria->getCriteria($criteria_group_id);

		foreach ($criteria_items as $item) {
			if (isset($this->request->post['criteria'][$item['criteria_id']])) {
				$value = $this->request->post['criteria'][$item['criteria_id']];
			} elseif (!empty($review_info['criteria_values']) && isset($review_info['criteria_values'][$item['criteria_id']])) {
				$value = $review_info['criteria_values'][$item['criteria_id']];
			} else {
				$value = '';
			}

			$data['criteria'][] = array(
				'criteria_id' => $item['criteria_id'],
				'type'        => $item['type'],
				'name'        => $item['name'],
				'is_required' => (int)$item['is_required'],
				'value'       => $value,
			);
		}

		// Images (max 3)
		$data['review_images'] = array();

		$images = array();

		if (isset($this->request->post['review_image'])) {
			$images = $this->request->post['review_image'];
		} elseif (!empty($review_info['images'])) {
			$images = array_column($review_info['images'], 'image');
		}

		foreach ($images as $image) {
			$image = (string)$image;

			$data['review_images'][] = array(
				'image' => $image,
				'thumb' => $image && is_file(DIR_IMAGE . $image) ? $this->model_tool_image->resize($image, 100, 100) : '',
			);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		// Video
		if (isset($this->request->post['review_video_type'])) {
			$data['review_video_type'] = $this->request->post['review_video_type'];
		} elseif (!empty($review_info['videos'][0]['video_type'])) {
			$data['review_video_type'] = $review_info['videos'][0]['video_type'];
		} else {
			$data['review_video_type'] = 'youtube';
		}

		if (isset($this->request->post['review_video'])) {
			$data['review_video'] = $this->request->post['review_video'];
		} elseif (!empty($review_info['videos'][0]['video'])) {
			$data['review_video'] = $review_info['videos'][0]['video'];
		} else {
			$data['review_video'] = '';
		}

		$data['text_form'] = !isset($this->request->get['review_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/review_form', $data));
	}

	/**
	 * AJAX: full review details for the expandable row in the review list.
	 */
	public function info() {
		$json = array();

		if (!$this->user->hasPermission('access', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error']) && !isset($this->request->get['review_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$this->load->language('catalog/review');

			$this->load->model('catalog/review');
			$this->load->model('tool/image');

			$review_info = $this->model_catalog_review->getReview((int)$this->request->get['review_id']);

			if (!$review_info) {
				$json['error'] = 'Review not found';
			} else {
				$json['review_id'] = (int)$review_info['review_id'];
				$json['product'] = $review_info['product'];
				$json['text'] = nl2br(htmlspecialchars($review_info['text'], ENT_QUOTES, 'UTF-8'));
				$json['likes'] = (int)$review_info['likes'];
				$json['dislikes'] = (int)$review_info['dislikes'];
				$json['criteria'] = array();

				foreach ((array)$review_info['criteria_values'] as $criteria_id => $value) {
					$name = (string)$criteria_id;

					$criteria = $this->db->query("SELECT name FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id = '" . (int)$criteria_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($criteria->num_rows) {
						$name = $criteria->row['name'];
					}

					$json['criteria'][] = array(
						'name'  => $name,
						'value' => $value,
					);
				}

				$json['images'] = array();

				foreach ((array)$review_info['images'] as $image) {
					$json['images'][] = array(
						'thumb' => $this->model_tool_image->resize($image['image'], 120, 120),
						'popup' => $this->model_tool_image->resize($image['image'], 900, 900),
					);
				}

				$json['video'] = '';

				if (!empty($review_info['videos'][0]['video'])) {
					$json['video'] = $review_info['videos'][0]['video'];
				}

				$json['replies'] = array();

				foreach ($this->model_catalog_review->getReplies((int)$review_info['review_id']) as $reply) {
					$json['replies'][] = array(
						'reply_id'        => (int)$reply['review_id'],
						'author'          => $reply['author'],
						'author_is_admin' => (int)$reply['author_is_admin'] === 1,
						'text'            => nl2br(htmlspecialchars($reply['text'], ENT_QUOTES, 'UTF-8')),
						'status'          => (int)$reply['status'],
						'date_added'      => date($this->language->get('date_format_short'), strtotime($reply['date_added'])),
					);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateField() {
		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['review_id']) || !isset($this->request->post['field']) || !isset($this->request->post['value'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$review_id = (int)$this->request->post['review_id'];
			$field = $this->request->post['field'];
			$value = $this->request->post['value'];

			$this->load->model('catalog/review');

			if ($field === 'author') {
				$val = trim((string)$value);

				if (utf8_strlen($val) < 3 || utf8_strlen($val) > 64) {
					$json['error'] = $this->language->get('error_author');
				} else {
					$this->model_catalog_review->updateReviewField($review_id, array('author' => $val));
					$json['success'] = true;
					$json['value_html'] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
				}
			} elseif ($field === 'rating') {
				$val = (float)$value;

				if ($val < 1 || $val > 5 || $val != (int)$val) {
					$json['error'] = $this->language->get('error_rating');
				} else {
					$this->model_catalog_review->updateReviewField($review_id, array('rating' => $val));
					$json['success'] = true;
					$json['value_html'] = ReviewRating::format($val);
				}
			} elseif ($field === 'verified') {
				$val = (int)$value;

				if ($val !== 0 && $val !== 1) {
					$json['error'] = 'Invalid verified value';
				} else {
					$this->model_catalog_review->updateReviewField($review_id, array('verified' => $val));
					$json['success'] = true;
					$json['value_html'] = $val ? $this->language->get('text_verified') : $this->language->get('text_not_verified');
				}
			} elseif ($field === 'status') {
				$val = (int)$value;

				if ($val !== 0 && $val !== 1) {
					$json['error'] = 'Invalid status value';
				} else {
					$this->model_catalog_review->updateReviewField($review_id, array('status' => $val));
					$json['success'] = true;
					$json['value_html'] = $val ? $this->language->get('text_enabled') : $this->language->get('text_disabled');
				}
			} elseif ($field === 'date_added') {
				$val = trim((string)$value);

				if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $val)) {
					$json['error'] = $this->language->get('error_invalid_date');
				} else {
					$this->model_catalog_review->updateReviewField($review_id, array('date_added' => $val));
					$json['success'] = true;
					$json['value_html'] = date($this->language->get('date_format_short'), strtotime($val));
				}
			} else {
				$json['error'] = 'Invalid field';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: post an admin reply to a review. Replies are always published.
	 */
	public function reply() {
		$this->load->language('catalog/review');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['review_id']) || !isset($this->request->post['text'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$review_id = (int)$this->request->post['review_id'];
			$text = trim((string)$this->request->post['text']);

			$min_length = (int)$this->config->get('config_review_reply_min_length');
			$max_length = (int)$this->config->get('config_review_reply_max_length');

			if ($min_length < 1) {
				$min_length = 2;
			}

			if ($max_length < $min_length) {
				$max_length = 1000;
			}

			if (utf8_strlen($text) < $min_length || utf8_strlen($text) > $max_length) {
				$json['error'] = $this->language->get('error_reply_text');
			} else {
				$this->load->model('catalog/review');

				$author = (string)$this->config->get('config_review_reply_author_name');

				if ($author === '') {
					$author = $this->config->get('config_name');
				}

				$reply_id = $this->model_catalog_review->addReply($review_id, array(
					'author' => $author,
					'text'   => $text,
				));

				if ($reply_id <= 0) {
					$json['error'] = $this->language->get('error_reply_not_found');
				} else {
					$reply_info = $this->model_catalog_review->getReplies($review_id);

					$reply = array();

					foreach ($reply_info as $row) {
						if ((int)$row['review_id'] === $reply_id) {
							$reply = array(
								'reply_id'        => (int)$row['review_id'],
								'author'          => $row['author'],
								'author_is_admin' => (int)$row['author_is_admin'] === 1,
								'text'            => nl2br(htmlspecialchars($row['text'], ENT_QUOTES, 'UTF-8')),
								'status'          => (int)$row['status'],
								'date_added'      => date($this->language->get('date_format_short'), strtotime($row['date_added'])),
							);
							break;
						}
					}

					$json['success'] = $this->language->get('text_reply_added');
					$json['reply'] = $reply;
					$json['counts'] = $this->model_catalog_review->getReplyCounts($review_id);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: delete a single reply.
	 */
	public function deleteReply() {
		$this->load->language('catalog/review');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['reply_id'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$reply_id = (int)$this->request->post['reply_id'];

			$this->load->model('catalog/review');

			$parent = $this->db->query("SELECT parent_id FROM " . DB_PREFIX . "review WHERE review_id = '" . $reply_id . "' LIMIT 1");

			if (!$parent->num_rows || !$parent->row['parent_id']) {
				$json['error'] = $this->language->get('error_reply_not_found');
			} else {
				$parent_id = (int)$parent->row['parent_id'];

				$this->model_catalog_review->deleteReview($reply_id);

				$json['success'] = $this->language->get('text_reply_deleted');
				$json['counts'] = $this->model_catalog_review->getReplyCounts($parent_id);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: toggle a reply's status.
	 */
	public function updateReplyField() {
		$this->load->language('catalog/review');

		$json = array();

		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['reply_id']) || !isset($this->request->post['status'])) {
			$json['error'] = 'Invalid request';
		}

		if (!isset($json['error'])) {
			$reply_id = (int)$this->request->post['reply_id'];
			$status = (int)$this->request->post['status'];

			if ($status !== 0 && $status !== 1) {
				$json['error'] = 'Invalid status value';
			} else {
				$this->load->model('catalog/review');

				$parent = $this->db->query("SELECT parent_id FROM " . DB_PREFIX . "review WHERE review_id = '" . $reply_id . "' LIMIT 1");

				if (!$parent->num_rows || !$parent->row['parent_id']) {
					$json['error'] = $this->language->get('error_reply_not_found');
				} else {
					$parent_id = (int)$parent->row['parent_id'];

					$this->model_catalog_review->updateReplyField($reply_id, array('status' => $status));

					$json['success'] = $this->language->get('text_reply_status_updated');
					$json['value_html'] = $status ? $this->language->get('text_enabled') : $this->language->get('text_disabled');
					$json['counts'] = $this->model_catalog_review->getReplyCounts($parent_id);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['product_id']) {
			$this->error['product'] = $this->language->get('error_product');
		}

		if ((utf8_strlen($this->request->post['author']) < 3) || (utf8_strlen($this->request->post['author']) > 64)) {
			$this->error['author'] = $this->language->get('error_author');
		}

		if (utf8_strlen($this->request->post['text']) < 1) {
			$this->error['text'] = $this->language->get('error_text');
		}

		if (!isset($this->request->post['rating']) || !is_numeric($this->request->post['rating']) || (float)$this->request->post['rating'] < 1 || (float)$this->request->post['rating'] > 5 || (float)$this->request->post['rating'] != (int)$this->request->post['rating']) {
			$this->error['rating'] = $this->language->get('error_rating');
		}

		return !$this->error;
	}

	protected function validateCopy() {
		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'catalog/review')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Convert saved filter conditions into review model filter_data.
	 */
	private function buildReviewFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'product':
					$data['filter_product'] = (string)$value;
					break;
				case 'author':
					$data['filter_author'] = (string)$value;
					break;
				case 'status':
					$data['filter_status'] = (string)$value;
					break;
				case 'date_added':
					$data['filter_date_added'] = (string)$value;
					break;
				case 'replies':
					$data['filter_replies'] = (string)$value;
					break;
			}
		}

		return $data;
	}
}
