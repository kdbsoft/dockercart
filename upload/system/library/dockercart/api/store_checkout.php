<?php
/**
 * DockerCart customer (storefront) REST API — checkout session.
 *
 * Mirrors the DockerCart one-page checkout step by step, across requests:
 *
 *   POST   /v1/checkout                        open a session (addresses + stock hold)
 *   GET    /v1/checkout/{id}                   full state: cart, totals, methods, missing
 *   PATCH  /v1/checkout/{id}                   addresses / shipping_code / payment_code / comment / agree
 *   DELETE /v1/checkout/{id}                   cancel (releases the stock hold)
 *   POST   /v1/checkout/{id}/confirm           create the order
 *   POST   /v1/checkout/{id}/payment           start the payment (see StorePayment)
 *
 * What the storefront keeps in the session, the API keeps in oc_api_checkout
 * (addresses, chosen method codes, comment, terms) and in the cart scope's
 * session data (coupon / voucher / reward points — the storefront total
 * extensions read them from there). Quotes are never cached: every state
 * computation re-resolves the chosen shipping/payment code, so a cart or price
 * change can never be confirmed with a stale amount.
 *
 * A row is a two-state machine: `open` while the checkout is under way, and
 * `completed` once confirm() placed the order. A completed row is kept (instead
 * of deleted) because the payment is started by a follow-up request that needs
 * the order id, the chosen method and the order status right after the write —
 * see Dockercart\Api\StorePayment. A customer has at most one open row.
 *
 * Order creation goes through the storefront order model
 * (upload/catalog/model/checkout/order.php::addOrder), which is the single write
 * path: order claim (double-submit guard), stock-reservation binding, the
 * order.created webhook outbox row and the warehouse allocation all live there.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreCheckout {
	public const STATUS_OPEN = 'open';

	public const STATUS_COMPLETED = 'completed';

	private \Registry $registry;

	private int $customer_id;

	private StoreCart $cart;

	private StoreAddress $addresses;

	private StoreMethods $methods;

	public function __construct(\Registry $registry, int $customer_id) {
		$this->registry = $registry;
		$this->customer_id = $customer_id;
		$this->cart = new StoreCart($registry);
		$this->addresses = new StoreAddress($registry, $customer_id);
		$this->methods = new StoreMethods($registry);
	}

	/* ─────────────────────────────── open / update ─────────────────────────────── */

	/**
	 * Opens a checkout session for the current cart.
	 */
	public function open(array $input): array {
		if ($this->cart->isEmpty()) {
			throw new HttpException(Error::CART_EMPTY);
		}

		$customer = $this->customerRow();

		$row = [
			'customer_id'   => $this->customer_id,
			'api_key_id'    => $this->apiKeyId(),
			'store_id'      => (int)$this->config()->get('config_store_id'),
			'language_id'   => (int)$this->config()->get('config_language_id'),
			'currency_code' => (string)$this->config()->get('config_currency'),
			'email'         => $this->pickEmail($input, $customer),
			'telephone'     => $this->pickTelephone($input, $customer),
			'firstname'     => (string)($customer['firstname'] ?? ''),
			'lastname'      => (string)($customer['lastname'] ?? ''),
			'tax_number'    => (string)($customer['tax_number'] ?? ''),
			'payment_address' => null,
			'shipping_address' => null,
			'shipping_code' => '',
			'payment_code'  => '',
			'comment'       => '',
			'agree'         => 0,
		];

		$payment_address = null;

		if (isset($input['payment_address'])) {
			$payment_address = $this->addresses->resolve($this->addressInput($input['payment_address']), 'payment_address');
		} else {
			$payment_address = $this->addresses->defaultAddress();
		}

		$row['payment_address'] = $payment_address ? (string)json_encode($this->addresses->stored($payment_address)) : null;

		$shipping_address = null;

		if (array_key_exists('shipping_address', $input)) {
			$shipping_address = $input['shipping_address'] === null
				? null
				: $this->addresses->resolve($this->addressInput($input['shipping_address']), 'shipping_address');
		} elseif (!isset($input['ship_to_payment_address']) || !empty($input['ship_to_payment_address'])) {
			$shipping_address = $payment_address;
		}

		$row['shipping_address'] = $shipping_address ? (string)json_encode($this->addresses->stored($shipping_address)) : null;
		$row['comment'] = $this->comment($input['comment'] ?? '');
		$row['agree'] = (int)!empty($input['agree']);

		$checkout_id = $this->insert($row);

		$row = $this->find($checkout_id);

		$this->refreshHolds($row);

		return $this->state($row);
	}

	/**
	 * Applies a partial update and returns the new state.
	 */
	public function update(int $checkout_id, array $input): array {
		$row = $this->requireOpen($this->find($checkout_id));

		$changes = [];

		foreach (['telephone', 'firstname', 'lastname', 'tax_number'] as $field) {
			if (array_key_exists($field, $input)) {
				$changes[$field] = $this->text($input[$field], $field, 32);
			}
		}

		if (array_key_exists('email', $input)) {
			$changes['email'] = $this->email($input['email']);
		}

		if (array_key_exists('comment', $input)) {
			$changes['comment'] = $this->comment($input['comment']);
		}

		if (array_key_exists('agree', $input)) {
			$changes['agree'] = (int)!empty($input['agree']);
		}

		$address_changed = false;

		if (array_key_exists('payment_address', $input)) {
			$address = $input['payment_address'] === null ? null : $this->addresses->resolve($this->addressInput($input['payment_address']), 'payment_address');

			$changes['payment_address'] = $address ? (string)json_encode($this->addresses->stored($address)) : null;
			$address_changed = true;
		}

		if (array_key_exists('shipping_address', $input)) {
			if ($input['shipping_address'] === null) {
				$changes['shipping_address'] = null;
			} else {
				$address = $this->addresses->resolve($this->addressInput($input['shipping_address']), 'shipping_address');

				$changes['shipping_address'] = (string)json_encode($this->addresses->stored($address));
			}

			$address_changed = true;
		} elseif (!empty($input['ship_to_payment_address'])) {
			$changes['shipping_address'] = $changes['payment_address'] ?? $row['payment_address'];
			$address_changed = true;
		}

		$row = array_merge($row, $changes);

		if ($address_changed) {
			// A new address invalidates the method chosen for the old one.
			if (!array_key_exists('shipping_code', $input)) {
				$row['shipping_code'] = '';
			}

			if (!array_key_exists('payment_code', $input)) {
				$row['payment_code'] = '';
			}
		}

		$row = $this->validateMethods($row, $input);

		$this->persist($row);

		$row = $this->find($checkout_id);

		$this->refreshHolds($row);

		return $this->state($row);
	}

	/**
	 * Cancels a checkout: releases the stock hold and drops the session.
	 */
	public function cancel(int $checkout_id): void {
		$this->requireOpen($this->find($checkout_id));

		$reservation = new \DockercartStockReservation($this->registry);

		if ($reservation->isEnabled()) {
			$reservation->releaseSession($this->sessionId());
		}

		$db = $this->db();

		$db->query("DELETE FROM `" . DB_PREFIX . "api_checkout` WHERE `api_checkout_id` = '" . (int)$checkout_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");
	}

	/* ─────────────────────────────── state ─────────────────────────────── */

	/**
	 * Full checkout representation: cart, totals, methods, what is still missing.
	 *
	 * A completed checkout has no cart and no methods left to choose, so it is
	 * answered with its summary instead (the order id is what a client that lost
	 * the confirm response needs); `status` tells the two apart.
	 */
	public function state(array $row): array {
		if ($this->status($row) !== self::STATUS_OPEN) {
			return [
				'checkout_id'     => (int)$row['api_checkout_id'],
				'status'          => $this->status($row),
				'order_id'        => (int)($row['order_id'] ?? 0),
				'payment_started' => trim((string)($row['payment_init'] ?? '')) !== '',
				'date_added'      => Serializer::datetime($row['date_added'] ?? ''),
				'date_modified'   => Serializer::datetime($row['date_modified'] ?? ''),
			];
		}

		$this->hydrateSession($row);

		$cart = $this->cart->state();

		$stored_payment_address = $this->decode($row['payment_address'] ?? null);
		$stored_shipping_address = $this->decode($row['shipping_address'] ?? null);

		$has_shipping = !empty($cart['has_shipping']);

		// The row keeps the escaped (stored) form — the shipping/payment extensions
		// and ModelCheckoutOrder::addOrder() consume it — while the response shows the
		// readable one.
		$stored_shipping_address = $has_shipping
			? ($stored_shipping_address ?: $stored_payment_address)
			: ($stored_shipping_address ?: null);

		$shipping_address = $stored_shipping_address ? $this->addresses->represent($stored_shipping_address, false) : null;
		$payment_address = $stored_payment_address ? $this->addresses->represent($stored_payment_address, false) : null;

		$shipping_methods = $stored_shipping_address ? $this->quotesFor($stored_shipping_address) : [];
		$payment_methods = $stored_payment_address ? $this->methodsFor($stored_payment_address) : [];

		$shipping_code = (string)($row['shipping_code'] ?? '');
		$payment_code = (string)($row['payment_code'] ?? '');

		$shipping_selected = $this->findQuote($shipping_methods, $shipping_code);
		$payment_selected = $this->findMethod($payment_methods, $payment_code);

		$shipping_code = $shipping_selected ? $shipping_code : '';
		$payment_code = $payment_selected ? $payment_code : '';

		$missing = [];

		if ((int)$cart['items'] === 0) {
			$missing[] = 'cart';
		}

		if (empty($cart['has_stock']) && !$this->config()->get('config_stock_checkout')) {
			$missing[] = 'stock';
		}

		if ($this->config()->get('config_checkout_id') && empty($row['agree'])) {
			$missing[] = 'agree';
		}

		if (!$payment_address) {
			$missing[] = 'payment_address';
		}

		if ($has_shipping && !$shipping_address) {
			$missing[] = 'shipping_address';
		}

		if ($has_shipping && !$shipping_code) {
			$missing[] = 'shipping_method';
		}

		if (!$payment_code) {
			$missing[] = 'payment_method';
		}

		return [
			'checkout_id'      => (int)$row['api_checkout_id'],
			'status'           => self::STATUS_OPEN,
			'order_id'         => 0,
			'firstname'        => Serializer::decodeText($row['firstname'] ?? ''),
			'lastname'         => Serializer::decodeText($row['lastname'] ?? ''),
			'email'            => (string)($row['email'] ?? ''),
			'telephone'        => Serializer::decodeText($row['telephone'] ?? ''),
			'tax_number'       => Serializer::decodeText($row['tax_number'] ?? ''),
			'comment'          => Serializer::decodeText($row['comment'] ?? ''),
			'agree'            => (bool)(int)($row['agree'] ?? 0),
			'payment_address'  => $payment_address,
			'shipping_address' => $shipping_address,
			'shipping_code'    => $shipping_code,
			'payment_code'     => $payment_code,
			'shipping_method'  => $shipping_selected ? $this->publicQuote($shipping_selected) : null,
			'payment_method'   => $payment_selected ? $this->publicMethod($payment_selected) : null,
			'shipping_methods' => $shipping_methods ? array_map([$this, 'publicQuote'], $shipping_methods) : [],
			'payment_methods'  => $payment_methods ? array_map([$this, 'publicMethod'], $payment_methods) : [],
			'cart'             => $cart,
			'missing'          => $missing,
			'can_confirm'      => $missing === [],
			'date_added'       => Serializer::datetime($row['date_added'] ?? ''),
			'date_modified'    => Serializer::datetime($row['date_modified'] ?? ''),
		];
	}

	public function shippingMethods(int $checkout_id): array {
		$row = $this->requireOpen($this->find($checkout_id));

		$address = $this->decode($row['shipping_address'] ?? null) ?: $this->decode($row['payment_address'] ?? null);

		if (!$address) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'shipping_address' => ['required before shipping methods can be quoted'],
			]);
		}

		return array_map([$this, 'publicQuote'], $this->quotesFor($address));
	}

	public function paymentMethods(int $checkout_id): array {
		$row = $this->requireOpen($this->find($checkout_id));

		$address = $this->decode($row['payment_address'] ?? null);

		if (!$address) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'payment_address' => ['required before payment methods can be listed'],
			]);
		}

		return array_map([$this, 'publicMethod'], $this->methodsFor($address));
	}

	/* ─────────────────────────────── confirm ─────────────────────────────── */

	/**
	 * Places the order and returns it (order representation).
	 */
	public function confirm(int $checkout_id, array $input): array {
		$row = $this->requireOpen($this->find($checkout_id));

		if ($input) {
			$row = $this->applyBeforeConfirm($row, $input);
		}

		$state = $this->state($row);

		$this->assertConfirmable($state);

		$row = $this->find($checkout_id);

		$this->hydrateSession($row);

		// Re-hold right before the write: an expired or grabbed hold must block
		// the order (the storefront does exactly the same).
		if (!$this->refreshHolds($row)) {
			throw new HttpException(Error::INSUFFICIENT_STOCK);
		}

		$order_status_id = $this->initialStatusId();

		$order_data = $this->orderData($row, $order_status_id);

		CatalogModels::preloadForOrder($this->registry);

		$order_model = CatalogModels::load($this->registry, 'checkout/order');

		$order_id = (int)$order_model->addOrder($order_data);

		$order_model->addOrderHistory($order_id, $order_status_id);

		$this->finish($order_id, $checkout_id, $order_status_id);

		$order = new StoreOrder($this->registry, $this->customer_id);

		return $order->full($order_id);
	}

	/**
	 * Validates the state before the write: a missing requirement is a 422 with
	 * a per-field reason, an empty cart or an out of stock line a 409.
	 */
	private function assertConfirmable(array $state): void {
		$missing = $state['missing'];

		if (in_array('cart', $missing, true)) {
			throw new HttpException(Error::CART_EMPTY);
		}

		if (in_array('stock', $missing, true)) {
			throw new HttpException(Error::INSUFFICIENT_STOCK);
		}

		$fields = [];

		$labels = [
			'agree'            => 'the checkout terms must be accepted',
			'payment_address'  => 'required',
			'shipping_address' => 'required',
			'shipping_method'  => 'required',
			'payment_method'   => 'required',
		];

		foreach ($missing as $key) {
			if (isset($labels[$key])) {
				$fields[$key] = [$labels[$key]];
			}
		}

		if ($fields) {
			throw new HttpException(Error::VALIDATION_FAILED, $fields);
		}
	}

	/**
	 * Applies the confirm-time payload (comment / agree / method codes) and
	 * persists it, so a retried confirm sees the same state.
	 */
	private function applyBeforeConfirm(array $row, array $input): array {
		$changed = array_intersect(['comment', 'agree', 'shipping_code', 'payment_code'], array_keys($input));

		if (!$changed) {
			return $row;
		}

		$patch = [];

		foreach ($changed as $field) {
			$patch[$field] = $input[$field];
		}

		$row = array_merge($row, $this->validateMethods($row, $patch));

		if (array_key_exists('comment', $patch)) {
			$row['comment'] = $this->comment($patch['comment']);
		}

		if (array_key_exists('agree', $patch)) {
			$row['agree'] = (int)!empty($patch['agree']);
		}

		$this->persist($row);

		return $this->find((int)$row['api_checkout_id']);
	}

	/**
	 * Everything addOrder() needs, built from the cart + the checkout session.
	 */
	private function orderData(array $row, int $order_status_id): array {
		$payment_address = $this->decode($row['payment_address'] ?? null) ?: [];
		$shipping_address = $this->decode($row['shipping_address'] ?? null) ?: $payment_address;

		$shipping_method = (string)($row['shipping_code'] ?? '') !== ''
			? $this->methods->shippingMethod($shipping_address, (string)$row['shipping_code'])
			: [];

		$payment_method = (string)($row['payment_code'] ?? '') !== ''
			? $this->methods->paymentMethod($payment_address, (float)$this->cart()->getTotal(), (string)$row['payment_code'])
			: [];

		$customer = $this->customerRow();

		$totals = $this->cart->totals()->raw();

		$products = $this->orderProducts($shipping_method);

		$currency_code = (string)$this->config()->get('config_currency');

		return [
			'invoice_prefix'   => (string)$this->config()->get('config_invoice_prefix'),
			'store_id'         => (int)$this->config()->get('config_store_id'),
			'store_name'       => (string)$this->config()->get('config_name'),
			'store_url'        => $this->config()->get('config_secure') ? (string)$this->config()->get('config_ssl') : (string)$this->config()->get('config_url'),
			'customer_id'      => $this->customer_id,
			'customer_group_id' => (int)($customer['customer_group_id'] ?? $this->config()->get('config_customer_group_id')),
			'firstname'        => (string)($row['firstname'] ?: ($customer['firstname'] ?? '')),
			'lastname'         => (string)($row['lastname'] ?: ($customer['lastname'] ?? '')),
			'email'            => (string)($row['email'] ?: ($customer['email'] ?? '')),
			'telephone'        => (string)($row['telephone'] ?: ($customer['telephone'] ?? '')),
			'tax_number'       => (string)($row['tax_number'] ?? ''),
			'custom_field'     => [],
			'payment_firstname' => (string)($payment_address['firstname'] ?? ''),
			'payment_lastname'  => (string)($payment_address['lastname'] ?? ''),
			'payment_company'   => (string)($payment_address['company'] ?? ''),
			'payment_address_1' => (string)($payment_address['address_1'] ?? ''),
			'payment_address_2' => (string)($payment_address['address_2'] ?? ''),
			'payment_city'      => (string)($payment_address['city'] ?? ''),
			'payment_postcode'  => (string)($payment_address['postcode'] ?? ''),
			'payment_country'   => (string)($payment_address['country'] ?? ''),
			'payment_country_id' => (int)($payment_address['country_id'] ?? 0),
			'payment_zone'      => (string)($payment_address['zone'] ?? ''),
			'payment_zone_id'   => (int)($payment_address['zone_id'] ?? 0),
			'payment_address_format' => (string)($payment_address['address_format'] ?? ''),
			'payment_custom_field' => (array)($payment_address['custom_field'] ?? []),
			'payment_method'    => (string)($payment_method['title'] ?? ''),
			'payment_code'      => (string)($payment_method['code'] ?? ''),
			'shipping_firstname' => (string)($shipping_address['firstname'] ?? ''),
			'shipping_lastname'  => (string)($shipping_address['lastname'] ?? ''),
			'shipping_company'   => (string)($shipping_address['company'] ?? ''),
			'shipping_address_1' => (string)($shipping_address['address_1'] ?? ''),
			'shipping_address_2' => (string)($shipping_address['address_2'] ?? ''),
			'shipping_city'      => (string)($shipping_address['city'] ?? ''),
			'shipping_postcode'  => (string)($shipping_address['postcode'] ?? ''),
			'shipping_country'   => (string)($shipping_address['country'] ?? ''),
			'shipping_country_id' => (int)($shipping_address['country_id'] ?? 0),
			'shipping_zone'      => (string)($shipping_address['zone'] ?? ''),
			'shipping_zone_id'   => (int)($shipping_address['zone_id'] ?? 0),
			'shipping_address_format' => (string)($shipping_address['address_format'] ?? ''),
			'shipping_custom_field' => (array)($shipping_address['custom_field'] ?? []),
			'shipping_method'    => (string)($shipping_method['title'] ?? ''),
			'shipping_code'      => (string)($shipping_method['code'] ?? ''),
			'comment'            => (string)($row['comment'] ?? ''),
			'total'              => (float)$totals['total'],
			'affiliate_id'       => 0,
			'commission'         => 0,
			'marketing_id'       => 0,
			'tracking'           => '',
			'language_id'        => (int)$this->config()->get('config_language_id'),
			'currency_id'        => (int)$this->registry->get('currency')->getId($currency_code),
			'currency_code'      => $currency_code,
			// Cart amounts are normalised to the store base currency by
			// Cart\Cart (multi-currency aware), so the order rate is 1.
			'currency_value'     => 1.0,
			// oc_order.ip / forwarded_ip are varchar(40): the journal keeps the full
			// header, the order column must not raise a "Data too long" 500.
			'ip'                 => substr($this->clientIp(), 0, 40),
			'forwarded_ip'       => substr((string)($this->registry->get('request')->server['HTTP_X_FORWARDED_FOR'] ?? ''), 0, 40),
			'user_agent'         => substr((string)($this->registry->get('request')->server['HTTP_USER_AGENT'] ?? ''), 0, 255),
			'accept_language'    => substr((string)($this->registry->get('request')->server['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 255),
			'products'           => $products,
			'totals'             => $totals['totals'],
			'vouchers'           => (array)($this->session()->data['vouchers'] ?? []),
			'order_status_id'    => $order_status_id,
		];
	}

	/**
	 * Status a new order starts in: the store default, or the lowest configured
	 * status when none is set (an order with status 0 is invisible in the panel
	 * and in the customer's order list).
	 */
	private function initialStatusId(): int {
		$configured = (int)$this->config()->get('config_order_status_id');

		if ($configured > 0) {
			return $configured;
		}

		$query = $this->db()->query("SELECT MIN(`order_status_id`) AS first_id FROM `" . DB_PREFIX . "order_status`");

		$fallback = (int)($query->row['first_id'] ?? 0);

		if ($fallback <= 0) {
			throw new HttpException(Error::NOT_CONFIGURED);
		}

		return $fallback;
	}

	/**
	 * Order lines: the cart lines plus the zero-priced gift lines, with the
	 * warehouse each line ships from (the storefront does the same).
	 *
	 * @return array<int, array>
	 */
	private function orderProducts(array $shipping_method): array {
		$lines = $this->cart->lines();

		$allocation = $this->warehouseAllocation($lines, $shipping_method);

		$warehouses = [];

		$products = [];

		foreach ($lines as $line) {
			$price = (float)$line['price'];
			$tax_class_id = (int)$line['tax_class_id'];
			$key = $this->allocationKey($line);
			$warehouse_id = (int)($allocation[$key] ?? 0);

			$warehouses[$warehouse_id] = true;

			$products[] = [
				'product_id'   => (int)$line['product_id'],
				'variant_id'   => (int)($line['variant_id'] ?? 0),
				'variant_sku'  => (string)($line['variant_sku'] ?? ''),
				'name'         => (string)$line['name'],
				'model'        => (string)$line['model'],
				'quantity'     => (float)$line['quantity'],
				'price'        => $price,
				'total'        => $price * (float)$line['quantity'],
				'tax'          => (float)$this->registry->get('tax')->getTax($price, $tax_class_id),
				'reward'       => (int)($line['reward'] ?? 0),
				'option'       => (array)($line['option'] ?? []),
				'warehouse_id' => $warehouse_id,
				'warehouse_name' => $this->warehouseName($warehouse_id),
				'estimate_date'  => (string)($allocation['estimate'][$key] ?? ''),
			];
		}

		foreach ($this->giftLines($lines) as $gift) {
			$products[] = $gift;
		}

		return $products;
	}

	/**
	 * Zero-priced gift lines (Product Gifts) for lines that reached their
	 * minimum quantity.
	 *
	 * @return array<int, array>
	 */
	private function giftLines(array $lines): array {
		$product_ids = [];

		foreach ($lines as $line) {
			$product_ids[(int)$line['product_id']] = true;
		}

		if (!$product_ids) {
			return [];
		}

		$db = $this->db();

		$query = $db->query("SELECT g.*, pd.`name` FROM `" . DB_PREFIX . "product_gift` g LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (g.`gift_product_id` = pd.`product_id` AND pd.`language_id` = '" . (int)$this->config()->get('config_language_id') . "') WHERE g.`product_id` IN (" . implode(',', array_keys($product_ids)) . ") AND (g.`date_start` = '0000-00-00' OR g.`date_start` <= NOW()) AND (g.`date_end` = '0000-00-00' OR g.`date_end` >= NOW()) ORDER BY g.`minimum_quantity` ASC");

		if (!$query->rows) {
			return [];
		}

		$pc = new \ProductConfigurable($this->registry);

		$gifts = [];

		// The storefront prefixes gift lines so a picker sees them as gifts.
		$gift_label = CatalogLanguage::get($this->registry, 'product/product', 'text_gift');

		foreach ($lines as $line) {
			foreach ($query->rows as $gift) {
				if ((int)$gift['product_id'] !== (int)$line['product_id']) {
					continue;
				}

				if ((float)$line['quantity'] < (int)$gift['minimum_quantity']) {
					continue;
				}

				$gift_product_id = (int)$gift['gift_product_id'];
				$variant = $pc->getGiftVariantData($gift_product_id);

				// A configurable gift without a usable default variant is skipped,
				// exactly like the storefront does.
				if ($pc->isConfigurable($gift_product_id) && empty($variant)) {
					continue;
				}

				$gift_name = (string)($gift['name'] ?? '');

				if (!empty($variant['label'])) {
					$gift_name .= ' (' . (string)$variant['label'] . ')';
				}

				$gifts[] = [
					'product_id'   => $gift_product_id,
					'variant_id'   => (int)($variant['variant_id'] ?? 0),
					'variant_sku'  => (string)($variant['variant_sku'] ?? ''),
					'name'         => $gift_label !== '' ? $gift_label . ': ' . $gift_name : $gift_name,
					'model'        => (string)($variant['model'] ?? ''),
					'quantity'     => 1.0,
					'price'        => 0.0,
					'total'        => 0.0,
					'tax'          => 0.0,
					'reward'       => 0,
					'option'       => (array)($variant['option'] ?? []),
					'warehouse_id' => 0,
					'warehouse_name' => '',
					'estimate_date'  => '',
				];
			}
		}

		return $gifts;
	}

	/* ─────────────────────────────── stock holds ─────────────────────────────── */

	/**
	 * Reserves (or refreshes) the cart's stock hold for this checkout session.
	 *
	 * @return bool false when a line cannot be held
	 */
	private function refreshHolds(array $row): bool {
		$reservation = new \DockercartStockReservation($this->registry);

		if (!$reservation->isEnabled()) {
			return true;
		}

		$shipping_method = (string)($row['shipping_code'] ?? '') !== '' ? ['code' => (string)$row['shipping_code']] : [];

		$allocation = $this->warehouseAllocation($this->cart->lines(), $shipping_method);

		$hold_lines = [];

		foreach ($this->cart->lines() as $line) {
			$hold_lines[] = [
				'product_id'   => (int)$line['product_id'],
				'variant_id'   => (int)($line['variant_id'] ?? 0),
				'quantity'     => (float)$line['quantity'],
				'warehouse_id' => (int)($allocation[$this->allocationKey($line)] ?? 0),
			];
		}

		$ttl = $reservation->getDefaultTtlMinutes();

		$method_ttl = $reservation->getMethodTtlMinutes((string)($row['payment_code'] ?? '') ?: null);

		if ($method_ttl !== null) {
			$ttl = $method_ttl;
		}

		if ($ttl <= 0) {
			$reservation->releaseSession($this->sessionId());

			return true;
		}

		$failed = $reservation->reserve($hold_lines, $ttl, $this->sessionId());

		return $failed === [];
	}

	/**
	 * Releases the session's unbound holds (checkout cancellation).
	 */
	public function releaseHolds(): void {
		$reservation = new \DockercartStockReservation($this->registry);

		if ($reservation->isEnabled()) {
			$reservation->releaseSession($this->sessionId());
		}
	}

	/* ─────────────────────────────── warehouse ─────────────────────────────── */

	/**
	 * Warehouse each cart line ships from, keyed by cart key, plus estimates.
	 *
	 * @return array<string, mixed>
	 */
	private function warehouseAllocation(array $lines, array $shipping_method): array {
		$warehouse = new \DockercartWarehouse($this->registry);

		$pickup_warehouse_id = 0;
		$shipping_code = (string)($shipping_method['code'] ?? '');

		if (strpos($shipping_code, 'pickup_') !== false) {
			$parts = explode('.', $shipping_code);
			$pickup_warehouse_id = (int)substr((string)end($parts), strlen('pickup_'));
		}

		$specs = [];

		foreach ($lines as $index => $line) {
			$specs[$index] = [
				'key'          => $this->allocationKey($line),
				'product_id'   => (int)$line['product_id'],
				'variant_id'   => (int)($line['variant_id'] ?? 0),
				'quantity'     => (float)$line['quantity'],
				'subtract'     => !empty($line['subtract']),
				'warehouse_id' => $pickup_warehouse_id,
			];
		}

		$result = $warehouse->allocate(array_values($specs));

		$allocation = [];

		foreach ($specs as $index => $spec) {
			$warehouse_id = $pickup_warehouse_id > 0
				? $pickup_warehouse_id
				: (int)($result['alloc'][$index] ?? $warehouse->getDefaultWarehouseId());

			$allocation[$spec['key']] = $warehouse_id;

			$estimate = $result['estimate'][$index] ?? $warehouse->estimateShipDate($warehouse_id, $spec);

			$allocation['estimate'][$spec['key']] = (string)$estimate;
		}

		return $allocation;
	}

	private function allocationKey(array $line): string {
		return (string)($line['cart_id'] ?? ($line['product_id'] . ':' . (int)($line['variant_id'] ?? 0)));
	}

	private function warehouseName(int $warehouse_id): string {
		if ($warehouse_id <= 0) {
			return '';
		}

		$warehouse = new \DockercartWarehouse($this->registry);
		$info = $warehouse->getWarehouse($warehouse_id);

		return $info ? (string)$info['name'] : '';
	}

	/* ─────────────────────────────── session row ─────────────────────────────── */

	/**
	 * Loads a checkout session of the authenticated customer (404 otherwise).
	 */
	public function find(int $checkout_id): array {
		$query = $this->db()->query("SELECT * FROM `" . DB_PREFIX . "api_checkout` WHERE `api_checkout_id` = '" . (int)$checkout_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");

		if (!$query->num_rows) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $query->row;
	}

	/**
	 * The customer's newest open checkout (used by the controllers to answer
	 * "where is my checkout" without an id).
	 */
	public function latest(): ?array {
		$query = $this->db()->query("SELECT * FROM `" . DB_PREFIX . "api_checkout` WHERE `customer_id` = '" . (int)$this->customer_id . "' AND `status` = '" . self::STATUS_OPEN . "' ORDER BY `api_checkout_id` DESC LIMIT 1");

		return $query->num_rows ? $query->row : null;
	}

	private function insert(array $row): int {
		$db = $this->db();

		// A customer has one open checkout: opening a new one replaces the
		// previous session (its stock holds are refreshed by refreshHolds(), not
		// accumulated). Completed rows are history (they carry the payment-init
		// result) and are pruned by age instead of by the next open().
		$db->query("DELETE FROM `" . DB_PREFIX . "api_checkout` WHERE `customer_id` = '" . (int)$this->customer_id . "' AND `status` = '" . self::STATUS_OPEN . "'");

		$db->query("DELETE FROM `" . DB_PREFIX . "api_checkout` WHERE `customer_id` = '" . (int)$this->customer_id . "' AND `status` = '" . self::STATUS_COMPLETED . "' AND `date_modified` < DATE_SUB(NOW(), INTERVAL 7 DAY)");

		$db->query("INSERT INTO `" . DB_PREFIX . "api_checkout` SET `customer_id` = '" . (int)$row['customer_id'] . "', `api_key_id` = '" . (int)$row['api_key_id'] . "', `store_id` = '" . (int)$row['store_id'] . "', `language_id` = '" . (int)$row['language_id'] . "', `currency_code` = '" . $db->escape((string)$row['currency_code']) . "', `email` = '" . $db->escape((string)$row['email']) . "', `telephone` = '" . $db->escape((string)$row['telephone']) . "', `firstname` = '" . $db->escape((string)$row['firstname']) . "', `lastname` = '" . $db->escape((string)$row['lastname']) . "', `tax_number` = '" . $db->escape((string)$row['tax_number']) . "', `payment_address` = " . $this->sqlNullable($row['payment_address']) . ", `shipping_address` = " . $this->sqlNullable($row['shipping_address']) . ", `shipping_code` = '', `payment_code` = '', `comment` = '" . $db->escape((string)$row['comment']) . "', `agree` = '" . (int)$row['agree'] . "', `status` = '" . self::STATUS_OPEN . "', `date_added` = NOW(), `date_modified` = NOW()");

		return (int)$db->getLastId();
	}

	private function persist(array $row): void {
		$db = $this->db();

		$db->query("UPDATE `" . DB_PREFIX . "api_checkout` SET `email` = '" . $db->escape((string)($row['email'] ?? '')) . "', `telephone` = '" . $db->escape((string)($row['telephone'] ?? '')) . "', `firstname` = '" . $db->escape((string)($row['firstname'] ?? '')) . "', `lastname` = '" . $db->escape((string)($row['lastname'] ?? '')) . "', `tax_number` = '" . $db->escape((string)($row['tax_number'] ?? '')) . "', `payment_address` = " . $this->sqlNullable($row['payment_address'] ?? null) . ", `shipping_address` = " . $this->sqlNullable($row['shipping_address'] ?? null) . ", `shipping_code` = '" . $db->escape((string)($row['shipping_code'] ?? '')) . "', `payment_code` = '" . $db->escape((string)($row['payment_code'] ?? '')) . "', `comment` = '" . $db->escape((string)($row['comment'] ?? '')) . "', `agree` = '" . (int)($row['agree'] ?? 0) . "', `date_modified` = NOW() WHERE `api_checkout_id` = '" . (int)$row['api_checkout_id'] . "' AND `customer_id` = '" . (int)$this->customer_id . "'");
	}

	/**
	 * After a successful order: clear the cart, drop the double-submit claim (the
	 * success page of the storefront does the same) and mark the row completed so
	 * the payment can be started by the follow-up request.
	 */
	private function finish(int $order_id, int $checkout_id, int $order_status_id): void {
		$this->cart->clear();

		$db = $this->db();

		$db->query("UPDATE `" . DB_PREFIX . "api_checkout` SET `status` = '" . self::STATUS_COMPLETED . "', `order_id` = '" . (int)$order_id . "', `order_status_id` = '" . (int)$order_status_id . "', `date_modified` = NOW() WHERE `api_checkout_id` = '" . (int)$checkout_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");

		$session_id = $this->sessionId();

		if ($session_id !== '') {
			$db->query("DELETE FROM `" . DB_PREFIX . "order_claim` WHERE `session_id` = '" . $db->escape($session_id) . "'");
		}
	}

	/**
	 * Row state: `open` while the checkout is under way, `completed` after the
	 * order write. A row written before the status column existed counts as open.
	 */
	public function status(array $row): string {
		$status = (string)($row['status'] ?? '');

		return $status !== '' ? $status : self::STATUS_OPEN;
	}

	/**
	 * A session that already placed its order can only be read, paid and
	 * cancelled — it can never be changed or confirmed again.
	 */
	private function requireOpen(array $row): array {
		if ($this->status($row) !== self::STATUS_OPEN) {
			throw new HttpException(Error::CHECKOUT_COMPLETED);
		}

		return $row;
	}

	/* ─────────────────────────────── helpers ─────────────────────────────── */

	/**
	 * Puts the checkout's choices where the storefront total extensions read
	 * them from. Nothing is persisted: the API recomputes the quotes on every
	 * request, so the session copy is a per-request view.
	 */
	private function hydrateSession(array $row): void {
		$session = $this->session();

		unset($session->data['shipping_method'], $session->data['payment_method']);

		$payment_address = $this->decode($row['payment_address'] ?? null);
		$shipping_address = $this->decode($row['shipping_address'] ?? null) ?: $payment_address;

		// Shipping/payment extensions read the checkout address from the session
		// in several third-party modules, so the snapshot is published there for
		// the duration of the request (the storefront checkout does the same).
		if ($payment_address) {
			$session->data['payment_address'] = $payment_address;
		}

		if ($shipping_address) {
			$session->data['shipping_address'] = $shipping_address;
		}

		$shipping_code = (string)($row['shipping_code'] ?? '');
		$payment_code = (string)($row['payment_code'] ?? '');

		if ($shipping_code !== '' && $shipping_address) {
			try {
				$session->data['shipping_method'] = $this->methods->shippingMethod($shipping_address, $shipping_code);
			} catch (HttpException $exception) {
				unset($session->data['shipping_method']);
			}
		}

		if ($payment_code !== '' && $payment_address) {
			try {
				$session->data['payment_method'] = $this->methods->paymentMethod($payment_address, (float)$this->cart()->getTotal(), $payment_code);
			} catch (HttpException $exception) {
				unset($session->data['payment_method']);
			}
		}
	}

	/**
	 * Validates the method codes present in the payload against the quotes that
	 * are available right now.
	 */
	private function validateMethods(array $row, array $input): array {
		$payment_address = $this->decode($row['payment_address'] ?? null);
		$shipping_address = $this->decode($row['shipping_address'] ?? null) ?: $payment_address;

		if (array_key_exists('shipping_code', $input)) {
			$code = trim((string)$input['shipping_code']);

			if ($code === '') {
				$row['shipping_code'] = '';
			} else {
				if (!$shipping_address) {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'shipping_address' => ['required before a shipping method can be selected'],
					]);
				}

				$this->methods->shippingMethod($shipping_address, $code);

				$row['shipping_code'] = $code;
			}

			unset($this->session()->data['shipping_method']);
		}

		if (array_key_exists('payment_code', $input)) {
			$code = trim((string)$input['payment_code']);

			if ($code === '') {
				$row['payment_code'] = '';
			} else {
				if (!$payment_address) {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'payment_address' => ['required before a payment method can be selected'],
					]);
				}

				$this->methods->paymentMethod($payment_address, (float)$this->cart()->getTotal(), $code);

				$row['payment_code'] = $code;
			}

			unset($this->session()->data['payment_method']);
		}

		return $row;
	}

	private function quotesFor(array $address): array {
		try {
			return $this->methods->shippingQuotes($address);
		} catch (HttpException $exception) {
			return [];
		}
	}

	private function methodsFor(array $address): array {
		try {
			return $this->methods->paymentMethods($address, (float)$this->cart()->getTotal());
		} catch (HttpException $exception) {
			return [];
		}
	}

	private function publicQuote(array $quote): array {
		unset($quote['quote']);

		if (isset($quote['cost']) && $quote['cost'] !== null) {
			$quote['cost'] = Serializer::money($quote['cost']);
		}

		return $quote;
	}

	private function publicMethod(array $method): array {
		unset($method['method']);

		return $method;
	}

	private function findQuote(array $quotes, string $code): ?array {
		if ($code === '') {
			return null;
		}

		foreach ($quotes as $quote) {
			if ((string)$quote['code'] === $code) {
				return $quote;
			}
		}

		return null;
	}

	private function findMethod(array $methods, string $code): ?array {
		if ($code === '') {
			return null;
		}

		foreach ($methods as $method) {
			if ((string)$method['code'] === $code) {
				return $method;
			}
		}

		return null;
	}

	/**
	 * An address payload is an object; a bare id is accepted as a shortcut.
	 */
	private function addressInput($value): array {
		if (is_int($value) || (is_string($value) && ctype_digit($value))) {
			return ['address_id' => (int)$value];
		}

		if (!is_array($value)) {
			throw new HttpException(Error::VALIDATION_FAILED, ['address' => ['must be an object or an address id']]);
		}

		return $value;
	}

	private function decode($value): ?array {
		if (!is_string($value) || trim($value) === '') {
			return null;
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : null;
	}

	private function pickEmail(array $input, array $customer): string {
		$email = trim((string)($input['email'] ?? ''));

		if ($email === '') {
			$email = (string)($customer['email'] ?? '');
		}

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new HttpException(Error::VALIDATION_FAILED, ['email' => ['must be a valid email address']]);
		}

		return $email;
	}

	private function email($value): string {
		if ($value === null) {
			return '';
		}

		if (!is_scalar($value)) {
			throw new HttpException(Error::VALIDATION_FAILED, ['email' => ['must be a string']]);
		}

		// An address is validated, stored and used for sending mail verbatim, so it
		// is the one field that is never HTML-escaped (a valid address cannot break
		// out of an attribute anyway: filter_var rejects quotes).
		$email = trim((string)$value);

		if (strlen($email) > 96) {
			throw new HttpException(Error::VALIDATION_FAILED, ['email' => ['must not be longer than 96 characters']]);
		}

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new HttpException(Error::VALIDATION_FAILED, ['email' => ['must be a valid email address']]);
		}

		return $email;
	}

	private function pickTelephone(array $input, array $customer): string {
		$telephone = $this->text($input['telephone'] ?? null, 'telephone', 32);

		return $telephone !== '' ? $telephone : (string)($customer['telephone'] ?? '');
	}

	/**
	 * A short free-text checkout field (firstname, lastname, telephone, tax number).
	 *
	 * The value is escaped like Request::clean() does on the storefront and then
	 * bounded, exactly like the panel form (escape first, validate the escaped
	 * length) — the panel theme renders these fields unescaped, so raw markup must
	 * never reach oc_order through this path.
	 */
	private function text($value, string $field, int $max): string {
		if ($value === null) {
			return '';
		}

		if (!is_scalar($value)) {
			throw new HttpException(Error::VALIDATION_FAILED, [$field => ['must be a string']]);
		}

		return Serializer::encodeTextWithin($field, trim((string)$value), $max);
	}

	private function comment($value): string {
		if ($value === null) {
			return '';
		}

		if (!is_scalar($value)) {
			throw new HttpException(Error::VALIDATION_FAILED, ['comment' => ['must be a string']]);
		}

		return Serializer::encodeTextWithinBytes('comment', trim(strip_tags((string)$value)), 65535);
	}

	private function sqlNullable($value): string {
		if ($value === null || $value === '') {
			return 'NULL';
		}

		return "'" . $this->db()->escape((string)$value) . "'";
	}

	private function customerRow(): array {
		$query = $this->db()->query("SELECT `customer_id`, `customer_group_id`, `firstname`, `lastname`, `email`, `telephone`, `tax_number` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$this->customer_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Client IP behind the front proxy, resolved exactly like the API startup does
	 * it: nginx overwrites X-Real-IP with the real remote address, X-Forwarded-For
	 * is only a fallback (its last entry is the one our own proxy appended). Without
	 * this the order would record the nginx container's address.
	 */
	private function clientIp(): string {
		$server = (array)$this->registry->get('request')->server;

		$real_ip = trim((string)($server['HTTP_X_REAL_IP'] ?? ''));

		if ($real_ip !== '' && filter_var($real_ip, FILTER_VALIDATE_IP)) {
			return $real_ip;
		}

		$forwarded = (string)($server['HTTP_X_FORWARDED_FOR'] ?? '');

		if ($forwarded !== '') {
			$parts = array_map('trim', explode(',', $forwarded));

			for ($index = count($parts) - 1; $index >= 0; $index--) {
				if (filter_var($parts[$index], FILTER_VALIDATE_IP)) {
					return (string)$parts[$index];
				}
			}
		}

		return (string)($server['REMOTE_ADDR'] ?? '');
	}

	private function apiKeyId(): int {
		$context = $this->registry->get('api_context');

		if ($context && $context->key) {
			return (int)($context->key['api_key_id'] ?? 0);
		}

		return 0;
	}

	private function sessionId(): string {
		return (string)$this->session()->getId();
	}

	private function cart(): \Cart\Cart {
		return $this->registry->get('cart');
	}

	private function session() {
		return $this->registry->get('session');
	}

	private function config() {
		return $this->registry->get('config');
	}

	private function db() {
		return $this->registry->get('db');
	}
}
