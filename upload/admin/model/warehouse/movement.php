<?php
/**
 * DockerCart Warehouse Movement admin model: inbound/outbound/inventory forms
 * and the filtered movement journal + per-position running balance.
 */

declare(strict_types=1);

class ModelWarehouseMovement extends Model {
	/**
	 * Apply a manual inbound/outbound/adjustment movement via the core library
	 * (writes journal + rewrites the denormalised cache).
	 */
	public function addMovement(array $data): void {
		$warehouse = new \DockercartWarehouse($this->registry);

		$type = (string)($data['type'] ?? 'adjustment');
		$sign = in_array($type, ['inbound', 'adjustment'], true) ? 1 : -1;
		$quantity = (float)($data['quantity'] ?? 0) * $sign;

		$warehouse->adjustStock(
			(int)$data['warehouse_id'],
			(int)$data['product_id'],
			(int)($data['variant_id'] ?? 0),
			$quantity,
			$type,
			[
				'reference' => (string)($data['reference'] ?? ''),
				'comment' => (string)($data['comment'] ?? ''),
				'user_id' => (int)($this->user ? $this->user->getId() : 0),
			]
		);
	}

	public function getMovements(array $data = []): array {
		$sql = "SELECT m.*, p.model AS product_model, pd.name AS product_name, pv.sku AS variant_sku, u.username, COALESCE(wd.`name`, w.`name`) AS warehouse_name
			FROM `" . DB_PREFIX . "warehouse_stock_movement` m
			LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = m.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = m.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.variant_id = m.variant_id)
			LEFT JOIN `" . DB_PREFIX . "user` u ON (u.user_id = m.user_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = m.warehouse_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			WHERE 1 = 1" . $this->buildFilterSql($data);

		$sql .= " ORDER BY m.`date_added` DESC, m.`movement_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalMovements(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "warehouse_stock_movement` m WHERE 1 = 1" . $this->buildFilterSql($data);

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Aggregate journal stats for the current filter: inbound/outbound unit sums,
	 * signed net change and the movement count (independent of pagination).
	 */
	public function getMovementSummary(array $data = []): array {
		$sql = "SELECT COUNT(*) AS total,
				COALESCE(SUM(CASE WHEN m.`quantity` > 0 THEN m.`quantity` ELSE 0 END), 0) AS inbound,
				COALESCE(ABS(SUM(CASE WHEN m.`quantity` < 0 THEN m.`quantity` ELSE 0 END)), 0) AS outbound,
				COALESCE(SUM(m.`quantity`), 0) AS net
			FROM `" . DB_PREFIX . "warehouse_stock_movement` m
			WHERE 1 = 1" . $this->buildFilterSql($data);

		$query = $this->db->query($sql);

		return [
			'total' => (int)$query->row['total'],
			'inbound' => (string)$query->row['inbound'],
			'outbound' => (string)$query->row['outbound'],
			'net' => (string)$query->row['net'],
		];
	}

	/**
	 * Shared journal WHERE clause (all filters optional).
	 */
	private function buildFilterSql(array $data): string {
		$sql = '';

		if (!empty($data['filter_warehouse_id'])) {
			$sql .= " AND m.`warehouse_id` = '" . (int)$data['filter_warehouse_id'] . "'";
		}

		if (!empty($data['filter_product_id'])) {
			$sql .= " AND m.`product_id` = '" . (int)$data['filter_product_id'] . "'";
		}

		if (!empty($data['filter_type'])) {
			$sql .= " AND m.`type` = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND m.`order_id` = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_date_from'])) {
			$sql .= " AND m.`date_added` >= '" . $this->db->escape($data['filter_date_from']) . " 00:00:00'";
		}

		if (!empty($data['filter_date_to'])) {
			$sql .= " AND m.`date_added` <= '" . $this->db->escape($data['filter_date_to']) . " 23:59:59'";
		}

		return $sql;
	}

	/**
	 * Running balance ledger for one position (product [+ variant] [+ warehouse]).
	 */
	public function getStockMap(array $data = []): array {
		$sql = "SELECT m.*, p.model AS product_model, pv.sku AS variant_sku, COALESCE(wd.`name`, w.`name`) AS warehouse_name
			FROM `" . DB_PREFIX . "warehouse_stock_movement` m
			LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = m.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.variant_id = m.variant_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = m.warehouse_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			WHERE m.`product_id` = '" . (int)$data['product_id'] . "'";

		if (!empty($data['variant_id'])) {
			$sql .= " AND m.`variant_id` = '" . (int)$data['variant_id'] . "'";
		}

		if (!empty($data['warehouse_id'])) {
			$sql .= " AND m.`warehouse_id` = '" . (int)$data['warehouse_id'] . "'";
		}

		$sql .= " ORDER BY m.`date_added` ASC, m.`movement_id` ASC";

		$query = $this->db->query($sql);

		$lines = [];
		$balance = 0.0;

		foreach ($query->rows as $row) {
			$balance += (float)$row['quantity'];
			$row['balance'] = $balance;
			$lines[] = $row;
		}

		return $lines;
	}
}