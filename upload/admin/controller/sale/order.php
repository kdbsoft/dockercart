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
		$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
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
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
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

		if (isset($this->request->get['filter_date_modified'])) {
			$filter_date_modified = $this->request->get['filter_date_modified'];
		} else {
			$filter_date_modified = '';
		}

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
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['delete'] = str_replace('&amp;', '&', $this->url->link('sale/order/delete', 'user_token=' . $this->session->data['user_token'] . $url, true));
		$data['text_list_subtitle'] = $this->language->get('text_list_subtitle');

		$data['orders'] = array();

		$filter_data = array(
			'filter_order_id'        => $filter_order_id,
			'filter_customer'	     => $filter_customer,
			'filter_order_status'    => $filter_order_status,
			'filter_order_status_id' => $filter_order_status_id,
			'filter_total'           => $filter_total,
			'filter_date_added'      => $filter_date_added,
			'filter_date_modified'   => $filter_date_modified,
			'sort'                   => $sort,
			'order'                  => $order,
			'start'                  => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                  => $this->config->get('config_limit_admin')
		);

		$order_total = $this->model_sale_order->getTotalOrders($filter_data);

		$results = $this->model_sale_order->getOrders($filter_data);

		$processing_statuses = (array)$this->config->get('config_processing_status');
		$complete_statuses   = (array)$this->config->get('config_complete_status');
		$fraud_status        = (int)$this->config->get('config_fraud_status_id');

		foreach ($results as $result) {
			$order_type = $this->getOrderType($result);
			$order_type_badge_class = $this->getOrderTypeBadgeClass($result);
			$customer_type = $this->getCustomerType($result);
			$customer_type_badge_class = $this->getCustomerTypeBadgeClass($result);
			$status_badge_class = $this->getOrderStatusBadgeClass((int)$result['order_status_id'], $processing_statuses, $complete_statuses, $fraud_status);

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
				'tracking_number' => $result['tracking_number'],
				'total'         => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'date_added'    => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'date_modified' => date($this->language->get('datetime_format'), strtotime($result['date_modified'])),
				'shipping_code' => $result['shipping_code'],
				'view'          => $this->url->link('sale/order_detail', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['order_id'] . $url, true),
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
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
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
			
		if (isset($this->request->get['filter_total'])) {
			$url .= '&filter_total=' . $this->request->get['filter_total'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_date_modified'])) {
			$url .= '&filter_date_modified=' . $this->request->get['filter_date_modified'];
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

		$data['results'] = sprintf($this->language->get('text_pagination'), ($order_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($order_total - $this->config->get('config_limit_admin'))) ? $order_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $order_total, ceil($order_total / $this->config->get('config_limit_admin')));

		$data['filter_order_id'] = $filter_order_id;
		$data['filter_customer'] = $filter_customer;
		$data['filter_order_status'] = $filter_order_status;
		$data['filter_order_status_id'] = $filter_order_status_id;
		$data['filter_total'] = $filter_total;
		$data['filter_date_added'] = $filter_date_added;
		$data['filter_date_modified'] = $filter_date_modified;

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
					$this->model_sale_order->addOrderHistory($order_id, $order_status_id, $comment, $notify, $override);

					$json['success'] = $this->language->get('text_success');
				} else {
					$json['error'] = $this->language->get('error_action');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
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
			return 'order-status-badge label label-danger';
		}

		if (in_array($order_status_id, $processing_statuses)) {
			return 'order-status-badge label label-warning';
		}

		if (in_array($order_status_id, $complete_statuses)) {
			return 'order-status-badge label label-success';
		}

		return 'order-status-badge label label-default';
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
}
