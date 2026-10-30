<?php
/**
 * DockerCart customer (storefront) REST API — orders.
 *
 *   GET /store-api/v1/orders                 list own orders (filters + pagination)
 *   GET /store-api/v1/orders/{id}            single own order with lines, totals and history
 *   GET /store-api/v1/orders/{id}/history    status history
 *
 * Read-only and strictly scoped: every query is filtered by the authenticated
 * customer, so a key can never see another customer's order. The representation
 * (Dockercart\Api\StoreOrder) is shared with the 201 answer of
 * POST /v1/checkout/{id}/confirm, so a new order and the same order read later
 * are byte-identical.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\StoreOrder;

class ControllerStoreApiOrders extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('sale/order');

		$pagination = $this->pagination();

		$filters = [
			'filter_customer_id' => $this->customerPrincipal()->getCustomerId(),
			'filter_date_added'  => $this->queryString('date_added'),
			'filter_date_preset' => $this->queryString('date_preset'),
			'start'              => $pagination['start'],
			'limit'              => $pagination['limit'],
		];

		if (($status_id = $this->queryOptionalInt('status_id')) !== null) {
			$filters['filter_order_status_id'] = $status_id;
		}

		$sorting = $this->sorting(
			[
				'order_id'      => 'o.order_id',
				'order_status'  => 'order_status',
				'date_added'    => 'o.date_added',
				'date_modified' => 'o.date_modified',
				'total'         => 'o.total',
			],
			'o.order_id',
			'DESC'
		);

		$filters['sort'] = $sorting['sort'];
		$filters['order'] = $sorting['order'];

		$orders = $this->storeOrder();

		$rows = [];

		foreach ($this->model_sale_order->getOrders($filters) as $order) {
			$rows[] = $orders->summary($order);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_sale_order->getTotalOrders($filters)));
	}

	public function get() {
		$this->respondResource($this->storeOrder()->full($this->param('order_id')));
	}

	public function history() {
		$order_id = $this->param('order_id');

		$this->storeOrder()->require($order_id);

		$this->respond($this->storeOrder()->history($order_id, $this->queryInt('limit', 100)));
	}

	private function storeOrder(): StoreOrder {
		return new StoreOrder($this->registry, $this->customerPrincipal()->getCustomerId());
	}
}
