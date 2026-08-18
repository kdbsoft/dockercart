<?php
class ControllerExtensionDashboardDockercartConversion extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/dockercart_conversion');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_dockercart_conversion', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->buildExtensionBackUrl('dashboard'));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('extension/dashboard/dockercart_conversion', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->buildExtensionBackUrl('dashboard');

		if (isset($this->request->post['dashboard_dockercart_conversion_width'])) {
			$data['dashboard_dockercart_conversion_width'] = $this->request->post['dashboard_dockercart_conversion_width'];
		} else {
			$data['dashboard_dockercart_conversion_width'] = $this->config->get('dashboard_dockercart_conversion_width');
		}

		$data['columns'] = array();

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		if (isset($this->request->post['dashboard_dockercart_conversion_status'])) {
			$data['dashboard_dockercart_conversion_status'] = $this->request->post['dashboard_dockercart_conversion_status'];
		} else {
			$data['dashboard_dockercart_conversion_status'] = $this->config->get('dashboard_dockercart_conversion_status');
		}

		if (isset($this->request->post['dashboard_dockercart_conversion_sort_order'])) {
			$data['dashboard_dockercart_conversion_sort_order'] = $this->request->post['dashboard_dockercart_conversion_sort_order'];
		} else {
			$data['dashboard_dockercart_conversion_sort_order'] = $this->config->get('dashboard_dockercart_conversion_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/dockercart_conversion_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/dockercart_conversion')) {
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
				$dates['prev_start'] = date('Y-m-d', strtotime('-1 day'));
				$dates['prev_end'] = date('Y-m-d', strtotime('-1 day'));
				break;
			case 'week':
				$dates['start'] = date('Y-m-d', strtotime('monday this week'));
				$dates['end'] = date('Y-m-d', strtotime('sunday this week'));
				$dates['prev_start'] = date('Y-m-d', strtotime('monday this week -1 week'));
				$dates['prev_end'] = date('Y-m-d', strtotime('sunday this week -1 week'));
				break;
			case 'month':
				$dates['start'] = date('Y-m-01');
				$dates['end'] = date('Y-m-t');
				$dates['prev_start'] = date('Y-m-01', strtotime('first day of last month'));
				$dates['prev_end'] = date('Y-m-t', strtotime('last day of last month'));
				break;
			case 'year':
				$dates['start'] = date('Y-01-01');
				$dates['end'] = date('Y-12-31');
				$dates['prev_start'] = date('Y-01-01', strtotime('-1 year'));
				$dates['prev_end'] = date('Y-12-31', strtotime('-1 year'));
				break;
			case 'all':
			default:
				$dates['start'] = '';
				$dates['end'] = '';
				$dates['prev_start'] = '';
				$dates['prev_end'] = '';
				break;
		}

		return $dates;
	}

	public function dashboard() {
		$this->load->language('extension/dashboard/dockercart_conversion');

		$data['user_token'] = $this->session->data['user_token'];

		$data['total'] = '—';
		$data['report'] = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=dockercart_analytics', true) . '#analytics-summary';

		return $this->load->view('extension/dashboard/dockercart_conversion_info', $data);
	}

	protected function getCompleteStatusCondition() {
		$implode = array();

		foreach ((array)$this->config->get('config_complete_status') as $order_status_id) {
			$implode[] = "'" . (int)$order_status_id . "'";
		}

		return $implode ? "IN(" . implode(",", $implode) . ")" : "IN (0)";
	}

	public function ajax() {
		$this->load->language('extension/dashboard/dockercart_conversion');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_dc_conversion_ajax_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$this->load->model('extension/report/dockercart_analytics');

		$dates = $this->getPeriodDates($period);

		$filter_current = array();
		$filter_previous = array();

		if ($dates['start']) {
			$filter_current['filter_date_start'] = $dates['start'];
			$filter_current['filter_date_end'] = $dates['end'];
			$filter_previous['filter_date_start'] = $dates['prev_start'];
			$filter_previous['filter_date_end'] = $dates['prev_end'];
		}

		$current = $this->model_extension_report_dockercart_analytics->getTotals($filter_current);
		$previous = $this->model_extension_report_dockercart_analytics->getTotals($filter_previous);

		$json = array();

		$json['total'] = $current['conversion_rate'] . '%';

		if ($dates['start'] && $previous['conversion_rate'] > 0) {
			$diff = $current['conversion_rate'] - $previous['conversion_rate'];
			$pct = $previous['conversion_rate'] > 0 ? round(($diff / $previous['conversion_rate']) * 100) : 0;
			$json['show_change'] = true;
			$json['percentage'] = abs($pct);
			$json['direction'] = $diff >= 0 ? 'up' : 'down';
		} else {
			$json['show_change'] = false;
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}

	public function sparkline() {
		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_dc_conversion_spark_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$complete_cond = $this->getCompleteStatusCondition();

		switch ($period) {
			case 'today':
				$sql = "SELECT FLOOR(HOUR(o.date_added) / 2) * 2 AS bucket, COUNT(*) AS total, SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0' AND DATE(o.date_added) = CURDATE() GROUP BY FLOOR(HOUR(o.date_added) / 2) * 2 ORDER BY FLOOR(HOUR(o.date_added) / 2) * 2 ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[(int)$row['bucket']] = $row;
				}
				$data = array();
				for ($i = 0; $i < 12; $i++) {
					$hour = $i * 2;
					if (isset($raw[$hour])) {
						$data[] = $raw[$hour]['total'] > 0 ? round($raw[$hour]['completed'] / $raw[$hour]['total'] * 100, 2) : 0;
					} else {
						$data[] = 0;
					}
				}
				break;
			case 'week':
				$week_start = date('Y-m-d', strtotime('monday this week'));
				$week_end = date('Y-m-d', strtotime('sunday this week'));
				$sql = "SELECT DATE(o.date_added) AS bucket, COUNT(*) AS total, SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0' AND DATE(o.date_added) >= '" . $week_start . "' AND DATE(o.date_added) <= '" . $week_end . "' GROUP BY DATE(o.date_added) ORDER BY DATE(o.date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = $row;
				}
				$data = array();
				for ($i = 0; $i < 7; $i++) {
					$date = date('Y-m-d', strtotime($week_start . " +{$i} days"));
					if (isset($raw[$date])) {
						$data[] = $raw[$date]['total'] > 0 ? round($raw[$date]['completed'] / $raw[$date]['total'] * 100, 2) : 0;
					} else {
						$data[] = 0;
					}
				}
				break;
			case 'month':
				$month_start = date('Y-m-01');
				$month_end = date('Y-m-t');
				$days_in_month = (int)date('t');
				$sql = "SELECT DATE(o.date_added) AS bucket, COUNT(*) AS total, SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0' AND DATE(o.date_added) >= '" . $month_start . "' AND DATE(o.date_added) <= '" . $month_end . "' GROUP BY DATE(o.date_added) ORDER BY DATE(o.date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = $row;
				}
				$data = array();
				for ($i = 0; $i < $days_in_month; $i++) {
					$date = date('Y-m-d', strtotime($month_start . " +{$i} days"));
					if (isset($raw[$date])) {
						$data[] = $raw[$date]['total'] > 0 ? round($raw[$date]['completed'] / $raw[$date]['total'] * 100, 2) : 0;
					} else {
						$data[] = 0;
					}
				}
				break;
			case 'year':
				$year_start = date('Y-01-01');
				$year_end = date('Y-12-31');
				$sql = "SELECT DATE_FORMAT(o.date_added, '%Y-%m') AS bucket, COUNT(*) AS total, SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0' AND DATE(o.date_added) >= '" . $year_start . "' AND DATE(o.date_added) <= '" . $year_end . "' GROUP BY DATE_FORMAT(o.date_added, '%Y-%m') ORDER BY DATE_FORMAT(o.date_added, '%Y-%m') ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = $row;
				}
				$data = array();
				for ($i = 0; $i < 12; $i++) {
					$key = date('Y-m', strtotime("first day of january +{$i} months"));
					if (isset($raw[$key])) {
						$data[] = $raw[$key]['total'] > 0 ? round($raw[$key]['completed'] / $raw[$key]['total'] * 100, 2) : 0;
					} else {
						$data[] = 0;
					}
				}
				break;
			case 'all':
			default:
				$sql = "SELECT DATE_FORMAT(o.date_added, '%Y') AS bucket, COUNT(*) AS total, SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed FROM `" . DB_PREFIX . "order` o WHERE o.order_status_id > '0' GROUP BY DATE_FORMAT(o.date_added, '%Y') ORDER BY DATE_FORMAT(o.date_added, '%Y') ASC";
				$result = $this->db->query($sql);
				$data = array();
				foreach ($result->rows as $row) {
					$data[] = $row['total'] > 0 ? round($row['completed'] / $row['total'] * 100, 2) : 0;
				}
				break;
		}

		$output = json_encode(array('data' => $data));
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}