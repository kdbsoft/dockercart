<?php
/**
 * DockerCart admin REST API — orders.
 *
 *   GET    /v1/orders                list (filters + pagination)
 *   GET    /v1/orders/{id}           single order with lines, totals, history, payments
 *   PATCH  /v1/orders/{id}           tracking / comment / contact / addresses / methods
 *   GET    /v1/orders/{id}/history   status history
 *   POST   /v1/orders/{id}/history   append a status (order flow, e-mails, stock)
 *
 * Write operations reuse the panel's own JSON actions — sale/order/addHistory and
 * sale/order/quickEdit — through $this->load->controller(), so order flow
 * transitions, stock movements, affiliate commission and notifications behave
 * exactly like an operator clicking in the panel.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiSaleOrders extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('sale/order');

		$pagination = $this->pagination();

		$filters = [
			'filter_order_id'      => $this->queryString('order_id'),
			'filter_customer'      => $this->queryString('customer'),
			'filter_payment_method' => $this->queryString('payment_method'),
			'filter_shipping_method' => $this->queryString('shipping_method'),
			'filter_date_added'    => $this->queryString('date_added'),
			'filter_date_preset'   => $this->queryString('date_preset'),
			'filter_total_min'     => $this->queryString('total_min'),
			'filter_total_max'     => $this->queryString('total_max'),
			'filter_payment_status' => $this->queryString('payment_status'),
			'filter_shipping_status' => $this->queryString('shipping_status'),
			'start'                => $pagination['start'],
			'limit'                => $pagination['limit'],
		];

		$status = $this->queryString('status');

		if ($status !== '') {
			$filters['filter_order_status'] = $status;
		} elseif (($status_id = $this->queryOptionalInt('status_id')) !== null) {
			$filters['filter_order_status_id'] = $status_id;
		}

		$sorting = $this->sorting(
			[
				'order_id'     => 'o.order_id',
				'customer'     => 'customer',
				'order_status' => 'order_status',
				'date_added'   => 'o.date_added',
				'date_modified' => 'o.date_modified',
				'total'        => 'o.total',
			],
			'o.order_id',
			'DESC'
		);

		$filters['sort'] = $sorting['sort'];
		$filters['order'] = $sorting['order'];

		$rows = [];

		foreach ($this->model_sale_order->getOrders($filters) as $order) {
			$rows[] = $this->orderSummary($order);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_sale_order->getTotalOrders($filters)));
	}

	public function get() {
		$this->respondResource($this->orderResponse($this->param('order_id')));
	}

	private function orderResponse(int $order_id): array {
		$order = $this->requireOrder($order_id);

		$statuses = $this->statusNames();

		$products = [];

		foreach ($this->model_sale_order->getOrderProducts($order_id) as $product) {
			$options = [];

			foreach ($this->model_sale_order->getOrderOptions($order_id, (int)$product['order_product_id']) as $option) {
				$options[] = [
					'order_option_id' => (int)$option['order_option_id'],
					'name'            => (string)$option['name'],
					'value'           => (string)$option['value'],
					'type'            => (string)$option['type'],
				];
			}

			$products[] = [
				'order_product_id' => (int)$product['order_product_id'],
				'product_id'       => (int)$product['product_id'],
				'name'             => Serializer::decodeText($product['name'] ?? ''),
				'model'            => (string)($product['model'] ?? ''),
				'quantity'         => (float)$product['quantity'],
				'price'            => Serializer::money($product['price']),
				'total'            => Serializer::money($product['total']),
				'tax'              => Serializer::money($product['tax'] ?? 0),
				'reward'           => (int)($product['reward'] ?? 0),
				'options'          => $options,
			];
		}

		$totals = [];

		foreach ($this->model_sale_order->getOrderTotals($order_id) as $total) {
			$totals[] = [
				'code'       => (string)$total['code'],
				'title'      => (string)$total['title'],
				'value'      => Serializer::money($total['value']),
				'sort_order' => (int)$total['sort_order'],
			];
		}

		$payments = [];

		foreach ($this->model_sale_order->getOrderPayments($order_id) as $payment) {
			$payments[] = [
				'order_payment_id' => (int)$payment['order_payment_id'],
				'amount'           => Serializer::money($payment['amount']),
				'reference'        => (string)($payment['reference'] ?? ''),
				'comment'          => (string)($payment['comment'] ?? ''),
				'payment_method'   => (string)($payment['payment_method'] ?? ''),
				'payment_code'     => (string)($payment['payment_code'] ?? ''),
				'date_added'       => Serializer::datetime($payment['date_added'] ?? ''),
			];
		}

		$response = $this->orderSummary($order);

		$response = array_merge($response, [
			'store_id'         => (int)$order['store_id'],
			'store_name'       => (string)($order['store_name'] ?? ''),
			'customer_id'      => (int)$order['customer_id'],
			'firstname'        => Serializer::decodeText($order['firstname'] ?? ''),
			'lastname'         => Serializer::decodeText($order['lastname'] ?? ''),
			'email'            => (string)($order['email'] ?? ''),
			'telephone'        => Serializer::decodeText($order['telephone'] ?? ''),
			'tax_number'       => Serializer::decodeText($order['tax_number'] ?? ''),
			'comment'          => Serializer::decodeText($order['comment'] ?? ''),
			'currency_code'    => (string)$order['currency_code'],
			'currency_value'   => (float)$order['currency_value'],
			'paid_amount'      => Serializer::money($order['paid_amount'] ?? 0),
			'payment_method'   => (string)($order['payment_method'] ?? ''),
			'payment_code'     => (string)($order['payment_code'] ?? ''),
			'shipping_method'  => (string)($order['shipping_method'] ?? ''),
			'shipping_code'    => (string)($order['shipping_code'] ?? ''),
			'payment_address'  => [
				'firstname'  => Serializer::decodeText($order['payment_firstname'] ?? ''),
				'lastname'   => Serializer::decodeText($order['payment_lastname'] ?? ''),
				'company'    => Serializer::decodeText($order['payment_company'] ?? ''),
				'address_1'  => Serializer::decodeText($order['payment_address_1'] ?? ''),
				'address_2'  => Serializer::decodeText($order['payment_address_2'] ?? ''),
				'city'       => Serializer::decodeText($order['payment_city'] ?? ''),
				'postcode'   => Serializer::decodeText($order['payment_postcode'] ?? ''),
				'country'    => (string)($order['payment_country'] ?? ''),
				'country_id' => (int)($order['payment_country_id'] ?? 0),
				'zone'       => (string)($order['payment_zone'] ?? ''),
				'zone_id'    => (int)($order['payment_zone_id'] ?? 0),
			],
			'shipping_address' => [
				'firstname'  => Serializer::decodeText($order['shipping_firstname'] ?? ''),
				'lastname'   => Serializer::decodeText($order['shipping_lastname'] ?? ''),
				'company'    => Serializer::decodeText($order['shipping_company'] ?? ''),
				'address_1'  => Serializer::decodeText($order['shipping_address_1'] ?? ''),
				'address_2'  => Serializer::decodeText($order['shipping_address_2'] ?? ''),
				'city'       => Serializer::decodeText($order['shipping_city'] ?? ''),
				'postcode'   => Serializer::decodeText($order['shipping_postcode'] ?? ''),
				'country'    => (string)($order['shipping_country'] ?? ''),
				'country_id' => (int)($order['shipping_country_id'] ?? 0),
				'zone'       => (string)($order['shipping_zone'] ?? ''),
				'zone_id'    => (int)($order['shipping_zone_id'] ?? 0),
			],
			'products'         => $products,
			'totals'           => $totals,
			'payments'         => $payments,
			'history'          => $this->historyRows($order_id, $statuses),
		]);

		return $response;
	}

	public function history() {
		$order_id = $this->param('order_id');

		$this->requireOrder($order_id);

		$this->respond($this->historyRows($order_id, $this->statusNames()));
	}

	/**
	 * Appends an order history entry through the panel's addHistory action.
	 */
	public function addHistory() {
		$order_id = $this->param('order_id');

		$this->assertPreconditions($this->orderResponse($order_id));

		$this->rejectFields(['override'], $this->body());

		$data = $this->validateRequest($this->body(), [
			'order_status_id' => ['required', 'int', 'min:1'],
			'comment'         => ['string', 'max:65535'],
			'notify'          => ['bool'],
		]);

		$this->request->get['order_id'] = $order_id;

		// `override` is never accepted from the API: the order-flow transition
		// rules (and the stock/affiliate/notification side effects that hang off
		// them) must not be bypassable by an integration. A refused transition is
		// reported as 409 invalid_transition.
		$this->request->post = [
			'order_status_id' => (int)$data['order_status_id'],
			// The history comment is rendered as HTML in the panel timeline, so it is
			// escaped here (the panel form is cleaned by Request::clean()).
			'comment'         => Serializer::encodeTextWithinBytes('comment', (string)($data['comment'] ?? ''), 65535),
			'notify'          => !empty($data['notify']) ? 1 : 0,
			'override'        => 0,
		];

		$result = $this->callPanelAction('sale/order/addHistory');

		if (isset($result['error'])) {
			$this->failFromPanelError((string)$result['error'], 'error_invalid_transition');
		}

		$this->respondResource($this->orderResponse($order_id));
	}

	/**
	 * Partial order update. Simple fields are written through the model, the
	 * rest goes through the panel's quickEdit action (same validation, same
	 * side effects).
	 */
	public function patch() {
		$order_id = $this->param('order_id');

		$this->assertPreconditions($this->orderResponse($order_id));

		$body = $this->body();

		if (!$body) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$updated = [];

		$this->request->get['order_id'] = $order_id;

		foreach (['tracking_number', 'comment'] as $field) {
			if (!array_key_exists($field, $body)) {
				continue;
			}

			// Both fields are writable with the `sale/order/shipment` capability
			// alone (courier role), so they bypass the panel's quickEdit action
			// (which requires full `sale/order` modify) and use the model directly.
			if (!$this->canShip() && !$this->canModifyOrder()) {
				throw new HttpException(Error::FORBIDDEN);
			}

			$rules = $field === 'tracking_number'
				? ['tracking_number' => ['string', 'max:1024', 'no_html']]
				: ['comment' => ['string', 'max:65535']];

			$value = (string)$this->validateRequest([$field => $body[$field]], $rules)[$field];

			if ($field === 'tracking_number') {
				$this->assertTrackingNumber($value);
			} else {
				// The order comment is rendered as HTML in the panel (autoescape is
				// off), so it is escaped here just like the panel form does it.
				$value = Serializer::encodeTextWithinBytes('comment', $value, 65535);
			}

			$this->load->model('sale/order');

			$this->model_sale_order->updateOrderQuick($order_id, [$field => $value]);

			$updated[$field] = $value;
		}

		foreach (['email', 'telephone', 'tax_number'] as $field) {
			if (!array_key_exists($field, $body)) {
				continue;
			}

			$this->requireOrderModify();

			// email is validated by the panel action; the two contact fields are
			// bounded here (varchar(32)) so an over-long value is a 422 instead of a
			// "Data too long" 500, and escaped like Request::clean() would (the order
			// page renders them unescaped).
			$rules = $field === 'email'
				? ['email' => ['email', 'max:96']]
				: [$field => ['string', 'max:32']];

			$value = (string)$this->validateRequest([$field => $body[$field]], $rules)[$field];

			if ($field !== 'email') {
				$value = Serializer::encodeTextWithin($field, $value, 32);
			}

			$this->request->post = ['field' => $field, 'value' => $value];

			$result = $this->callPanelAction('sale/order/quickEdit');

			$this->assertPanelSuccess($result);

			$updated[$field] = (string)$body[$field];
		}

		if (array_key_exists('payment_method', $body)) {
			$this->requireOrderModify();

			$method = $this->methodPayload($body['payment_method'], 'payment_code');

			$this->request->post = ['field' => 'payment_method', 'title' => $method['title'], 'code' => $method['code']];

			$this->assertPanelSuccess($this->callPanelAction('sale/order/quickEdit'));

			$updated['payment_method'] = $method;
		}

		if (array_key_exists('shipping_method', $body)) {
			$this->requireOrderModify();

			$method = $this->methodPayload($body['shipping_method'], 'shipping_code');

			$this->request->post = ['field' => 'shipping_method', 'title' => $method['title'], 'code' => $method['code']];

			$this->assertPanelSuccess($this->callPanelAction('sale/order/quickEdit'));

			$updated['shipping_method'] = $method;
		}

		foreach (['payment_address', 'shipping_address'] as $field) {
			if (!array_key_exists($field, $body) || !is_array($body[$field])) {
				continue;
			}

			$this->requireOrderModify();

			$address = $this->validateRequest($body[$field], [
				'firstname'  => ['required', 'string', 'max:32'],
				'lastname'   => ['required', 'string', 'max:32'],
				'company'    => ['string', 'max:60'],
				'address_1'  => ['required', 'string', 'max:128'],
				'address_2'  => ['string', 'max:128'],
				'city'       => ['required', 'string', 'max:128'],
				'postcode'   => ['string', 'max:10'],
				'country_id' => ['required', 'int', 'min:1'],
				'zone_id'    => ['required', 'int', 'min:0'],
			]);

			$this->request->post = ['field' => $field] + $address;

			$this->assertPanelSuccess($this->callPanelAction('sale/order/quickEdit'));

			$updated[$field] = $address;
		}

		if (!$updated) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$this->respondResource(array_merge($this->orderResponse($order_id), ['updated' => array_keys($updated)]));
	}

	/**
	 * The panel stores tracking numbers as a `|`-separated list (at most 10
	 * parcels) — the same format the order page renders. Reject a longer list
	 * instead of silently truncating it.
	 */
	private function assertTrackingNumber(string $value): void {
		$numbers = array_values(array_filter(array_map('trim', explode('|', $value)), static function ($number) {
			return $number !== '';
		}));

		if (count($numbers) > 10) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'tracking_number' => ['at most 10 tracking numbers separated by "|"'],
			]);
		}
	}

	/**
	 * Rejects fields the API deliberately does not accept (fail loudly instead of
	 * silently ignoring them, which would hide a broken integration).
	 */
	private function rejectFields(array $fields, array $body): void {
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $body)) {
				$errors[$field] = ['is not accepted by the API'];
			}
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}
	}

	private function orderSummary(array $order): array {
		return [
			'order_id'        => (int)$order['order_id'],
			'invoice_no'      => (int)($order['invoice_no'] ?? 0),
			'invoice_prefix'  => (string)($order['invoice_prefix'] ?? ''),
			'customer'        => Serializer::decodeText($order['customer'] ?? ''),
			'order_status_id' => (int)$order['order_status_id'],
			'order_status'    => (string)($order['order_status'] ?? ''),
			'total'           => Serializer::money($order['total'] ?? 0),
			'tracking_number' => (string)($order['tracking_number'] ?? ''),
			'date_added'      => Serializer::datetime($order['date_added'] ?? ''),
			'date_modified'   => Serializer::datetime($order['date_modified'] ?? ''),
		];
	}

	/**
	 * Status history of an order (payments are returned separately, the model's
	 * timeline query merges both).
	 */
	private function historyRows(int $order_id, array $statuses): array {
		$history = [];

		$limit = min(500, max(1, $this->queryInt('limit', 100)));

		foreach ($this->model_sale_order->getOrderTimeline($order_id, 0, $limit) as $entry) {
			if (($entry['type'] ?? '') !== 'history') {
				continue;
			}

			$status_id = (int)$entry['order_status_id'];

			$history[] = [
				'order_history_id' => (int)$entry['order_history_id'],
				'order_status_id'  => $status_id,
				'order_status'     => $statuses[$status_id] ?? (string)($entry['status_name'] ?? ''),
				'notify'           => Serializer::bool($entry['notify']),
				'comment'          => Serializer::decodeText($entry['comment'] ?? ''),
				'date_added'       => Serializer::datetime($entry['date_added']),
			];
		}

		return $history;
	}

	/**
	 * @return array<int, string> order_status_id => name in the request language
	 */
	private function statusNames(): array {
		$this->load->model('localisation/order_status');

		$names = [];

		foreach ($this->model_localisation_order_status->getOrderStatuses() as $status) {
			$names[(int)$status['order_status_id']] = (string)$status['name'];
		}

		return $names;
	}

	private function requireOrder(int $order_id): array {
		$this->load->model('sale/order');

		$order = $this->model_sale_order->getOrder($order_id);

		if (!$order) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $order;
	}

	private function canModifyOrder(): bool {
		return $this->user->hasPermission('modify', 'sale/order');
	}

	private function canShip(): bool {
		return $this->user->hasPermission('modify', 'sale/order/shipment') || $this->canModifyOrder();
	}

	private function requireOrderModify(): void {
		if (!$this->canModifyOrder()) {
			throw new HttpException(Error::FORBIDDEN);
		}
	}

	/**
	 * Runs a panel JSON action and returns its decoded payload.
	 *
	 * The action writes to the shared Response object (that is how the panel's
	 * AJAX endpoints work), so the output is read back and then replaced by the
	 * API envelope.
	 */
	private function callPanelAction(string $route): array {
		$this->response->setOutput('');

		$this->load->controller($route);

		$output = (string)$this->response->getOutput();

		$this->response->setOutput('');

		$decoded = json_decode($output, true);

		if (!is_array($decoded)) {
			throw new HttpException(Error::SERVER_ERROR);
		}

		return $decoded;
	}

	private function assertPanelSuccess(array $result): void {
		if (isset($result['error'])) {
			$this->failFromPanelError((string)$result['error']);
		}
	}

	/**
	 * Maps an error message coming from a panel action onto an API error.
	 */
	private function failFromPanelError(string $message, string $fallback_code = Error::VALIDATION_FAILED): void {
		$this->load->language('sale/order');

		if ($message === $this->language->get('error_permission')) {
			throw new HttpException(Error::FORBIDDEN);
		}

		if ($message === $this->language->get('error_invalid_transition')) {
			throw new HttpException(Error::INVALID_TRANSITION);
		}

		if ($message === $this->language->get('error_tracking_required')) {
			throw new HttpException(Error::TRACKING_REQUIRED);
		}

		throw new HttpException($fallback_code);
	}

	/**
	 * Accepts {"title": "...", "code": "..."} or a plain title string.
	 *
	 * @return array{title: string, code: string}
	 */
	private function methodPayload($value, string $code_field): array {
		if (!is_array($value)) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'error' => ['expected an object with "title" and "code"'],
			]);
		}

		$title = trim((string)($value['title'] ?? ''));
		$code = trim((string)($value['code'] ?? ''));

		if ($title === '' || $code === '') {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'title'    => ['required'],
				$code_field => ['required'],
			]);
		}

		return ['title' => $title, 'code' => $code];
	}
}
