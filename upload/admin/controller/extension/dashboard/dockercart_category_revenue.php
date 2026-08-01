<?php
class ControllerExtensionDashboardDockercartCategoryRevenue extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/dockercart_category_revenue');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_dockercart_category_revenue', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('extension/dashboard/dockercart_category_revenue', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_dockercart_category_revenue_width'])) {
			$data['dashboard_dockercart_category_revenue_width'] = $this->request->post['dashboard_dockercart_category_revenue_width'];
		} else {
			$data['dashboard_dockercart_category_revenue_width'] = $this->config->get('dashboard_dockercart_category_revenue_width');
		}

		$data['columns'] = array();

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		if (isset($this->request->post['dashboard_dockercart_category_revenue_status'])) {
			$data['dashboard_dockercart_category_revenue_status'] = $this->request->post['dashboard_dockercart_category_revenue_status'];
		} else {
			$data['dashboard_dockercart_category_revenue_status'] = $this->config->get('dashboard_dockercart_category_revenue_status');
		}

		if (isset($this->request->post['dashboard_dockercart_category_revenue_sort_order'])) {
			$data['dashboard_dockercart_category_revenue_sort_order'] = $this->request->post['dashboard_dockercart_category_revenue_sort_order'];
		} else {
			$data['dashboard_dockercart_category_revenue_sort_order'] = $this->config->get('dashboard_dockercart_category_revenue_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/dockercart_category_revenue_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/dockercart_category_revenue')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function getPeriodDates($period) {
		$dates = array();

		switch ($period) {
			case 'today':
				$dates['start'] = date('Y-m-d');
				$dates['end'] = date('Y-m-d');
				break;
			case 'week':
				$dates['start'] = date('Y-m-d', strtotime('monday this week'));
				$dates['end'] = date('Y-m-d', strtotime('sunday this week'));
				break;
			case 'month':
				$dates['start'] = date('Y-m-01');
				$dates['end'] = date('Y-m-t');
				break;
			case 'year':
				$dates['start'] = date('Y-01-01');
				$dates['end'] = date('Y-12-31');
				break;
			case 'all':
			default:
				$dates['start'] = '';
				$dates['end'] = '';
				break;
		}

		return $dates;
	}

	public function dashboard() {
		$this->load->language('extension/dashboard/dockercart_category_revenue');

		$data['text_category_revenue_subtitle'] = $this->language->get('text_category_revenue_subtitle');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('extension/dashboard/dockercart_category_revenue_info', $data);
	}

	public function ajax() {
		$this->load->language('extension/dashboard/dockercart_category_revenue');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_dc_cat_revenue_' . $this->config->get('config_admin_language') . '_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$this->load->model('extension/report/dockercart_analytics');

		$dates = $this->getPeriodDates($period);

		$filter = array(
			'limit' => 5
		);

		if ($dates['start']) {
			$filter['filter_date_start'] = $dates['start'];
			$filter['filter_date_end'] = $dates['end'];
		}

		$results = $this->model_extension_report_dockercart_analytics->getRevenueByCategory($filter);

		$totals = array_column($results, 'revenue');
		$max = $totals ? max($totals) : 0;

		$json = array();
		$json['items'] = array();

		foreach ($results as $result) {
			$name = !empty($result['category_name']) ? $result['category_name'] : $this->language->get('text_uncategorized');
			$pct = $max > 0 ? round(((float)$result['revenue'] / $max) * 100) : 0;

			$json['items'][] = array(
				'name'  => $name,
				'meta'  => (string)(int)$result['quantity_sold'] . ' ' . $this->language->get('text_pcs'),
				'value' => $this->currency->format($result['revenue'], $this->config->get('config_currency')),
				'pct'   => $pct
			);
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}
