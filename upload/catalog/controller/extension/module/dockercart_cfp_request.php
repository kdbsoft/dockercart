<?php
/**
 * Call for Price — "Request" mode (one-click order).
 *
 * AJAX endpoint for the storefront request modal. Creates a real order
 * with a dedicated status ("Awaiting request", id 135) without touching
 * the customer's cart. Only products that are actually marked as
 * call-for-price (per-product flag or zero price + global setting) are
 * accepted, so the endpoint cannot be abused to place arbitrary orders.
 */
class ControllerExtensionModuleDockercartCfpRequest extends Controller {
	const REQUEST_ORDER_STATUS_ID = 135;

	public function request() {
		$this->load->language('extension/module/dockercart_cfp_request');

		$json = array();

		$this->load->model('catalog/product');

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$product_info = $product_id ? $this->model_catalog_product->getProduct($product_id) : null;

		// Only call-for-price products may be ordered through this endpoint.
		$cfp_status = (int)$this->config->get('dockercart_theme_call_for_price_status');
		$cfp_phone = (string)$this->config->get('config_telephone');

		if (!$product_info) {
			$json['error'] = $this->language->get('error_product');
		} elseif (empty($product_info['call_for_price']) && !($cfp_status && $cfp_phone !== '' && (float)$product_info['price'] <= 0)) {
			$json['error'] = $this->language->get('error_product');
		}

		$firstname = trim((string)($this->request->post['name'] ?? ''));
		$telephone = trim((string)($this->request->post['telephone'] ?? ''));
		$email = trim((string)($this->request->post['email'] ?? ''));
		$comment = trim((string)($this->request->post['comment'] ?? ''));
		$quantity = max(1, (int)($this->request->post['quantity'] ?? 1));

		if (!isset($json['error']) && mb_strlen($firstname) < 1) {
			$json['error'] = $this->language->get('error_name');
		}

		if (!isset($json['error']) && !$this->validatePhone($telephone)) {
			$json['error'] = $this->language->get('error_telephone');
		}

		if (!isset($json['error']) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$json['error'] = $this->language->get('error_email');
		}

		if (!isset($json['error'])) {
			try {
				$order_id = $this->createOrder($product_info, $firstname, $telephone, $email, $comment, $quantity);

				$json['success'] = sprintf($this->language->get('text_success'), $order_id);
			} catch (\Exception $e) {
				$json['error'] = $this->language->get('error_order');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Validate the telephone against the store's default-country phone
	 * mask (same source as the client-side mask). Without a mask, fall
	 * back to a plain 5+ digit count.
	 */
	private function validatePhone(string $telephone): bool {
		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));
		$phone_format = $country_info ? (string)($country_info['phone_format'] ?? '') : '';

		if ($phone_format !== '') {
			$pattern = '/^';
			for ($i = 0; $i < strlen($phone_format); $i++) {
				$ch = $phone_format[$i];
				$pattern .= $ch === 'X' ? '\d' : preg_quote($ch, '/');
			}
			$pattern .= '$/';

			return (bool)preg_match($pattern, $telephone);
		}

		$digits = preg_replace('/\D/', '', $telephone);

		return strlen($digits) >= 5;
	}

	/**
	 * Build the order data array (same shape as ModelCheckoutOrder::addOrder
	 * expects — mirrored from checkout/dockercart_checkout::prepareOrderData)
	 * and create the order with the "Awaiting request" status.
	 */
	private function createOrder($product_info, $firstname, $telephone, $email, $comment, $quantity) {
		$this->load->model('checkout/order');

		$email = $email !== '' ? $email : 'localhost_' . time() . '@localhost';

		// If the customer is logged in, prefer the account data for fields
		// the form does not collect (lastname, tax number).
		$lastname = '';
		$tax_number = '';

		if ($this->customer->isLogged()) {
			$lastname = $this->customer->getLastName();

			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
			$tax_number = isset($customer_info['tax_number']) ? (string)$customer_info['tax_number'] : '';
		}

		// Configurable products: pass the selected variant (validated below),
		// otherwise fall back to the default variant if the product is configurable.
		$variant_id = isset($this->request->post['variant_id']) ? (int)$this->request->post['variant_id'] : 0;
		$variant_sku = '';

		if ($variant_id > 0) {
			$variant_valid = false;

			if (!empty($product_info['is_configurable'])) {
				$pc = new \ProductConfigurable($this->registry);
				$variant_info = $pc->getVariant((int)$variant_id);

				if ($variant_info && (int)$variant_info['product_id'] === (int)$product_info['product_id']) {
					$variant_valid = true;
					$variant_sku = isset($variant_info['sku']) ? (string)$variant_info['sku'] : '';
				}
			}

			if (!$variant_valid) {
				$variant_id = 0;
			}
		} elseif (!empty($product_info['is_configurable']) && !empty($product_info['default_variant_id'])) {
			$variant_id = (int)$product_info['default_variant_id'];

			$pc = new \ProductConfigurable($this->registry);
			$variant_info = $pc->getVariant((int)$variant_id);
			$variant_sku = ($variant_info && isset($variant_info['sku'])) ? (string)$variant_info['sku'] : '';
		}

		$payment_method = $this->language->get('text_request_order');

		$order_data = array(
			// Store information
			'invoice_prefix' => $this->config->get('config_invoice_prefix'),
			'store_id' => $this->config->get('config_store_id'),
			'store_name' => $this->config->get('config_name'),
			'store_url' => $this->config->get('config_secure') ? $this->config->get('config_ssl') : $this->config->get('config_url'),

			// Customer information
			'customer_id' => $this->customer->isLogged() ? $this->customer->getId() : 0,
			'customer_group_id' => $this->customer->isLogged() ? $this->customer->getGroupId() : $this->config->get('config_customer_group_id'),
			'firstname' => $firstname,
			'lastname' => $lastname,
			'email' => $email,
			'telephone' => $telephone,
			'tax_number' => $tax_number,
			'custom_field' => array(),

			// Payment address (not collected — request order)
			'payment_firstname' => $firstname,
			'payment_lastname' => $lastname,
			'payment_company' => '',
			'payment_address_1' => '',
			'payment_address_2' => '',
			'payment_city' => '',
			'payment_postcode' => '',
			'payment_country' => '',
			'payment_country_id' => 0,
			'payment_zone' => '',
			'payment_zone_id' => 0,
			'payment_address_format' => '',
			'payment_custom_field' => array(),
			'payment_method' => $payment_method,
			'payment_code' => 'cfp_request',

			// Shipping address (not collected — request order)
			'shipping_firstname' => $firstname,
			'shipping_lastname' => $lastname,
			'shipping_company' => '',
			'shipping_address_1' => '',
			'shipping_address_2' => '',
			'shipping_city' => '',
			'shipping_postcode' => '',
			'shipping_country' => '',
			'shipping_country_id' => 0,
			'shipping_zone' => '',
			'shipping_zone_id' => 0,
			'shipping_address_format' => '',
			'shipping_custom_field' => array(),
			'shipping_method' => '',
			'shipping_code' => '',

			// Order details
			'comment' => $comment,
			'total' => 0.0,
			'affiliate_id' => 0,
			'commission' => 0,
			'marketing_id' => 0,
			'tracking' => '',
			'language_id' => $this->config->get('config_language_id'),
			'currency_id' => $this->config->get('config_currency_id'),
			'currency_code' => isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency'),
			'currency_value' => isset($this->session->data['currency_value']) ? $this->session->data['currency_value'] : 1.0,

			// Environment
			'ip' => $this->request->server['REMOTE_ADDR'],
			'forwarded_ip' => isset($this->request->server['HTTP_X_FORWARDED_FOR']) ? $this->request->server['HTTP_X_FORWARDED_FOR'] : '',
			'user_agent' => isset($this->request->server['HTTP_USER_AGENT']) ? substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '',
			'accept_language' => isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? substr($this->request->server['HTTP_ACCEPT_LANGUAGE'], 0, 255) : '',

			// Single product, zero total — the order is a price request
			'products' => array(
				array(
					'product_id' => (int)$product_info['product_id'],
					'variant_id' => $variant_id,
					'variant_sku' => $variant_sku,
					'name' => $product_info['name'],
					'model' => $product_info['model'],
					'quantity' => $quantity,
					'price' => 0.0,
					'total' => 0.0,
					'tax' => 0,
					'reward' => 0,
					'option' => array(),
				),
			),
			'totals' => array(
				array(
					'code' => 'sub_total',
					'title' => $this->language->get('text_sub_total'),
					'value' => 0.0,
					'sort_order' => 1,
				),
			),
		);

		$order_id = $this->model_checkout_order->addOrder($order_data);

		// Apply the dedicated "Awaiting request" status (not in
		// processing/complete sets, so stock and coupons are untouched).
		$this->model_checkout_order->addOrderHistory($order_id, self::REQUEST_ORDER_STATUS_ID, $comment);

		// addOrder() binds an oc_order_claim to this session to guard against
		// double-submits. For one-click requests every submission must create
		// a new order, so release the claim right away (same as checkout/success).
		if (session_id() !== '') {
			$this->db->query("DELETE FROM " . DB_PREFIX . "order_claim WHERE session_id = '" . $this->db->escape(session_id()) . "'");
		}

		return $order_id;
	}
}
