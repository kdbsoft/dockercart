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

			$this->response->redirect($this->buildExtensionBackUrl('dashboard'));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('extension/dashboard/recent', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->buildExtensionBackUrl('dashboard');

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
		$this->load->language('sale/order');
		$this->load->language('extension/dashboard/recent');

		$data['text_recent_subtitle'] = $this->language->get('text_recent_subtitle');
		$data['text_products']        = $this->language->get('text_products');
		$data['text_tracking']        = $this->language->get('text_tracking');
		$data['text_no_products']     = $this->language->get('text_no_products');
		$data['text_no_results']      = $this->language->get('text_no_results');
		$data['text_view']            = $this->language->get('text_view');
		$data['user_token']           = $this->session->data['user_token'];
		$data['orders_link']          = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true);

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

		if (!$results) {
			return $this->load->view('extension/dashboard/recent_info', $data);
		}

		// Collect order IDs for batch product query
		$order_ids = array();
		foreach ($results as $result) {
			$order_ids[] = (int)$result['order_id'];
		}

		// Batch: get all products for these orders in one query
		$order_products = array();
		if ($order_ids) {
			$product_query = $this->db->query(
				"SELECT order_id, product_id, name, quantity FROM " . DB_PREFIX . "order_product WHERE order_id IN (" . implode(',', $order_ids) . ") ORDER BY order_id, order_product_id ASC"
			);
			foreach ($product_query->rows as $row) {
				$order_products[$row['order_id']][] = array(
					'product_id' => (int)$row['product_id'],
					'name'     => $row['name'],
					'quantity' => (int)$row['quantity'],
				);
			}
		}

		$processing_statuses = (array)$this->config->get('config_processing_status');
		$complete_statuses   = (array)$this->config->get('config_complete_status');
		$fraud_status        = (int)$this->config->get('config_fraud_status_id');

		foreach ($results as $result) {
			$order_id = $result['order_id'];
			$order_type = $this->getOrderType($result);
			$order_type_badge_class = $this->getOrderTypeBadgeClass($result);
			$customer_type = $this->getCustomerType($result);
			$customer_type_badge_class = $this->getCustomerTypeBadgeClass($result);
			$status_badge_class = $this->getOrderStatusBadgeClass((int)$result['order_status_id'], $processing_statuses, $complete_statuses, $fraud_status);
			$payment_status = $this->model_sale_order->getPaymentStatus($result['total'], $result['paid_amount'], $this->currency->getDecimalPlace($result['currency_code']), $result['currency_value']);

			// Products summary
			$products = isset($order_products[$order_id]) ? $order_products[$order_id] : array();
			$total_items = 0;
			foreach ($products as $p) {
				$total_items += $p['quantity'];
			}
			$product_names = array();
			$max_show = 3;
			$count = 0;
			$order_localizer = new OrderLocalizer($this->registry);
			foreach ($products as $p) {
				if ($count >= $max_show) {
					break;
				}
				$product_names[] = $order_localizer->productName($p);
				$count++;
			}
			$has_more = count($products) > $max_show;

			$tracking_number = !empty($result['tracking_number']) ? $result['tracking_number'] : '';

			$data['orders'][] = array(
				'order_id'                  => $result['order_id'],
				'customer'                  => $result['customer'],
				'customer_type'             => $customer_type,
				'customer_type_badge_class' => $customer_type_badge_class,
				'order_type'                => $order_type,
				'order_type_badge_class'    => $order_type_badge_class,
				'status'                    => $result['order_status'],
				'order_status_badge_class'  => $status_badge_class,
				'payment_status'            => $payment_status,
				'payment_status_text'       => $this->language->get('text_payment_status_' . $payment_status),
				'payment_status_badge_class' => $this->getPaymentStatusBadgeClass($payment_status),
				'date_added'                => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'total'                     => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'view'                      => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true),
				'payment_method'            => $order_localizer->paymentMethodTitle($result),
				'shipping_method'           => $order_localizer->shippingMethodTitle($result),
				'tracking_number'           => $tracking_number,
				'total_items'               => $total_items,
				'product_names'             => $product_names,
				'products_count'            => count($products),
				'has_more_products'         => $has_more,
			);
		}

		return $this->load->view('extension/dashboard/recent_info', $data);
	}

	private function getOrderType($order) {
		if (!empty($order['customer_id'])) {
			return $this->language->get('text_badge_registered_order');
		}

		return $this->language->get('text_badge_guest_order');
	}

	private function getOrderTypeBadgeClass($order) {
		if (!empty($order['customer_id'])) {
			return 'label label-primary';
		}

		return 'label label-default';
	}

	private function getCustomerType($order) {
		if (!empty($order['customer_id'])) {
			return $this->language->get('text_badge_registered');
		}

		return $this->language->get('text_badge_guest');
	}

	private function getCustomerTypeBadgeClass($order) {
		if (!empty($order['customer_id'])) {
			return 'label label-primary';
		}

		return 'label label-default';
	}

	private function getOrderStatusBadgeClass($order_status_id, $processing_statuses, $complete_statuses, $fraud_status) {
		if ($fraud_status && $order_status_id === $fraud_status) {
			return 'page-header__badge page-header__badge--danger';
		}

		if (in_array($order_status_id, $processing_statuses)) {
			return 'page-header__badge page-header__badge--warning page-header__badge--unfilled';
		}

		if (in_array($order_status_id, $complete_statuses)) {
			return 'page-header__badge page-header__badge--success';
		}

		return 'page-header__badge page-header__badge--default page-header__badge--unfilled';
	}

	private function getPaymentStatusBadgeClass($payment_status) {
		switch ($payment_status) {
			case 'paid':
				return 'page-header__badge page-header__badge--success';
			case 'partial':
				return 'page-header__badge page-header__badge--warning page-header__badge--unfilled';
			case 'overpaid':
				return 'page-header__badge page-header__badge--danger';
			default:
				return 'page-header__badge page-header__badge--default page-header__badge--unfilled';
		}
	}
}
