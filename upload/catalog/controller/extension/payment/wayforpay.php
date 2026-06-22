<?php
class ControllerExtensionPaymentWayforpay extends Controller {
	public function index() {
		$this->load->language('extension/payment/wayforpay');

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['text_loading'] = $this->language->get('text_loading');

		return $this->load->view('extension/payment/wayforpay', $data);
	}

	public function confirm() {
		$json = array();

		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'wayforpay') {
			$order_id = $this->session->data['order_id'];

			$awaiting_status_id = $this->config->get('payment_wayforpay_awaiting_status_id');
			if ($awaiting_status_id) {
				$this->load->model('checkout/order');
				$this->model_checkout_order->addOrderHistory($order_id, (int)$awaiting_status_id);
			}

			$this->load->model('extension/payment/wayforpay');
			$this->model_extension_payment_wayforpay->addOrder($order_id);

			$base = $this->config->get('config_url');
			$lang = $this->config->get('config_language');
			$json['redirect'] = $base . 'index.php?route=extension/payment/wayforpay/pay&language=' . $lang;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function pay() {
		if (!isset($this->session->data['order_id'])) {
			$this->response->redirect($this->url->link('common/home'));
			return;
		}

		$this->load->model('checkout/order');

		$order_id = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link('common/home'));
			return;
		}

		$order_products = $this->model_checkout_order->getOrderProducts($order_id);

		$productNames = array();
		$productPrices = array();
		$productCounts = array();

		foreach ($order_products as $product) {
			$productNames[] = $product['name'];
			$productPrices[] = number_format((float)$product['price'], 2, '.', '');
			$productCounts[] = (int)$product['quantity'];
		}

		if (empty($productNames)) {
			$productNames[] = 'Order #' . $order_id;
			$productPrices[] = number_format((float)$order_info['total'], 2, '.', '');
			$productCounts[] = 1;
		}

		$base = $this->config->get('config_url');
		$lang = $this->config->get('config_language');
		$merchantAccount = $this->config->get('payment_wayforpay_merchant');
		$secretKey = $this->config->get('payment_wayforpay_secretkey');
		$merchantDomainName = parse_url($this->config->get('config_url'), PHP_URL_HOST);
		$orderReference = (string)$order_id;
		$orderDate = strtotime($order_info['date_added']);
		$amount = number_format((float)$order_info['total'], 2, '.', '');
		$currency = $order_info['currency_code'];

		$signatureString = $merchantAccount . ';' . $merchantDomainName . ';' . $orderReference . ';' . $orderDate . ';' . $amount . ';' . $currency . ';';
		$signatureString .= implode(';', $productNames) . ';' . implode(';', $productCounts) . ';' . implode(';', $productPrices);
		$merchantSignature = hash_hmac('md5', $signatureString, $secretKey);

		if ($this->config->get('payment_wayforpay_debug')) {
			$log = new \Log('wayforpay.log');
			$log->write('--- PAY SIGNATURE DEBUG ---');
			$log->write('merchantAccount: ' . $merchantAccount);
			$log->write('merchantDomainName: ' . $merchantDomainName);
			$log->write('orderReference: ' . $orderReference);
			$log->write('orderDate: ' . $orderDate);
			$log->write('amount: ' . $amount);
			$log->write('currency: ' . $currency);
			$log->write('productNames: ' . print_r($productNames, true));
			$log->write('productCounts: ' . print_r($productCounts, true));
			$log->write('productPrices: ' . print_r($productPrices, true));
			$log->write('signatureString: ' . $signatureString);
			$log->write('merchantSignature: ' . $merchantSignature);
			$log->write('--- END ---');
		}

		$data['action'] = 'https://secure.wayforpay.com/pay';
		$data['merchantAccount'] = $merchantAccount;
		$data['merchantDomainName'] = $merchantDomainName;
		$data['merchantSignature'] = $merchantSignature;
		$data['orderReference'] = $orderReference;
		$data['orderDate'] = $orderDate;
		$data['amount'] = $amount;
		$data['currency'] = $currency;
		$data['productNames'] = $productNames;
		$data['productPrices'] = $productPrices;
		$data['productCounts'] = $productCounts;
		$data['serviceUrl'] = $base . 'index.php?route=extension/payment/wayforpay/callback&language=' . $lang;
		$data['returnUrl'] = $base . 'index.php?route=extension/payment/wayforpay/success&order_id=' . $order_id . '&language=' . $lang;

		$data['clientFirstName'] = $order_info['payment_firstname'];
		$data['clientLastName'] = $order_info['payment_lastname'];
		$data['clientEmail'] = $order_info['email'];
		$data['clientPhone'] = $order_info['telephone'];
		$data['clientCity'] = $order_info['payment_city'];
		$data['clientAddress'] = $order_info['payment_address_1'];

		$language = 'AUTO';
		$lang_map = array(
			'en-gb' => 'EN',
			'ru-ua' => 'RU',
			'uk-ua' => 'UA',
		);
		$current_lang = $this->config->get('config_language');
		if (isset($lang_map[$current_lang])) {
			$language = $lang_map[$current_lang];
		}
		$data['language'] = $language;

		$this->response->setOutput($this->load->view('extension/payment/wayforpay_pay', $data));
	}

	public function success() {
		$this->load->language('extension/payment/wayforpay');
		$this->load->language('common/home');

		$this->document->setTitle($this->language->get('text_success_title'));

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success_title'),
			'href' => ''
		);

		if (isset($this->request->get['order_id'])) {
			$this->load->model('extension/payment/wayforpay');
			$wf_order = $this->model_extension_payment_wayforpay->getOrder((int)$this->request->get['order_id']);

			if ($wf_order) {
				$data['payment_status'] = $wf_order['payment_status'];
			} else {
				$data['payment_status'] = 'unknown';
			}

			$data['order_id'] = $this->request->get['order_id'];
		}

		$base = $this->config->get('config_url');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['continue'] = $base . 'index.php?route=common/home&language=' . $this->config->get('config_language');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/payment/wayforpay_success', $data));
	}

	public function callback() {
		$json = file_get_contents('php://input');
		$data = json_decode($json, true);

		if (!$data || !isset($data['merchantAccount']) || !isset($data['orderReference']) || !isset($data['merchantSignature'])) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');

			return;
		}

		$merchantAccount = $this->config->get('payment_wayforpay_merchant');

		if ($data['merchantAccount'] !== $merchantAccount) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');

			return;
		}

		$secretKey = $this->config->get('payment_wayforpay_secretkey');

		$signatureString = $data['merchantAccount'] . ';' . $data['orderReference'] . ';' . $data['amount'] . ';' . $data['currency'] . ';' . ($data['authCode'] ?? '') . ';' . ($data['cardPan'] ?? '') . ';' . $data['transactionStatus'] . ';' . $data['reasonCode'];
		$calculatedSignature = hash_hmac('md5', $signatureString, $secretKey);

		if (!hash_equals($calculatedSignature, $data['merchantSignature'])) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');

			return;
		}

		$order_id = (int)$data['orderReference'];

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');

			return;
		}

		$this->load->model('extension/payment/wayforpay');

		$transactionStatus = $data['transactionStatus'];

		switch ($transactionStatus) {
			case 'Approved':
				$new_status = 'approved';
				$order_status_id = $this->config->get('payment_wayforpay_completed_status_id');
				break;
			case 'Refunded':
			case 'Reversed':
				$new_status = 'refunded';
				$order_status_id = $this->config->get('payment_wayforpay_refunded_status_id');
				break;
			default:
				$new_status = 'failed';
				$order_status_id = $this->config->get('payment_wayforpay_failed_status_id');
		}

		if ($order_status_id) {
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id);
		}

		$this->model_extension_payment_wayforpay->updateOrder($order_id, array(
			'payment_status' => $new_status,
			'callback_data'  => $json
		));

		$time = time();
		$status = 'accept';
		$responseSignature = hash_hmac('md5', $data['orderReference'] . ';' . $status . ';' . $time, $secretKey);

		$response = array(
			'orderReference' => $data['orderReference'],
			'status'         => $status,
			'time'           => $time,
			'signature'      => $responseSignature
		);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($response));
	}
}
