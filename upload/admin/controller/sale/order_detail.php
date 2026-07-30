<?php
declare(strict_types=1);

class ControllerSaleOrderDetail extends Controller {
	private array $error = [];

	public function index(): void {
		$this->load->language('sale/order');

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		$this->load->model('sale/order');
		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->document->setTitle(sprintf($this->language->get('text_order_number'), $order_id));

		$data['heading_title'] = sprintf($this->language->get('text_order_number'), $order_id);

		$url = $this->buildFilterUrl();

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url, true)
		];

		$data['cancel'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['print_url'] = $this->url->link('sale/order_detail/print', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['order_id'] = $order_id;

		$data['store_id'] = $order_info['store_id'];
		$data['store_name'] = $order_info['store_name'];

		if ($order_info['store_id'] == 0) {
			$data['store_url'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;
		} else {
			$data['store_url'] = $order_info['store_url'];
		}

		$data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));
		$data['date_modified'] = date($this->language->get('datetime_format'), strtotime($order_info['date_modified']));

		$data['firstname'] = $order_info['firstname'];
		$data['lastname'] = $order_info['lastname'];
		$data['email'] = $order_info['email'];
		$data['telephone'] = $order_info['telephone'];
		$data['tax_number'] = $order_info['tax_number'];

		$data['customer_id'] = $order_info['customer_id'];
		$data['customer_group_id'] = $order_info['customer_group_id'];

		if ($order_info['customer_id']) {
			$data['customer_link'] = $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $order_info['customer_id'], true);
		} else {
			$data['customer_link'] = '';
		}

		$this->load->model('customer/customer_group');
		$customer_group_info = $this->model_customer_customer_group->getCustomerGroup($order_info['customer_group_id']);
		$data['customer_group'] = $customer_group_info ? $customer_group_info['name'] : '';

		$data['ip'] = $order_info['ip'];
		$data['forwarded_ip'] = $order_info['forwarded_ip'];
		$data['user_agent'] = $order_info['user_agent'];
		$data['accept_language'] = $order_info['accept_language'];
		$data['tracking_number'] = $order_info['tracking_number'];
		$data['comment'] = $order_info['comment'];
		$data['payment_code'] = $order_info['payment_code'];
		$data['shipping_code'] = $order_info['shipping_code'];

		$data['payment_method'] = $order_info['payment_method'];
		$data['shipping_method'] = $order_info['shipping_method'];

		$data['payment_firstname'] = $order_info['payment_firstname'];
		$data['payment_lastname'] = $order_info['payment_lastname'];
		$data['payment_company'] = $order_info['payment_company'];
		$data['payment_address_1'] = $order_info['payment_address_1'];
		$data['payment_address_2'] = $order_info['payment_address_2'];
		$data['payment_city'] = $order_info['payment_city'];
		$data['payment_postcode'] = $order_info['payment_postcode'];
		$data['payment_country_id'] = $order_info['payment_country_id'];
		$data['payment_zone_id'] = $order_info['payment_zone_id'];
		$data['payment_country'] = $order_info['payment_country'];
		$data['payment_zone'] = $order_info['payment_zone'];

		$data['shipping_firstname'] = $order_info['shipping_firstname'];
		$data['shipping_lastname'] = $order_info['shipping_lastname'];
		$data['shipping_company'] = $order_info['shipping_company'];
		$data['shipping_address_1'] = $order_info['shipping_address_1'];
		$data['shipping_address_2'] = $order_info['shipping_address_2'];
		$data['shipping_city'] = $order_info['shipping_city'];
		$data['shipping_postcode'] = $order_info['shipping_postcode'];
		$data['shipping_country_id'] = $order_info['shipping_country_id'];
		$data['shipping_zone_id'] = $order_info['shipping_zone_id'];
		$data['shipping_country'] = $order_info['shipping_country'];
		$data['shipping_zone'] = $order_info['shipping_zone'];

		$data['payment_address'] = $this->formatAddress($order_info, 'payment');
		$data['shipping_address'] = $this->formatAddress($order_info, 'shipping');

		$data['addresses_match'] = (
			$order_info['payment_firstname'] === $order_info['shipping_firstname'] &&
			$order_info['payment_lastname'] === $order_info['shipping_lastname'] &&
			$order_info['payment_address_1'] === $order_info['shipping_address_1'] &&
			$order_info['payment_address_2'] === $order_info['shipping_address_2'] &&
			$order_info['payment_city'] === $order_info['shipping_city'] &&
			$order_info['payment_postcode'] === $order_info['shipping_postcode'] &&
			$order_info['payment_country_id'] == $order_info['shipping_country_id'] &&
			$order_info['payment_zone_id'] == $order_info['shipping_zone_id']
		);

		$processing_statuses = (array)$this->config->get('config_processing_status');
		$complete_statuses = (array)$this->config->get('config_complete_status');
		$fraud_status = (int)$this->config->get('config_fraud_status_id');
		$data['status_badge_class'] = $this->getStatusBadgeClass((int)$order_info['order_status_id'], $processing_statuses, $complete_statuses, $fraud_status);
		$data['order_status_id'] = $order_info['order_status_id'];
		$data['order_status'] = $order_info['order_status'];

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/country');
		$data['countries'] = $this->model_localisation_country->getCountries();

		$data['currency_code'] = $order_info['currency_code'];
		$data['currency_value'] = $order_info['currency_value'];

		$this->load->model('tool/image');
		$this->load->model('catalog/product');

		$order_product_discounts = $this->model_sale_order->getOrderProductDiscounts($order_id);
		$products = $this->model_sale_order->getOrderProducts($order_id);
		$data['products'] = [];
		$data['product_count'] = count($products);
		$data['total_quantity'] = array_sum(array_column($products, 'quantity'));

		foreach ($products as $product) {
			$product_info = $this->model_catalog_product->getProduct($product['product_id']);

			if ($product_info && !empty($product_info['image']) && is_file(DIR_IMAGE . $product_info['image'])) {
				$thumb = $this->model_tool_image->resize($product_info['image'], 40, 40);
			} else {
				$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
			}

			$options = $this->model_sale_order->getOrderOptions($order_id, $product['order_product_id']);
			$option_data = [];

			foreach ($options as $option) {
				$option_data[] = [
					'name'  => $option['name'],
					'value' => $option['value'],
					'type'  => $option['type'],
				];
			}

			$data['products'][] = [
				'order_product_id' => $product['order_product_id'],
				'product_id'       => $product['product_id'],
				'name'             => $product['name'],
				'model'            => $product['model'],
				'option'           => $option_data,
				'quantity'         => $product['quantity'],
				'price'            => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
				'price_raw'        => $product['price'],
				'tax_raw'          => $product['tax'],
				'total'            => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
				'total_raw'        => $product['total'],
				'discount_percent' => $order_product_discounts[(int)$product['order_product_id']] ?? 0,
				'thumb'            => $thumb,
				'href'             => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product['product_id'], true),
			];
		}

		$data['totals'] = [];
		$totals = $this->model_sale_order->getOrderTotals($order_id);

		foreach ($totals as $total) {
			$data['totals'][] = [
				'code'  => $total['code'],
				'title' => $total['title'],
				'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				'value' => $total['value'],
			];
		}

		$data['reward'] = $order_info['reward'];
		$this->load->model('customer/customer');
		$data['reward_total'] = $this->model_customer_customer->getTotalCustomerRewardsByOrderId($order_id);

		$data['affiliate_firstname'] = $order_info['affiliate_firstname'];
		$data['affiliate_lastname'] = $order_info['affiliate_lastname'];
		$data['affiliate_id'] = (int)$order_info['affiliate_id'];
		$data['commission'] = $this->currency->format($order_info['commission'], $order_info['currency_code'], $order_info['currency_value']);
		$data['commission_total'] = $this->model_customer_customer->getTotalTransactionsByOrderId($order_id);

		$data['customer_type'] = $this->getCustomerType($order_info);
		$data['customer_type_badge'] = $this->getCustomerTypeBadgeClass($order_info);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order_detail', $data));
	}

	public function print(): void {
		$this->load->language('sale/order');

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		$this->load->model('sale/order');
		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('tool/image');

		$data['order_id'] = $order_id;
		$data['store_name'] = $order_info['store_name'];
		$data['store_url'] = $order_info['store_id'] == 0
			? ($this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG)
			: $order_info['store_url'];
		$data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));
		$data['firstname'] = $order_info['firstname'];
		$data['lastname'] = $order_info['lastname'];
		$data['email'] = $order_info['email'];
		$data['telephone'] = $order_info['telephone'];
		$data['payment_address'] = $this->formatAddress($order_info, 'payment');
		$data['shipping_address'] = $this->formatAddress($order_info, 'shipping');
		$data['payment_method'] = $order_info['payment_method'];
		$data['shipping_method'] = $order_info['shipping_method'];
		$data['tracking_number'] = $order_info['tracking_number'];
		$data['comment'] = $order_info['comment'];
		$data['currency_code'] = $order_info['currency_code'];
		$data['currency_value'] = $order_info['currency_value'];

		$products = $this->model_sale_order->getOrderProducts($order_id);
		$data['products'] = [];

		foreach ($products as $product) {
			$options = $this->model_sale_order->getOrderOptions($order_id, $product['order_product_id']);
			$option_data = [];

			foreach ($options as $option) {
				$option_data[] = $option['name'] . ': ' . $option['value'];
			}

			$data['products'][] = [
				'name'    => $product['name'],
				'model'   => $product['model'],
				'option'  => implode(', ', $option_data),
				'quantity' => $product['quantity'],
				'price'   => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
				'total'   => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
			];
		}

		$data['totals'] = [];
		$totals = $this->model_sale_order->getOrderTotals($order_id);

		foreach ($totals as $total) {
			$data['totals'][] = [
				'title' => $total['title'],
				'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
			];
		}

		$this->response->setOutput($this->load->view('sale/order_detail_print', $data));
	}

	public function getTimeline(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$page = (int)($this->request->get['page'] ?? 1);
			$limit = 20;
			$start = ($page - 1) * $limit;

			$this->load->model('sale/order');

			$entries = $this->model_sale_order->getOrderTimeline($order_id, $start, $limit);
			$total = $this->model_sale_order->countOrderTimeline($order_id);

			$data['entries'] = [];
			foreach ($entries as $entry) {
				if ((int)$entry['order_status_id'] === 0) {
					$status_name = '<span class="badge badge-note">' . $this->language->get('text_note') . '</span>';
				} else {
					$status_name = $entry['status_name'] ?? '';
				}

				$data['entries'][] = [
					'order_history_id' => $entry['order_history_id'],
					'status_name'      => $status_name,
					'order_status_id'  => $entry['order_status_id'],
					'comment'          => nl2br(htmlspecialchars($entry['comment'], ENT_QUOTES, 'UTF-8')),
					'notify'           => $entry['notify'],
					'date_added'       => date($this->language->get('datetime_format'), strtotime($entry['date_added'])),
				];
			}

			$this->load->model('localisation/order_status');
			$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

			$data['page'] = $page;
			$data['limit'] = $limit;
			$data['total'] = $total;
			$data['pages'] = ceil($total / $limit);

			$json['success'] = true;
			$json['html'] = $this->load->view('sale/order_timeline', $data);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addNote(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$comment = $this->request->post['comment'] ?? '';
			$notify = !empty($this->request->post['notify']);

			if (!trim($comment)) {
				$json['error'] = $this->language->get('error_comment');
			} else {
				$this->load->model('sale/order');
				$this->model_sale_order->addOrderNote($order_id, $comment, $notify);
				$json['success'] = $this->language->get('text_success');
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

				$this->model_sale_order->addOrderHistory($order_id, $order_status_id, $comment, $notify, $override);

				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateField(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$field = $this->request->post['field'] ?? '';
			$value = $this->request->post['value'] ?? '';

			$this->load->model('sale/order');
			$result = $this->model_sale_order->updateOrderField($order_id, $field, $value);

			if ($result) {
				$json['success'] = $this->language->get('text_success');

				$order_info = $this->model_sale_order->getOrder($order_id);

				if ($field === 'payment_method' || $field === 'shipping_method' ||
					$field === 'payment_firstname' || $field === 'payment_lastname' ||
					$field === 'shipping_firstname' || $field === 'shipping_lastname') {
					$json['value_html'] = $order_info[$field];
				} elseif ($field === 'payment_address' || $field === 'shipping_address') {
					$json['value_html'] = $this->formatAddress($order_info, str_replace('_address', '', $field));
				} else {
					$json['value_html'] = $value;
				}
			} else {
				$json['error'] = $this->language->get('error_action');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateProduct(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$order_product_id = (int)($this->request->post['order_product_id'] ?? 0);
			$field = $this->request->post['field'] ?? '';
			$value = $this->request->post['value'] ?? '';

			$this->load->model('sale/order');

			if ($field === 'quantity') {
				$this->model_sale_order->updateOrderProductQuantity($order_product_id, $order_id, $value);
			} elseif ($field === 'price') {
				$this->model_sale_order->updateOrderProductPrice($order_product_id, $order_id, $value);
			} else {
				$json['error'] = $this->language->get('error_action');
			}

			if (!isset($json['error'])) {
				$this->model_sale_order->recalculateOrderTotals($order_id);
				$json['success'] = $this->language->get('text_order_saved');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addProduct(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$product_id = (int)($this->request->post['product_id'] ?? 0);
			$quantity = (int)($this->request->post['quantity'] ?? 1);
			$options = $this->request->post['options'] ?? [];

			if (!$product_id) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$this->load->model('sale/order');
				$order_product_id = $this->model_sale_order->addProductToOrder($order_id, $product_id, $quantity, $options);

				if ($order_product_id) {
					$this->model_sale_order->recalculateOrderTotals($order_id);
					$json['success'] = $this->language->get('text_success');
					$this->load->model('tool/image');

					$this->load->model('catalog/product');
					$product_info = $this->model_catalog_product->getProduct($product_id);

					if ($product_info && !empty($product_info['image']) && is_file(DIR_IMAGE . $product_info['image'])) {
						$thumb = $this->model_tool_image->resize($product_info['image'], 40, 40);
					} else {
						$thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
					}

					$order_info = $this->model_sale_order->getOrder($order_id);

					$product = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "'")->row;

					$options = $this->model_sale_order->getOrderOptions($order_id, $order_product_id);
					$option_data = [];
					foreach ($options as $option) {
						$option_data[] = [
							'name'  => $option['name'],
							'value' => $option['value'],
							'type'  => $option['type'],
						];
					}

					$json['product'] = [
						'order_product_id' => $order_product_id,
						'product_id'       => $product['product_id'],
						'name'             => $product['name'],
						'model'            => $product['model'],
						'option'           => $option_data,
						'quantity'         => $product['quantity'],
						'price'            => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
						'price_raw'        => $product['price'],
						'thumb'            => $thumb,
						'href'             => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product['product_id'], true),
					];
				} else {
					$json['error'] = $this->language->get('error_action');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removeProduct(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$order_product_id = (int)($this->request->post['order_product_id'] ?? 0);

			$this->load->model('sale/order');
			$this->model_sale_order->removeProductFromOrder($order_product_id, $order_id);
			$this->model_sale_order->recalculateOrderTotals($order_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function recalculate(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('sale/order');
			$this->model_sale_order->recalculateOrderTotals($order_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function applyLineDiscounts(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$discounts = $this->request->post['discount'] ?? [];

			$this->load->model('sale/order');
			$this->model_sale_order->applyLineDiscounts($order_id, $discounts);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addReward(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('sale/order');
			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info && $order_info['customer_id'] && ($order_info['reward'] > 0)) {
				$this->load->model('customer/customer');
				$reward_total = $this->model_customer_customer->getTotalCustomerRewardsByOrderId($order_id);

				if (!$reward_total) {
					$this->model_customer_customer->addReward($order_info['customer_id'], $this->language->get('text_order_id') . ' #' . $order_id, $order_info['reward'], $order_id);
				}
			}

			$json['success'] = $this->language->get('text_reward_added');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removeReward(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('customer/customer');
			$this->model_customer_customer->deleteReward($order_id);

			$json['success'] = $this->language->get('text_reward_removed');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addCommission(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('sale/order');
			$order_info = $this->model_sale_order->getOrder($order_id);

			if ($order_info && $order_info['affiliate_id']) {
				$this->load->model('customer/customer');
				$commission_total = $this->model_customer_customer->getTotalTransactionsByOrderId($order_id);

				if (!$commission_total) {
					$this->model_customer_customer->addTransaction($order_info['affiliate_id'], $this->language->get('text_order_id') . ' #' . $order_id, $order_info['commission'], $order_id);
				}
			}

			$json['success'] = $this->language->get('text_commission_added');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removeCommission(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('customer/customer');
			$this->model_customer_customer->deleteTransaction($order_id);

			$json['success'] = $this->language->get('text_commission_removed');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function formatAddress(array $order_info, string $type): string {
		$prefix = $type === 'payment' ? 'payment' : 'shipping';

		if ($order_info[$prefix . '_address_format']) {
			$format = $order_info[$prefix . '_address_format'];
		} else {
			$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
		}

		$find = ['{firstname}', '{lastname}', '{company}', '{address_1}', '{address_2}', '{city}', '{postcode}', '{zone}', '{zone_code}', '{country}'];
		$replace = [
			'firstname' => $order_info[$prefix . '_firstname'],
			'lastname'  => $order_info[$prefix . '_lastname'],
			'company'   => $order_info[$prefix . '_company'],
			'address_1' => $order_info[$prefix . '_address_1'],
			'address_2' => $order_info[$prefix . '_address_2'],
			'city'      => $order_info[$prefix . '_city'],
			'postcode'  => $order_info[$prefix . '_postcode'],
			'zone'      => $order_info[$prefix . '_zone'],
			'zone_code' => $order_info[$prefix . '_zone_code'] ?? '',
			'country'   => $order_info[$prefix . '_country'],
		];

		return str_replace(["\r\n", "\r", "\n"], '<br />', preg_replace(["/\s\s+/", "/\r\r+/", "/\n\n+/"], '<br />', trim(str_replace($find, $replace, $format))));
	}

	private function getStatusBadgeClass(int $status_id, array $processing_statuses, array $complete_statuses, int $fraud_status): string {
		if ($fraud_status && $fraud_status === $status_id) {
			return 'danger';
		}

		if (in_array($status_id, $processing_statuses)) {
			return 'warning';
		}

		if (in_array($status_id, $complete_statuses)) {
			return 'success';
		}

		return 'default';
	}

	private function getCustomerType(array $order_info): string {
		return $order_info['customer_id'] ? $this->language->get('text_badge_registered') : $this->language->get('text_badge_guest');
	}

	private function getCustomerTypeBadgeClass(array $order_info): string {
		return $order_info['customer_id'] ? 'registered' : 'guest';
	}

	private function buildFilterUrl(): string {
		$url = '';
		$params = ['filter_order_id', 'filter_customer', 'filter_order_status', 'filter_order_status_id', 'filter_total', 'filter_date_added', 'filter_date_modified', 'sort', 'order', 'page'];

		foreach ($params as $param) {
			if (isset($this->request->get[$param])) {
				$url .= '&' . $param . '=' . urlencode(html_entity_decode((string)$this->request->get[$param], ENT_QUOTES, 'UTF-8'));
			}
		}

		return $url;
	}
}
