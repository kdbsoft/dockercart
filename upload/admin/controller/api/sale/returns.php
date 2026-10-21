<?php
/**
 * DockerCart admin REST API — returns (RMA).
 *
 *   GET  /v1/returns                  list (filters + pagination)
 *   GET  /v1/returns/{id}             single return with lines and status history
 *   POST /v1/returns                  create from an order (panel auto-fill rules)
 *   POST /v1/returns/{id}/history     append a return status
 *
 * Nothing here re-implements business rules: ModelSaleReturn owns the return
 * document, and addReturnHistory() is the single path that completes a return —
 * it restocks the returned lines through DockercartWarehouse ('return' movement
 * on the warehouse the original order line came from), refunds the amount once
 * and moves a full return's order to the Refunded status.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiSaleReturns extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('sale/return');

		$pagination = $this->pagination();

		$filters = [
			'filter_return_id'     => $this->queryString('return_id'),
			'filter_order_id'      => $this->queryString('order_id'),
			'filter_customer'      => $this->queryString('customer'),
			'filter_product'       => $this->queryString('product'),
			'filter_model'         => $this->queryString('model'),
			'filter_type'          => $this->queryString('type'),
			'filter_date_added'    => $this->queryString('date_added'),
			'filter_date_modified' => $this->queryString('date_modified'),
			'start'                => $pagination['start'],
			'limit'                => $pagination['limit'],
		];

		if ($this->queryString('status_id') !== '') {
			$filters['filter_return_status_id'] = $this->queryInt('status_id');
		}

		$sorting = $this->sorting(
			[
				'return_id'     => 'r.return_id',
				'order_id'      => 'r.order_id',
				'customer'      => 'customer',
				'product'       => 'r.product',
				'model'         => 'r.model',
				'type'          => 'r.type',
				'status'        => 'status',
				'date_added'    => 'r.date_added',
				'date_modified' => 'r.date_modified',
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
	 * Creates a return from an order.
	 *
	 * The payload only carries what the client actually knows (order, lines and
	 * quantities, reason/action/status): contact details, product names, models,
	 * prices and the variant id are read from the order lines, exactly like the
	 * panel form pre-fills them. A return created here is a request — restock and
	 * refund happen only when POST /v1/returns/{id}/history reaches the Complete
	 * status.
	 */
	public function add() {
		$this->load->model('sale/return');
		$this->load->model('sale/order');

		// Panel-shaped fields are refused instead of silently ignored: they would
		// otherwise suggest the client can set the customer or the price itself.
		$this->rejectFields(
			['customer_id', 'firstname', 'lastname', 'email', 'telephone', 'product', 'model', 'quantity', 'product_id', 'refunded'],
			$this->body()
		);

		$data = $this->validateRequest($this->body(), [
			'order_id'         => ['required', 'int', 'min:1'],
			'products'         => ['required', 'array'],
			'return_reason_id' => ['required', 'int', 'min:1'],
			'return_action_id' => ['int', 'min:0'],
			'return_status_id' => ['int', 'min:1'],
			'amount'           => ['float', 'min:0'],
			'type'             => ['string', 'in:full,partial'],
			'opened'           => ['bool'],
			'comment'          => ['string', 'max:65535'],
			'date_ordered'     => ['date'],
		]);

		$order_id = (int)$data['order_id'];

		$order = $this->model_sale_order->getOrder($order_id);

		if (!$order) {
			throw new HttpException(Error::VALIDATION_FAILED, ['order_id' => ['order not found']]);
		}

		$products = $this->returnLines($order_id, (array)$data['products']);

		$this->assertReturnDictionaries($data);

		$payload = [
			'order_id'         => $order_id,
			'type'             => (string)($data['type'] ?? 'full'),
			'customer_id'      => (int)$order['customer_id'],
			'firstname'        => (string)$order['firstname'],
			'lastname'         => (string)$order['lastname'],
			'email'            => (string)($order['email'] ?? ''),
			'telephone'        => (string)($order['telephone'] ?? ''),
			'products'         => $products,
			'amount'           => (float)($data['amount'] ?? 0),
			'opened'           => !empty($data['opened']) ? 1 : 0,
			'return_reason_id' => (int)$data['return_reason_id'],
			'return_action_id' => (int)($data['return_action_id'] ?? 0),
			'return_status_id' => (int)($data['return_status_id'] ?? $this->defaultReturnStatusId()),
			'comment'          => (string)($data['comment'] ?? ''),
			'date_ordered'     => (string)($data['date_ordered'] ?? date('Y-m-d', strtotime((string)$order['date_added']))),
		];

		$return_id = (int)$this->model_sale_return->addReturn($payload);

		$response = $this->returnResponse($return_id);

		$this->context()->etag = $this->etagFor($response);

		$this->respondCreated($response, '/api/v1/returns/' . $return_id);
	}

	/**
	 * The single-return representation used by GET, POST and the ETag.
	 */
	private function returnResponse(int $return_id): array {
		$return = $this->requireReturn($return_id);

		return array_merge($this->returnSummary($return), [
			'comment'  => (string)($return['comment'] ?? ''),
			'products' => $this->productRows($return_id),
			'history'  => $this->historyRows($return_id),
		]);
	}

	private function returnSummary(array $return): array {
		return [
			'return_id'         => (int)$return['return_id'],
			'order_id'          => (int)$return['order_id'],
			'customer_id'       => (int)$return['customer_id'],
			'customer'          => (string)($return['customer'] ?? trim(($return['firstname'] ?? '') . ' ' . ($return['lastname'] ?? ''))),
			'firstname'         => (string)$return['firstname'],
			'lastname'          => (string)$return['lastname'],
			'email'             => (string)$return['email'],
			'telephone'         => (string)$return['telephone'],
			'type'              => (string)$return['type'],
			'product_id'        => (int)$return['product_id'],
			'product'           => (string)$return['product'],
			'model'             => (string)$return['model'],
			'quantity'          => (int)$return['quantity'],
			'amount'            => Serializer::money($return['amount'] ?? 0),
			'refunded'          => Serializer::bool($return['refunded'] ?? 0),
			'opened'            => Serializer::bool($return['opened'] ?? 0),
			'return_reason_id'  => (int)$return['return_reason_id'],
			'return_action_id'  => (int)$return['return_action_id'],
			'return_status_id'  => (int)$return['return_status_id'],
			'return_status'     => (string)($return['return_status'] ?? ''),
			'date_ordered'      => Serializer::date($return['date_ordered'] ?? ''),
			'date_added'        => Serializer::datetime($return['date_added'] ?? ''),
			'date_modified'     => Serializer::datetime($return['date_modified'] ?? ''),
		];
	}

	/**
	 * Returned lines (a snapshot of the order lines at creation time).
	 */
	private function productRows(int $return_id): array {
		$rows = [];

		foreach ($this->model_sale_return->getReturnProducts($return_id) as $product) {
			$rows[] = [
				'order_product_id' => (int)$product['order_product_id'],
				'product_id'       => (int)$product['product_id'],
				'variant_id'       => (int)($product['variant_id'] ?? 0),
				'name'             => (string)$product['name'],
				'model'            => (string)$product['model'],
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
				'notify'           => Serializer::bool($entry['notify']),
				'comment'          => (string)$entry['comment'],
				'date_added'       => Serializer::datetime($entry['date_added']),
			];
		}

		return array_reverse($requested);
	}

	/**
	 * Appends a status history entry.
	 *
	 * Reaching the Complete status runs ModelSaleReturn::completeReturn() — the
	 * panel's own completion path: returned lines are restocked through the
	 * warehouse layer, the amount is refunded once and a full return moves the
	 * order to Refunded. `restock` is not negotiable from the API (the panel does
	 * not negotiate it either), so a client cannot complete a return without the
	 * stock and money side effects.
	 */
	public function addHistory() {
		$return_id = $this->param('return_id');

		$this->assertPreconditions($this->returnResponse($return_id));

		$this->rejectFields(
			['restock', 'refund', 'refunded', 'amount', 'products'],
			$this->body(),
			'is not accepted by the API; completing the return applies it'
		);

		$data = $this->validateRequest($this->body(), [
			'return_status_id' => ['required', 'int', 'min:1'],
			'comment'          => ['string', 'max:65535'],
			'notify'           => ['bool'],
		]);

		$this->load->model('localisation/return_status');

		if (!$this->model_localisation_return_status->getReturnStatusDescriptions((int)$data['return_status_id'])) {
			throw new HttpException(Error::VALIDATION_FAILED, ['return_status_id' => ['unknown return status']]);
		}

		// completeReturn() reuses the panel's language strings (refund / order notes).
		$this->load->language('sale/return');

		$this->model_sale_return->addReturnHistory(
			$return_id,
			(int)$data['return_status_id'],
			(string)($data['comment'] ?? ''),
			!empty($data['notify']) ? 1 : 0
		);

		$this->respondResource($this->returnResponse($return_id));
	}

	/**
	 * Rejects fields the API deliberately does not accept (fail loudly instead of
	 * silently ignoring them, which would hide a broken integration).
	 */
	private function rejectFields(array $fields, array $body, string $reason = 'is not accepted by the API; it is taken from the order'): void {
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $body)) {
				$errors[$field] = [$reason];
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

	/**
	 * A return stores reason/action/status ids, so they must exist (the panel can
	 * only pick them from a dropdown). Descriptions are checked language-agnostic.
	 */
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

		if (!empty($data['return_status_id'])) {
			$this->load->model('localisation/return_status');

			if (!$this->model_localisation_return_status->getReturnStatusDescriptions((int)$data['return_status_id'])) {
				$errors['return_status_id'] = ['unknown return status'];
			}
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}
	}

	/**
	 * Status a return starts in: the lowest configured one (1 = Pending in the
	 * seeded dictionaries).
	 */
	private function defaultReturnStatusId(): int {
		$this->load->model('localisation/return_status');

		$ids = [];

		foreach ($this->model_localisation_return_status->getReturnStatuses() as $status) {
			$ids[] = (int)$status['return_status_id'];
		}

		if (!$ids) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'return_status_id' => ['no return status is configured; send one explicitly'],
			]);
		}

		return min($ids);
	}

	private function requireReturn(int $return_id): array {
		$this->load->model('sale/return');

		$return = $this->model_sale_return->getReturn($return_id);

		if (!$return) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $return;
	}
}
