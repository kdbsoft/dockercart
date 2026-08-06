<?php
/**
 * Abandoned Carts - Conversion Statistics
 * Shows the checkout funnel, conversion rate, average checkout time and the
 * top drop-off step, based on the checkout analytics table.
 */

class ControllerMarketingAbandonedStats extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('marketing/abandoned_stats');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_checkout');

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

		$filter = array(
			'date_start' => $filter_date_start,
			'date_end'   => $filter_date_end
		);

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_marketing'),
			'href' => $this->url->link('marketing/marketing', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/abandoned_stats', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;
		$data['filter_url'] = $this->url->link('marketing/abandoned_stats', 'user_token=' . $this->session->data['user_token'], true);

		// Funnel
		$funnel = $this->model_extension_module_dockercart_checkout->getCheckoutFunnel($filter);

		$funnel_labels = array(
			'cart'             => $this->language->get('text_step_cart'),
			'customer'         => $this->language->get('text_step_customer'),
			'shipping_address' => $this->language->get('text_step_shipping_address'),
			'shipping_method'  => $this->language->get('text_step_shipping_method'),
			'payment_method'   => $this->language->get('text_step_payment_method'),
			'confirm'          => $this->language->get('text_step_confirm'),
			'completed'        => $this->language->get('text_step_completed')
		);

		$data['funnel'] = array();

		$prev = null;

		foreach ($funnel as $step => $count) {
			$drop_rate = null;

			if ($prev !== null && $prev > 0) {
				$drop_rate = round((($prev - $count) / $prev) * 100, 1);
			}

			$data['funnel'][] = array(
				'step'      => $step,
				'name'      => isset($funnel_labels[$step]) ? $funnel_labels[$step] : $step,
				'count'     => $count,
				'drop_rate' => $drop_rate
			);

			$prev = $count;
		}

		// Summary cards
		$data['total_started'] = $funnel['cart'];
		$data['total_completed'] = $funnel['completed'];
		$data['conversion_rate'] = $this->model_extension_module_dockercart_checkout->getConversionRate($filter);
		$data['avg_checkout_time'] = $this->model_extension_module_dockercart_checkout->getAverageCheckoutTime($filter);

		$drop_off = $this->model_extension_module_dockercart_checkout->getTopDropOffStep($filter);

		$data['top_drop_step'] = isset($funnel_labels[$drop_off['step']]) ? $funnel_labels[$drop_off['step']] : $drop_off['step'];
		$data['top_drop_rate'] = $drop_off['drop_rate'];

		// Abandoned carts total in the period (for context)
		$abandoned_filter = array(
			'date_start' => $filter_date_start,
			'date_end'   => $filter_date_end
		);

		$data['abandoned_total'] = $this->model_extension_module_dockercart_checkout->getTotalAbandonedCarts($abandoned_filter);

		$data['user_token'] = $this->session->data['user_token'];

		// Template strings (OpenCart templates do not get the language object automatically)
		$data['text_filter'] = $this->language->get('text_filter');
		$data['text_started'] = $this->language->get('text_started');
		$data['text_conversion'] = $this->language->get('text_conversion');
		$data['text_avg_time'] = $this->language->get('text_avg_time');
		$data['text_top_drop'] = $this->language->get('text_top_drop');
		$data['text_funnel'] = $this->language->get('text_funnel');
		$data['text_abandoned_period'] = $this->language->get('text_abandoned_period');
		$data['entry_date_start'] = $this->language->get('entry_date_start');
		$data['entry_date_end'] = $this->language->get('entry_date_end');
		$data['column_step'] = $this->language->get('column_step');
		$data['column_sessions'] = $this->language->get('column_sessions');
		$data['column_drop'] = $this->language->get('column_drop');
		$data['button_filter'] = $this->language->get('button_filter');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/abandoned_stats', $data));
	}
}
