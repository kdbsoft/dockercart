<?php
/**
 * DockerCart customer (storefront) REST API — order representation.
 *
 * One source of truth for what a customer sees of an order, shared by
 * GET /v1/orders/{id} and by the 201 answer of POST /v1/checkout/{id}/confirm,
 * so a freshly placed order looks exactly like the same order read later.
 *
 * Every lookup is scoped to the authenticated customer: another customer's order
 * id — and a deleted/voided order — is answered with 404, never 403, so ids are
 * not disclosed.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreOrder {
	private \Registry $registry;

	private int $customer_id;

	private ?array $status_names = null;

	public function __construct(\Registry $registry, int $customer_id) {
		$this->registry = $registry;
		$this->customer_id = $customer_id;
	}

	/**
	 * Full representation: summary + contact + addresses + lines + totals + history.
	 */
	public function full(int $order_id): array {
		$order = $this->require($order_id);

		return array_merge($this->summary($order), [
			'firstname'        => Serializer::decodeText($order['firstname'] ?? ''),
			'lastname'         => Serializer::decodeText($order['lastname'] ?? ''),
			'email'            => (string)($order['email'] ?? ''),
			'telephone'        => Serializer::decodeText($order['telephone'] ?? ''),
			'comment'          => Serializer::decodeText($order['comment'] ?? ''),
			'currency_code'    => (string)$order['currency_code'],
			'currency_value'   => (float)$order['currency_value'],
			'payment_method'   => (string)($order['payment_method'] ?? ''),
			'shipping_method'  => (string)($order['shipping_method'] ?? ''),
			'payment_address'  => $this->address($order, 'payment'),
			'shipping_address' => $this->address($order, 'shipping'),
			'products'         => $this->products($order_id),
			'totals'           => $this->totals($order_id),
			'history'          => $this->history($order_id),
		]);
	}

	/**
	 * List-row representation.
	 */
	public function summary(array $order): array {
		return [
			'order_id'        => (int)$order['order_id'],
			'invoice_no'      => (int)($order['invoice_no'] ?? 0),
			'invoice_prefix'  => (string)($order['invoice_prefix'] ?? ''),
			'order_status_id' => (int)$order['order_status_id'],
			'order_status'    => (string)($order['order_status'] ?? ''),
			'total'           => Serializer::money($order['total'] ?? 0),
			'tracking_number' => (string)($order['tracking_number'] ?? ''),
			'date_added'      => Serializer::datetime($order['date_added'] ?? ''),
			'date_modified'   => Serializer::datetime($order['date_modified'] ?? ''),
		];
	}

	/**
	 * Status history, oldest first.
	 */
	public function history(int $order_id, int $limit = 100): array {
		$statuses = $this->statuses();
		$rows = [];

		$limit = min(500, max(1, $limit));

		foreach ($this->orderModel()->getOrderTimeline($order_id, 0, $limit) as $entry) {
			if (($entry['type'] ?? '') !== 'history') {
				continue;
			}

			$status_id = (int)$entry['order_status_id'];

			$rows[] = [
				'order_history_id' => (int)$entry['order_history_id'],
				'order_status_id'  => $status_id,
				'order_status'     => $statuses[$status_id] ?? (string)($entry['status_name'] ?? ''),
				'comment'          => Serializer::decodeText($entry['comment'] ?? ''),
				'date_added'       => Serializer::datetime($entry['date_added']),
			];
		}

		return $rows;
	}

	/**
	 * @return array<int, array>
	 */
	private function products(int $order_id): array {
		$model = $this->orderModel();
		$products = [];

		foreach ($model->getOrderProducts($order_id) as $product) {
			$options = [];

			foreach ($model->getOrderOptions($order_id, (int)$product['order_product_id']) as $option) {
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
				'options'          => $options,
			];
		}

		return $products;
	}

	/**
	 * @return array<int, array>
	 */
	private function totals(int $order_id): array {
		$totals = [];

		foreach ($this->orderModel()->getOrderTotals($order_id) as $total) {
			$totals[] = [
				'code'       => (string)$total['code'],
				'title'      => (string)$total['title'],
				'value'      => Serializer::money($total['value']),
				'sort_order' => (int)$total['sort_order'],
			];
		}

		return $totals;
	}

	private function address(array $order, string $prefix): array {
		return [
			'firstname'  => Serializer::decodeText($order[$prefix . '_firstname'] ?? ''),
			'lastname'   => Serializer::decodeText($order[$prefix . '_lastname'] ?? ''),
			'company'    => Serializer::decodeText($order[$prefix . '_company'] ?? ''),
			'address_1'  => Serializer::decodeText($order[$prefix . '_address_1'] ?? ''),
			'address_2'  => Serializer::decodeText($order[$prefix . '_address_2'] ?? ''),
			'city'       => Serializer::decodeText($order[$prefix . '_city'] ?? ''),
			'postcode'   => Serializer::decodeText($order[$prefix . '_postcode'] ?? ''),
			'country'    => (string)($order[$prefix . '_country'] ?? ''),
			'zone'       => (string)($order[$prefix . '_zone'] ?? ''),
		];
	}

	/**
	 * @return array<int, string> order_status_id => name in the request language
	 */
	private function statuses(): array {
		if ($this->status_names !== null) {
			return $this->status_names;
		}

		$load = $this->registry->get('load');

		$load->model('localisation/order_status');

		$names = [];

		foreach ($this->registry->get('model_localisation_order_status')->getOrderStatuses() as $status) {
			$names[(int)$status['order_status_id']] = (string)$status['name'];
		}

		$this->status_names = $names;

		return $names;
	}

	/**
	 * Order of the authenticated customer, 404 for anything else.
	 */
	public function require(int $order_id): array {
		if ($order_id <= 0) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$order = $this->orderModel()->getOrder($order_id);

		if (!$order
			|| (int)$order['customer_id'] !== $this->customer_id
			|| (int)$order['order_status_id'] <= 0) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $order;
	}

	/**
	 * The panel order model (admin MVC tree).
	 *
	 * @return mixed
	 */
	private function orderModel() {
		$load = $this->registry->get('load');

		$load->model('sale/order');

		return $this->registry->get('model_sale_order');
	}
}
