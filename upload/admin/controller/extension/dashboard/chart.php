<?php
class ControllerExtensionDashboardChart extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/chart');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_chart', $this->request->post);

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
			'href' => $this->url->link('extension/dashboard/chart', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/chart', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_chart_width'])) {
			$data['dashboard_chart_width'] = $this->request->post['dashboard_chart_width'];
		} else {
			$data['dashboard_chart_width'] = $this->config->get('dashboard_chart_width');
		}
	
		$data['columns'] = array();
		
		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}
				
		if (isset($this->request->post['dashboard_chart_status'])) {
			$data['dashboard_chart_status'] = $this->request->post['dashboard_chart_status'];
		} else {
			$data['dashboard_chart_status'] = $this->config->get('dashboard_chart_status');
		}

		if (isset($this->request->post['dashboard_chart_sort_order'])) {
			$data['dashboard_chart_sort_order'] = $this->request->post['dashboard_chart_sort_order'];
		} else {
			$data['dashboard_chart_sort_order'] = $this->config->get('dashboard_chart_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/chart_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/chart')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}	
	
	public function dashboard() {
		$this->load->language('extension/dashboard/chart');

		$data['text_chart_subtitle'] = $this->language->get('text_chart_subtitle');
		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('extension/dashboard/chart_info', $data);
	}

	public function chart() {
		$this->load->language('extension/dashboard/chart');

		$range = isset($this->request->get['range']) ? $this->request->get['range'] : 'month';

		$cache_key = 'dash_chart_' . $range;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$this->load->model('extension/dashboard/chart');

		$json = array(
			'labels' => array(),
			'completed' => array(),
			'pending' => array(),
			'total' => array(),
			'revenue' => array()
		);

		switch ($range) {
			default:
			case 'day':
				$completed = $this->model_extension_dashboard_chart->getTotalOrdersByDay();
				$pending = $this->model_extension_dashboard_chart->getPendingOrdersByDay();
				$revenue = $this->model_extension_dashboard_chart->getRevenueByDay();

				for ($i = 0; $i < 24; $i++) {
					$json['labels'][] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
					$json['completed'][] = $completed[$i]['total'];
					$json['pending'][] = $pending[$i]['total'];
					$json['total'][] = $completed[$i]['total'] + $pending[$i]['total'];
					$json['revenue'][] = $revenue[$i]['total'];
				}
				break;
			case 'week':
				$completed = $this->model_extension_dashboard_chart->getTotalOrdersByWeek();
				$pending = $this->model_extension_dashboard_chart->getPendingOrdersByWeek();
				$revenue = $this->model_extension_dashboard_chart->getRevenueByWeek();

				$date_start = strtotime('-' . date('w') . ' days');

				for ($i = 0; $i < 7; $i++) {
					$date = date('Y-m-d', $date_start + ($i * 86400));
					$w = (int)date('w', strtotime($date));

					$json['labels'][] = date('D', strtotime($date));
					$json['completed'][] = $completed[$w]['total'];
					$json['pending'][] = $pending[$w]['total'];
					$json['total'][] = $completed[$w]['total'] + $pending[$w]['total'];
					$json['revenue'][] = $revenue[$w]['total'];
				}
				break;
			case 'month':
				$completed = $this->model_extension_dashboard_chart->getTotalOrdersByMonth();
				$pending = $this->model_extension_dashboard_chart->getPendingOrdersByMonth();
				$revenue = $this->model_extension_dashboard_chart->getRevenueByMonth();

				for ($i = 1; $i <= date('t'); $i++) {
					$date = date('Y') . '-' . date('m') . '-' . $i;
					$j = (int)date('j', strtotime($date));

					$json['labels'][] = date('d', strtotime($date));
					$json['completed'][] = $completed[$j]['total'];
					$json['pending'][] = $pending[$j]['total'];
					$json['total'][] = $completed[$j]['total'] + $pending[$j]['total'];
					$json['revenue'][] = $revenue[$j]['total'];
				}
				break;
			case 'year':
				$completed = $this->model_extension_dashboard_chart->getTotalOrdersByYear();
				$pending = $this->model_extension_dashboard_chart->getPendingOrdersByYear();
				$revenue = $this->model_extension_dashboard_chart->getRevenueByYear();

				for ($i = 1; $i <= 12; $i++) {
					$json['labels'][] = date('M', mktime(0, 0, 0, $i));
					$json['completed'][] = $completed[$i]['total'];
					$json['pending'][] = $pending[$i]['total'];
					$json['total'][] = $completed[$i]['total'] + $pending[$i]['total'];
					$json['revenue'][] = $revenue[$i]['total'];
				}
				break;
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}