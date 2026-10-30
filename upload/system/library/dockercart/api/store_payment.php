<?php
/**
 * DockerCart customer (storefront) REST API — payment initialisation.
 *
 * `POST /v1/checkout/{id}/confirm` creates the order; this class starts the
 * *payment* of the method the buyer chose, by running the payment extension's
 * own execution step (upload/catalog/controller/extension/payment/<code>.php
 * `confirm()`, with `index()` as the fallback that renders instructions or an
 * inline form).
 *
 * Nothing here knows a gateway. The module is the only thing that does, and it
 * answers with one of four shapes, classified into `kind`:
 *
 *   external  a URL on another host — open it in a browser/WebView (Stripe,
 *             PayPal, LiqPay, Portmone, …),
 *   internal  a URL on this store only, i.e. nothing to pay online (the built-in
 *             method sends the buyer to the success page),
 *   html      markup to render (an inline form, bank details, …),
 *   none      nothing to do; `instructions` carries the method description
 *             (cash on delivery, bank transfer, …).
 *
 * Guards:
 *
 *   - only a `completed` checkout of the authenticated customer, only its own
 *     order (StoreOrder::require() scopes by customer, 404 otherwise);
 *   - the method must still be enabled in this store;
 *   - the answer is stored (`oc_api_checkout.payment_init`) and replayed
 *     verbatim, so a retried request can never open a second gateway session;
 *   - `refresh=1` re-runs the module only while the order status is exactly what
 *     the previous init left behind — once a gateway or an operator moved the
 *     order on, init fails with `409 payment_already_started` instead of
 *     charging twice.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StorePayment {
	/**
	 * Largest HTML answer kept in the stored result (an oversized view is
	 * returned once and replayed without it rather than bloating the row).
	 */
	private const MAX_HTML = 262144;

	private \Registry $registry;

	private int $customer_id;

	private StoreCheckout $checkout;

	private StoreMethods $methods;

	private StoreOrder $orders;

	public function __construct(\Registry $registry, int $customer_id) {
		$this->registry = $registry;
		$this->customer_id = $customer_id;
		$this->checkout = new StoreCheckout($registry, $customer_id);
		$this->methods = new StoreMethods($registry);
		$this->orders = new StoreOrder($registry, $customer_id);
	}

	/**
	 * Starts — or replays — the payment of a confirmed checkout.
	 *
	 * @param array $body the request payload; it is also what the module sees as
	 *                    POSTed form fields ($this->request->post)
	 */
	public function start(int $checkout_id, array $body = [], bool $refresh = false): array {
		$row = $this->checkout->find($checkout_id);

		if ($this->checkout->status($row) !== StoreCheckout::STATUS_COMPLETED) {
			throw new HttpException(Error::CHECKOUT_NOT_CONFIRMED);
		}

		$order = $this->orders->require((int)($row['order_id'] ?? 0));

		$module = $this->module((string)($order['payment_code'] ?? ''));

		$stored = $this->decode($row['payment_init'] ?? null);

		if ($stored !== null && !$refresh) {
			return self::publicPayload($stored);
		}

		$status = (int)$order['order_status_id'];

		if ($stored !== null) {
			// A refresh may only re-run the module while the previous init is the
			// last thing that touched the order (an expired gateway session).
			if ($status !== (int)($stored['_status_after'] ?? -1)) {
				throw new HttpException(Error::PAYMENT_ALREADY_STARTED);
			}
		} elseif ($status !== (int)($row['order_status_id'] ?? 0)) {
			throw new HttpException(Error::PAYMENT_ALREADY_STARTED);
		}

		$instructions = $this->instructions($order);

		$result = $this->invoke($order, $module, $body);

		$kind = self::kind($result['redirect'], $result['html'], (string)($this->registry->get('config')->get('config_url')));

		$now = date('Y-m-d H:i:s');

		$payload = [
			'order_id'      => (int)$order['order_id'],
			'payment_code'  => (string)($order['payment_code'] ?? ''),
			'module'        => $module,
			'kind'          => $kind,
			'redirect_url'  => $result['redirect'] !== '' ? $result['redirect'] : null,
			'html'          => $kind === 'html' ? $result['html'] : null,
			'instructions'  => $instructions,
			'gateway'       => $this->gatewayExtras($result['json']),
			'created_at'    => $stored !== null ? (string)($stored['created_at'] ?? Serializer::datetime($now)) : Serializer::datetime($now),
			'refreshed_at'  => $stored !== null ? Serializer::datetime($now) : null,
			// Internal: the status the init left the order in, used by the refresh
			// guard above. Stripped from every response.
			'_status_after' => $this->orderStatus((int)$order['order_id']),
		];

		$this->persist((int)$row['api_checkout_id'], $payload, $now);

		return self::publicPayload($payload);
	}

	/**
	 * Answer kind from what the module produced.
	 *
	 * @param string $store_url the store's own base URL, to tell "go pay" apart
	 *                          from "your order is done, here is the receipt"
	 */
	public static function kind(string $redirect, string $html, string $store_url): string {
		if (trim($redirect) !== '') {
			$host = strtolower((string)parse_url($redirect, PHP_URL_HOST));
			$store_host = strtolower((string)parse_url($store_url, PHP_URL_HOST));

			if ($host === '' || ($store_host !== '' && $host === $store_host)) {
				return 'internal';
			}

			return 'external';
		}

		return trim($html) !== '' ? 'html' : 'none';
	}

	/**
	 * What a client sees: the stored payload without the internal keys.
	 */
	public static function publicPayload(array $payload): array {
		$public = [];

		foreach ($payload as $key => $value) {
			if (is_string($key) && strpos($key, '_') === 0) {
				continue;
			}

			$public[$key] = $value;
		}

		// JSON: an empty object rather than an empty array.
		if (isset($public['gateway']) && $public['gateway'] === []) {
			$public['gateway'] = new \stdClass();
		}

		return $public;
	}

	/**
	 * Runs the module: `confirm()`, falling back to `index()` when the module
	 * has no execution step at all (a passive method renders its instructions).
	 *
	 * @return array{redirect: string, html: string, json: array|null}
	 */
	private function invoke(array $order, string $module, array $body): array {
		$file = DIR_CATALOG . 'controller/extension/payment/' . $module . '.php';

		// A model-only payment method (no controller) has nothing to start.
		if (!is_file($file)) {
			return ['redirect' => '', 'html' => '', 'json' => null];
		}

		$payment_address = $this->address($order, 'payment');
		$shipping_address = $this->address($order, 'shipping') ?: $payment_address;

		$session = [
			'order_id'         => (int)$order['order_id'],
			'payment_method'   => $this->sessionPaymentMethod($order, $payment_address),
			'shipping_method'  => $this->sessionShippingMethod($order, $shipping_address),
			'payment_address'  => $payment_address,
			'shipping_address' => $shipping_address,
			'currency'         => (string)($order['currency_code'] ?? ''),
			'language'         => CatalogLanguage::code($this->registry),
		];

		$result = $this->run($module, 'confirm', $session, $body);

		if ($result['redirect'] === '' && trim($result['output']) === '') {
			$result = $this->run($module, 'index', $session, $body);
		}

		return [
			'redirect' => $result['redirect'],
			'html'     => $result['json'] === null ? $this->truncateHtml($result['output']) : '',
			'json'     => $result['json'],
		];
	}

	/**
	 * @return array{output: string, redirect: string, json: array|null}
	 */
	private function run(string $module, string $action, array $session, array $body): array {
		try {
			return CatalogControllers::run($this->registry, 'extension/payment/' . $module, $action, [
				'session' => $session,
				'post'    => $body,
			]);
		} catch (\Throwable $exception) {
			$this->log('payment init ' . $module . '/' . $action . ' failed: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

			throw new HttpException(Error::PAYMENT_GATEWAY_ERROR);
		}
	}

	/**
	 * The payment method in the shape the storefront keeps in the session.
	 *
	 * Re-quoted for the order total; a method that refuses to quote once the cart
	 * is cleared (some modules check the cart) falls back to the order snapshot.
	 */
	private function sessionPaymentMethod(array $order, array $address): array {
		$code = (string)($order['payment_code'] ?? '');

		try {
			return $this->methods->paymentMethod($address, (float)$order['total'], $code);
		} catch (HttpException $exception) {
			return [
				'code'  => $code,
				'title' => (string)($order['payment_method'] ?? ''),
				'terms' => '',
			];
		}
	}

	/**
	 * The shipping method in the session shape, with the same fallback.
	 */
	private function sessionShippingMethod(array $order, array $address): array {
		$code = (string)($order['shipping_code'] ?? '');

		if ($code === '' || !$address) {
			return [];
		}

		try {
			return $this->methods->shippingMethod($address, $code);
		} catch (HttpException $exception) {
			return [
				'code'  => $code,
				'title' => (string)($order['shipping_method'] ?? ''),
				'cost'  => 0.0,
				'text'  => '',
			];
		}
	}

	/**
	 * Human-readable text of the method, for `kind: none`.
	 */
	private function instructions(array $order): string {
		$title = trim((string)($order['payment_method'] ?? ''));

		$terms = '';

		try {
			$method = $this->sessionPaymentMethod($order, $this->address($order, 'payment'));

			$title = trim((string)($method['title'] ?? $title));
			$terms = trim(html_entity_decode(strip_tags((string)($method['terms'] ?? ($method['description'] ?? ''))), ENT_QUOTES, 'UTF-8'));
		} catch (\Throwable $exception) {
			// The snapshot title is enough.
		}

		$text = $title;

		if ($terms !== '') {
			$text .= ($text !== '' ? "\n\n" : '') . $terms;
		}

		return $text;
	}

	/**
	 * Address snapshot of the order in the session shape.
	 */
	private function address(array $order, string $type): array {
		$address = [
			'firstname'      => (string)($order[$type . '_firstname'] ?? ''),
			'lastname'       => (string)($order[$type . '_lastname'] ?? ''),
			'company'        => (string)($order[$type . '_company'] ?? ''),
			'address_1'      => (string)($order[$type . '_address_1'] ?? ''),
			'address_2'      => (string)($order[$type . '_address_2'] ?? ''),
			'city'           => (string)($order[$type . '_city'] ?? ''),
			'postcode'       => (string)($order[$type . '_postcode'] ?? ''),
			'country'        => (string)($order[$type . '_country'] ?? ''),
			'country_id'     => (int)($order[$type . '_country_id'] ?? 0),
			'zone'           => (string)($order[$type . '_zone'] ?? ''),
			'zone_id'        => (int)($order[$type . '_zone_id'] ?? 0),
			'address_format' => (string)($order[$type . '_address_format'] ?? ''),
			'custom_field'   => $this->decode($order[$type . '_custom_field'] ?? '') ?? [],
		];

		if ($address['country_id'] <= 0 || $address['zone_id'] <= 0) {
			return $address['city'] !== '' || $address['address_1'] !== '' ? $address : [];
		}

		return $address;
	}

	/**
	 * Validated payment module code ("stripe.stripe" => "stripe").
	 */
	private function module(string $code): string {
		$module = strtolower((string)strtok($code, '.'));

		if ($module === '' || !preg_match('/^[a-z0-9_]+$/', $module)) {
			throw new HttpException(Error::PAYMENT_UNAVAILABLE);
		}

		$db = $this->registry->get('db');

		$query = $db->query("SELECT `code` FROM `" . DB_PREFIX . "extension` WHERE `type` = 'payment' AND `code` = '" . $db->escape($module) . "'");

		if (!$query->num_rows || !$this->registry->get('config')->get('payment_' . $module . '_status')) {
			throw new HttpException(Error::PAYMENT_UNAVAILABLE);
		}

		return $module;
	}

	/**
	 * Status the order is in right now (after the module ran).
	 */
	private function orderStatus(int $order_id): int {
		$db = $this->registry->get('db');

		$query = $db->query("SELECT `order_status_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . (int)$order_id . "'");

		return $query->num_rows ? (int)$query->row['order_status_id'] : 0;
	}

	private function persist(int $checkout_id, array $payload, string $now): void {
		$db = $this->registry->get('db');

		$db->query("UPDATE `" . DB_PREFIX . "api_checkout` SET `payment_init` = '" . $db->escape((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "', `payment_init_at` = '" . $db->escape($now) . "', `date_modified` = NOW() WHERE `api_checkout_id` = '" . (int)$checkout_id . "' AND `customer_id` = '" . (int)$this->customer_id . "'");
	}

	/**
	 * Extra scalar fields the module returned (a bespoke gateway's own keys).
	 */
	private function gatewayExtras(?array $json): array {
		if (!$json) {
			return [];
		}

		unset($json['redirect'], $json['error']);

		$extras = [];

		foreach ($json as $key => $value) {
			if (!is_string($key) || $key === '' || strpos($key, '_') === 0) {
				continue;
			}

			if (is_scalar($value) || $value === null) {
				$extras[$key] = $value;
			}
		}

		return $extras;
	}

	private function truncateHtml(string $html): string {
		$html = trim($html);

		if ($html === '') {
			return '';
		}

		return strlen($html) > self::MAX_HTML ? substr($html, 0, self::MAX_HTML) : $html;
	}

	private function decode($value): ?array {
		if (!is_string($value) || trim($value) === '') {
			return null;
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : null;
	}

	private function log(string $message): void {
		$log = $this->registry->has('log') ? $this->registry->get('log') : null;

		if ($log) {
			$log->write('STORE API ' . $message);
		}
	}
}
