<?php
class ControllerExtensionReportCustomerSearch extends Controller {

	public function index() {
		// The settings form was removed: reports are managed on the
		// Reports page (report/report). Keep the route alive for old
		// bookmarks by redirecting there.
		$this->response->redirect($this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=customer_search', true));
	}
	
	
	public function report() {
		$this->load->language('extension/report/customer_search');

		if (isset($this->request->get['filter_date_start'])) {
			$filter_date_start = $this->request->get['filter_date_start'];
		} else {
			$filter_date_start = '';
		}

		if (isset($this->request->get['filter_date_end'])) {
			$filter_date_end = $this->request->get['filter_date_end'];
		} else {
			$filter_date_end = '';
		}

		if (isset($this->request->get['filter_keyword'])) {
			$filter_keyword = $this->request->get['filter_keyword'];
		} else {
			$filter_keyword = '';
		}

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = '';
		}

		if (isset($this->request->get['filter_ip'])) {
			$filter_ip = $this->request->get['filter_ip'];
		} else {
			$filter_ip = '';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->load->model('extension/report/customer');
		$this->load->model('catalog/category');

		$data['searches'] = array();

		$filter_data = array(
			'filter_date_start' => $filter_date_start,
			'filter_date_end'   => $filter_date_end,
			'filter_keyword'    => $filter_keyword,
			'filter_customer'   => $filter_customer,
			'filter_ip'         => $filter_ip,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin')
		);

		$search_total = $this->model_extension_report_customer->getTotalCustomerSearches($filter_data);

		$results = $this->model_extension_report_customer->getCustomerSearches($filter_data);

		foreach ($results as $result) {
			$category_info = $this->model_catalog_category->getCategory($result['category_id']);

			if ($category_info) {
				$category = ($category_info['path']) ? $category_info['path'] . ' &gt; ' . $category_info['name'] : $category_info['name'];
			} else {
				$category = '';
			}

			if ($result['customer_id'] > 0) {
				$customer = sprintf($this->language->get('text_customer'), $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'], true), $result['customer']);
			} else {
				$customer = $this->language->get('text_guest');
			}

			$data['searches'][] = array(
				'keyword'     => $result['keyword'],
				'products'    => $result['products'],
				'category'    => $category,
				'customer'    => $customer,
				'ip'          => $result['ip'],
				'date_added'  => date($this->language->get('datetime_format'), strtotime($result['date_added']))
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$url = '';

		if (isset($this->request->get['filter_date_start'])) {
			$url .= '&filter_date_start=' . $this->request->get['filter_date_start'];
		}

		if (isset($this->request->get['filter_date_end'])) {
			$url .= '&filter_date_end=' . $this->request->get['filter_date_end'];
		}

		if (isset($this->request->get['filter_keyword'])) {
			$url .= '&filter_keyword=' . urlencode($this->request->get['filter_keyword']);
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode($this->request->get['filter_customer']);
		}

		if (isset($this->request->get['filter_ip'])) {
			$url .= '&filter_ip=' . $this->request->get['filter_ip'];
		}

		$pagination = new Pagination();
		$pagination->total = $search_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=customer_search' . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;
		$data['filter_keyword'] = $filter_keyword;
		$data['filter_customer'] = $filter_customer;
		$data['filter_ip'] = $filter_ip;

		return $this->load->view('extension/report/customer_search_info', $data);
	}
}
