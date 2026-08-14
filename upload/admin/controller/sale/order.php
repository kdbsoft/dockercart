<?php
class ControllerSaleOrder extends Controller {
	private $error = array();
// List of recently edited files:
	public function index() {
		$this->load->language('sale/order');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('sale/order');

		$this->getList();
	}

	public function add() {
		$this->load->language('sale/order');

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('sale/order');

		$order_id = $this->model_sale_order->createOrder();

		$this->session->data['success'] = sprintf($this->language->get('text_order_created'), $order_id);

		$this->response->redirect($this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id . '&edit=1', true));
	}

	public function edit() {
		if (isset($this->request->get['order_id'])) {
			$this->response->redirect($this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$this->request->get['order_id'], true));
		}

		$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
	}
	
	public function delete() {
		$this->load->language('sale/order');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->session->data['success'] = $this->language->get('text_success');

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}
	
		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . $this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_payment_status'])) {
			$url .= '&filter_payment_status=' . $this->request->get['filter_payment_status'];
		}
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}



		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url, true));
	}

	public function exportCsv(): void {
		$this->load->language('sale/order');

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$ids = array();

		if (isset($this->request->get['order_id'])) {
			if (is_array($this->request->get['order_id'])) {
				$ids = $this->request->get['order_id'];
			} else {
				$ids = explode(',', (string)$this->request->get['order_id']);
			}

			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		}

		if (!$ids) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('sale/order');

		$items_query = $this->db->query("SELECT order_id, SUM(quantity) AS total_items FROM " . DB_PREFIX . "order_product WHERE order_id IN (" . implode(',', $ids) . ") GROUP BY order_id");

		$items_count = array();

		foreach ($items_query->rows as $row) {
			$items_count[(int)$row['order_id']] = (int)$row['total_items'];
		}

		$headers = array(
			$this->language->get('column_order_id'),
			$this->language->get('column_date_added'),
			$this->language->get('column_customer'),
			$this->language->get('entry_email'),
			$this->language->get('entry_telephone'),
			$this->language->get('text_payment_method'),
			$this->language->get('text_shipping_method'),
			$this->language->get('column_status'),
			$this->language->get('text_payment_status'),
			$this->language->get('column_items'),
			$this->language->get('column_total'),
			$this->language->get('column_paid'),
			$this->language->get('text_tracking_number'),
			$this->language->get('column_comment')
		);

		$rows = array();

		foreach ($ids as $order_id) {
			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info) {
				continue;
			}

			$payment_status = $this->model_sale_order->getPaymentStatus($order_info['total'], $order_info['paid_amount']);
			$order_localizer = new OrderLocalizer($this->registry);
			$currency_code = $order_info['currency_code'];
			$currency_value = $order_info['currency_value'];

			$rows[] = array(
				$order_id,
				date($this->language->get('datetime_format'), strtotime($order_info['date_added'])),
				$order_info['firstname'] . ' ' . $order_info['lastname'],
				$order_info['email'],
				$order_info['telephone'],
				$order_localizer->paymentMethodTitle($order_info),
				$order_localizer->shippingMethodTitle($order_info),
				$order_info['order_status'] ? $order_info['order_status'] : $this->language->get('text_missing'),
				$this->language->get('text_payment_status_' . $payment_status),
				isset($items_count[(int)$order_id]) ? $items_count[(int)$order_id] : 0,
				$this->currency->format($order_info['total'], $currency_code, $currency_value),
				$this->currency->format($order_info['paid_amount'], $currency_code, $currency_value),
				str_replace('|', ' | ', (string)$order_info['tracking_number']),
				$order_info['comment']
			);
		}

		if (!$rows) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$output = "\xEF\xBB\xBF" . $this->csvLine($headers);

		foreach ($rows as $row) {
			$output .= $this->csvLine($row);
		}

		$filename = 'orders-' . date('Ymd-His') . '.csv';

		$this->response->addHeader('Content-Type: text/csv; charset=UTF-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($output);
	}

	private function csvLine(array $fields): string {
		$quoted = array();

		foreach ($fields as $field) {
			$quoted[] = '"' . str_replace('"', '""', (string)$field) . '"';
		}

		return implode(',', $quoted) . "\r\n";
	}
			
	protected function getList() {
		if (isset($this->request->get['filter_order_id'])) {
			$filter_order_id = $this->request->get['filter_order_id'];
		} else {
			$filter_order_id = '';
		}

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = '';
		}

		if (isset($this->request->get['filter_order_status'])) {
			$filter_order_status = $this->request->get['filter_order_status'];
		} else {
			$filter_order_status = '';
		}
		
		if (isset($this->request->get['filter_order_status_id'])) {
			$filter_order_status_id = $this->request->get['filter_order_status_id'];
		} else {
			$filter_order_status_id = '';
		}

		if (isset($this->request->get['filter_payment_status'])) {
			$filter_payment_status = $this->request->get['filter_payment_status'];
		} else {
			$filter_payment_status = '';
		}
		
		if (isset($this->request->get['filter_total'])) {
			$filter_total = $this->request->get['filter_total'];
		} else {
			$filter_total = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		if (isset($this->request->get['filter_date_added_operator'])) {
			$filter_date_added_operator = $this->request->get['filter_date_added_operator'];
		} else {
			$filter_date_added_operator = '';
		}

		if (isset($this->request->get['filter_total_operator'])) {
			$filter_total_operator = $this->request->get['filter_total_operator'];
		} else {
			$filter_total_operator = '';
		}

		// Active saved filter (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('order');
		$active_filter_id = isset($this->request->get['filter_id']) ? (int)$this->request->get['filter_id'] : 0;

		$this->load->model('user/user_filter');
		$this->load->model('extension/module/dockercart_checkout');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'order');

		$active_builtin = isset($this->request->get['filter']) ? $this->request->get['filter'] : '';

		$tab_counts = array(
			'all' => $this->model_sale_order->getTotalOrders(array()),
			'unfulfilled' => $this->model_sale_order->getTotalOrders(array('filter_order_status_exclude' => $this->getFulfilledStatusIds())),
			'unpaid' => $this->model_sale_order->getTotalOrders(array('filter_payment_status' => 'unpaid')),
			'abandoned' => $this->countAbandonedCarts()
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_sale_order->getTotalOrders($this->buildFilterData($saved['conditions']));
		}

		// Add builtin tabs (unfulfilled / unpaid / abandoned) on top of the "All" tab
		$builtin_tabs = array(
			array(
				'id'    => 'unfulfilled',
				'name'  => $this->language->get('text_filter_unfulfilled'),
				'href'  => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&filter=unfulfilled', true),
				'count' => $tab_counts['unfulfilled'],
				'is_active' => $active_builtin === 'unfulfilled'
			),
			array(
				'id'    => 'unpaid',
				'name'  => $this->language->get('text_filter_unpaid'),
				'href'  => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&filter=unpaid', true),
				'count' => $tab_counts['unpaid'],
				'is_active' => $active_builtin === 'unpaid'
			),
			array(
				'id'    => 'abandoned',
				'name'  => $this->language->get('text_filter_abandoned'),
				'href'  => $this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true),
				'count' => $tab_counts['abandoned'],
				'is_active' => $active_builtin === 'abandoned'
			)
		);

		$data['user_filter'] = $this->renderUserFilter('order', 'sale/order', array(
			array('key' => 'order_status', 'label' => $this->language->get('entry_order_status'), 'type' => 'multi', 'options' => $this->getOrderStatusOptions()),
			array('key' => 'payment_status', 'label' => $this->language->get('text_payment_status'), 'type' => 'select', 'options' => array(
				array('value' => 'unpaid', 'label' => $this->language->get('text_payment_status_unpaid')),
				array('value' => 'partial', 'label' => $this->language->get('text_payment_status_partial')),
				array('value' => 'paid', 'label' => $this->language->get('text_payment_status_paid')),
				array('value' => 'overpaid', 'label' => $this->language->get('text_payment_status_overpaid'))
			)),
			array('key' => 'payment_method', 'label' => $this->language->get('text_payment_method'), 'type' => 'text'),
			array('key' => 'shipping_method', 'label' => $this->language->get('text_shipping_method'), 'type' => 'text'),
			array('key' => 'total', 'label' => $this->language->get('entry_total'), 'type' => 'range'),
			array('key' => 'date_preset', 'label' => $this->language->get('entry_date_added'), 'type' => 'preset', 'options' => $this->getDatePresetOptions())
		), $tab_counts, $active_builtin, $builtin_tabs, array(
			'placeholder' => $this->language->get('text_search_orders'),
			'url'         => $this->url->link('sale/order/autocomplete', 'user_token=' . $this->session->data['user_token'], true)
		));

		$data['active_filter'] = $active_filter;
		$data['active_filter_id'] = $active_filter_id;
		$data['filter'] = $active_builtin;

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'o.order_id';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}
	
		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . $this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_payment_status'])) {
			$url .= '&filter_payment_status=' . $this->request->get['filter_payment_status'];
		}
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}



		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['delete'] = str_replace('&amp;', '&', $this->url->link('sale/order/delete', 'user_token=' . $this->session->data['user_token'] . $url, true));
		$data['add'] = str_replace('&amp;', '&', $this->url->link('sale/order/add', 'user_token=' . $this->session->data['user_token'], true));
		$data['print_url'] = str_replace('&amp;', '&', $this->url->link('sale/order_detail/printSelected', 'user_token=' . $this->session->data['user_token'], true));
		$data['export_url'] = str_replace('&amp;', '&', $this->url->link('sale/order/exportCsv', 'user_token=' . $this->session->data['user_token'], true));
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		$this->load->model('customer/customer_group');
		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		$data['customer_group_default'] = (int)$this->config->get('config_customer_group_id');

		$data['orders'] = array();

		$filter_data = array(
			'filter_order_id'        => $filter_order_id,
			'filter_customer'	     => $filter_customer,
			'filter_order_status'    => $filter_order_status,
			'filter_order_status_id' => $filter_order_status_id,
			'filter_payment_status'  => $filter_payment_status,
			'filter_total'           => $filter_total,
			'filter_total_operator'  => $filter_total_operator,
			'filter_date_added'      => $filter_date_added,
			'filter_date_added_operator' => $filter_date_added_operator,
			'sort'                   => $sort,
			'order'                  => $order,
			'start'                  => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                  => $this->config->get('config_limit_admin')
		);

		// Apply built-in quick filter (unfulfilled / unpaid)
		if (isset($this->request->get['filter']) && $this->request->get['filter'] === 'unfulfilled') {
			$filter_data['filter_order_status_exclude'] = $this->getFulfilledStatusIds();
		} elseif (isset($this->request->get['filter']) && $this->request->get['filter'] === 'unpaid') {
			$filter_data['filter_payment_status'] = 'unpaid';
		}

		// Apply conditions of the active saved filter
		if ($active_filter) {
			$this->applyFilterConditions($filter_data, $active_filter['conditions']);
		}

		$order_total = $this->model_sale_order->getTotalOrders($filter_data);

		$results = $this->model_sale_order->getOrders($filter_data);

		$processing_statuses = (array)$this->config->get('config_processing_status');
		$complete_statuses   = (array)$this->config->get('config_complete_status');
		$fraud_status        = (int)$this->config->get('config_fraud_status_id');

		$order_ids = array();
		foreach ($results as $result) {
			$order_ids[] = (int)$result['order_id'];
		}

		$order_items = array();
		if ($order_ids) {
			$item_query = $this->db->query(
				"SELECT order_id, SUM(quantity) AS total_items FROM " . DB_PREFIX . "order_product WHERE order_id IN (" . implode(',', $order_ids) . ") GROUP BY order_id"
			);
			foreach ($item_query->rows as $row) {
				$order_items[(int)$row['order_id']] = (int)$row['total_items'];
			}
		}

		foreach ($results as $result) {
			$order_type = $this->getOrderType($result);
			$order_type_badge_class = $this->getOrderTypeBadgeClass($result);
			$customer_type = $this->getCustomerType($result);
			$customer_type_badge_class = $this->getCustomerTypeBadgeClass($result);
			$status_badge_class = $this->getOrderStatusBadgeClass((int)$result['order_status_id'], $processing_statuses, $complete_statuses, $fraud_status);
			$payment_status = $this->model_sale_order->getPaymentStatus($result['total'], $result['paid_amount']);
			$order_localizer = new OrderLocalizer($this->registry);

			$data['orders'][] = array(
				'order_id'      => $result['order_id'],
				'customer'      => $result['customer'],
				'customer_type' => $customer_type,
				'customer_type_badge_class' => $customer_type_badge_class,
				'order_type'    => $order_type,
				'order_type_badge_class' => $order_type_badge_class,
				'order_status_id' => $result['order_status_id'],
				'order_status'  => $result['order_status'] ? $result['order_status'] : $this->language->get('text_missing'),
				'order_status_badge_class' => $status_badge_class,
				'payment_status' => $payment_status,
				'payment_status_text' => $this->language->get('text_payment_status_' . $payment_status),
				'payment_status_badge_class' => $this->getPaymentStatusBadgeClass($payment_status),
				'payment_method' => $order_localizer->paymentMethodTitle($result),
				'paid_amount'   => $this->currency->format($result['paid_amount'], $result['currency_code'], $result['currency_value']),
				'shipping_method' => $order_localizer->shippingMethodTitle($result),
				'items_count'   => isset($order_items[(int)$result['order_id']]) ? $order_items[(int)$result['order_id']] : 0,
				'tracking_number' => $result['tracking_number'],
				'total'         => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'date_added'    => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'shipping_code' => $result['shipping_code'],
				'view'          => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'] . $url, true),
				'edit'          => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'] . '&edit=1' . $url, true),
				'delete_id'     => $result['order_id']
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['store_id'] = 0;

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}
		
		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}
		
		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . $this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_payment_status'])) {
			$url .= '&filter_payment_status=' . $this->request->get['filter_payment_status'];
		}
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}



		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_order'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=o.order_id' . $url, true);
		$data['sort_customer'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=customer' . $url, true);
		$data['sort_status'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=order_status' . $url, true);
		$data['sort_total'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=o.total' . $url, true);
		$data['sort_date_added'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=o.date_added' . $url, true);
		$data['sort_date_modified'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . '&sort=o.date_modified' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_order_id'])) {
			$url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
		}

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_order_status'])) {
			$url .= '&filter_order_status=' . $this->request->get['filter_order_status'];
		}
		
		if (isset($this->request->get['filter_order_status_id'])) {
			$url .= '&filter_order_status_id=' . $this->request->get['filter_order_status_id'];
		}

		if (isset($this->request->get['filter_payment_status'])) {
			$url .= '&filter_payment_status=' . $this->request->get['filter_payment_status'];
		}
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}



		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $order_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

		$data['filter_order_id'] = $filter_order_id;
		$data['filter_customer'] = $filter_customer;
		$data['filter_order_status'] = $filter_order_status;
		$data['filter_order_status_id'] = $filter_order_status_id;
		$data['filter_payment_status'] = $filter_payment_status;
		$data['filter_total'] = $filter_total;
		$data['filter_total_operator'] = $filter_total_operator;
		$data['filter_date_added'] = $filter_date_added;
		$data['filter_date_added_operator'] = $filter_date_added_operator;

		$data['payment_statuses'] = array(
			'unpaid'   => $this->language->get('text_payment_status_unpaid'),
			'partial'  => $this->language->get('text_payment_status_partial'),
			'paid'     => $this->language->get('text_payment_status_paid'),
			'overpaid' => $this->language->get('text_payment_status_overpaid')
		);

		$data['filter_operators'] = array(
			'eq'   => $this->language->get('text_operator_eq'),
			'ne'   => $this->language->get('text_operator_ne'),
			'gt'   => $this->language->get('text_operator_gt'),
			'gte'  => $this->language->get('text_operator_gte'),
			'lt'   => $this->language->get('text_operator_lt'),
			'lte'  => $this->language->get('text_operator_lte'),
			'contains' => $this->language->get('text_operator_contains')
		);

		$data['sort'] = $sort;
		$data['order'] = $order;

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order_list', $data));
	}
		

	public function info() {
		if (isset($this->request->get['order_id'])) {
			$this->response->redirect($this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$this->request->get['order_id'], true));
		}

		$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function quickEdit() {
		$this->load->language('sale/order');

		$json = array();

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['order_id'])) {
			$json['error'] = $this->language->get('error_action');
		}

		$this->load->model('sale/order');

		if (!$json) {
			$order_id = (int)$this->request->get['order_id'];
			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info) {
				$json['error'] = $this->language->get('error_action');
			}
		}

		if (!$json) {
			$field = isset($this->request->post['field']) ? $this->request->post['field'] : '';
			$update_data = array();

			switch ($field) {
				case 'customer_name':
					$firstname = isset($this->request->post['firstname']) ? trim($this->request->post['firstname']) : '';
					$lastname = isset($this->request->post['lastname']) ? trim($this->request->post['lastname']) : '';

					if (!$firstname || !$lastname) {
						$json['error'] = $this->language->get('error_warning');
					} else {
						$update_data['firstname'] = $firstname;
						$update_data['lastname'] = $lastname;
					}
					break;

				case 'email':
					$email = isset($this->request->post['value']) ? trim($this->request->post['value']) : '';

					if ((utf8_strlen($email) > 96) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
						$json['error'] = $this->language->get('error_warning');
					} else {
						$update_data['email'] = $email;
					}
					break;

				case 'telephone':
					$update_data['telephone'] = isset($this->request->post['value']) ? trim($this->request->post['value']) : '';
					break;

				case 'tax_number':
					$update_data['tax_number'] = isset($this->request->post['value']) ? trim($this->request->post['value']) : '';
					break;

				case 'tracking_number':
					$update_data['tracking_number'] = isset($this->request->post['value']) ? trim($this->request->post['value']) : '';
					break;

				case 'payment_method':
					$update_data['payment_method'] = isset($this->request->post['title']) ? trim($this->request->post['title']) : '';
					$update_data['payment_code'] = isset($this->request->post['code']) ? trim($this->request->post['code']) : '';

					if (!$update_data['payment_method'] && isset($this->request->post['value'])) {
						$update_data['payment_method'] = trim($this->request->post['value']);
					}

					if (!$update_data['payment_code']) {
						$json['error'] = $this->language->get('error_warning');
					}
					break;

				case 'shipping_method':
					$update_data['shipping_method'] = isset($this->request->post['title']) ? trim($this->request->post['title']) : '';
					$update_data['shipping_code'] = isset($this->request->post['code']) ? trim($this->request->post['code']) : '';

					if (!$update_data['shipping_method'] && isset($this->request->post['value'])) {
						$update_data['shipping_method'] = trim($this->request->post['value']);
					}

					if (!$update_data['shipping_code']) {
						$json['error'] = $this->language->get('error_warning');
					}
					break;

				case 'payment_address':
				case 'shipping_address':
					$prefix = ($field == 'payment_address') ? 'payment' : 'shipping';

					$required_keys = array('firstname', 'lastname', 'address_1', 'city', 'country_id', 'zone_id');

					foreach ($required_keys as $required_key) {
						if (!isset($this->request->post[$required_key]) || trim((string)$this->request->post[$required_key]) === '') {
							$json['error'] = $this->language->get('error_warning');
							break;
						}
					}

					if (!$json) {
						$this->load->model('localisation/country');
						$this->load->model('localisation/zone');

						$country_id = (int)$this->request->post['country_id'];
						$zone_id = (int)$this->request->post['zone_id'];

						$country_info = $this->model_localisation_country->getCountry($country_id);
						$zone_info = $this->model_localisation_zone->getZone($zone_id);

						$update_data[$prefix . '_firstname'] = trim($this->request->post['firstname']);
						$update_data[$prefix . '_lastname'] = trim($this->request->post['lastname']);
						$update_data[$prefix . '_company'] = isset($this->request->post['company']) ? trim($this->request->post['company']) : '';
						$update_data[$prefix . '_address_1'] = trim($this->request->post['address_1']);
						$update_data[$prefix . '_address_2'] = isset($this->request->post['address_2']) ? trim($this->request->post['address_2']) : '';
						$update_data[$prefix . '_city'] = trim($this->request->post['city']);
						$update_data[$prefix . '_postcode'] = isset($this->request->post['postcode']) ? trim($this->request->post['postcode']) : '';
						$update_data[$prefix . '_country_id'] = $country_id;
						$update_data[$prefix . '_country'] = $country_info ? $country_info['name'] : '';
						$update_data[$prefix . '_zone_id'] = $zone_id;
						$update_data[$prefix . '_zone'] = $zone_info ? $zone_info['name'] : '';
					}
					break;

				case 'comment':
					$update_data['comment'] = isset($this->request->post['value']) ? trim($this->request->post['value']) : '';
					break;

				default:
					$json['error'] = $this->language->get('error_action');
					break;
			}

			if (!$json && !$this->model_sale_order->updateOrderQuick($order_id, $update_data)) {
				$json['error'] = $this->language->get('error_action');
			}

			if (!$json) {
				$order_info = $this->model_sale_order->getOrder($order_id);
				$firstname = htmlspecialchars($order_info['firstname'], ENT_QUOTES, 'UTF-8');
				$lastname = htmlspecialchars($order_info['lastname'], ENT_QUOTES, 'UTF-8');
				$email = htmlspecialchars($order_info['email'], ENT_QUOTES, 'UTF-8');
				$telephone = htmlspecialchars($order_info['telephone'], ENT_QUOTES, 'UTF-8');
				$tax_number = htmlspecialchars($order_info['tax_number'], ENT_QUOTES, 'UTF-8');
				$tracking_number = htmlspecialchars($order_info['tracking_number'], ENT_QUOTES, 'UTF-8');
				$payment_method = htmlspecialchars($order_info['payment_method'], ENT_QUOTES, 'UTF-8');
				$shipping_method = htmlspecialchars($order_info['shipping_method'], ENT_QUOTES, 'UTF-8');
				$comment = nl2br(htmlspecialchars($order_info['comment'], ENT_QUOTES, 'UTF-8'));

				$json['success'] = $this->language->get('text_success');
				$json['field'] = $field;

				switch ($field) {
					case 'customer_name':
						if ($order_info['customer_id']) {
							$customer_link = $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $order_info['customer_id'], true);
							$json['value_html'] = '<a href="' . $customer_link . '" target="_blank">' . $firstname . ' ' . $lastname . '</a>';
						} else {
							$json['value_html'] = $firstname . ' ' . $lastname;
						}
						break;

					case 'email':
						$json['value_html'] = '<a href="mailto:' . $email . '">' . $email . '</a>';
						break;

					case 'telephone':
						$json['value_html'] = $telephone;
						break;

					case 'tax_number':
						$json['value_html'] = $tax_number;
						break;

					case 'tracking_number':
						$json['value_html'] = $tracking_number;
						break;

					case 'payment_method':
						$json['value_html'] = $payment_method;
						$json['method_code'] = $order_info['payment_code'];
						break;

					case 'shipping_method':
						$json['value_html'] = $shipping_method;
						$json['method_code'] = $order_info['shipping_code'];
						break;

					case 'comment':
						$json['value_html'] = $comment;
						break;

					case 'payment_address':
						$json['value_html'] = $this->formatOrderAddress($order_info, 'payment');
						break;

					case 'shipping_address':
						$json['value_html'] = $this->formatOrderAddress($order_info, 'shipping');
						break;
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteOrder(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			if ($order_id) {
				$this->load->model('sale/order');

				$order_info = $this->model_sale_order->getOrder($order_id);

				if ($order_info) {
					$this->model_sale_order->deleteOrder($order_id);

					$json['success'] = $this->language->get('text_success');
				} else {
					$json['error'] = $this->language->get('error_action');
				}
			} else {
				$json['error'] = $this->language->get('error_action');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addHistory(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$order_status_id = (int)($this->request->post['order_status_id'] ?? 0);
			$comment = $this->request->post['comment'] ?? '';
			$notify = !empty($this->request->post['notify']);
			$override = !empty($this->request->post['override']);

			if (!$order_status_id) {
				$json['error'] = $this->language->get('error_order_status');
			} else {
				$this->load->model('sale/order');

				$order_info = $this->model_sale_order->getOrder($order_id);

				if ($order_info) {
					$shipping_status_id = (int)$this->config->get('config_order_flow_shipping_status');

					if (!$override && $shipping_status_id > 0 && $order_status_id === $shipping_status_id && !$order_info['tracking_number']) {
						$json['error'] = $this->language->get('error_tracking_required');
					} elseif (!$this->model_sale_order->addOrderHistory($order_id, $order_status_id, $comment, $notify, $override)) {
						$json['error'] = $this->language->get('error_invalid_transition');
					} else {
						$json['success'] = $this->language->get('text_success');
					}
				} else {
					$json['error'] = $this->language->get('error_action');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX autocomplete for the order list search bar.
	 * Finds orders by ID, customer name, email or phone.
	 */
	public function autocomplete(): void {
		$this->load->language('sale/order');

		$json = array();

		if (isset($this->request->get['filter_search'])) {
			$filter_search = trim((string)$this->request->get['filter_search']);
		} else {
			$filter_search = '';
		}

		if ($filter_search !== '') {
			$this->load->model('common/admin_search');

			$manticore = $this->model_common_admin_search->searchEntity('order', $filter_search, array('limit' => 8));

			if ($manticore === false) {
				// Fallback: Manticore unavailable → SQL LIKE path
				$this->load->model('sale/order');

				$is_numeric = ctype_digit($filter_search);

				$filter_data = array(
					'filter_order_id'  => $is_numeric ? $filter_search : '',
					'filter_customer'  => $is_numeric ? '' : $filter_search,
					'sort'             => 'o.order_id',
					'order'            => 'DESC',
					'start'            => 0,
					'limit'            => 8
				);

				$results = $this->model_sale_order->getOrders($filter_data);

				foreach ($results as $result) {
					$json[] = array(
						'id'       => '#' . $result['order_id'],
						'name'     => $result['customer'],
						'subtitle' => date($this->language->get('datetime_format'), strtotime($result['date_added'])) . ' · ' . ($result['order_status'] ? $result['order_status'] : $this->language->get('text_missing')),
						'meta'     => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
						'href'     => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'], true)
					);
				}
			} else {
				foreach ($manticore['results'] as $result) {
					$row = $result['row'];

					$json[] = array(
						'id'       => '#' . $result['id'],
						'name'     => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')),
						'subtitle' => date($this->language->get('datetime_format'), (int)($row['date_added'] ?? 0)) . ' · ' . ($row['order_status_name'] ? $row['order_status_name'] : $this->language->get('text_missing')),
						'meta'     => $this->currency->format((float)($row['total'] ?? 0), $row['currency_code'] ?? $this->config->get('config_currency'), $this->currency->getValue($row['currency_code'] ?? $this->config->get('config_currency'))),
						'href'     => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['id'], true)
					);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Order statuses considered "fulfilled" (order complete). Used by the
	 * built-in Unfulfilled tab. Falls back to config_complete_status.
	 */
	private function getFulfilledStatusIds(): array {
		$complete = (array)$this->config->get('config_complete_status');

		$ids = array_filter(array_map('intval', $complete));

		return $ids ? $ids : array(0);
	}

	private function getOrderStatusOptions(): array {
		$this->load->model('localisation/order_status');

		$options = array();

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$options[] = array(
				'value' => $status['order_status_id'],
				'label' => $status['name']
			);
		}

		return $options;
	}

	private function getDatePresetOptions(): array {
		return array(
			array('value' => 'today', 'label' => $this->language->get('text_date_today')),
			array('value' => 'yesterday', 'label' => $this->language->get('text_date_yesterday')),
			array('value' => 'this_week', 'label' => $this->language->get('text_date_this_week')),
			array('value' => 'this_month', 'label' => $this->language->get('text_date_this_month')),
			array('value' => 'this_year', 'label' => $this->language->get('text_date_this_year'))
		);
	}

	/**
	 * Convert saved filter conditions into model filter_data.
	 */
	private function buildFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$operator = (string)($condition['operator'] ?? 'eq');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'order_status':
					if ($operator === 'ne' && is_array($value)) {
						$data['filter_order_status_exclude'] = array_map('intval', $value);
					} elseif (is_array($value)) {
						$data['filter_order_status'] = implode(',', array_map('intval', $value));
					}
					break;

				case 'payment_status':
					$data['filter_payment_status'] = $value;
					break;

				case 'payment_method':
					$data['filter_payment_method'] = (string)$value;
					break;

				case 'shipping_method':
					$data['filter_shipping_method'] = (string)$value;
					break;

				case 'total':
					if (isset($condition['value_min']) || isset($condition['value_max'])) {
						if (isset($condition['value_min']) && $condition['value_min'] !== '') {
							$data['filter_total_min'] = $condition['value_min'];
						}

						if (isset($condition['value_max']) && $condition['value_max'] !== '') {
							$data['filter_total_max'] = $condition['value_max'];
						}
					} else {
						$data['filter_total'] = (string)$value;
						$data['filter_total_operator'] = $operator;
					}
					break;

				case 'date_preset':
					$data['filter_date_preset'] = (string)$value;
					break;
			}
		}

		return $data;
	}

	/**
	 * Merge saved filter conditions into an existing filter_data array.
	 */
	private function applyFilterConditions(array &$filter_data, array $conditions): void {
		$saved = $this->buildFilterData($conditions);

		// Saved conditions win over URL params for the same keys.
		foreach ($saved as $key => $value) {
			$filter_data[$key] = $value;
		}
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
	
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function formatOrderAddress($order_info, $type = 'payment') {
		$prefix = $type . '_';

		if (!empty($order_info[$prefix . 'address_format'])) {
			$format = $order_info[$prefix . 'address_format'];
		} else {
			$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
		}

		$find = array('{firstname}', '{lastname}', '{company}', '{address_1}', '{address_2}', '{city}', '{postcode}', '{zone}', '{zone_code}', '{country}');
		$replace = array(
			'firstname' => isset($order_info[$prefix . 'firstname']) ? $order_info[$prefix . 'firstname'] : '',
			'lastname'  => isset($order_info[$prefix . 'lastname']) ? $order_info[$prefix . 'lastname'] : '',
			'company'   => isset($order_info[$prefix . 'company']) ? $order_info[$prefix . 'company'] : '',
			'address_1' => isset($order_info[$prefix . 'address_1']) ? $order_info[$prefix . 'address_1'] : '',
			'address_2' => isset($order_info[$prefix . 'address_2']) ? $order_info[$prefix . 'address_2'] : '',
			'city'      => isset($order_info[$prefix . 'city']) ? $order_info[$prefix . 'city'] : '',
			'postcode'  => isset($order_info[$prefix . 'postcode']) ? $order_info[$prefix . 'postcode'] : '',
			'zone'      => isset($order_info[$prefix . 'zone']) ? $order_info[$prefix . 'zone'] : '',
			'zone_code' => isset($order_info[$prefix . 'zone_code']) ? $order_info[$prefix . 'zone_code'] : '',
			'country'   => isset($order_info[$prefix . 'country']) ? $order_info[$prefix . 'country'] : ''
		);

		return str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));
	}

	protected function countAbandonedCarts() {
		try {
			return (int)$this->model_extension_module_dockercart_checkout->getTotalAbandonedCarts();
		} catch (\Exception $e) {
			// Table may not exist yet (module not installed)
			return 0;
		}
	}
}
