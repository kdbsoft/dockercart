<?php
/**
 * DockerCart customer (storefront) REST API — returns (RMA).
 *
 *   GET  /store-api/v1/returns          list own returns (filters + pagination)
 *   GET  /store-api/v1/returns/{id}     single own return with lines and history
 *   POST /store-api/v1/returns          create a return request for an own order
 *
 * Strictly scoped: every query is filtered by the authenticated customer. A
 * return created here is a request — restock and refund happen only when the
 * panel completes it, so the API never negotiates `amount`, `return_status_id`
 * or the contact details (they are snapshotted from the order).
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerStoreApiReturns extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('sale/return');

		$pagination = $this->pagination();

		$filters = [
			'filter_customer_id' => $this->customerPrincipal()->getCustomerId(),
			'filter_order_id'    => $this->queryString('order_id'),
			'filter_date_added'  => $this->queryString('date_added'),
			'start'              => $pagination['start'],
			'limit'              => $pagination['limit'],
		];

		if (($status_id = $this->queryOptionalInt('status_id')) !== null) {
			$filters['filter_return_status_id'] = $status_id;
		}

		$sorting = $this->sorting(
			[
				'return_id'  => 'r.return_id',
				'order_id'   => 'r.order_id',
				'type'       => 'r.type',
				'status'     => 'status',
				'date_added' => 'r.date_added',
			],
			'r.return_id',
			'DESC'
		);

		$filters['sort'] = $sorting['sort'];
		$filters['order'] = $sorting['order'];

		$rows = [];

		foreach ($this->model_sale_return->getReturns($filters) as $return) {
			$rows[] = $this->returnSummary($return);
		}

		$total = (int)$this->model_sale_return->getTotalReturns($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$this->respondResource($this->returnResponse($this->param('return_id')));
	}

	/**
	 * Creates a return request from one of the customer's own orders.
	 *
	 * The payload only carries what the client knows (order, lines and quantities,
	 * reason/action): contact details, product names, models, prices and the
	 * variant id are read from the order lines. The return always starts in the
	 * store's default return status.
	 */
	public function add() {
		$this->load->model('sale/return');
		$this->load->model('sale/order');

		// Panel-shaped fields are refused instead of silently ignored: they would
		// otherwise suggest the client can set the customer, price or status.
		$this->rejectFields(
			['customer_id', 'firstname', 'lastname', 'email', 'telephone', 'product', 'model', 'quantity', 'product_id', 'amount', 'refunded', 'return_status_id'],
			$this->body()
		);

		$data = $this->validateRequest($this->body(), [
			'order_id'         => ['required', 'int', 'min:1'],
			'products'         => ['required', 'array'],
			'return_reason_id' => ['required', 'int', 'min:1'],
			'return_action_id' => ['int', 'min:0'],
			'opened'           => ['bool'],
			'comment'          => ['string', 'max:65535'],
			'date_ordered'     => ['date'],
		]);

		$order_id = (int)$data['order_id'];

		$order = $this->model_sale_order->getOrder($order_id);

		if (!$order
			|| (int)$order['customer_id'] !== $this->customerPrincipal()->getCustomerId()
			|| (int)$order['order_status_id'] <= 0) {
			throw new HttpException(Error::VALIDATION_FAILED, ['order_id' => ['order not found']]);
		}

		$products = $this->returnLines($order_id, (array)$data['products']);

		$this->assertReturnDictionaries($data);

		$payload = [
			'order_id'         => $order_id,
			'type'             => 'full',
			'customer_id'      => (int)$order['customer_id'],
			'firstname'        => (string)$order['firstname'],
			'lastname'         => (string)$order['lastname'],
			'email'            => (string)($order['email'] ?? ''),
			'telephone'        => (string)($order['telephone'] ?? ''),
			'products'         => $products,
			'amount'           => 0.0,
			'opened'           => !empty($data['opened']) ? 1 : 0,
			'return_reason_id' => (int)$data['return_reason_id'],
			'return_action_id' => (int)($data['return_action_id'] ?? 0),
			'return_status_id' => $this->defaultReturnStatusId(),
			// Escaped like Request::clean() does on the storefront: the panel theme
			// renders the return comment unescaped (autoescape is off), so raw markup
			// from the API would execute in the operator's session.
			'comment'          => Serializer::encodeTextWithinBytes('comment', (string)($data['comment'] ?? ''), 65535),
			'date_ordered'     => (string)($data['date_ordered'] ?? date('Y-m-d', strtotime((string)$order['date_added']))),
		];

		$return_id = (int)$this->model_sale_return->addReturn($payload);

		$response = $this->returnResponse($return_id);

		$this->context()->etag = $this->etagFor($response);

		$this->respondCreated($response, '/store-api/v1/returns/' . $return_id);
	}

	private function returnResponse(int $return_id): array {
		$return = $this->requireReturn($return_id);

		return array_merge($this->returnSummary($return), [
			'comment'  => Serializer::decodeText($return['comment'] ?? ''),
			'products' => $this->productRows($return_id),
			'history'  => $this->historyRows($return_id),
		]);
	}

	private function returnSummary(array $return): array {
		return [
			'return_id'        => (int)$return['return_id'],
			'order_id'         => (int)$return['order_id'],
			'type'             => (string)($return['type'] ?? 'full'),
			'product'          => Serializer::decodeText($return['product'] ?? ''),
			'model'            => (string)($return['model'] ?? ''),
			'quantity'         => (int)$return['quantity'],
			'refunded'         => Serializer::bool($return['refunded'] ?? 0),
			'opened'           => Serializer::bool($return['opened'] ?? 0),
			'return_reason_id' => (int)$return['return_reason_id'],
			'return_action_id' => (int)$return['return_action_id'],
			'return_status_id' => (int)$return['return_status_id'],
			'return_status'    => (string)($return['return_status'] ?? ''),
			'date_ordered'     => Serializer::date($return['date_ordered'] ?? ''),
			'date_added'       => Serializer::datetime($return['date_added'] ?? ''),
			'date_modified'    => Serializer::datetime($return['date_modified'] ?? ''),
		];
	}

	private function productRows(int $return_id): array {
		$rows = [];

		foreach ($this->model_sale_return->getReturnProducts($return_id) as $product) {
			$rows[] = [
				'order_product_id' => (int)$product['order_product_id'],
				'product_id'       => (int)$product['product_id'],
				'variant_id'       => (int)($product['variant_id'] ?? 0),
				'name'             => Serializer::decodeText($product['name'] ?? ''),
				'model'            => (string)($product['model'] ?? ''),
				'quantity'         => (int)$product['quantity'],
				'price'            => Serializer::money($product['price'] ?? 0),
				'total'            => Serializer::money($product['total'] ?? 0),
			];
		}

		return $rows;
	}

	/**
	 * Status history, oldest first (the model returns the newest rows first).
	 */
	private function historyRows(int $return_id): array {
		$limit = min(500, max(1, $this->queryInt('limit', 100)));

		$requested = [];

		foreach ($this->model_sale_return->getReturnHistories($return_id, 0, $limit) as $entry) {
			$requested[] = [
				'return_status_id' => (int)$entry['return_status_id'],
				'return_status'    => (string)$entry['status'],
				'comment'          => Serializer::decodeText($entry['comment'] ?? ''),
				'date_added'       => Serializer::datetime($entry['date_added']),
			];
		}

		return array_reverse($requested);
	}

	private function rejectFields(array $fields, array $body): void {
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $body)) {
				$errors[$field] = ['is not accepted by the API; it is taken from the order'];
			}
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}
	}

	/**
	 * Normalizes the requested lines against the real order lines: the client
	 * sends `{order_product_id, quantity}`, everything else is a snapshot of the
	 * order line (name, model, price, variant).
	 *
	 * @return array<int, array<string, mixed>> model-shaped return products
	 */
	private function returnLines(int $order_id, array $requested): array {
		$order_products = [];

		foreach ($this->model_sale_order->getOrderProducts($order_id) as $order_product) {
			$order_products[(int)$order_product['order_product_id']] = $order_product;
		}

		$lines = [];
		$seen = [];

		foreach ($requested as $index => $line) {
			$field = 'products.' . $index;

			if (!is_array($line)) {
				throw new HttpException(Error::VALIDATION_FAILED, [$field => ['must be an object']]);
			}

			try {
				$normalized = $this->validateRequest($line, [
					'order_product_id' => ['required', 'int', 'min:1'],
					'quantity'         => ['required', 'int', 'min:1'],
				]);
			} catch (HttpException $exception) {
				$errors = [];

				foreach ($exception->getFields() as $name => $messages) {
					$errors[$field . '.' . $name] = $messages;
				}

				throw new HttpException(Error::VALIDATION_FAILED, $errors);
			}

			$order_product_id = (int)$normalized['order_product_id'];

			if (!isset($order_products[$order_product_id])) {
				throw new HttpException(Error::VALIDATION_FAILED, [
					$field . '.order_product_id' => ['does not belong to this order'],
				]);
			}

			if (isset($seen[$order_product_id])) {
				throw new HttpException(Error::VALIDATION_FAILED, [
					$field . '.order_product_id' => ['duplicate line; sum the quantity instead'],
				]);
			}

			$seen[$order_product_id] = true;

			$order_product = $order_products[$order_product_id];
			$quantity = (int)$normalized['quantity'];
			$ordered = (int)$order_product['quantity'];

			if ($quantity > $ordered) {
				throw new HttpException(Error::VALIDATION_FAILED, [
					$field . '.quantity' => ['must not exceed the ordered quantity (' . $ordered . ')'],
				]);
			}

			$price = (float)$order_product['price'];

			$lines[] = [
				'order_product_id' => $order_product_id,
				'product_id'       => (int)$order_product['product_id'],
				'variant_id'       => (int)($order_product['variant_id'] ?? 0),
				'name'             => (string)$order_product['name'],
				'model'            => (string)$order_product['model'],
				'quantity'         => $quantity,
				'price'            => $price,
				'total'            => $price * $quantity,
			];
		}

		if (!$lines) {
			throw new HttpException(Error::VALIDATION_FAILED, ['products' => ['at least one returned line is required']]);
		}

		return $lines;
	}

	private function assertReturnDictionaries(array $data): void {
		$errors = [];

		$this->load->model('localisation/return_reason');

		if (!$this->model_localisation_return_reason->getReturnReasonDescriptions((int)$data['return_reason_id'])) {
			$errors['return_reason_id'] = ['unknown return reason'];
		}

		if (!empty($data['return_action_id'])) {
			$this->load->model('localisation/return_action');

			if (!$this->model_localisation_return_action->getReturnActionDescriptions((int)$data['return_action_id'])) {
				$errors['return_action_id'] = ['unknown return action'];
			}
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}
	}

	/**
	 * Status a customer-created return starts in: the store default, or the
	 * lowest configured status when none is set.
	 */
	private function defaultReturnStatusId(): int {
		$configured = (int)$this->config->get('config_return_status_id');

		if ($configured > 0) {
			return $configured;
		}

		$this->load->model('localisation/return_status');

		$ids = [];

		foreach ($this->model_localisation_return_status->getReturnStatuses() as $status) {
			$ids[] = (int)$status['return_status_id'];
		}

		if (!$ids) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'return_status_id' => ['no return status is configured'],
			]);
		}

		return min($ids);
	}

	private function requireReturn(int $return_id): array {
		$this->load->model('sale/return');

		$return = $this->model_sale_return->getReturn($return_id);

		if (!$return || (int)$return['customer_id'] !== $this->customerPrincipal()->getCustomerId()) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $return;
	}
}
