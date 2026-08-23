<?php
/**
 * DockerCart Warehouse Supplier Orders admin model: dropship order lines per
 * supplier, deadline computation and status updates.
 */

declare(strict_types=1);

class ModelWarehouseSupplierOrders extends Model {
	/**
	 * Dropship lines: order_product rows whose warehouse is a dropship type.
	 */
	public function getOrders(array $data = []): array {
		$sql = "SELECT op.*, o.order_status_id, os.`name` AS order_status_name, o.`firstname` AS customer_firstname, o.`lastname` AS customer_lastname, COALESCE(wd.`name`, w.`name`) AS warehouse_name, w.`supplier_name`, w.`supplier_phone`, w.`supplier_email`, COALESCE(NULLIF(w.`supplier_lead_time`, 0), ws.`lead_time`, 0) AS `supplier_lead_time`
			FROM `" . DB_PREFIX . "order_product` op
			JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = op.warehouse_id AND w.`type` = 'dropship')
			LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)($this->config->get('config_language_id')) . "')
			LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id)
			LEFT JOIN `" . DB_PREFIX . "order_status` os ON (os.order_status_id = o.order_status_id AND os.language_id = '" . (int)($this->config->get('config_language_id')) . "')
			LEFT JOIN `" . DB_PREFIX . "warehouse_stock` ws ON (ws.warehouse_id = op.warehouse_id AND ws.product_id = op.product_id AND ws.variant_id = op.variant_id)
			WHERE 1 = 1" . $this->filterSql($data);

		$sql .= " ORDER BY op.`order_product_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		$rows = [];

		foreach ($query->rows as $row) {
			$row['deadline'] = $this->computeDeadline($row);
			$rows[] = $row;
		}

		return $rows;
	}

	public function getTotalOrders(array $data = []): int {
		$query = $this->db->query("SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "order_product` op
			JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = op.warehouse_id AND w.`type` = 'dropship')
			LEFT JOIN `" . DB_PREFIX . "warehouse_stock` ws ON (ws.warehouse_id = op.warehouse_id AND ws.product_id = op.product_id AND ws.variant_id = op.variant_id)
			WHERE 1 = 1" . $this->filterSql($data));

		return (int)$query->row['total'];
	}

	/**
	 * Global KPI counters for the summary strip (not affected by list filters).
	 *
	 * @return array{pending: int, ordered: int, shipped: int, overdue: int}
	 */
	public function getSummaryCounts(): array {
		$query = $this->db->query("SELECT COUNT(*) AS total,
				SUM(op.`supplier_status` = 'pending') AS pending,
				SUM(op.`supplier_status` = 'ordered') AS ordered,
				SUM(op.`supplier_status` = 'shipped') AS shipped,
				SUM(op.`supplier_status` <> 'shipped' AND op.`supplier_ordered_date` IS NOT NULL AND " . $this->sqlDeadline() . " < CURDATE()) AS overdue
			FROM `" . DB_PREFIX . "order_product` op
			JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = op.warehouse_id AND w.`type` = 'dropship')
			LEFT JOIN `" . DB_PREFIX . "warehouse_stock` ws ON (ws.warehouse_id = op.warehouse_id AND ws.product_id = op.product_id AND ws.variant_id = op.variant_id)");

		return [
			'pending' => (int)($query->row['pending'] ?? 0),
			'ordered' => (int)($query->row['ordered'] ?? 0),
			'shipped' => (int)($query->row['shipped'] ?? 0),
			'overdue' => (int)($query->row['overdue'] ?? 0),
		];
	}

	public function getDropshipWarehouses(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse` WHERE `type` = 'dropship' AND `status` = '1' ORDER BY `name` ASC");

		return $query->rows;
	}

	/**
	 * Search-as-you-type for the toolbar: dropship orders matched by number /
	 * customer / product name / model.
	 *
	 * @return array<int, array{order_id: int, customer: string, product: string}>
	 */
	public function autocompleteOrders(string $search, int $limit = 8): array {
		$search = trim($search);

		if ($search === '') {
			return [];
		}

		$keyword = $this->db->escape($search);

		$conditions = [
			"op.`name` LIKE '%" . $keyword . "%'",
			"op.`model` LIKE '%" . $keyword . "%'",
			"CONCAT(COALESCE(o.`firstname`, ''), ' ', COALESCE(o.`lastname`, '')) LIKE '%" . $keyword . "%'",
		];

		if (ctype_digit($search)) {
			$conditions[] = "op.`order_id` = '" . (int)$search . "'";
		}

		$query = $this->db->query("SELECT op.`order_id`, CONCAT(COALESCE(o.`firstname`, ''), ' ', COALESCE(o.`lastname`, '')) AS customer, MIN(op.`name`) AS product
			FROM `" . DB_PREFIX . "order_product` op
			JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = op.warehouse_id AND w.`type` = 'dropship')
			LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id)
			WHERE (" . implode(" OR ", $conditions) . ")
			GROUP BY op.`order_id`, o.`firstname`, o.`lastname`
			ORDER BY op.`order_id` DESC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Mark a line as ordered from the supplier (starts the deadline clock from
	 * the supplier lead time).
	 */
	public function markOrdered(int $order_product_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET `supplier_status` = 'ordered', `supplier_ordered_date` = COALESCE(`supplier_ordered_date`, NOW()) WHERE `order_product_id` = '" . (int)$order_product_id . "'");
	}

	public function markShipped(int $order_product_id, string $tracking = ''): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET `supplier_status` = 'shipped', `supplier_tracking` = '" . $this->db->escape($tracking) . "' WHERE `order_product_id` = '" . (int)$order_product_id . "'");
	}

	/**
	 * Deadline date expression: supplier_ordered_date + lead_time, warehouse
	 * supplier_lead_time takes priority over the per-product stock row.
	 */
	private function sqlDeadline(): string {
		return "DATE(op.`supplier_ordered_date` + INTERVAL COALESCE(NULLIF(w.`supplier_lead_time`, 0), ws.`lead_time`, 0) DAY)";
	}

	/**
	 * Shared WHERE branches for the list/count queries.
	 */
	private function filterSql(array $data): string {
		$sql = '';

		if (!empty($data['filter_supplier_id'])) {
			$sql .= " AND w.`warehouse_id` = '" . (int)$data['filter_supplier_id'] . "'";
		}

		if (!empty($data['filter_status'])) {
			$sql .= " AND op.`supplier_status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
		}

		if (!empty($data['filter_ordered'])) {
			$sql .= " AND op.`supplier_ordered_date` " . ($data['filter_ordered'] === 'pending' ? 'IS NULL' : 'IS NOT NULL');
		}

		if (!empty($data['filter_product_id'])) {
			$sql .= " AND op.`product_id` = '" . (int)$data['filter_product_id'] . "'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND op.`order_id` = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_overdue'])) {
			$sql .= " AND op.`supplier_status` <> 'shipped' AND op.`supplier_ordered_date` IS NOT NULL AND " . $this->sqlDeadline() . " < CURDATE()";
		}

		return $sql;
	}

	/**
	 * Deadline = supplier_ordered_date + lead_time (warehouse supplier_lead_time
	 * OR per-line lead_time from the stock row), as deadline for the supplier to
	 * dispatch. Returns '' when not yet ordered.
	 */
	protected function computeDeadline(array $row): string {
		if (empty($row['supplier_ordered_date'])) {
			return '';
		}

		$lead_time = (int)($row['supplier_lead_time'] ?? 0);

		if ($lead_time <= 0) {
			return $row['supplier_ordered_date'];
		}

		return date('Y-m-d', strtotime($row['supplier_ordered_date'] . ' + ' . (int)$lead_time . ' days'));
	}
}
