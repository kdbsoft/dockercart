<?php
class ControllerExtensionDashboardOrder extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/order');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_order', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
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
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/dashboard/order', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/order', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_order_width'])) {
			$data['dashboard_order_width'] = $this->request->post['dashboard_order_width'];
		} else {
			$data['dashboard_order_width'] = $this->config->get('dashboard_order_width');
		}
		
		$data['columns'] = array();
		
		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}
				
		if (isset($this->request->post['dashboard_order_status'])) {
			$data['dashboard_order_status'] = $this->request->post['dashboard_order_status'];
		} else {
			$data['dashboard_order_status'] = $this->config->get('dashboard_order_status');
		}

		if (isset($this->request->post['dashboard_order_sort_order'])) {
			$data['dashboard_order_sort_order'] = $this->request->post['dashboard_order_sort_order'];
		} else {
			$data['dashboard_order_sort_order'] = $this->config->get('dashboard_order_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/order_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/order')) {
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

	protected function formatTotal($value) {
		if ($value > 1000000000000) {
			return round($value / 1000000000000, 1) . 'T';
		} elseif ($value > 1000000000) {
			return round($value / 1000000000, 1) . 'B';
		} elseif ($value > 1000000) {
			return round($value / 1000000, 1) . 'M';
		} elseif ($value > 1000) {
			return round($value / 1000, 1) . 'K';
		} else {
			return $value;
		}
	}
	
	public function dashboard() {
		$this->load->language('extension/dashboard/order');

		$data['user_token'] = $this->session->data['user_token'];

		$data['total'] = '—';
		$data['percentage'] = 0;
		$data['order'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true);

		return $this->load->view('extension/dashboard/order_info', $data);
	}

	public function ajax() {
		$this->load->language('extension/dashboard/order');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_order_ajax_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$dates = $this->getPeriodDates($period);

		if ($dates['start']) {
			$current = $this->countOrders($dates['start'], $dates['end']);
			$previous = $this->countOrders($dates['prev_start'], $dates['prev_end']);

			if ($previous > 0) {
				$difference = $current - $previous;
				$percentage = round(($difference / $previous) * 100);

				$json = array(
					'total' => $this->formatTotal($current),
					'show_change' => true,
					'percentage' => abs($percentage),
					'direction' => $difference >= 0 ? 'up' : 'down',
				);
			} else {
				$json = array(
					'total' => $this->formatTotal($current),
					'show_change' => false,
				);
			}
		} else {
			$current = $this->countOrders();

			$json = array(
				'total' => $this->formatTotal($current),
				'show_change' => false,
			);
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}

	protected function getCompleteStatusCondition() {
		$implode = array();

		foreach ((array)$this->config->get('config_complete_status') as $order_status_id) {
			$implode[] = "'" . (int)$order_status_id . "'";
		}

		return $implode ? "order_status_id IN(" . implode(",", $implode) . ")" : "1=0";
	}

	protected function countOrders($date_start = '', $date_end = '') {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE " . $this->getCompleteStatusCondition();

		if ($date_start) {
			$sql .= " AND DATE(date_added) >= DATE('" . $this->db->escape($date_start) . "')";
		}

		if ($date_end) {
			$sql .= " AND DATE(date_added) <= DATE('" . $this->db->escape($date_end) . "')";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function sparkline() {
		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_order_spark_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$cond = $this->getCompleteStatusCondition();

		switch ($period) {
			case 'today':
			$sql = "SELECT HOUR(date_added) AS bucket, COUNT(*) AS val FROM `" . DB_PREFIX . "order` WHERE " . $cond . " AND DATE(date_added) = CURDATE() GROUP BY HOUR(date_added) ORDER BY HOUR(date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[(int)$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 0; $i < 12; $i++) {
					$hour = $i * 2;
					$data[] = isset($raw[$hour]) ? $raw[$hour] : (isset($raw[$hour + 1]) ? $raw[$hour + 1] : 0);
				}
				break;
			case 'week':
				$week_start = date('Y-m-d', strtotime('monday this week'));
				$week_end = date('Y-m-d', strtotime('sunday this week'));
				$sql = "SELECT DATE(date_added) AS bucket, COUNT(*) AS val FROM `" . DB_PREFIX . "order` WHERE " . $cond . " AND DATE(date_added) >= '" . $week_start . "' AND DATE(date_added) <= '" . $week_end . "' GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 0; $i < 7; $i++) {
					$date = date('Y-m-d', strtotime($week_start . " +{$i} days"));
					$data[] = isset($raw[$date]) ? $raw[$date] : 0;
				}
				break;
			case 'month':
				$month_start = date('Y-m-01');
				$month_end = date('Y-m-t');
				$days_in_month = (int)date('t');
				$sql = "SELECT DATE(date_added) AS bucket, COUNT(*) AS val FROM `" . DB_PREFIX . "order` WHERE " . $cond . " AND DATE(date_added) >= '" . $month_start . "' AND DATE(date_added) <= '" . $month_end . "' GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 0; $i < $days_in_month; $i++) {
					$date = date('Y-m-d', strtotime($month_start . " +{$i} days"));
					$data[] = isset($raw[$date]) ? $raw[$date] : 0;
				}
				break;
			case 'year':
				$year_start = date('Y-01-01');
				$year_end = date('Y-12-31');
				$sql = "SELECT DATE_FORMAT(date_added, '%Y-%m') AS bucket, COUNT(*) AS val FROM `" . DB_PREFIX . "order` WHERE " . $cond . " AND DATE(date_added) >= '" . $year_start . "' AND DATE(date_added) <= '" . $year_end . "' GROUP BY DATE_FORMAT(date_added, '%Y-%m') ORDER BY DATE_FORMAT(date_added, '%Y-%m') ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 0; $i < 12; $i++) {
					$key = date('Y-m', strtotime("first day of january +{$i} months"));
					$data[] = isset($raw[$key]) ? $raw[$key] : 0;
				}
				break;
			case 'all':
			default:
				$sql = "SELECT DATE_FORMAT(date_added, '%Y') AS bucket, COUNT(*) AS val FROM `" . DB_PREFIX . "order` WHERE " . $cond . " GROUP BY DATE_FORMAT(date_added, '%Y') ORDER BY DATE_FORMAT(date_added, '%Y') ASC";
				$result = $this->db->query($sql);
				$data = array();
				foreach ($result->rows as $row) {
					$data[] = (int)$row['val'];
				}
				break;
		}

		$output = json_encode(array('data' => $data));
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}
