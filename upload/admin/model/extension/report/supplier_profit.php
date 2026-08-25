<?php
/**
 * DockerCart dropship supplier profit report model: per-supplier revenue,
 * purchase cost and gross margin over dropship order lines.
 *
 * Revenue = SUM(order_product.total), purchase = SUM(supplier_cost * quantity)
 * over lines with a known supplier_cost (NULL prices are counted separately).
 */

declare(strict_types=1);

class ModelExtensionReportSupplierProfit extends Model {
	public function getSupplierProfit(array $data = []): array {
		$sql = "SELECT w.`warehouse_id`, COALESCE(NULLIF(w.`supplier_name`, ''), w.`name`) AS supplier, COUNT(*) AS lines_total, SUM(op.`quantity`) AS units, SUM(op.`total`) AS revenue, SUM(CASE WHEN op.`supplier_cost` IS NOT NULL THEN op.`total` ELSE 0 END) AS revenue_known, SUM(CASE WHEN op.`supplier_cost` IS NOT NULL THEN op.`supplier_cost` * op.`quantity` ELSE 0 END) AS purchase, SUM(op.`supplier_cost` IS NULL) AS lines_unknown_cost
			" . $this->sqlFrom($data) . "
			GROUP BY w.`warehouse_id`, COALESCE(NULLIF(w.`supplier_name`, ''), w.`name`)
			ORDER BY `revenue` DESC, w.`warehouse_id` ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalSupplierProfit(array $data = []): int {
		$query = $this->db->query("SELECT COUNT(DISTINCT w.`warehouse_id`) AS total " . $this->sqlFrom($data));

		return (int)$query->row['total'];
	}

	/**
	 * Grand totals across all matching suppliers (no GROUP BY).
	 */
	public function getProfitTotals(array $data = []): array {
		$query = $this->db->query("SELECT '' AS supplier, COUNT(*) AS lines_total, SUM(op.`quantity`) AS units, SUM(op.`total`) AS revenue, SUM(CASE WHEN op.`supplier_cost` IS NOT NULL THEN op.`total` ELSE 0 END) AS revenue_known, SUM(CASE WHEN op.`supplier_cost` IS NOT NULL THEN op.`supplier_cost` * op.`quantity` ELSE 0 END) AS purchase, SUM(op.`supplier_cost` IS NULL) AS lines_unknown_cost
			" . $this->sqlFrom($data));

		return $query->row;
	}

	/**
	 * Shared FROM/WHERE for the profit queries.
	 */
	private function sqlFrom(array $data): string {
		$sql = "FROM `" . DB_PREFIX . "order_product` op
			JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = op.warehouse_id AND w.`type` = 'dropship')
			LEFT JOIN `" . DB_PREFIX . "order` o ON (o.order_id = op.order_id)
			WHERE 1 = 1";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.`date_added`) >= DATE('" . $this->db->escape((string)$data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.`date_added`) <= DATE('" . $this->db->escape((string)$data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_status'])) {
			$sql .= " AND op.`supplier_status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
		}

		return $sql;
	}
}
