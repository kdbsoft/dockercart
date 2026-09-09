<?php
class ControllerExtensionDashboardDockercartTopProducts extends Controller {
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
		$this->load->language('extension/dashboard/dockercart_top_products');

		$data['text_top_products_subtitle'] = $this->language->get('text_top_products_subtitle');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_view'] = $this->language->get('text_view');
		$data['user_token'] = $this->session->data['user_token'];

		$data['report'] = $this->url->link('report/report', 'user_token=' . $this->session->data['user_token'] . '&code=dockercart_analytics', true) . '#analytics-top-products';

		return $this->load->view('extension/dashboard/dockercart_top_products_info', $data);
	}

	public function ajax() {
		$this->load->language('extension/dashboard/dockercart_top_products');

		$period = isset($this->request->get['period']) ? $this->request->get['period'] : 'month';

		$cache_key = 'dash_dc_top_products_' . $this->config->get('config_admin_language') . '_' . $period;
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

		$results = $this->model_extension_report_dockercart_analytics->getTopProducts($filter);

		$json = array();
		$json['items'] = array();

		$rank = 1;
		foreach ($results as $result) {
			$meta = (string)(int)$result['quantity'] . ' ' . $this->language->get('text_pcs');

			if (!empty($result['model'])) {
				$meta .= ' · ' . $result['model'];
			}

			$json['items'][] = array(
				'rank'  => $rank,
				'name'  => $result['name'],
				'meta'  => $meta,
				'value' => $this->currency->format($result['total'], $this->config->get('config_currency'))
			);

			$rank++;
		}

		$output = json_encode($json);
		$this->cache->set($cache_key, $output, 300);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($output);
	}
}
