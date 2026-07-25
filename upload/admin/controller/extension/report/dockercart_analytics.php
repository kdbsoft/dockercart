<?php
class ControllerExtensionReportDockercartAnalytics extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/report/dockercart_analytics');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('report_dockercart_analytics', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/report/dockercart_analytics', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/report/dockercart_analytics', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true);

		if (isset($this->request->post['report_dockercart_analytics_status'])) {
			$data['report_dockercart_analytics_status'] = $this->request->post['report_dockercart_analytics_status'];
		} else {
			$data['report_dockercart_analytics_status'] = $this->config->get('report_dockercart_analytics_status');
		}

		if (isset($this->request->post['report_dockercart_analytics_sort_order'])) {
			$data['report_dockercart_analytics_sort_order'] = $this->request->post['report_dockercart_analytics_sort_order'];
		} else {
			$data['report_dockercart_analytics_sort_order'] = $this->config->get('report_dockercart_analytics_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/report/dockercart_analytics_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/report/dockercart_analytics')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function report() {
		$this->load->language('extension/report/dockercart_analytics');

		if (isset($this->request->get['filter_date_start'])) {
			$filter_date_start = $this->request->get['filter_date_start'];
		} else {
			$filter_date_start = date('Y-m-d', strtotime('-30 days'));
		}

		if (isset($this->request->get['filter_date_end'])) {
			$filter_date_end = $this->request->get['filter_date_end'];
		} else {
			$filter_date_end = date('Y-m-d');
		}

		if (isset($this->request->get['filter_group'])) {
			$filter_group = $this->request->get['filter_group'];
		} else {
			$filter_group = 'day';
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$filter_order_status_id = (int)$this->request->get['filter_order_status_id'];
		} else {
			$filter_order_status_id = 0;
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$this->load->model('extension/report/dockercart_analytics');

		$filter_data = array(
			'filter_date_start'        => $filter_date_start,
			'filter_date_end'          => $filter_date_end,
			'filter_group'             => $filter_group,
			'filter_order_status_id'   => $filter_order_status_id,
			'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                    => $this->config->get('config_limit_admin')
		);

		// Totals (summary cards)
		$totals = $this->model_extension_report_dockercart_analytics->getTotals($filter_data);

		$data['total_orders'] = (int)$totals['total_orders'];
		$data['completed_orders'] = (int)$totals['completed_orders'];
		$data['conversion_rate'] = $totals['conversion_rate'] . '%';
		$data['revenue'] = $this->currency->format($totals['revenue'], $this->config->get('config_currency'));
		$data['aov'] = $this->currency->format($totals['aov'], $this->config->get('config_currency'));

		// Repeat purchase rate
		$repeat = $this->model_extension_report_dockercart_analytics->getRepeatPurchaseRate($filter_data);
		$data['repeat_rate'] = $repeat['repeat_rate'] . '%';
		$data['repeat_customers'] = (int)$repeat['repeat_customers'];
		$data['total_customers'] = (int)$repeat['total_customers'];

		// Cancellation rate
		$cancel = $this->model_extension_report_dockercart_analytics->getCancellationRate($filter_data);
		$data['cancellation_rate'] = $cancel['cancellation_rate'] . '%';
		$data['cancelled_orders'] = (int)$cancel['cancelled_orders'];

		// Time-series table
		$report_total = $this->model_extension_report_dockercart_analytics->getTotalReport($filter_data);

		$results = $this->model_extension_report_dockercart_analytics->getReport($filter_data);

		$data['reports'] = array();

		foreach ($results as $result) {
			$data['reports'][] = array(
				'date_start'       => date($this->language->get('date_format_short'), strtotime($result['date_start'])),
				'date_end'         => date($this->language->get('date_format_short'), strtotime($result['date_end'])),
				'total_orders'     => (int)$result['total_orders'],
				'completed_orders'  => (int)$result['completed_orders'],
				'conversion_rate'  => $result['conversion_rate'] . '%',
				'revenue'          => $this->currency->format($result['revenue'], $this->config->get('config_currency')),
				'aov'              => $this->currency->format($result['aov'], $this->config->get('config_currency'))
			);
		}

		// Top products
		$top_filter = array(
			'filter_date_start'      => $filter_date_start,
			'filter_date_end'        => $filter_date_end,
			'filter_order_status_id' => $filter_order_status_id,
			'limit'                  => 10
		);

		$top_products = $this->model_extension_report_dockercart_analytics->getTopProducts($top_filter);

		$data['top_products'] = array();

		foreach ($top_products as $product) {
			$data['top_products'][] = array(
				'name'     => $product['name'],
				'model'    => $product['model'],
				'quantity' => (int)$product['quantity'],
				'total'    => $this->currency->format($product['total'], $this->config->get('config_currency'))
			);
		}

		// Orders by day of week
		$day_results = $this->model_extension_report_dockercart_analytics->getOrdersByDayOfWeek($filter_data);
		$days_names = array('', 'text_sunday', 'text_monday', 'text_tuesday', 'text_wednesday', 'text_thursday', 'text_friday', 'text_saturday');
		$data['orders_by_day'] = array();

		for ($i = 1; $i <= 7; $i++) {
			$found = 0;
			foreach ($day_results as $row) {
				if ((int)$row['day_of_week'] === $i) {
					$found = (int)$row['total'];
					break;
				}
			}
			$data['orders_by_day'][] = array(
				'name'  => $this->language->get($days_names[$i]),
				'total' => $found
			);
		}

		// Orders by hour
		$hour_results = $this->model_extension_report_dockercart_analytics->getOrdersByHour($filter_data);
		$hour_map = array();
		foreach ($hour_results as $row) {
			$hour_map[(int)$row['hour']] = (int)$row['total'];
		}
		$data['orders_by_hour'] = array();
		for ($h = 0; $h < 24; $h++) {
			$data['orders_by_hour'][] = array(
				'hour'  => sprintf('%02d:00', $h),
				'total' => isset($hour_map[$h]) ? $hour_map[$h] : 0
			);
		}

		// Revenue by category
		$categories = $this->model_extension_report_dockercart_analytics->getRevenueByCategory($top_filter);

		$data['categories'] = array();

		foreach ($categories as $cat) {
			$data['categories'][] = array(
				'name'         => $cat['category_name'] ? $cat['category_name'] : $this->language->get('text_uncategorized'),
				'quantity_sold' => (int)$cat['quantity_sold'],
				'revenue'       => $this->currency->format($cat['revenue'], $this->config->get('config_currency'))
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['groups'] = array();

		$data['groups'][] = array(
			'text'  => $this->language->get('text_year'),
			'value' => 'year',
		);

		$data['groups'][] = array(
			'text'  => $this->language->get('text_month'),
			'value' => 'month',
		);

		$data['groups'][] = array(
			'text'  => $this->language->get('text_week'),
			'value' => 'week',
		);

		$data['groups'][] = array(
			'text'  => $this->language->get('text_day'),
			'value' => 'day',
		);

		$url = '';

		if (isset($this->request->get['filter_date_start'])) {
			$url .= '&filter_date_start=' . $this->request->get['filter_date_start'];
		}

		if (isset($this->request->get['filter_date_end'])) {
			$url .= '&filter_date_end=' . $this->request->get['filter_date_end'];
		}

		if (isset($this->request->get['filter_group'])) {
			$url .= '&filter_group=' . $this->request->get['filter_group'];
		}

		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . $this->request->get['filter_order_status_id'];
		}

		$pagination = new Pagination();
		$pagination->total = $report_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=dockercart_analytics' . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($report_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($report_total - $this->config->get('config_limit_admin'))) ? $report_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $report_total, ceil($report_total / $this->config->get('config_limit_admin')));

		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;
		$data['filter_group'] = $filter_group;
		$data['filter_order_status_id'] = $filter_order_status_id;

		return $this->load->view('extension/report/dockercart_analytics_info', $data);
	}
}