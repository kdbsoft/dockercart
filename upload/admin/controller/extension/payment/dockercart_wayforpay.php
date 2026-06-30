<?php
class ControllerExtensionPaymentDockercartWayforpay extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/dockercart_wayforpay');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_dockercart_wayforpay', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['merchant'])) {
			$data['error_merchant'] = $this->error['merchant'];
		} else {
			$data['error_merchant'] = '';
		}

		if (isset($this->error['secretkey'])) {
			$data['error_secretkey'] = $this->error['secretkey'];
		} else {
			$data['error_secretkey'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/dockercart_wayforpay', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/dockercart_wayforpay', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		if (isset($this->request->post['payment_dockercart_wayforpay_merchant'])) {
			$data['payment_dockercart_wayforpay_merchant'] = $this->request->post['payment_dockercart_wayforpay_merchant'];
		} else {
			$data['payment_dockercart_wayforpay_merchant'] = $this->config->get('payment_dockercart_wayforpay_merchant');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_secretkey'])) {
			$data['payment_dockercart_wayforpay_secretkey'] = $this->request->post['payment_dockercart_wayforpay_secretkey'];
		} else {
			$data['payment_dockercart_wayforpay_secretkey'] = $this->config->get('payment_dockercart_wayforpay_secretkey');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_total'])) {
			$data['payment_dockercart_wayforpay_total'] = $this->request->post['payment_dockercart_wayforpay_total'];
		} else {
			$data['payment_dockercart_wayforpay_total'] = $this->config->get('payment_dockercart_wayforpay_total');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_completed_status_id'])) {
			$data['payment_dockercart_wayforpay_completed_status_id'] = $this->request->post['payment_dockercart_wayforpay_completed_status_id'];
		} else {
			$data['payment_dockercart_wayforpay_completed_status_id'] = $this->config->get('payment_dockercart_wayforpay_completed_status_id');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_failed_status_id'])) {
			$data['payment_dockercart_wayforpay_failed_status_id'] = $this->request->post['payment_dockercart_wayforpay_failed_status_id'];
		} else {
			$data['payment_dockercart_wayforpay_failed_status_id'] = $this->config->get('payment_dockercart_wayforpay_failed_status_id');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_refunded_status_id'])) {
			$data['payment_dockercart_wayforpay_refunded_status_id'] = $this->request->post['payment_dockercart_wayforpay_refunded_status_id'];
		} else {
			$data['payment_dockercart_wayforpay_refunded_status_id'] = $this->config->get('payment_dockercart_wayforpay_refunded_status_id');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_geo_zone_id'])) {
			$data['payment_dockercart_wayforpay_geo_zone_id'] = $this->request->post['payment_dockercart_wayforpay_geo_zone_id'];
		} else {
			$data['payment_dockercart_wayforpay_geo_zone_id'] = $this->config->get('payment_dockercart_wayforpay_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_dockercart_wayforpay_status'])) {
			$data['payment_dockercart_wayforpay_status'] = $this->request->post['payment_dockercart_wayforpay_status'];
		} else {
			$data['payment_dockercart_wayforpay_status'] = $this->config->get('payment_dockercart_wayforpay_status');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_sort_order'])) {
			$data['payment_dockercart_wayforpay_sort_order'] = $this->request->post['payment_dockercart_wayforpay_sort_order'];
		} else {
			$data['payment_dockercart_wayforpay_sort_order'] = $this->config->get('payment_dockercart_wayforpay_sort_order');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_awaiting_status_id'])) {
			$data['payment_dockercart_wayforpay_awaiting_status_id'] = $this->request->post['payment_dockercart_wayforpay_awaiting_status_id'];
		} else {
			$data['payment_dockercart_wayforpay_awaiting_status_id'] = $this->config->get('payment_dockercart_wayforpay_awaiting_status_id');
		}

		if (isset($this->request->post['payment_dockercart_wayforpay_debug'])) {
			$data['payment_dockercart_wayforpay_debug'] = $this->request->post['payment_dockercart_wayforpay_debug'];
		} else {
			$data['payment_dockercart_wayforpay_debug'] = $this->config->get('payment_dockercart_wayforpay_debug');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['callback_url'] = HTTPS_CATALOG . 'index.php?route=extension/payment/dockercart_wayforpay/callback';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/dockercart_wayforpay', $data));
	}

	public function install() {
		$this->load->model('extension/payment/dockercart_wayforpay');
		$this->model_extension_payment_dockercart_wayforpay->install();
	}

	public function uninstall() {
		$this->load->model('extension/payment/dockercart_wayforpay');
		$this->model_extension_payment_dockercart_wayforpay->uninstall();
	}

	public function order() {
		if ($this->config->get('payment_dockercart_wayforpay_status')) {
			$this->load->language('extension/payment/dockercart_wayforpay');
			$this->load->model('extension/payment/dockercart_wayforpay');

			$order_id = (int)$this->request->get['order_id'];

			$wayforpay_order = $this->model_extension_payment_dockercart_wayforpay->getOrderByOrderId($order_id);

			$data['order_id'] = $order_id;
			$data['user_token'] = $this->session->data['user_token'];
			$data['wayforpay_order'] = $wayforpay_order;

			return $this->load->view('extension/payment/dockercart_wayforpay_order', $data);
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/dockercart_wayforpay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_dockercart_wayforpay_merchant']) {
			$this->error['merchant'] = $this->language->get('error_merchant');
		}

		if (!$this->request->post['payment_dockercart_wayforpay_secretkey']) {
			$this->error['secretkey'] = $this->language->get('error_secretkey');
		}

		return !$this->error;
	}
}
