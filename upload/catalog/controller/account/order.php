<?php
class ControllerAccountOrder extends Controller {
	private $order_localizer = null;

	private function orderLocalizer() {
		if ($this->order_localizer === null) {
			$this->order_localizer = new OrderLocalizer($this->registry);
		}

		return $this->order_localizer;
	}

	public function invoice() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/order/invoice', 'order_id=' . (int)($this->request->get['order_id'] ?? 0), true);
			$this->response->redirect($this->url->link('account/login', '', true));
			return;
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);
		$this->load->model('account/order');
		$document = $this->model_account_order->getOrderDocument($order_id);

		if (!$document) {
			$this->response->redirect($this->url->link('error/not_found', '', true));
			return;
		}

		$this->sendInvoiceDocument($document, $order_id);
	}

	public function public_invoice(): void {
		$token = strtolower(trim((string)($this->request->get['token'] ?? '')));

		if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
			$this->response->redirect($this->url->link('error/not_found', '', true));
			return;
		}

		$this->load->model('account/order');
		$document = $this->model_account_order->getPublicInvoiceDocument($token);

		if (!$document) {
			$this->response->redirect($this->url->link('error/not_found', '', true));
			return;
		}

		$this->sendInvoiceDocument($document, (int)$document['order_id']);
	}

	private function sendInvoiceDocument(array $document, int $order_id): void {
		$storage_key = basename((string)$document['storage_key']);
		$path = DIR_STORAGE . 'documents/invoices/' . $storage_key;

		if (!is_file($path) || !is_readable($path)) {
			$this->response->redirect($this->url->link('error/not_found', '', true));
			return;
		}

		$pdf = file_get_contents($path);
		if ($pdf === false) {
			$this->response->redirect($this->url->link('error/not_found', '', true));
			return;
		}

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$this->response->addHeader('Content-Type: application/pdf');
		$this->response->addHeader('Content-Length: ' . strlen($pdf));
		$this->response->addHeader('Content-Disposition: inline; filename="invoice-' . $order_id . '.pdf"');
		$this->response->addHeader('X-Robots-Tag: noindex, nofollow');
		$this->response->setOutput($pdf);
	}

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/order', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/order');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$url = '';

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);
		
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/order', $url, true)
		);

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$limit = 10;

		$data['orders'] = array();

		$this->load->model('account/order');

		$order_total = $this->model_account_order->getTotalOrders();

		$results = $this->model_account_order->getOrders(($page - 1) * $limit, $limit);

		$flow = $this->buildOrderFlowData();

		// Bulk item counts for all orders on the page (one query each instead of per-order)
		$order_ids = array_map('intval', array_column($results, 'order_id'));
		$products_count = $order_ids ? $this->model_account_order->getTotalOrderProductsByOrderIds($order_ids) : array();
		$vouchers_count = $order_ids ? $this->model_account_order->getTotalOrderVouchersByOrderIds($order_ids) : array();
		$images_map = $order_ids ? $this->model_account_order->getOrderProductsImagesByOrderIds($order_ids) : array();
		$returns_map = $order_ids ? $this->model_account_order->getReturnStatusByOrderIds($order_ids) : array();
		$this->load->model('tool/image');

		foreach ($results as $result) {
			$product_total = isset($products_count[(int)$result['order_id']]) ? $products_count[(int)$result['order_id']] : 0;
			$voucher_total = isset($vouchers_count[(int)$result['order_id']]) ? $vouchers_count[(int)$result['order_id']] : 0;
			$payment_status = $this->model_account_order->getPaymentStatus($result['total'], $result['paid_amount'], $this->currency->getDecimalPlace($result['currency_code']), $result['currency_value']);

			$thumbs = array();
			$raw_images = isset($images_map[(int)$result['order_id']]) ? $images_map[(int)$result['order_id']] : array();
			foreach ($raw_images as $raw_image) {
				$image = $raw_image ?: 'placeholder.png';
				$thumb = $this->model_tool_image->resize($image, 56, 56);
				if (!$thumb) {
					$thumb = $this->model_tool_image->resize('placeholder.png', 56, 56);
				}
				if ($thumb) {
					$thumbs[] = $thumb;
				}
			}
			$display_thumbs = array_slice($thumbs, 0, 4);
			$thumbs_more = count($thumbs) > 4 ? count($thumbs) - 4 : 0;

			$data['orders'][] = array(
				'order_id'   => $result['order_id'],
				'name'       => $result['firstname'] . ' ' . $result['lastname'],
				'status'     => $result['status'],
				'payment_status' => $payment_status,
				'payment_status_text' => $this->language->get('text_payment_status_' . $payment_status),
				'tracking_number' => $result['tracking_number'],
				'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				'products'   => ($product_total + $voucher_total),
				'total'      => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value']),
				'flow_progress' => $flow['enabled'] ? $this->flowProgress($flow['steps'], (int)$result['order_status_id']) : 0,
				'thumbs'     => $display_thumbs,
				'thumbs_more' => $thumbs_more,
				'return_count' => isset($returns_map[(int)$result['order_id']]) ? $returns_map[(int)$result['order_id']]['count'] : 0,
				'return_status' => isset($returns_map[(int)$result['order_id']]) ? $returns_map[(int)$result['order_id']]['status'] : '',
				'view'       => $this->url->link('account/order/info', 'order_id=' . $result['order_id'], true),
			);
		}

		$data['flow_enabled'] = $flow['enabled'];

		$pagination = new Pagination();
		$pagination->total = $order_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('account/order', 'page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($order_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($order_total - $limit)) ? $order_total : ((($page - 1) * $limit) + $limit), $order_total, ceil($order_total / $limit));

		$data['continue'] = $this->url->link('account/account', '', true);

		$data['account_menu'] = $this->load->controller('common/account_menu');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/order_list', $data));
	}

	public function info() {
		$this->load->language('account/order');

		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/order/info', 'order_id=' . $order_id, true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);

		if ($order_info) {
			$this->document->setTitle($this->language->get('text_order'));

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', '', true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('account/order', $url, true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_order'),
				'href' => $this->url->link('account/order/info', 'order_id=' . $this->request->get['order_id'] . $url, true)
			);

			if (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];

				unset($this->session->data['error']);
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			$data['order_id'] = (int)$this->request->get['order_id'];
			$invoice_document = $this->model_account_order->getOrderDocument((int)$order_info['order_id']);
			$data['invoice_no'] = !empty($order_info['invoice_no']) ? $order_info['invoice_prefix'] . $order_info['invoice_no'] : '';
			$data['invoice_generated'] = (bool)$invoice_document;
			$data['invoice_url'] = $data['invoice_generated'] ? $this->url->link('account/order/invoice', 'order_id=' . (int)$order_info['order_id'], true) : '';
			$data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));
			$data['tracking_number'] = $order_info['tracking_number'];

			$return_query = $this->db->query("SELECT COUNT(*) AS total, (SELECT rs.name FROM `" . DB_PREFIX . "return` r2 LEFT JOIN `" . DB_PREFIX . "return_status` rs ON (rs.return_status_id = r2.return_status_id AND rs.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE r2.order_id = '" . (int)$order_info['order_id'] . "' ORDER BY r2.return_id DESC LIMIT 1) AS status FROM `" . DB_PREFIX . "return` WHERE order_id = '" . (int)$order_info['order_id'] . "'");
			$data['return_count'] = (int)$return_query->row['total'];
			$data['return_status'] = $return_query->num_rows ? (string)$return_query->row['status'] : '';

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $this->orderLocalizer()->zoneName($order_info, 'payment'),
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $this->orderLocalizer()->countryName($order_info, 'payment')
			);

			$data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['payment_method'] = $this->orderLocalizer()->paymentMethodTitle($order_info);

			$data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
			$data['paid_amount'] = $this->currency->format($order_info['paid_amount'], $order_info['currency_code'], $order_info['currency_value']);
			$data['payment_status'] = $this->model_account_order->getPaymentStatus($order_info['total'], $order_info['paid_amount'], $this->currency->getDecimalPlace($order_info['currency_code']), $order_info['currency_value']);
			$data['payment_status_text'] = $this->language->get('text_payment_status_' . $data['payment_status']);
			$data['payment_remaining'] = $this->currency->format(max(0, (float)$order_info['total'] - (float)$order_info['paid_amount']), $order_info['currency_code'], $order_info['currency_value']);

			$data['payments'] = array();

			$payments = $this->model_account_order->getOrderPayments($this->request->get['order_id']);

			foreach ($payments as $payment) {
				$data['payments'][] = array(
					'amount'         => $this->currency->format(abs($payment['amount']), $order_info['currency_code'], $order_info['currency_value']),
					'is_reversal'    => (float)$payment['amount'] < 0,
					'payment_method' => $this->orderLocalizer()->paymentEntryTitle($payment),
					'date_added'     => date($this->language->get('date_format_short'), strtotime($payment['date_added'])),
				);
			}

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $this->orderLocalizer()->zoneName($order_info, 'shipping'),
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $this->orderLocalizer()->countryName($order_info, 'shipping')
			);

			$data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['shipping_method'] = $this->orderLocalizer()->shippingMethodTitle($order_info);

			$this->load->model('catalog/product');
			$this->load->model('tool/upload');
			$this->load->model('tool/image');

			// Products
			$data['products'] = array();

			$products = $this->model_account_order->getOrderProducts($this->request->get['order_id']);

			$options_map = $this->model_account_order->getOrderOptionsByOrderProductIds($this->request->get['order_id'], array_column($products, 'order_product_id'));

			$product_info_map = array();

			if ($products) {
				$product_info_map = $this->model_catalog_product->getProductsByIds(array_map('intval', array_column($products, 'product_id')));
			}

			// Variant images for per-line image override (bulk, one query)
			$variant_image_map = array();
			$variant_ids = array_filter(array_map('intval', array_column($products, 'variant_id')));
			if ($variant_ids) {
				$variant_ids = array_values(array_unique($variant_ids));
				$variant_query = $this->db->query("SELECT variant_id, image FROM `" . DB_PREFIX . "product_variant` WHERE variant_id IN (" . implode(',', $variant_ids) . ")");
				foreach ($variant_query->rows as $variant_row) {
					$variant_image_map[(int)$variant_row['variant_id']] = (string)$variant_row['image'];
				}
			}

			foreach ($products as $product) {
				$option_data = array();

				$options = isset($options_map[(int)$product['order_product_id']]) ? $options_map[(int)$product['order_product_id']] : array();

				foreach ($options as $option) {
					if ($option['type'] == 'file') {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					} else {
						$value = $this->orderLocalizer()->optionValue($option);
					}

					$option_data[] = array(
						'name'  => $this->orderLocalizer()->optionName($option),
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = isset($product_info_map[(int)$product['product_id']]) ? $product_info_map[(int)$product['product_id']] : false;

				if ($product_info) {
					$reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
				} else {
					$reorder = '';
				}

				// Gift lines are zero-price product lines; exclude products with
				// "call for price" (also zero price) from the gift badge.
				$is_gift = (float)$product['price'] == 0 && (float)$product['total'] == 0 && (int)$product['reward'] == 0 && !empty($product_info) && empty($product_info['call_for_price']);

				// Prefer variant image when variant specifies one, fall back to catalog product image, then placeholder.
				$raw_image = '';
				$variant_id = (int)($product['variant_id'] ?? 0);
				if ($variant_id && !empty($variant_image_map[$variant_id])) {
					$raw_image = $variant_image_map[$variant_id];
				} elseif (!empty($product_info) && !empty($product_info['image'])) {
					$raw_image = $product_info['image'];
				}
				if (!$raw_image) {
					$raw_image = 'placeholder.png';
				}
				$image = $this->model_tool_image->resize($raw_image, 64, 64);
				if (!$image) {
					$image = $this->model_tool_image->resize('placeholder.png', 64, 64);
				}
				if ($product_info) {
					$product_href = $this->url->link('product/product', 'product_id=' . $product['product_id'], true);
				} else {
					$product_href = '';
				}

				$data['products'][] = array(
					'name'     => $this->orderLocalizer()->productName($product),
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					'is_gift'  => $is_gift,
					'image'    => $image,
					'href'     => $product_href,
					'reorder'  => $reorder,
					'return'   => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
				);
			}

			// Voucher
			$data['vouchers'] = array();

			$vouchers = $this->model_account_order->getOrderVouchers($this->request->get['order_id']);

			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			// Totals
			$data['totals'] = array();

			$totals = $this->model_account_order->getOrderTotals($this->request->get['order_id']);
			$shipping_method_title = $this->orderLocalizer()->shippingMethodTitle($order_info);

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $this->orderLocalizer()->totalTitle($total, $shipping_method_title),
					'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				);
			}

			$data['comment'] = nl2br($order_info['comment']);

			// Order flow (status stepper)
			$flow = $this->buildOrderFlowData((int)$order_info['order_status_id']);

			$data['flow_enabled'] = $flow['enabled'];
			$data['flow_steps'] = $flow['steps'];
			$data['flow_terminal'] = $flow['terminal'];

			// History
			$data['histories'] = array();

			$results = $this->model_account_order->getOrderHistories($this->request->get['order_id']);

			foreach ($results as $result) {
				$comment = $result['comment'];

				$resolved = $this->orderLocalizer()->historyComment($result);

				if ($resolved !== null) {
					$comment = $resolved;
				}

				$data['histories'][] = array(
					'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
					'status'     => $result['status'],
					'comment'    => $result['notify'] ? nl2br($comment) : ''
				);
			}

			$data['continue'] = $this->url->link('account/order', '', true);

			$data['account_menu'] = $this->load->controller('common/account_menu');

		$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('account/order_info', $data));
		} else {
			return new Action('error/not_found');
		}
	}

	/**
	 * Build the configured order flow (status chain) with localized step names.
	 *
	 * @param int $order_status_id Current order status (0 = chain not used)
	 * @return array{enabled: bool, steps: array<int, array<string, mixed>>, terminal: bool}
	 */
	private function buildOrderFlowData($order_status_id = 0) {
		$order_status_id = (int)$order_status_id;

		$order_flow = new OrderFlow(array(
			'steps'       => (array)$this->config->get('config_order_flow_steps'),
			'transitions' => (array)$this->config->get('config_order_flow_transitions'),
		));

		$enabled = $order_flow->isEnabled();

		$steps = array();
		$current_index = $order_flow->getStepIndex($order_status_id);

		if ($enabled) {
			$status_names = array();

			$statuses = $this->db->query("SELECT order_status_id, name FROM " . DB_PREFIX . "order_status WHERE language_id = '" . (int)$this->config->get('config_language_id') . "'");

			foreach ($statuses->rows as $status) {
				$status_names[(int)$status['order_status_id']] = $status['name'];
			}

			$completed = $order_flow->isFinalStep($order_status_id);

			foreach ($order_flow->getSteps() as $step) {
				$index = $order_flow->getStepIndex($step);

				if ($current_index >= 0) {
					if ($completed && $index === $current_index) {
						$state = 'done';
					} else {
						$state = $index < $current_index ? 'done' : ($index === $current_index ? 'current' : 'upcoming');
					}
				} else {
					$state = 'upcoming';
				}

				$steps[] = array(
					'order_status_id' => $step,
					'name'            => isset($status_names[$step]) ? $status_names[$step] : '',
					'state'           => $state,
				);
			}
		}

		return array(
			'enabled'  => $enabled,
			'steps'    => $steps,
			'terminal' => $order_flow->isTerminal($order_status_id),
		);
	}

	/**
	 * Progress through the flow chain as a 0..100 percent value.
	 *
	 * @param array<int, array<string, mixed>> $steps
	 */
	private function flowProgress($steps, $order_status_id) {
		$count = count($steps);

		if ($count <= 0) {
			return 0;
		}

		$order_status_id = (int)$order_status_id;

		foreach ($steps as $index => $step) {
			if ((int)$step['order_status_id'] === $order_status_id) {
				return (int)round((($index + 1) / $count) * 100);
			}
		}

		// Status not in the chain: treat as completed when it sits past the flow
		return 100;
	}

	public function reorder() {
		$this->load->language('account/order');

		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);

		if ($order_info) {
			if (isset($this->request->get['order_product_id'])) {
				$order_product_id = $this->request->get['order_product_id'];
			} else {
				$order_product_id = 0;
			}

			$order_product_info = $this->model_account_order->getOrderProduct($order_id, $order_product_id);

			if ($order_product_info) {
				$this->load->model('catalog/product');

				$product_info = $this->model_catalog_product->getProduct($order_product_info['product_id']);

				if ($product_info) {
					$option_data = array();

					$order_options = $this->model_account_order->getOrderOptions($order_product_info['order_id'], $order_product_id);

					foreach ($order_options as $order_option) {
						if ($order_option['type'] == 'select' || $order_option['type'] == 'radio' || $order_option['type'] == 'image' || $order_option['type'] == 'color') {
							$option_data[$order_option['product_option_id']] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'checkbox') {
							$option_data[$order_option['product_option_id']][] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'text' || $order_option['type'] == 'textarea' || $order_option['type'] == 'date' || $order_option['type'] == 'datetime' || $order_option['type'] == 'time') {
							$option_data[$order_option['product_option_id']] = $order_option['value'];
						} elseif ($order_option['type'] == 'file') {
							$option_data[$order_option['product_option_id']] = $order_option['value'];
						}
					}

					$this->cart->add($order_product_info['product_id'], $order_product_info['quantity'], $option_data);

					$this->session->data['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_info['product_id']), $product_info['name'], $this->url->link('checkout/cart'));

					unset($this->session->data['shipping_method']);
					unset($this->session->data['shipping_methods']);
					unset($this->session->data['payment_method']);
					unset($this->session->data['payment_methods']);
				} else {
					$this->session->data['error'] = sprintf($this->language->get('error_reorder'), $order_product_info['name']);
				}
			}
		}

		$this->response->redirect($this->url->link('account/order/info', 'order_id=' . $order_id));
	}
}
