<?php
/**
 * DockerCart admin REST API — warehouses.
 *
 *   GET /v1/warehouses                 all warehouses (active and disabled)
 *   GET /v1/warehouses/{id}/stock      the warehouse x product stock matrix
 *
 * Everything is read through DockercartWarehouse / ModelWarehouseStock — the
 * same layer the panel uses — so allocation priority, the default warehouse and
 * the denormalised quantity caches behave identically. Stock is written only
 * through POST /v1/stock/adjustments.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiWarehouseWarehouses extends \Dockercart\Api\Controller {
	public function index() {
		$warehouse = new DockercartWarehouse($this->registry);

		$status = $this->queryString('status');

		$rows = [];

		foreach ($warehouse->getAllWarehouses() as $row) {
			// Disabled warehouses are returned too: an integration keeps syncing a
			// warehouse it has switched off instead of silently losing it.
			if ($status !== '' && (int)$row['status'] !== (int)$status) {
				continue;
			}

			$rows[] = $this->warehouseResponse($row);
		}

		$this->respond($rows);
	}

	/**
	 * Stock rows of one warehouse (the panel's stock matrix, paginated).
	 *
	 * Ordered by product_id, variant_id — the model's own order — so pages are
	 * stable while quantities change.
	 */
	public function stock() {
		$warehouse_id = $this->param('warehouse_id');

		$this->requireWarehouse($warehouse_id);

		$this->load->model('warehouse/stock');

		$pagination = $this->pagination();

		$filters = [
			'filter_warehouse_id' => $warehouse_id,
			'filter_product_id'   => $this->queryInt('product_id'),
			'filter_name'         => $this->queryString('name'),
			'filter_model'        => $this->queryString('model'),
			'filter_sku'          => $this->queryString('sku'),
			'filter_quantity_min' => $this->queryString('quantity_min'),
			'filter_quantity_max' => $this->queryString('quantity_max'),
			'start'               => $pagination['start'],
			'limit'               => $pagination['limit'],
		];

		if ($this->queryString('variant_id') !== '') {
			$filters['filter_variant_id'] = $this->queryInt('variant_id');
		}

		if ($this->queryString('unlimited') !== '') {
			$filters['filter_unlimited'] = $this->queryInt('unlimited');
		}

		$rows = [];

		$matrix = $this->model_warehouse_stock->getStockMatrix($filters);

		$reserved = $this->reservedMap($matrix, $warehouse_id);

		foreach ($matrix as $row) {
			$key = (int)$row['product_id'] . ':' . (int)$row['variant_id'] . ':' . (int)$row['warehouse_id'];

			$rows[] = $this->stockResponse($row, (float)($reserved[$key] ?? 0));
		}

		$total = (int)$this->model_warehouse_stock->getTotalStock($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	/**
	 * Reserved quantities of the current page, keyed by
	 * "product_id:variant_id:warehouse_id" (one grouped query).
	 *
	 * @return array<string, float>
	 */
	private function reservedMap(array $matrix, int $warehouse_id): array {
		$lines = [];

		foreach ($matrix as $row) {
			$lines[] = [
				'product_id' => (int)$row['product_id'],
				'variant_id' => (int)$row['variant_id'],
			];
		}

		$warehouse = new DockercartWarehouse($this->registry);

		return $warehouse->getReservedBatch($lines, [$warehouse_id]);
	}

	/**
	 * A stock row plus the sellable view of it: `available` is null for an
	 * unlimited (dropship) row, because the internal PHP_FLOAT_MAX sentinel is not
	 * a JSON number a client could use.
	 */
	private function stockResponse(array $row, float $reserved): array {
		$quantity = (float)($row['quantity'] ?? 0);
		$unlimited = Serializer::bool($row['unlimited'] ?? 0);

		return [
			'stock_id'     => (int)$row['stock_id'],
			'warehouse_id' => (int)$row['warehouse_id'],
			'product_id'   => (int)$row['product_id'],
			'variant_id'   => (int)$row['variant_id'],
			'name'         => (string)($row['product_name'] ?? ''),
			'model'        => (string)($row['product_model'] ?? ''),
			'sku'          => (string)($row['variant_sku'] ?? ''),
			'quantity'     => Serializer::quantity($quantity),
			'unlimited'    => $unlimited,
			'lead_time'    => (int)($row['lead_time'] ?? 0),
			'reserved'     => Serializer::quantity($reserved),
			'available'    => $unlimited ? null : Serializer::quantity(max(0.0, $quantity - $reserved)),
		];
	}

	private function warehouseResponse(array $row): array {
		return [
			'warehouse_id'       => (int)$row['warehouse_id'],
			'name'               => (string)$row['name'],
			'type'               => (string)$row['type'],
			'is_default'         => Serializer::bool($row['is_default'] ?? 0),
			'priority'           => (int)($row['priority'] ?? 0),
			'status'             => (int)$row['status'],
			'sort_order'         => (int)($row['sort_order'] ?? 0),
			'address_1'          => (string)($row['address_1'] ?? ''),
			'address_2'          => (string)($row['address_2'] ?? ''),
			'city'               => (string)($row['city'] ?? ''),
			'postcode'           => (string)($row['postcode'] ?? ''),
			'country_id'         => (int)($row['country_id'] ?? 0),
			'zone_id'            => (int)($row['zone_id'] ?? 0),
			'latitude'           => (float)($row['latitude'] ?? 0),
			'longitude'          => (float)($row['longitude'] ?? 0),
			'phone'              => (string)($row['phone'] ?? ''),
			'email'              => (string)($row['email'] ?? ''),
			'map_url'            => (string)($row['map_url'] ?? ''),
			'prepare_days'       => (int)($row['prepare_days'] ?? 0),
			'low_stock'          => Serializer::money($row['low_stock'] ?? 0),
			'allow_pickup'       => Serializer::bool($row['allow_pickup'] ?? 0),
			'pickup_cost'        => Serializer::money($row['pickup_cost'] ?? 0),
			'supplier_lead_time' => (int)($row['supplier_lead_time'] ?? 0),
			'date_added'         => Serializer::datetime($row['date_added'] ?? ''),
			'date_modified'      => Serializer::datetime($row['date_modified'] ?? ''),
		];
	}

	private function requireWarehouse(int $warehouse_id): array {
		$warehouse = new DockercartWarehouse($this->registry);

		$row = $warehouse->getWarehouse($warehouse_id);

		if (!$row) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $row;
	}
}
