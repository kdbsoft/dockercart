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

	/**
	 * Delta (%) between current and previous period value.
	 * Returns null when there is no meaningful previous value.
	 */
	protected function computeDelta($current, $previous) {
		if ($previous == 0) {
			return null;
		}

		return round(($current - $previous) / abs($previous) * 100, 1);
	}

	/**
	 * Build the filter data array shared by all model queries.
	 */
	protected function buildFilterData() {
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

		if (isset($this->request->get['filter_utm_medium'])) {
			$filter_utm_medium = $this->request->get['filter_utm_medium'];
		} else {
			$filter_utm_medium = '';
		}

		if (isset($this->request->get['filter_source'])) {
			$filter_source = $this->request->get['filter_source'];
		} else {
			$filter_source = '';
		}

		return array(
			'filter_date_start'      => $filter_date_start,
			'filter_date_end'        => $filter_date_end,
			'filter_group'           => $filter_group,
			'filter_order_status_id' => $filter_order_status_id,
			'filter_utm_medium'      => $filter_utm_medium,
			'filter_source'          => $filter_source
		);
	}

	public function report() {
		$this->load->language('extension/report/dockercart_analytics');

		$filter_data = $this->buildFilterData();

		$this->load->model('extension/report/dockercart_analytics');

		$currency = $this->config->get('config_currency');

		// --- Period comparison: previous period of equal length ---
		$start_ts = strtotime($filter_data['filter_date_start']);
		$end_ts = strtotime($filter_data['filter_date_end']);

		$period_days = max(1, (int)round(($end_ts - $start_ts) / 86400));

		$prev_start = date('Y-m-d', strtotime('-' . $period_days . ' days', $start_ts));
		$prev_end = date('Y-m-d', strtotime('-1 day', $start_ts));

		$prev_filter = $filter_data;
		$prev_filter['filter_date_start'] = $prev_start;
		$prev_filter['filter_date_end'] = $prev_end;

		$totals = $this->model_extension_report_dockercart_analytics->getTotals($filter_data);
		$prev_totals = $this->model_extension_report_dockercart_analytics->getTotals($prev_filter);

		// KPI cards with delta
		$kpi_defs = array(
			'revenue' => array(
				'label'  => $this->language->get('text_revenue'),
				'value'  => $this->currency->format($totals['revenue'], $currency),
				'delta'  => $this->computeDelta($totals['revenue'], $prev_totals['revenue']),
				'icon'   => 'banknote',
				'color'  => 'success'
			),
			'orders' => array(
				'label'  => $this->language->get('text_orders'),
				'value'  => (string)(int)$totals['total_orders'],
				'delta'  => $this->computeDelta($totals['total_orders'], $prev_totals['total_orders']),
				'icon'   => 'shopping-cart',
				'color'  => 'info'
			),
			'conversion' => array(
				'label'  => $this->language->get('text_conversion'),
				'value'  => $totals['conversion_rate'] . '%',
				'delta'  => $this->computeDelta($totals['conversion_rate'], $prev_totals['conversion_rate']),
				'icon'   => 'percent',
				'color'  => 'primary'
			),
			'aov' => array(
				'label'  => $this->language->get('text_aov'),
				'value'  => $this->currency->format($totals['aov'], $currency),
				'delta'  => $this->computeDelta($totals['aov'], $prev_totals['aov']),
				'icon'   => 'receipt',
				'color'  => 'warning'
			),
			'repeat' => array(
				'label'  => $this->language->get('text_repeat_rate'),
				'value'  => '',
				'delta'  => null,
				'icon'   => 'repeat',
				'color'  => 'purple'
			),
			'cancellation' => array(
				'label'  => $this->language->get('text_cancellation_rate'),
				'value'  => '',
				'delta'  => null,
				'icon'   => 'x-circle',
				'color'  => 'danger'
			)
		);

		// Repeat purchase rate
		$repeat = $this->model_extension_report_dockercart_analytics->getRepeatPurchaseRate($filter_data);
		$prev_repeat = $this->model_extension_report_dockercart_analytics->getRepeatPurchaseRate($prev_filter);

		$kpi_defs['repeat']['value'] = $repeat['repeat_rate'] . '%';
		$kpi_defs['repeat']['delta'] = $this->computeDelta($repeat['repeat_rate'], $prev_repeat['repeat_rate']);
		$kpi_defs['repeat']['sub'] = sprintf($this->language->get('text_repeat_sub'), (int)$repeat['repeat_customers'], (int)$repeat['total_customers']);

		// Cancellation rate
		$cancel = $this->model_extension_report_dockercart_analytics->getCancellationRate($filter_data);
		$prev_cancel = $this->model_extension_report_dockercart_analytics->getCancellationRate($prev_filter);

		$kpi_defs['cancellation']['value'] = $cancel['cancellation_rate'] . '%';
		$kpi_defs['cancellation']['delta'] = $this->computeDelta($cancel['cancellation_rate'], $prev_cancel['cancellation_rate']);
		$kpi_defs['cancellation']['sub'] = sprintf($this->language->get('text_cancelled_sub'), (int)$cancel['cancelled_orders'], (int)$cancel['total_orders']);

		$data['kpis'] = $kpi_defs;

		// --- Time series (revenue / orders / aov / conversion) ---
		$series = $this->model_extension_report_dockercart_analytics->getTimeSeries($filter_data);

		$data['ts_labels'] = array();
		$data['ts_revenue'] = array();
		$data['ts_orders'] = array();
		$data['ts_aov'] = array();
		$data['ts_conversion'] = array();
		$data['ts_products'] = array();

		$date_format = $this->language->get('date_format_short');

		foreach ($series as $row) {
			$data['ts_labels'][] = date($date_format, strtotime($row['date_start']));
			$data['ts_revenue'][] = round((float)$row['revenue'], 2);
			$data['ts_orders'][] = (int)$row['total_orders'];
			$data['ts_aov'][] = round((float)$row['aov'], 2);
			$data['ts_conversion'][] = (float)$row['conversion_rate'];
			$data['ts_products'][] = (float)$row['products'];
		}

		// --- Orders by day of week ---
		$day_results = $this->model_extension_report_dockercart_analytics->getOrdersByDayOfWeek($filter_data);
		$days_names = array('', 'text_sunday', 'text_monday', 'text_tuesday', 'text_wednesday', 'text_thursday', 'text_friday', 'text_saturday');
		$data['dow_labels'] = array();
		$data['dow_values'] = array();

		for ($i = 1; $i <= 7; $i++) {
			$found = 0;

			foreach ($day_results as $row) {
				if ((int)$row['day_of_week'] === $i) {
					$found = (int)$row['total'];
					break;
				}
			}

			$data['dow_labels'][] = $this->language->get($days_names[$i]);
			$data['dow_values'][] = $found;
		}

		// --- Orders by hour ---
		$hour_results = $this->model_extension_report_dockercart_analytics->getOrdersByHour($filter_data);
		$hour_map = array();

		foreach ($hour_results as $row) {
			$hour_map[(int)$row['hour']] = (int)$row['total'];
		}

		$data['hour_labels'] = array();
		$data['hour_values'] = array();

		for ($h = 0; $h < 24; $h++) {
			$data['hour_labels'][] = sprintf('%02d:00', $h);
			$data['hour_values'][] = isset($hour_map[$h]) ? $hour_map[$h] : 0;
		}

		// --- Revenue by category ---
		$categories = $this->model_extension_report_dockercart_analytics->getRevenueByCategory($filter_data);

		$data['cat_labels'] = array();
		$data['cat_values'] = array();

		foreach ($categories as $cat) {
			$data['cat_labels'][] = $cat['category_name'] ? $cat['category_name'] : $this->language->get('text_uncategorized');
			$data['cat_values'][] = round((float)$cat['revenue'], 2);
		}

		// --- Top products by revenue ---
		$top_products = $this->model_extension_report_dockercart_analytics->getTopProducts($filter_data);

		$data['top_products'] = array();

		foreach ($top_products as $product) {
			$data['top_products'][] = array(
				'name'     => $product['name'],
				'model'    => $product['model'],
				'quantity' => (int)$product['quantity'],
				'total'    => $this->currency->format($product['total'], $currency)
			);
		}

		// --- Top products by quantity ---
		$qty_products = $this->model_extension_report_dockercart_analytics->getProductsByQuantity($filter_data);

		$data['qty_products'] = array();

		foreach ($qty_products as $product) {
			$data['qty_products'][] = array(
				'name'     => $product['name'],
				'model'    => $product['model'],
				'quantity' => (float)$product['quantity'],
				'orders'   => (int)$product['orders'],
				'total'    => $this->currency->format($product['total'], $currency)
			);
		}

		// --- Items per order distribution ---
		$items_dist = $this->model_extension_report_dockercart_analytics->getOrderItemsDistribution($filter_data);

		$data['avg_items'] = $items_dist['avg_items'];
		$data['total_products'] = (int)$items_dist['total_products'];
		$data['total_orders_items'] = (int)$items_dist['total_orders'];

		// --- Conversion by medium (channel) ---
		$mediums = $this->model_extension_report_dockercart_analytics->getTotalsByMedium($filter_data);

		$data['medium_labels'] = array();
		$data['medium_orders'] = array();
		$data['medium_revenue'] = array();

		$medium_names = array(
			'none'     => $this->language->get('text_direct'),
			'organic'  => $this->language->get('text_organic'),
			'social'   => $this->language->get('text_social'),
			'email'    => $this->language->get('text_email'),
			'referral' => $this->language->get('text_referral'),
			'cpc'      => $this->language->get('text_cpc'),
			'display'  => $this->language->get('text_display')
		);

		foreach ($mediums as $medium) {
			$name = isset($medium_names[$medium['medium']]) ? $medium_names[$medium['medium']] : ucfirst($medium['medium']);

			$data['medium_labels'][] = $name;
			$data['medium_orders'][] = (int)$medium['orders'];
			$data['medium_revenue'][] = round((float)$medium['revenue'], 2);
		}

		// --- Traffic sources (visits) ---
		$traffic = $this->model_extension_report_dockercart_analytics->getTrafficSources($filter_data);

		$data['traffic_labels'] = array();
		$data['traffic_values'] = array();

		foreach ($traffic as $row) {
			$data['traffic_labels'][] = $row['source'] === '' ? $this->language->get('text_direct') : ucfirst($row['source']);
			$data['traffic_values'][] = (int)$row['visits'];
		}

		// --- Traffic conversions (sessions -> orders -> revenue) ---
		$traffic_conv = $this->model_extension_report_dockercart_analytics->getTrafficConversions($filter_data);

		$data['traffic_conv'] = array();

		foreach ($traffic_conv as $row) {
			$data['traffic_conv'][] = array(
				'source'   => $row['source'] === '' ? $this->language->get('text_direct') : ucfirst($row['source']),
				'medium'   => isset($medium_names[$row['medium']]) ? $medium_names[$row['medium']] : ucfirst($row['medium']),
				'sessions' => (int)$row['sessions'],
				'orders'   => (int)$row['orders'],
				'revenue'  => $this->currency->format($row['revenue'], $currency)
			);
		}

		// --- Social traffic ---
		$social_traffic = $this->model_extension_report_dockercart_analytics->getSocialTraffic($filter_data);

		$data['social_traffic_labels'] = array();
		$data['social_traffic_values'] = array();

		foreach ($social_traffic as $row) {
			$data['social_traffic_labels'][] = ucfirst($row['source']);
			$data['social_traffic_values'][] = (int)$row['visits'];
		}

		// --- Social sales ---
		$social_sales = $this->model_extension_report_dockercart_analytics->getSocialSales($filter_data);

		$data['social_sales'] = array();

		foreach ($social_sales as $row) {
			$data['social_sales'][] = array(
				'source'   => ucfirst($row['source']),
				'orders'   => (int)$row['orders'],
				'customers'=> (int)$row['customers'],
				'revenue'  => $this->currency->format($row['revenue'], $currency)
			);
		}

		// --- Checkout funnel ---
		$funnel = $this->model_extension_report_dockercart_analytics->getCheckoutFunnel($filter_data);

		$data['funnel_labels'] = array();
		$data['funnel_values'] = array();

		$this->load->model('localisation/order_status');

		$status_names = array();

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$status_names[$status['order_status_id']] = $status['name'];
		}

		foreach ($funnel as $step) {
			$data['funnel_labels'][] = isset($status_names[$step['step']]) ? $status_names[$step['step']] : (string)$step['step'];
			$data['funnel_values'][] = (int)$step['count'];
		}

		// --- Filters / UI data ---
		$data['user_token'] = $this->session->data['user_token'];

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['groups'] = array();

		$data['groups'][] = array('text' => $this->language->get('text_year'), 'value' => 'year');
		$data['groups'][] = array('text' => $this->language->get('text_month'), 'value' => 'month');
		$data['groups'][] = array('text' => $this->language->get('text_week'), 'value' => 'week');
		$data['groups'][] = array('text' => $this->language->get('text_day'), 'value' => 'day');

		$data['mediums'] = array(
			array('value' => '', 'text' => $this->language->get('text_all_mediums')),
			array('value' => 'none', 'text' => $this->language->get('text_direct')),
			array('value' => 'organic', 'text' => $this->language->get('text_organic')),
			array('value' => 'social', 'text' => $this->language->get('text_social')),
			array('value' => 'email', 'text' => $this->language->get('text_email')),
			array('value' => 'referral', 'text' => $this->language->get('text_referral')),
			array('value' => 'cpc', 'text' => $this->language->get('text_cpc')),
			array('value' => 'display', 'text' => $this->language->get('text_display'))
		);

		$data['currency_code'] = $currency;
		$data['currency_symbol_left'] = $this->currency->getSymbolLeft($currency);
		$data['currency_symbol_right'] = $this->currency->getSymbolRight($currency);

		$data['filter_date_start'] = $filter_data['filter_date_start'];
		$data['filter_date_end'] = $filter_data['filter_date_end'];
		$data['filter_group'] = $filter_data['filter_group'];
		$data['filter_order_status_id'] = $filter_data['filter_order_status_id'];
		$data['filter_utm_medium'] = $filter_data['filter_utm_medium'];
		$data['filter_source'] = $filter_data['filter_source'];

		$data['text_period'] = sprintf($this->language->get('text_period'), $data['filter_date_start'], $data['filter_date_end']);
		$data['text_prev_period'] = sprintf($this->language->get('text_period'), $prev_start, $prev_end);

		return $this->load->view('extension/report/dockercart_analytics_info', $data);
	}
}
