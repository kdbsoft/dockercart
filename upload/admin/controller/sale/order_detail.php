<?php
declare(strict_types=1);

class ControllerSaleOrderDetail extends Controller {
	private array $error = [];
	private ?OrderLocalizer $order_localizer = null;

	private function orderLocalizer(): OrderLocalizer {
		if ($this->order_localizer === null) {
			$this->order_localizer = new OrderLocalizer($this->registry);
		}

		return $this->order_localizer;
	}

	public function index(): void {
		$this->load->language('sale/order');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

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

		$data['cancel'] = $this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['print_url'] = $this->url->link('sale/order_detail/print', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $order_id, true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['order_id'] = $order_id;
		$data['edit_mode'] = !empty($this->request->get['edit']);

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
		$data['tracking_numbers'] = array_values(array_filter(array_map('trim', explode('|', $order_info['tracking_number'])), function (string $number): bool {
			return $number !== '';
		}));
		$data['comment'] = $order_info['comment'];
		$data['payment_code'] = $order_info['payment_code'];
		$data['shipping_code'] = $order_info['shipping_code'];

		$data['payment_method'] = $this->orderLocalizer()->paymentMethodTitle($order_info);
		$data['shipping_method'] = $this->orderLocalizer()->shippingMethodTitle($order_info);

		$data['payment_methods'] = $this->getAvailablePaymentMethods();
		$data['shipping_methods'] = $this->getAvailableShippingMethods();

		$data['payment_firstname'] = $order_info['payment_firstname'];
		$data['payment_lastname'] = $order_info['payment_lastname'];
		$data['payment_company'] = $order_info['payment_company'];
		$data['payment_address_1'] = $order_info['payment_address_1'];
		$data['payment_address_2'] = $order_info['payment_address_2'];
		$data['payment_city'] = $order_info['payment_city'];
		$data['payment_postcode'] = $order_info['payment_postcode'];
		$data['payment_country_id'] = $order_info['payment_country_id'] ? (int)$order_info['payment_country_id'] : (int)$this->config->get('config_country_id');
		$data['payment_zone_id'] = $order_info['payment_zone_id'] ? (int)$order_info['payment_zone_id'] : (int)$this->config->get('config_zone_id');
		$data['payment_country'] = $order_info['payment_country'];
		$data['payment_zone'] = $order_info['payment_zone'];

		$data['shipping_firstname'] = $order_info['shipping_firstname'];
		$data['shipping_lastname'] = $order_info['shipping_lastname'];
		$data['shipping_company'] = $order_info['shipping_company'];
		$data['shipping_address_1'] = $order_info['shipping_address_1'];
		$data['shipping_address_2'] = $order_info['shipping_address_2'];
		$data['shipping_city'] = $order_info['shipping_city'];
		$data['shipping_postcode'] = $order_info['shipping_postcode'];
		$data['shipping_country_id'] = $order_info['shipping_country_id'] ? (int)$order_info['shipping_country_id'] : (int)$this->config->get('config_country_id');
		$data['shipping_zone_id'] = $order_info['shipping_zone_id'] ? (int)$order_info['shipping_zone_id'] : (int)$this->config->get('config_zone_id');
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
		$data['currency_decimal_place'] = $this->currency->getDecimalPlace($order_info['currency_code']);

		$this->load->model('tool/image');
		$this->load->model('catalog/product');

		$order_product_discounts = $this->model_sale_order->getOrderProductDiscounts($order_id);
		$products = $this->model_sale_order->getOrderProducts($order_id);
		$data['products'] = [];
		$data['product_count'] = count($products);
		$data['total_quantity'] = array_sum(array_column($products, 'quantity'));

		$order_localizer = $this->orderLocalizer();

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
					'name'  => $order_localizer->optionName($option),
					'value' => $order_localizer->optionValue($option),
					'type'  => $option['type'],
				];
			}

			$quantity = max(1, (int)$product['quantity']);
			$unit_tax = $product['tax'] / $quantity;

			$data['products'][] = [
				'order_product_id' => $product['order_product_id'],
				'product_id'       => $product['product_id'],
				'name'             => $order_localizer->productName($product),
				'model'            => $product['model'],
				'variant_sku'      => $product['variant_sku'] ?? '',
				'option'           => $option_data,
				'quantity'         => $product['quantity'],
				'price'            => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $unit_tax : 0), $order_info['currency_code'], $order_info['currency_value']),
				'price_raw'        => $product['price'],
				'tax_raw'          => $product['tax'],
				'total'            => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
				'total_raw'        => $product['total'],
				'discount_percent' => $order_product_discounts[(int)$product['order_product_id']] ?? 0,
				'thumb'            => $thumb,
				'href'             => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product['product_id'], true),
			];
		}

		$data['totals'] = [];
		$totals = $this->model_sale_order->getOrderTotals($order_id);
		$shipping_method_title = $this->orderLocalizer()->shippingMethodTitle($order_info);

		foreach ($totals as $total) {
			$data['totals'][] = [
				'code'  => $total['code'],
				'title' => $this->orderLocalizer()->totalTitle($total, $shipping_method_title),
				'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				'value' => $total['value'],
			];
		}

		$data['reward'] = $order_info['reward'];
		$this->load->model('customer/customer');
		$data['reward_total'] = $this->model_customer_customer->getTotalCustomerRewardsByOrderId($order_id);

		$data = array_merge($data, $this->getPaymentsPartialData($order_id));

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

		$this->sendPdf(
			$this->load->view('sale/order_detail_print', [
				'orders' => [$this->buildPrintData($order_id)],
			]),
			'order-' . $order_id . '.pdf'
		);
	}

	public function printSelected(): void {
		$this->load->language('sale/order');

		$ids = [];

		if (isset($this->request->get['order_id'])) {
			if (is_array($this->request->get['order_id'])) {
				$ids = $this->request->get['order_id'];
			} else {
				$ids = explode(',', (string)$this->request->get['order_id']);
			}

			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		}

		$this->load->model('sale/order');

		$orders = [];

		foreach ($ids as $order_id) {
			if ($this->model_sale_order->getOrder($order_id)) {
				$orders[] = $this->buildPrintData($order_id);
			}
		}

		if (!$orders) {
			$this->response->redirect($this->url->link('sale/order', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$filename = count($orders) === 1
			? 'order-' . $orders[0]['order_id'] . '.pdf'
			: 'orders-' . implode('-', array_column($orders, 'order_id')) . '.pdf';

		$this->sendPdf(
			$this->load->view('sale/order_detail_print', ['orders' => $orders]),
			$filename
		);
	}

	private function buildPrintData(int $order_id): array {
		$order_info = $this->model_sale_order->getOrder($order_id);

		$data['order_id'] = $order_id;
		$data['heading_title'] = sprintf($this->language->get('text_order_number'), $order_id);
		$data['store_name'] = $order_info['store_name'];
		$data['store_url'] = $order_info['store_id'] == 0
			? ($this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG)
			: $order_info['store_url'];
		$data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));
		$data['firstname'] = $order_info['firstname'];
		$data['lastname'] = $order_info['lastname'];
		$data['email'] = $order_info['email'];
		$data['telephone'] = $order_info['telephone'];
		$data['order_status'] = $order_info['order_status'] ? $order_info['order_status'] : $this->language->get('text_missing');
		$data['payment_address'] = $this->formatAddress($order_info, 'payment');
		$data['shipping_address'] = $this->formatAddress($order_info, 'shipping');
		$data['payment_method'] = $this->orderLocalizer()->paymentMethodTitle($order_info);
		$data['shipping_method'] = $this->orderLocalizer()->shippingMethodTitle($order_info);
		$data['tracking_number'] = $order_info['tracking_number'];
		$data['comment'] = $order_info['comment'];
		$data['currency_code'] = $order_info['currency_code'];
		$data['currency_value'] = $order_info['currency_value'];

		$order_localizer = $this->orderLocalizer();
		$products = $this->model_sale_order->getOrderProducts($order_id);
		$data['products'] = [];

		foreach ($products as $product) {
			$options = $this->model_sale_order->getOrderOptions($order_id, $product['order_product_id']);
			$option_data = [];

			foreach ($options as $option) {
				$option_data[] = $order_localizer->optionName($option) . ': ' . $order_localizer->optionValue($option);
			}

			$quantity = max(1, (int)$product['quantity']);
			$unit_tax = $product['tax'] / $quantity;

			$data['products'][] = [
				'name'     => $order_localizer->productName($product),
				'model'    => $product['model'],
				'option'   => implode(', ', $option_data),
				'quantity' => $product['quantity'],
				'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $unit_tax : 0), $order_info['currency_code'], $order_info['currency_value']),
				'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
			];
		}

		$data['totals'] = [];
		$totals = $this->model_sale_order->getOrderTotals($order_id);
		$shipping_method_title = $this->orderLocalizer()->shippingMethodTitle($order_info);

		foreach ($totals as $total) {
			$data['totals'][] = [
				'title' => $this->orderLocalizer()->totalTitle($total, $shipping_method_title),
				'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
			];
		}

		return $data;
	}

	private function sendPdf(string $html, string $filename): void {
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', false);
		$options->set('defaultFont', 'DejaVu Sans');
		$options->set('isHtml5ParserEnabled', true);

		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		$this->response->addHeader('Content-Type: application/pdf');
		$this->response->addHeader('Content-Disposition: inline; filename="' . $filename . '"');
		$this->response->setOutput($dompdf->output());
	}

	public function getTimeline(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$page = (int)($this->request->get['page'] ?? 1);
			$limit = 5;
			$start = ($page - 1) * $limit;

			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$entries = $this->model_sale_order->getOrderTimeline($order_id, $start, $limit);
				$total = $this->model_sale_order->countOrderTimeline($order_id);

				$data['entries'] = [];
				foreach ($entries as $entry) {
					if (($entry['type'] ?? 'history') === 'payment') {
						$amount = (float)$entry['amount'];
						$amount_text = $this->currency->format(abs($amount), $order_info['currency_code'], $order_info['currency_value']);
						$status_name = '<span class="badge ' . ($amount >= 0 ? 'badge-success' : 'badge-danger') . '">' . ($amount >= 0 ? '+' : '−') . ' ' . $amount_text . '</span>';
						$payment_method = $this->orderLocalizer()->paymentEntryTitle($entry);
					} elseif ((int)$entry['order_status_id'] === 0) {
						$status_name = '<span class="badge badge-note">' . $this->language->get('text_note') . '</span>';
						$payment_method = '';
					} else {
						$status_name = $entry['status_name'] ?? '';
						$payment_method = '';
					}

					$comment = $this->renderTimelineComment($entry);

					$data['entries'][] = [
						'order_history_id' => $entry['order_history_id'],
						'type'             => $entry['type'] ?? 'history',
						'status_name'      => $status_name,
						'order_status_id'  => $entry['order_status_id'],
						'comment'          => $comment,
						'payment_method'   => htmlspecialchars($payment_method, ENT_QUOTES, 'UTF-8'),
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
				$this->load->model('localisation/country');
				$this->load->model('localisation/zone');

				if ($field === 'payment_country_id' || $field === 'shipping_country_id') {
					$prefix = str_replace('_country_id', '', $field);
					$country_info = $this->model_localisation_country->getCountry((int)$value);
					$this->model_sale_order->updateOrderField($order_id, $prefix . '_country', $country_info ? $country_info['name'] : '');
				} elseif ($field === 'payment_zone_id' || $field === 'shipping_zone_id') {
					$prefix = str_replace('_zone_id', '', $field);
					$zone_info = $this->model_localisation_zone->getZone((int)$value);
					$this->model_sale_order->updateOrderField($order_id, $prefix . '_zone', $zone_info ? $zone_info['name'] : '');
				}

				if ($field === 'shipping_method' || $field === 'shipping_code') {
					$recalc = $this->model_sale_order->recalculateShipping($order_id);

					if ($recalc !== null) {
						$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);
					}
				}

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

				if ($field === 'shipping_method' || $field === 'shipping_code') {
					$json['totals'] = $this->buildTotalsJson($order_id, $order_info);
				}
			} else {
				$json['error'] = $this->language->get('error_action');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function quoteShipping(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$shipping_code = trim((string)($this->request->post['shipping_code'] ?? ''));
			$country_id = (int)($this->request->post['shipping_country_id'] ?? 0);
			$zone_id = (int)($this->request->post['shipping_zone_id'] ?? 0);
			$subtotal = isset($this->request->post['subtotal']) ? (float)$this->request->post['subtotal'] : null;

			$this->load->model('sale/order');

			$quote = $this->model_sale_order->previewShippingQuote($order_id, $shipping_code, $country_id, $zone_id, $subtotal);

			if ($quote === null) {
				$json['available'] = false;
			} else {
				$json['available'] = true;
				$json['cost'] = $quote['cost'];
				$json['title'] = $quote['title'];
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
				$recalc = $this->model_sale_order->recalculateOrderTotals($order_id);

				$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);

				$order_info = $this->model_sale_order->getOrder($order_id);

				$product = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "'")->row;

				if (!$product) {
					$json['error'] = $this->language->get('error_action');
				}

				if ($product && !isset($json['error'])) {
					$quantity = max(1, (int)$product['quantity']);
					$unit_tax = $product['tax'] / $quantity;

					$json['success'] = $this->language->get('text_order_saved');
					$json['product_total'] = $this->currency->format(
						$product['total'] + ($this->config->get('config_tax') ? $product['tax'] : 0),
						$order_info['currency_code'],
						$order_info['currency_value']
					);
					$json['product_price'] = $this->currency->format(
						$product['price'] + ($this->config->get('config_tax') ? $unit_tax : 0),
						$order_info['currency_code'],
						$order_info['currency_value']
					);
					$json['product_quantity'] = (int)$product['quantity'];
					$json['totals'] = $this->buildTotalsJson($order_id, $order_info);
					$json['total_quantity'] = $this->getOrderTotalQuantity($order_id);
				}
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

			$optionsRaw = html_entity_decode((string)($this->request->post['options'] ?? '[]'), ENT_QUOTES, 'UTF-8');
			$options = json_decode($optionsRaw, true);

			if (!is_array($options)) {
				$options = [];
			}

			if (!$product_id) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$this->load->model('sale/order');

				try {
					$order_product_id = $this->model_sale_order->addProductToOrder($order_id, $product_id, $quantity, $options);
				} catch (\RuntimeException $e) {
					$order_product_id = false;
					$json['error'] = $this->language->get($e->getMessage());
				}

				if ($order_product_id) {
					$recalc = $this->model_sale_order->recalculateOrderTotals($order_id);

					$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);

					$this->session->data['success'] = $this->language->get('text_success');
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

					$quantity = max(1, (int)$product['quantity']);
					$unit_tax = $product['tax'] / $quantity;

					$json['product'] = [
						'order_product_id' => $order_product_id,
						'product_id'       => $product['product_id'],
						'name'             => $product['name'],
						'model'            => $product['model'],
						'variant_id'       => $product['variant_id'],
						'variant_sku'      => $product['variant_sku'],
						'option'           => $option_data,
						'quantity'         => $product['quantity'],
						'price'            => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $unit_tax : 0), $order_info['currency_code'], $order_info['currency_value']),
						'price_raw'        => $product['price'],
						'tax_raw'          => $product['tax'],
						'total'            => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
						'total_raw'        => $product['total'],
						'discount_percent' => 0,
						'thumb'            => $thumb,
						'href'             => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product['product_id'], true),
					];

					$json['totals'] = $this->buildTotalsJson($order_id, $order_info);
					$json['total_quantity'] = $this->getOrderTotalQuantity($order_id);
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

			$recalc = $this->model_sale_order->recalculateOrderTotals($order_id);

			$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);

			$order_info = $this->model_sale_order->getOrder($order_id);

			$json['success'] = $this->language->get('text_success');
			$json['totals'] = $this->buildTotalsJson($order_id, $order_info);
			$json['total_quantity'] = $this->getOrderTotalQuantity($order_id);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function previewProduct(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$product_id = (int)($this->request->post['product_id'] ?? 0);
			$quantity = (int)($this->request->post['quantity'] ?? 1);

			$optionsRaw = html_entity_decode((string)($this->request->post['options'] ?? '[]'), ENT_QUOTES, 'UTF-8');
			$options = json_decode($optionsRaw, true);

			if (!is_array($options)) {
				$options = [];
			}

			if (!$product_id) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$this->load->model('sale/order');

				$order_info = $this->model_sale_order->getOrder($order_id);

				if (!$order_info) {
					$json['error'] = $this->language->get('error_action');
				} else {
					try {
						$pricing = $this->model_sale_order->calculateProductPricing($order_id, $product_id, $quantity, $options);
					} catch (\RuntimeException $e) {
						$pricing = false;
						$json['variant_error'] = $this->language->get($e->getMessage());
					}

					if ($pricing) {
						$include_tax = (bool)$this->config->get('config_tax');
						$unit_price = $pricing['price'] + ($include_tax ? $pricing['tax'] : 0);
						$line_total = $pricing['total'] + ($include_tax ? $pricing['tax_total'] : 0);

						$json['price'] = $this->currency->format($unit_price, $order_info['currency_code'], $order_info['currency_value']);
						$json['price_raw'] = $pricing['price'];
						$json['tax'] = $this->currency->format($pricing['tax'], $order_info['currency_code'], $order_info['currency_value']);
						$json['tax_raw'] = $pricing['tax'];
						$json['total'] = $this->currency->format($line_total, $order_info['currency_code'], $order_info['currency_value']);
						$json['total_raw'] = $line_total;
						$json['quantity'] = $pricing['quantity'];
						$json['stock'] = (float)$pricing['stock'];
						$json['subtract'] = (bool)$pricing['subtract'];
						$json['available'] = !$pricing['subtract'] || $pricing['stock'] > 0;
						$json['variant'] = $pricing['variant_id'] ? [
							'variant_id' => (int)$pricing['variant_id'],
							'sku'        => $pricing['variant_sku'],
							'model'      => $pricing['model'],
						] : null;
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function buildTotalsJson($order_id, $order_info): array {
		$totals = $this->model_sale_order->getOrderTotals($order_id);
		$json = [];

		foreach ($totals as $total) {
			$json[] = [
				'code'  => $total['code'],
				'title' => $total['title'],
				'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				'value' => (float)$total['value'],
			];
		}

		return $json;
	}

	private function getOrderTotalQuantity($order_id): int {
		return (int)array_sum(array_column(
			$this->model_sale_order->getOrderProducts($order_id),
			'quantity'
		));
	}

	public function recalculate(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('sale/order');

			$recalc = $this->model_sale_order->recalculateOrderTotals($order_id);

			$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);

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

			if (!is_array($discounts)) {
				$decoded = json_decode((string)$discounts, true);
				$discounts = is_array($decoded) ? $decoded : [];
			}

			$this->load->model('sale/order');

			$recalc = $this->model_sale_order->applyLineDiscounts($order_id, $discounts);

			$this->journalTotalChange($order_id, (float)$recalc['old_total'], (float)$recalc['new_total']);

			$order_info = $this->model_sale_order->getOrder($order_id);

			$json['success'] = $this->language->get('text_success');
			$json['totals'] = $this->buildTotalsJson($order_id, $order_info);
			$json['total_quantity'] = $this->getOrderTotalQuantity($order_id);
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

	public function addPayment(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$amount = (float)($this->request->post['amount'] ?? 0);
			$reference = trim((string)($this->request->post['reference'] ?? ''));
			$comment = trim((string)($this->request->post['comment'] ?? ''));
			$payment_code = trim((string)($this->request->post['payment_code'] ?? ''));
			$payment_method = trim((string)($this->request->post['payment_method'] ?? ''));

			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info) {
				$json['error'] = $this->language->get('error_action');
			} elseif ($amount <= 0) {
				$json['error'] = $this->language->get('error_payment_amount');
			} else {
				$order_payment_id = $this->model_sale_order->addOrderPayment($order_id, $amount, $reference, $comment, $payment_method, $payment_code);

				if (!$order_payment_id) {
					$json['error'] = $this->language->get('error_action');
				} else {
					$paid_amount = (float)$order_info['paid_amount'] + $amount;
					$total = (float)$order_info['total'];
					$status = $this->model_sale_order->getPaymentStatus($total, $paid_amount);
					$currency_code = $order_info['currency_code'];
					$currency_value = $order_info['currency_value'];

					$note_params = [
						$this->currency->format($amount, $currency_code, $currency_value),
						$this->currency->format($paid_amount, $currency_code, $currency_value),
						$this->currency->format($total, $currency_code, $currency_value),
					];

					$this->model_sale_order->addOrderNote(
						$order_id,
						sprintf($this->language->get('text_payment_note_received'), ...$note_params),
						false,
						'text_payment_note_received',
						$note_params
					);

					if ($status === 'overpaid') {
						$overpaid_params = [
							$this->currency->format($paid_amount - $total, $currency_code, $currency_value),
						];

						$this->model_sale_order->addOrderNote(
							$order_id,
							sprintf($this->language->get('text_payment_note_overpaid'), ...$overpaid_params),
							false,
							'text_payment_note_overpaid',
							$overpaid_params
						);
					}

					$json['success'] = $this->language->get('text_payment_added');
					$json['payments_html'] = $this->load->view('sale/order_payments', $this->getPaymentsPartialData($order_id));
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removePayment(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_payment_id = (int)($this->request->post['order_payment_id'] ?? 0);

			$this->load->model('sale/order');

			$payment = $this->model_sale_order->getOrderPayment($order_payment_id);

			if (!$payment) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$order_id = (int)$payment['order_id'];
				$order_info = $this->model_sale_order->getOrder($order_id);

				$reversal_id = $this->model_sale_order->removeOrderPayment($order_payment_id, $this->language->get('text_payment_reversal_comment') . ' #' . $order_payment_id);

				if (!$reversal_id) {
					$json['error'] = $this->language->get('error_action');
				} else {
					$reversed_params = [
						$this->currency->format((float)$payment['amount'], $order_info['currency_code'], $order_info['currency_value']),
					];

					$this->model_sale_order->addOrderNote(
						$order_id,
						sprintf($this->language->get('text_payment_note_reversed'), ...$reversed_params),
						false,
						'text_payment_note_reversed',
						$reversed_params
					);

					$json['success'] = $this->language->get('text_payment_removed');
					$json['payments_html'] = $this->load->view('sale/order_payments', $this->getPaymentsPartialData($order_id));
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function removeOverpayment(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$order_id = (int)($this->request->get['order_id'] ?? 0);

			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info) {
				$json['error'] = $this->language->get('error_action');
			} else {
				$overpaid = (float)$order_info['paid_amount'] - (float)$order_info['total'];

				if ($overpaid <= 0) {
					$json['error'] = $this->language->get('error_no_overpayment');
				} else {
					$reversal_id = $this->model_sale_order->removeOrderOverpayment($order_id, $this->language->get('text_overpayment_reversal_comment'));

					if (!$reversal_id) {
						$json['error'] = $this->language->get('error_action');
					} else {
						$overpaid_removed_params = [
							$this->currency->format($overpaid, $order_info['currency_code'], $order_info['currency_value']),
						];

						$this->model_sale_order->addOrderNote(
							$order_id,
							sprintf($this->language->get('text_payment_note_overpaid_removed'), ...$overpaid_removed_params),
							false,
							'text_payment_note_overpaid_removed',
							$overpaid_removed_params
						);

						$json['success'] = $this->language->get('text_overpayment_removed');
						$json['payments_html'] = $this->load->view('sale/order_payments', $this->getPaymentsPartialData($order_id));
					}
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getPaymentsPartialData(int $order_id): array {
		$order_info = $this->model_sale_order->getOrder($order_id);

		$status = $this->model_sale_order->getPaymentStatus($order_info['total'], $order_info['paid_amount']);
		$total = (float)$order_info['total'];
		$paid_amount = (float)$order_info['paid_amount'];
		$currency_code = $order_info['currency_code'];
		$currency_value = $order_info['currency_value'];

		return [
			'payment_status'             => $status,
			'payment_status_text'        => $this->language->get('text_payment_status_' . $status),
			'payment_status_badge_class' => $this->getPaymentStatusBadgeClass($status),
			'payment_status_header_badge_class' => $this->getPaymentStatusHeaderBadgeClass($status),
			'paid_amount'                => $this->currency->format($paid_amount, $currency_code, $currency_value),
			'paid_amount_raw'            => $paid_amount,
			'total'                      => $this->currency->format($total, $currency_code, $currency_value),
			'total_raw'                  => $total,
			'payment_remaining'          => $this->currency->format(max(0, $total - $paid_amount), $currency_code, $currency_value),
			'payment_overpaid'           => $this->currency->format(max(0, $paid_amount - $total), $currency_code, $currency_value),
			'payment_progress'           => $this->getPaymentProgress($total, $paid_amount),
			'payments'                   => $this->getPaymentsViewData($order_id, $order_info),
			'payment_methods'            => $this->getAvailablePaymentMethods(),
			'payment_code'               => $order_info['payment_code'],
		];
	}

	private function getPaymentsViewData(int $order_id, array $order_info): array {
		$payments = $this->model_sale_order->getOrderPayments($order_id);
		$data = [];

		foreach ($payments as $payment) {
			$amount = (float)$payment['amount'];

			$data[] = [
				'order_payment_id' => (int)$payment['order_payment_id'],
				'amount'           => $this->currency->format(abs($amount), $order_info['currency_code'], $order_info['currency_value']),
				'amount_raw'       => $amount,
				'is_reversal'      => $amount < 0,
				'payment_method'   => $this->orderLocalizer()->paymentEntryTitle($payment),
				'reference'        => $payment['reference'],
				'comment'          => $payment['comment'],
				'date_added'       => date($this->language->get('datetime_format'), strtotime($payment['date_added'])),
			];
		}

		return $data;
	}

	private function getPaymentStatusBadgeClass(string $status): string {
		switch ($status) {
			case 'paid':
				return 'badge badge-success';
			case 'partial':
				return 'badge badge-warning';
			case 'overpaid':
				return 'badge badge-danger';
			default:
				return 'badge badge-default';
		}
	}

	private function getPaymentStatusHeaderBadgeClass(string $status): string {
		switch ($status) {
			case 'paid':
				return 'page-header__badge--success';
			case 'partial':
				return 'page-header__badge--warning page-header__badge--unfilled';
			case 'overpaid':
				return 'page-header__badge--danger';
			default:
				return 'page-header__badge--default page-header__badge--unfilled';
		}
	}

	private function getPaymentProgress(float $total, float $paid): int {
		if ($total <= 0) {
			return 100;
		}

		return (int)min(100, round($paid / $total * 100));
	}

	private function journalTotalChange(int $order_id, float $old_total, float $new_total): void {
		$order_info = $this->model_sale_order->getOrder($order_id);

		if (!$order_info || abs($old_total - $new_total) < 0.0001) {
			return;
		}

		$currency_code = $order_info['currency_code'];
		$currency_value = $order_info['currency_value'];

		$total_changed_params = [
			$this->currency->format($old_total, $currency_code, $currency_value),
			$this->currency->format($new_total, $currency_code, $currency_value),
		];

		$this->model_sale_order->addOrderNote(
			$order_id,
			sprintf($this->language->get('text_payment_note_total_changed'), ...$total_changed_params),
			false,
			'text_payment_note_total_changed',
			$total_changed_params
		);

		if ((float)$order_info['paid_amount'] > $new_total) {
			$overpaid_params = [
				$this->currency->format((float)$order_info['paid_amount'] - $new_total, $currency_code, $currency_value),
			];

			$this->model_sale_order->addOrderNote(
				$order_id,
				sprintf($this->language->get('text_payment_note_total_changed_overpaid'), ...$overpaid_params),
				false,
				'text_payment_note_total_changed_overpaid',
				$overpaid_params
			);
		}
	}

	private function renderTimelineComment(array $entry): string {
		$comment = (string)($entry['comment'] ?? '');

		$resolved = $this->orderLocalizer()->historyComment($entry);

		if ($resolved !== null) {
			$comment = $resolved;
		} else {
			$comment_key = (string)($entry['comment_key'] ?? '');

			if ($comment_key !== '') {
				$translated = $this->language->get($comment_key);

				if ($translated !== $comment_key) {
					$params = json_decode((string)($entry['comment_params'] ?? ''), true);

					if (is_array($params) && count($params) > 0) {
						$translated = sprintf($translated, ...array_values($params));
					}

					$comment = $translated;
				}
			}
		}

		return nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'));
	}

	private function formatAddress(array $order_info, string $type): string {
		$prefix = $type === 'payment' ? 'payment' : 'shipping';

		if ($order_info[$prefix . '_address_format']) {
			$format = $order_info[$prefix . '_address_format'];
		} else {
			$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
		}

		$order_localizer = $this->orderLocalizer();

		$find = ['{firstname}', '{lastname}', '{company}', '{address_1}', '{address_2}', '{city}', '{postcode}', '{zone}', '{zone_code}', '{country}'];
		$replace = [
			'firstname' => $order_info[$prefix . '_firstname'],
			'lastname'  => $order_info[$prefix . '_lastname'],
			'company'   => $order_info[$prefix . '_company'],
			'address_1' => $order_info[$prefix . '_address_1'],
			'address_2' => $order_info[$prefix . '_address_2'],
			'city'      => $order_info[$prefix . '_city'],
			'postcode'  => $order_info[$prefix . '_postcode'],
			'zone'      => $order_localizer->zoneName($order_info, $prefix),
			'zone_code' => $order_info[$prefix . '_zone_code'] ?? '',
			'country'   => $order_localizer->countryName($order_info, $prefix),
		];

		return str_replace(["\r\n", "\r", "\n"], '<br />', preg_replace(["/\s\s+/", "/\r\r+/", "/\n\n+/"], '<br />', trim(str_replace($find, $replace, $format))));
	}

	private function getStatusBadgeClass(int $status_id, array $processing_statuses, array $complete_statuses, int $fraud_status): string {
		if ($fraud_status && $fraud_status === $status_id) {
			return 'danger';
		}

		if (in_array($status_id, $processing_statuses)) {
			return 'warning page-header__badge--unfilled';
		}

		if (in_array($status_id, $complete_statuses)) {
			return 'success';
		}

		return 'default page-header__badge--unfilled';
	}

	private function getCustomerType(array $order_info): string {
		return $order_info['customer_id'] ? $this->language->get('text_badge_registered') : $this->language->get('text_badge_guest');
	}

	private function getCustomerTypeBadgeClass(array $order_info): string {
		return $order_info['customer_id'] ? 'registered' : 'guest';
	}

	private function getAvailablePaymentMethods(): array {
		$methods = [];
		$this->load->model('setting/extension');
		$extensions = $this->model_setting_extension->getInstalled('payment');

		foreach ($extensions as $code) {
			$status = $this->config->get('payment_' . $code . '_status');
			if ($status) {
				$this->load->language('extension/payment/' . $code);
				$default_title = $this->language->get('heading_title');
				if (empty($default_title) || $default_title === 'heading_title') {
					$default_title = ucfirst(str_replace('_', ' ', $code));
				}

				$sub_methods = $this->getPaymentModuleMethods($code);

				if (!empty($sub_methods)) {
					foreach ($sub_methods as $sub_code => $sub_data) {
						$methods[$sub_code] = [
							'code'          => $sub_code,
							'title'         => $sub_data['title'],
							'module_title'  => $default_title,
							'module_code'   => $code,
						];
					}
				} else {
					$methods[$code] = [
						'code'          => $code,
						'title'         => $default_title,
						'module_title'  => $default_title,
						'module_code'   => $code,
					];
				}
			}
		}

		return $methods;
	}

	private function getPaymentModuleMethods(string $code): array {
		$methods = [];

		if ($code === 'dockercart_universal') {
			$this->load->model('extension/payment/dockercart_universal');
			$db_methods = $this->model_extension_payment_dockercart_universal->getMethods();

			foreach ($db_methods as $method) {
				$method_code = 'dockercart_universal.dockercart_universal_' . $method['method_id'];
				$methods[$method_code] = [
					'title'     => $method['name'] ?? 'Method ' . $method['method_id'],
					'method_id' => $method['method_id'],
				];
			}
		}

		return $methods;
	}

	private function getAvailableShippingMethods(): array {
		$methods = [];
		$this->load->model('setting/extension');
		$extensions = $this->model_setting_extension->getInstalled('shipping');

		foreach ($extensions as $code) {
			$status = $this->config->get('shipping_' . $code . '_status');
			if ($status) {
				$this->load->language('extension/shipping/' . $code);
				$default_title = $this->language->get('heading_title');
				if (empty($default_title) || $default_title === 'heading_title') {
					$default_title = ucfirst(str_replace('_', ' ', $code));
				}

				$sub_methods = $this->getShippingModuleMethods($code);

				if (!empty($sub_methods)) {
					foreach ($sub_methods as $sub_code => $sub_data) {
						$methods[$sub_code] = [
							'code'          => $sub_code,
							'title'         => $sub_data['title'],
							'module_title'  => $default_title,
							'module_code'   => $code,
						];
					}
				} else {
					$methods[$code] = [
						'code'          => $code,
						'title'         => $default_title,
						'module_title'  => $default_title,
						'module_code'   => $code,
					];
				}
			}
		}

		return $methods;
	}

	private function getShippingModuleMethods(string $code): array {
		$methods = [];

		if ($code === 'dockercart_universal') {
			$this->load->model('extension/shipping/dockercart_universal');
			$db_methods = $this->model_extension_shipping_dockercart_universal->getMethods();

			foreach ($db_methods as $method) {
				$method_code = 'dockercart_universal.dockercart_universal_' . $method['method_id'];
				$methods[$method_code] = [
					'title'     => $method['name'] ?? 'Method ' . $method['method_id'],
					'method_id' => $method['method_id'],
				];
			}
		}

		if ($code === 'dockercart_novapost') {
			$this->load->language('extension/shipping/dockercart_novapost');

			$delivery_types = [
				'branch'  => 'delivery_branch',
				'locker'  => 'delivery_locker',
				'courier' => 'delivery_courier',
			];

			foreach ($delivery_types as $key => $lang_key) {
				$method_code = 'dockercart_novapost.' . $key;
				$title = $this->language->get($lang_key);
				if (empty($title) || $title === $lang_key) {
					$title = ucfirst($key);
				}
				$methods[$method_code] = [
					'title' => $title,
				];
			}
		}

		return $methods;
	}

	private function buildFilterUrl(): string {
		$url = '';
		$params = ['filter_order_id', 'filter_customer', 'filter_order_status', 'filter_order_status_id', 'filter_payment_status', 'filter_total', 'filter_date_added', 'sort', 'order', 'page'];

		foreach ($params as $param) {
			if (isset($this->request->get[$param])) {
				$url .= '&' . $param . '=' . urlencode(html_entity_decode((string)$this->request->get[$param], ENT_QUOTES, 'UTF-8'));
			}
		}

		return $url;
	}

	public function getProductCard(): void {
		$this->load->language('sale/order');

		$json = [];

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$product_id = (int)($this->request->get['product_id'] ?? 0);
			$order_id = (int)($this->request->get['order_id'] ?? 0);
			$order_product_id = (int)($this->request->get['order_product_id'] ?? 0);

			if ($product_id) {
				$this->load->model('catalog/product');
				$this->load->model('tool/image');
				$this->load->model('sale/order');

				$order_info = $order_id ? $this->model_sale_order->getOrder($order_id) : null;
				$product_info = $this->model_catalog_product->getProduct($product_id);

				if ($product_info) {
					$language_id = (int)$this->config->get('config_language_id');

					$main_image = $product_info['image'] ?? '';
					if (!empty($main_image) && is_file(DIR_IMAGE . $main_image)) {
						$image = $this->model_tool_image->resize($main_image, 300, 300);
						$image_full = HTTP_CATALOG . 'image/' . $main_image;
					} else {
						$image = $this->model_tool_image->resize('no_image.png', 300, 300);
						$image_full = '';
					}

					$images = $this->model_catalog_product->getProductImages($product_id);
					$gallery = [];
					if ($image_full) {
						$gallery[] = ['thumb' => $image, 'full' => $image_full];
					}
					foreach ($images as $img) {
						if (!empty($img['image']) && is_file(DIR_IMAGE . $img['image'])) {
							$gallery[] = [
								'thumb' => $this->model_tool_image->resize($img['image'], 80, 80),
								'full'  => $this->model_tool_image->resize($img['image'], 300, 300),
							];
						}
					}

					$attributes = $this->model_catalog_product->getProductAttributes($product_id);
					$attr_data = [];
					$this->load->model('catalog/attribute');
					foreach ($attributes as $attr) {
						$descs = $this->model_catalog_attribute->getAttributeDescriptions($attr['attribute_id']);
						$name = $descs[$language_id]['name'] ?? '';
						$text = strip_tags($attr['product_attribute_description'][$language_id]['text'] ?? '');
						if ($name) {
							$attr_data[] = ['name' => $name, 'text' => $text];
						}
					}

					$codes = [];
					if (!empty($product_info['sku'])) $codes[] = ['label' => 'SKU', 'value' => $product_info['sku']];
					if (!empty($product_info['upc'])) $codes[] = ['label' => 'UPC', 'value' => $product_info['upc']];
					if (!empty($product_info['ean'])) $codes[] = ['label' => 'EAN', 'value' => $product_info['ean']];
					if (!empty($product_info['jan'])) $codes[] = ['label' => 'JAN', 'value' => $product_info['jan']];
					if (!empty($product_info['isbn'])) $codes[] = ['label' => 'ISBN', 'value' => $product_info['isbn']];
					if (!empty($product_info['mpn'])) $codes[] = ['label' => 'MPN', 'value' => $product_info['mpn']];

					$variant_data = null;
					$order_options = [];
					$order_product = null;
					if ($order_id && $order_product_id) {
						$order_products = $this->model_sale_order->getOrderProducts($order_id);
						foreach ($order_products as $op) {
							if ((int)$op['order_product_id'] === $order_product_id) {
								$order_product = $op;
								break;
							}
						}

						$order_price = $order_product ? (float)$order_product['price'] : 0;
						$order_tax = $order_product ? (float)$order_product['tax'] : 0;
						$order_total = $order_product ? (float)$order_product['total'] : 0;
						$order_quantity = $order_product ? (int)$order_product['quantity'] : 0;

						if ($order_product && !empty($order_product['variant_id'])) {
							$variant_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$order_product['variant_id'] . "'");
							if ($variant_query->row) {
								$v = $variant_query->row;

								$pc = new \ProductConfigurable($this->registry);

								$variant_base_price = (float)$v['price'];
								$variant_effective_price = $variant_base_price;

								if ($order_info && !empty($order_info['customer_group_id'])) {
									$cg_price = $pc->getVariantCustomerGroupPrice((int)$v['variant_id'], (int)$order_info['customer_group_id']);

									if ($cg_price !== null && $cg_price > 0) {
										$variant_effective_price = $cg_price;
									}

									$special_price = $pc->getVariantSpecialPrice((int)$v['variant_id'], (int)$order_info['customer_group_id']);

									if ($special_price !== null && $special_price < $variant_effective_price) {
										$variant_effective_price = $special_price;
									}
								}

								$variant_data = [
									'model' => $v['model'] ?? '',
									'sku'  => $v['sku'] ?? '',
									'upc'  => $v['upc'] ?? '',
									'ean'  => $v['ean'] ?? '',
									'mpn'  => $v['mpn'] ?? '',
									'price' => $this->currency->format($variant_effective_price, $this->config->get('config_currency')),
									'price_from' => $variant_effective_price < $variant_base_price ? $this->currency->format($variant_base_price, $this->config->get('config_currency')) : '',
									'stock' => (int)$v['quantity'],
								];
								if (!empty($v['model'])) $codes[] = ['label' => 'Variant Model', 'value' => $v['model']];
								if (!empty($v['sku'])) $codes[] = ['label' => 'Variant SKU', 'value' => $v['sku']];
								if (!empty($v['upc'])) $codes[] = ['label' => 'Variant UPC', 'value' => $v['upc']];
								if (!empty($v['ean'])) $codes[] = ['label' => 'Variant EAN', 'value' => $v['ean']];
								if (!empty($v['mpn'])) $codes[] = ['label' => 'Variant MPN', 'value' => $v['mpn']];
							}
						}

						$options = $this->model_sale_order->getOrderOptions($order_id, $order_product_id);
						foreach ($options as $opt) {
							$order_options[] = [
								'name'  => $opt['name'],
								'value' => $opt['value'],
							];
						}
					}

					$description = strip_tags(htmlspecialchars_decode($product_info['description'] ?? '', ENT_QUOTES));
					$description = trim(preg_replace('/\s+/', ' ', $description));

					$currency_code = $order_info['currency_code'] ?? $this->config->get('config_currency');
					$currency_value = $order_info['currency_value'] ?? 1;

					$stock = ($variant_data && isset($variant_data['stock'])) ? $variant_data['stock'] : ($product_info['quantity'] ?? 0);

					$display_model = ($order_product && !empty($order_product['model'])) ? $order_product['model'] : ($product_info['model'] ?? '');

					$unit_tax = $order_quantity > 0 ? $order_tax / $order_quantity : 0;

					$data = [
						'name'        => $product_info['name'] ?? '',
						'model'       => $display_model,
						'description' => $description,
						'price'       => $this->currency->format($order_price + ($this->config->get('config_tax') ? $unit_tax : 0), $currency_code, $currency_value),
						'total'       => $this->currency->format($order_total + ($this->config->get('config_tax') ? $order_tax : 0), $currency_code, $currency_value),
						'quantity'    => $order_quantity,
						'stock'       => $stock,
						'status'      => $product_info['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
						'image'       => $image,
						'gallery'     => $gallery,
						'codes'       => $codes,
						'attributes'  => $attr_data,
						'order_options' => $order_options,
						'variant'     => $variant_data,
						'href'        => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id, true),
					];

					$json['success'] = true;
					$json['html'] = $this->load->view('sale/order_product_card_modal', $data);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
