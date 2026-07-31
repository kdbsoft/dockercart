<?php
class ControllerExtensionDashboardTrafficSource extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/traffic_source');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_traffic_source', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('extension/dashboard/traffic_source', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_traffic_source_width'])) {
			$data['dashboard_traffic_source_width'] = $this->request->post['dashboard_traffic_source_width'];
		} else {
			$data['dashboard_traffic_source_width'] = $this->config->get('dashboard_traffic_source_width');
		}

		$data['columns'] = array();

		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}

		if (isset($this->request->post['dashboard_traffic_source_status'])) {
			$data['dashboard_traffic_source_status'] = $this->request->post['dashboard_traffic_source_status'];
		} else {
			$data['dashboard_traffic_source_status'] = $this->config->get('dashboard_traffic_source_status');
		}

		if (isset($this->request->post['dashboard_traffic_source_sort_order'])) {
			$data['dashboard_traffic_source_sort_order'] = $this->request->post['dashboard_traffic_source_sort_order'];
		} else {
			$data['dashboard_traffic_source_sort_order'] = $this->config->get('dashboard_traffic_source_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/traffic_source_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/traffic_source')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function dashboard() {
		$this->load->language('extension/dashboard/traffic_source');

		$data['text_traffic_subtitle'] = $this->language->get('text_traffic_subtitle');
		$data['text_direct'] = $this->language->get('text_direct');
		$data['text_other'] = $this->language->get('text_other');
		$data['text_no_results'] = $this->language->get('text_no_results');

		$this->load->model('extension/report/dockercart_analytics');

		$results = $this->model_extension_report_dockercart_analytics->getTrafficSources();

		$total = 0;
		foreach ($results as $result) {
			$total += (int)$result['visits'];
		}

		$data['sources'] = array();
		$shown = 0;
		$other_count = 0;

		foreach ($results as $result) {
			$shown++;
			$visits = (int)$result['visits'];
			$pct = $total > 0 ? round(($visits / $total) * 100) : 0;

			if ($shown <= 10) {
				$source_name = $result['source'] === '' ? $data['text_direct'] : ucfirst($result['source']);

				$data['sources'][] = array(
					'name'   => $source_name,
					'visits' => $visits,
					'pct'    => $pct,
				);
			} else {
				$other_count += $visits;
			}
		}

		if ($other_count > 0) {
			$pct = $total > 0 ? round(($other_count / $total) * 100) : 0;

			$data['sources'][] = array(
				'name'   => $data['text_other'],
				'visits' => $other_count,
				'pct'    => $pct,
			);
		}

		return $this->load->view('extension/dashboard/traffic_source_info', $data);
	}
}
