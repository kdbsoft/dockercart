<?php
class ControllerExtensionDashboardRecent extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/dashboard/recent');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('dashboard_recent', $this->request->post);

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
			'href' => $this->url->link('extension/dashboard/recent', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/dashboard/recent', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=dashboard', true);

		if (isset($this->request->post['dashboard_recent_width'])) {
			$data['dashboard_recent_width'] = $this->request->post['dashboard_recent_width'];
		} else {
			$data['dashboard_recent_width'] = $this->config->get('dashboard_recent_width');
		}

		$data['columns'] = array();
		
		for ($i = 3; $i <= 12; $i++) {
			$data['columns'][] = $i;
		}
				
		if (isset($this->request->post['dashboard_recent_status'])) {
			$data['dashboard_recent_status'] = $this->request->post['dashboard_recent_status'];
		} else {
			$data['dashboard_recent_status'] = $this->config->get('dashboard_recent_status');
		}

		if (isset($this->request->post['dashboard_recent_sort_order'])) {
			$data['dashboard_recent_sort_order'] = $this->request->post['dashboard_recent_sort_order'];
		} else {
			$data['dashboard_recent_sort_order'] = $this->config->get('dashboard_recent_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/dashboard/recent_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/dashboard/recent')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
	
	public function dashboard() {
		$this->load->language('extension/dashboard/recent');

		$data['text_recent_subtitle'] = $this->language->get('text_recent_subtitle');
		$data['user_token'] = $this->session->data['user_token'];

		// Last 5 Orders
		$data['orders'] = array();

		$filter_data = array(
			'sort'  => 'o.date_added',
			'order' => 'DESC',
			'start' => 0,
			'limit' => 5
		);

		$this->load->model('sale/order');
		
		$results = $this->model_sale_order->getOrders($filter_data);

		$processing_statuses = (array)$this->config->get('config_processing_status');
		$complete_statuses   = (array)$this->config->get('config_complete_status');
		$fraud_status        = (int)$this->config->get('config_fraud_status_id');

		foreach ($results as $result) {
			$order_type = $this->getOrderType($result);
			$status_badge_class = $this->getOrderStatusBadgeClass((int)$result['order_status_id'], $processing_statuses, $complete_statuses, $fraud_status);

			$data['orders'][] = array(
				'order_id'   => $result['order_id'],
				'customer'   => $result['customer'],
				'order_type' => $order_type,
				'status'     => $result['order_status'],
				'order_status_badge_class' => $status_badge_class,
				'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'total'      => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'view'       => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'], true),
			);
		}

		return $this->load->view('extension/dashboard/recent_info', $data);
	}

	private function getOrderType($order) {
		if (!empty($order['payment_code']) && $order['payment_code'] === 'oneclickcheckout') {
			return $this->language->get('text_badge_oneclick_order');
		}

		if (!empty($order['customer_id'])) {
			return $this->language->get('text_badge_registered_order');
		}

		return $this->language->get('text_badge_guest_order');
	}

	private function getOrderStatusBadgeClass($order_status_id, $processing_statuses, $complete_statuses, $fraud_status) {
		if ($fraud_status && $order_status_id === $fraud_status) {
			return 'label label-danger';
		}

		if (in_array($order_status_id, $processing_statuses)) {
			return 'label label-warning';
		}

		if (in_array($order_status_id, $complete_statuses)) {
			return 'label label-success';
		}

		return 'label label-default';
	}
}
