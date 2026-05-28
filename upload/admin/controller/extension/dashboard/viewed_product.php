<?php
class ControllerExtensionDashboardViewedProduct extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/viewed_product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_viewed_product', $this->request->post);

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
			'href' => $this->url->link('extension/dashboard/viewed_product', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/viewed_product', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_viewed_product_width'])) {
			$data['dashboard_viewed_product_width'] = $this->request->post['dashboard_viewed_product_width'];
		} else {
			$data['dashboard_viewed_product_width'] = $this->config->get('dashboard_viewed_product_width');
		}

		$data['columns'] = array();

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		if (isset($this->request->post['dashboard_viewed_product_status'])) {
			$data['dashboard_viewed_product_status'] = $this->request->post['dashboard_viewed_product_status'];
		} else {
			$data['dashboard_viewed_product_status'] = $this->config->get('dashboard_viewed_product_status');
		}

		if (isset($this->request->post['dashboard_viewed_product_sort_order'])) {
			$data['dashboard_viewed_product_sort_order'] = $this->request->post['dashboard_viewed_product_sort_order'];
		} else {
			$data['dashboard_viewed_product_sort_order'] = $this->config->get('dashboard_viewed_product_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/viewed_product_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/viewed_product')) {
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
			case 'yesterday':
				$dates['start'] = date('Y-m-d', strtotime('-1 day'));
				$dates['end'] = date('Y-m-d', strtotime('-1 day'));
				$dates['prev_start'] = date('Y-m-d', strtotime('-2 day'));
				$dates['prev_end'] = date('Y-m-d', strtotime('-2 day'));
				break;
			case 'week':
				$dates['start'] = date('Y-m-d', strtotime('-6 days'));
				$dates['end'] = date('Y-m-d');
				$dates['prev_start'] = date('Y-m-d', strtotime('-13 days'));
				$dates['prev_end'] = date('Y-m-d', strtotime('-7 days'));
				break;
			case 'month':
				$dates['start'] = date('Y-m-d', strtotime('-29 days'));
				$dates['end'] = date('Y-m-d');
				$dates['prev_start'] = date('Y-m-d', strtotime('-59 days'));
				$dates['prev_end'] = date('Y-m-d', strtotime('-30 days'));
				break;
			case 'year':
				$dates['start'] = date('Y-m-d', strtotime('-364 days'));
				$dates['end'] = date('Y-m-d');
				$dates['prev_start'] = date('Y-m-d', strtotime('-729 days'));
				$dates['prev_end'] = date('Y-m-d', strtotime('-365 days'));
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
			return round($value);
		}
	}

	public function dashboard() {
		$this->load->language('extension/dashboard/viewed_product');

		$data['user_token'] = $this->session->data['user_token'];

		$data['total'] = '—';
		$data['percentage'] = 0;
		$data['report'] = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=product_viewed', true);

		return $this->load->view('extension/dashboard/viewed_product_info', $data);
	}

	public function ajax() {
		$this->load->language('extension/dashboard/viewed_product');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_viewed_product_ajax_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$this->load->model('extension/dashboard/viewed_product');

		$dates = $this->getPeriodDates($period);

		if ($dates['start']) {
			$current = $this->model_extension_dashboard_viewed_product->getTotalViews(array('filter_date_start' => $dates['start'], 'filter_date_end' => $dates['end']));
			$previous = $this->model_extension_dashboard_viewed_product->getTotalViews(array('filter_date_start' => $dates['prev_start'], 'filter_date_end' => $dates['prev_end']));
		} else {
			$current = $this->model_extension_dashboard_viewed_product->getTotalViews();
			$previous = 0;
		}

		$difference = $current - $previous;

		if ($difference && (int)$current) {
			$percentage = round(($difference / $current) * 100);
		} else {
			$percentage = 0;
		}

		$json = array(
			'total' => $this->formatTotal($current),
			'percentage' => $percentage,
			'direction' => $difference >= 0 ? 'up' : 'down'
		);

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}

	public function sparkline() {
		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_viewed_product_spark_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$table = DB_PREFIX . 'dockercart_viewed_product';

		switch ($period) {
			case 'today':
				$sql = "SELECT HOUR(date_added) AS bucket, COUNT(*) AS val FROM `" . $table . "` WHERE DATE(date_added) = CURDATE() GROUP BY HOUR(date_added) ORDER BY HOUR(date_added) ASC";
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
			case 'yesterday':
				$sql = "SELECT HOUR(date_added) AS bucket, COUNT(*) AS val FROM `" . $table . "` WHERE DATE(date_added) = DATE(CURDATE() - INTERVAL 1 DAY) GROUP BY HOUR(date_added) ORDER BY HOUR(date_added) ASC";
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
				$sql = "SELECT DATE(date_added) AS bucket, COUNT(*) AS val FROM `" . $table . "` WHERE DATE(date_added) >= DATE(CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 6; $i >= 0; $i--) {
					$date = date('Y-m-d', strtotime("-{$i} days"));
					$data[] = isset($raw[$date]) ? $raw[$date] : 0;
				}
				break;
			case 'month':
				$sql = "SELECT DATE(date_added) AS bucket, COUNT(*) AS val FROM `" . $table . "` WHERE DATE(date_added) >= DATE(CURDATE() - INTERVAL 29 DAY) GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 29; $i >= 0; $i--) {
					$date = date('Y-m-d', strtotime("-{$i} days"));
					$data[] = isset($raw[$date]) ? $raw[$date] : 0;
				}
				break;
			case 'year':
				$sql = "SELECT DATE_FORMAT(date_added, '%Y-%m') AS bucket, COUNT(*) AS val FROM `" . $table . "` WHERE date_added >= DATE(CURDATE() - INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(date_added, '%Y-%m') ORDER BY DATE_FORMAT(date_added, '%Y-%m') ASC";
				$result = $this->db->query($sql);
				$raw = array();
				foreach ($result->rows as $row) {
					$raw[$row['bucket']] = (int)$row['val'];
				}
				$data = array();
				for ($i = 11; $i >= 0; $i--) {
					$key = date('Y-m', strtotime("-{$i} months"));
					$data[] = isset($raw[$key]) ? $raw[$key] : 0;
				}
				break;
			case 'all':
			default:
				$sql = "SELECT DATE_FORMAT(date_added, '%Y-%m') AS bucket, COUNT(*) AS val FROM `" . $table . "` GROUP BY DATE_FORMAT(date_added, '%Y-%m') ORDER BY DATE_FORMAT(date_added, '%Y-%m') ASC";
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
