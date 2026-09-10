<?php
class ControllerExtensionDashboardTrafficSource extends Controller {
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
		if (!$this->userHasAccess('extension/dashboard/traffic_source')) {
			return '';
		}
		$this->load->language('extension/dashboard/traffic_source');

		$data['text_traffic_subtitle'] = $this->language->get('text_traffic_subtitle');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_all_time'] = $this->language->get('text_all_time');
		$data['text_year'] = $this->language->get('text_year');
		$data['text_month'] = $this->language->get('text_month');
		$data['text_week'] = $this->language->get('text_week');
		$data['text_today'] = $this->language->get('text_today');
		$data['user_token'] = $this->session->data['user_token'];

		return $this->load->view('extension/dashboard/traffic_source_info', $data);
	}

	public function ajax() {
		if (!$this->userHasAccess('extension/dashboard/traffic_source')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('error' => 'permission')));
			return;
		}
		$this->load->language('extension/dashboard/traffic_source');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_traffic_source_' . $this->config->get('config_admin_language') . '_' . $period;
		$cached = $this->cache->get($cache_key);
		if ($cached !== false) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput($cached);
			return;
		}

		$this->load->model('extension/report/dockercart_analytics');

		$dates = $this->getPeriodDates($period);

		$filter = array();

		if ($dates['start']) {
			$filter['filter_date_start'] = $dates['start'];
			$filter['filter_date_end'] = $dates['end'];
		}

		$results = $this->model_extension_report_dockercart_analytics->getTrafficSources($filter);

		$total = 0;
		foreach ($results as $result) {
			$total += (int)$result['visits'];
		}

		$json = array();
		$json['items'] = array();
		$shown = 0;
		$other_count = 0;

		foreach ($results as $result) {
			$shown++;
			$visits = (int)$result['visits'];
			$pct = $total > 0 ? round(($visits / $total) * 100) : 0;

			if ($shown <= 10) {
				$source_name = $result['source'] === '' ? $this->language->get('text_direct') : ucfirst($result['source']);

				$json['items'][] = array(
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

			$json['items'][] = array(
				'name'   => $this->language->get('text_other'),
				'visits' => $other_count,
				'pct'    => $pct,
			);
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}
