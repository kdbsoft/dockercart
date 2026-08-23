<?php
/**
 * DockerCart Warehouse Stock admin model: the warehouse x product matrix,
 * AJAX cell updates and the "recalculate totals" drift check.
 */

declare(strict_types=1);

class ModelWarehouseStock extends Model {
	/**
	 * Stock rows for a product/variant across all warehouses.
	 */
	public function getProductStocks(int $product_id, int $variant_id = 0): array {
		$query = $this->db->query("SELECT ws.*, COALESCE(wd.`name`, w.`name`) AS warehouse_name FROM `" . DB_PREFIX . "warehouse_stock` ws LEFT JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = ws.warehouse_id) LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE ws.`product_id` = '" . (int)$product_id . "' AND ws.`variant_id` = '" . (int)$variant_id . "'");

		$map = [];

		foreach ($query->rows as $row) {
			$map[(int)$row['warehouse_id']] = $row;
		}

		return $map;
	}

	/**
	 * Matrix rows keyed by warehouse_id for the admin stock screen.
	 */
	public function getStockMatrix(array $data = []): array {
		[$join_sql, $where_sql] = $this->buildFilterSql($data);

		$sql = "SELECT ws.*, p.model AS product_model, pd.`name` AS product_name, pv.sku AS variant_sku, COALESCE(wd.`name`, w.`name`) AS warehouse_name, w.`type` AS warehouse_type
			FROM `" . DB_PREFIX . "warehouse_stock` ws" . $join_sql . "
			WHERE 1=1" . $where_sql;

		$sql .= " ORDER BY ws.`product_id` ASC, ws.`variant_id` ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 100);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalStock(array $data = []): int {
		[$join_sql, $where_sql] = $this->buildFilterSql($data);

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "warehouse_stock` ws" . $join_sql . "
			WHERE 1=1" . $where_sql;

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Shared JOINs + WHERE for the matrix list / count queries.
	 *
	 * Supported keys: filter_warehouse_id, filter_product_id, filter_name,
	 * filter_model, filter_sku, filter_quantity_min, filter_quantity_max,
	 * filter_unlimited.
	 *
	 * @return array{0: string, 1: string} [join_sql, where_sql]
	 */
	protected function buildFilterSql(array $data): array {
		$join_sql = "
			LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ws.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = ws.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.variant_id = ws.variant_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse` w ON (w.warehouse_id = ws.warehouse_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "')";

		$where_sql = '';

		if (!empty($data['filter_warehouse_id'])) {
			$where_sql .= " AND ws.`warehouse_id` = '" . (int)$data['filter_warehouse_id'] . "'";
		}

		if (!empty($data['filter_product_id'])) {
			$where_sql .= " AND ws.`product_id` = '" . (int)$data['filter_product_id'] . "'";
		}

		if (!empty($data['filter_name'])) {
			$where_sql .= " AND pd.`name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_model'])) {
			$where_sql .= " AND p.`model` LIKE '%" . $this->db->escape($data['filter_model']) . "%'";
		}

		if (!empty($data['filter_sku'])) {
			$where_sql .= " AND pv.`sku` LIKE '%" . $this->db->escape($data['filter_sku']) . "%'";
		}

		if (isset($data['filter_quantity_min']) && (string)$data['filter_quantity_min'] !== '') {
			$where_sql .= " AND ws.`quantity` >= '" . (float)$data['filter_quantity_min'] . "'";
		}

		if (isset($data['filter_quantity_max']) && (string)$data['filter_quantity_max'] !== '') {
			$where_sql .= " AND ws.`quantity` <= '" . (float)$data['filter_quantity_max'] . "'";
		}

		if (isset($data['filter_unlimited']) && (string)$data['filter_unlimited'] !== '') {
			$where_sql .= " AND ws.`unlimited` = '" . (int)$data['filter_unlimited'] . "'";
		}

		return [$join_sql, $where_sql];
	}

	/**
	 * Search-as-you-type for the stock toolbar: products present in the
	 * warehouse matrix, matched by name / model / variant SKU.
	 *
	 * @return array<int, array{product_id:int, name:string, model:string}>
	 */
	public function autocompleteProducts(string $search, int $limit = 8): array {
		$search = trim($search);

		if ($search === '') {
			return [];
		}

		$keyword = $this->db->escape($search);

		$query = $this->db->query("SELECT ws.`product_id`, pd.`name` AS name, p.`model` AS model
			FROM `" . DB_PREFIX . "warehouse_stock` ws
			LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ws.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = ws.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.variant_id = ws.variant_id)
			WHERE pd.`name` LIKE '%" . $keyword . "%' OR p.`model` LIKE '%" . $keyword . "%' OR pv.`sku` LIKE '%" . $keyword . "%'
			GROUP BY ws.`product_id`, pd.`name`, p.`model`
			ORDER BY pd.`name` ASC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Search-as-you-type for position pickers ("product + variant"): simple
	 * products come back as single rows (variant_id = 0), configurable products
	 * as one row per active variant. Variant SKU matches rank above plain
	 * name/model matches. Option-value names are included for labels.
	 *
	 * @return array<int, array{product_id:int, variant_id:int, name:string, model:string, sku:string, option_names:?string}>
	 */
	public function autocompletePositions(string $search, int $limit = 10): array {
		$search = trim($search);

		if ($search === '') {
			return [];
		}

		$keyword = $this->db->escape($search);
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT p.`product_id`, COALESCE(pv.`variant_id`, 0) AS variant_id, pd.`name` AS name, p.`model` AS model, pv.`sku` AS sku,
				(SELECT GROUP_CONCAT(ovd2.`name` ORDER BY pvv2.`option_id` SEPARATOR ', ')
					FROM `" . DB_PREFIX . "product_variant_value` pvv2
					LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd2 ON (ovd2.`option_value_id` = pvv2.`option_value_id` AND ovd2.`language_id` = '" . $language_id . "')
					WHERE pvv2.`variant_id` = pv.`variant_id`) AS option_names
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.`product_id` = p.`product_id` AND pd.`language_id` = '" . $language_id . "')
			LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.`product_id` = p.`product_id` AND pv.`status` = '1')
			WHERE pd.`name` LIKE '%" . $keyword . "%' OR p.`model` LIKE '%" . $keyword . "%' OR pv.`sku` LIKE '%" . $keyword . "%'
			ORDER BY
				CASE WHEN pv.`sku` LIKE '" . $keyword . "%' THEN 0 WHEN pv.`sku` LIKE '%" . $keyword . "%' THEN 1 ELSE 2 END ASC,
				pd.`name` ASC, p.`product_id` ASC, pv.`sort_order` ASC, pv.`variant_id` ASC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Product identity for the active matrix filter (search box label).
	 *
	 * @return array{product_id: int, name?: string, model?: string}|null
	 */
	public function getStockProduct(int $product_id): ?array {
		$query = $this->db->query("SELECT p.`product_id`, pd.`name` AS name, p.`model` AS model
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			WHERE p.`product_id` = '" . (int)$product_id . "'");

		return $query->row ?: null;
	}

	/**
	 * Atomic update of one cell's quantity/unlimited/lead_time, rewriting the
	 * denormalised cache + a movement journal entry (adjustment).
	 */
	public function setCell(int $stock_id, float $quantity, bool $unlimited, int $lead_time): void {
		$row = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . (int)$stock_id . "'");

		if (!$row->num_rows) {
			return;
		}

		$stock = $row->row;
		$old_qty = (float)$stock['quantity'];

		$delta = $quantity - $old_qty;

		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_stock` SET `quantity` = '" . (float)$quantity . "', `unlimited` = '" . (int)(int)$unlimited . "', `lead_time` = '" . (int)$lead_time . "' WHERE `stock_id` = '" . (int)$stock_id . "'");

		if (abs($delta) > 0.0001) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_stock_movement` SET
				`warehouse_id` = '" . (int)$stock['warehouse_id'] . "',
				`product_id` = '" . (int)$stock['product_id'] . "',
				`variant_id` = '" . (int)$stock['variant_id'] . "',
				`type` = 'adjustment',
				`quantity` = '" . (float)$delta . "',
				`reference` = 'admin-cell',
				`user_id` = '" . (int)($this->user ? $this->user->getId() : 0) . "',
				`date_added` = NOW()");
		}

		$warehouse = new \DockercartWarehouse($this->registry);
		$warehouse->recomputeTotals((int)$stock['product_id']);
	}

	/**
	 * Ensure a stock row exists for a product/variant/warehouse (returns id).
	 *
	 * Returns 0 without inserting when asked for a head-level row
	 * (variant_id = 0) of a configurable product: configurables carry no
	 * stock of their own, only their variants do.
	 */
	public function ensureRow(int $warehouse_id, int $product_id, int $variant_id): int {
		$query = $this->db->query("SELECT `stock_id` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

		if ($query->num_rows) {
			return (int)$query->row['stock_id'];
		}

		if (!$variant_id) {
			$configurable = $this->db->query("SELECT `product_id` FROM `" . DB_PREFIX . "product_configurable` WHERE `product_id` = '" . (int)$product_id . "' AND `is_configurable` = '1'");

			if ($configurable->num_rows) {
				return 0;
			}
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_stock` SET `warehouse_id` = '" . (int)$warehouse_id . "', `product_id` = '" . (int)$product_id . "', `variant_id` = '" . (int)$variant_id . "', `quantity` = '0', `unlimited` = '0', `lead_time` = '0'");

		return (int)$this->db->getLastId();
	}

	/**
	 * Resolve a stock row id from its unique (warehouse, product, variant)
	 * key. Returns 0 when the row does not exist.
	 */
	public function findStockId(int $warehouse_id, int $product_id, int $variant_id): int {
		$query = $this->db->query("SELECT `stock_id` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

		return $query->num_rows ? (int)$query->row['stock_id'] : 0;
	}

	/**
	 * Bulk-update stock cells (CSV import). Only rows whose values actually
	 * change are written; quantity deltas get an adjustment movement
	 * (reference 'admin-csv') and denormalised totals are recomputed once per
	 * affected product.
	 *
	 * @param array<int, array{stock_id:int, quantity:float, unlimited:bool, lead_time:int}> $updates
	 * @return int number of rows actually changed
	 */
	public function applyCellUpdates(array $updates): int {
		$changed = 0;
		$product_ids = [];

		foreach ($updates as $update) {
			$row = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_stock` WHERE `stock_id` = '" . (int)$update['stock_id'] . "'");

			if (!$row->num_rows) {
				continue;
			}

			$stock = $row->row;
			$quantity = (float)$update['quantity'];
			$unlimited = (int)$update['unlimited'];
			$lead_time = (int)$update['lead_time'];

			if (abs($quantity - (float)$stock['quantity']) < 0.0001 && $unlimited === (int)$stock['unlimited'] && $lead_time === (int)$stock['lead_time']) {
				continue;
			}

			$delta = $quantity - (float)$stock['quantity'];

			$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_stock` SET `quantity` = '" . $quantity . "', `unlimited` = '" . $unlimited . "', `lead_time` = '" . $lead_time . "' WHERE `stock_id` = '" . (int)$update['stock_id'] . "'");

			if (abs($delta) > 0.0001) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_stock_movement` SET
					`warehouse_id` = '" . (int)$stock['warehouse_id'] . "',
					`product_id` = '" . (int)$stock['product_id'] . "',
					`variant_id` = '" . (int)$stock['variant_id'] . "',
					`type` = 'adjustment',
					`quantity` = '" . $delta . "',
					`reference` = 'admin-csv',
					`user_id` = '" . (int)($this->user ? $this->user->getId() : 0) . "',
					`date_added` = NOW()");
			}

			$product_ids[(int)$stock['product_id']] = true;
			$changed++;
		}

		if ($product_ids) {
			$warehouse = new \DockercartWarehouse($this->registry);

			foreach (array_keys($product_ids) as $product_id) {
				$warehouse->recomputeTotals($product_id);
			}
		}

		return $changed;
	}

	/**
	 * Recompute denormalised product/variant caches and report drift vs the
	 * warehouse source of truth.
	 *
	 * @return array{total:int, drifted:int, details:array}
	 */
	public function recalculate(): array {
		$products = $this->db->query("SELECT DISTINCT `product_id` FROM `" . DB_PREFIX . "warehouse_stock`");

		$warehouse = new \DockercartWarehouse($this->registry);
		$drifted = 0;
		$details = [];

		foreach ($products->rows as $row) {
			$product_id = (int)$row['product_id'];

			$before = $this->getCachedQuantities($product_id);

			$warehouse->recomputeTotals($product_id);

			$after = $this->getCachedQuantities($product_id);

			foreach ($after as $vid => $qty) {
				if (!isset($before[$vid]) || abs((float)$before[$vid] - (float)$qty) > 0.0001) {
					$drifted++;
					$details[] = sprintf('product #%d / variant #%d: %s -> %s', $product_id, $vid, isset($before[$vid]) ? (string)(float)$before[$vid] : 'missing', (string)(float)$qty);
				}
			}
		}

		return ['total' => count($products->rows), 'drifted' => $drifted, 'details' => $details];
	}

	protected function getCachedQuantities(int $product_id): array {
		$query = $this->db->query("SELECT p.`quantity` AS product_qty, pv.`variant_id`, pv.`quantity` AS variant_qty FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.product_id = p.product_id) WHERE p.`product_id` = '" . (int)$product_id . "'");

		$map = [];

		foreach ($query->rows as $row) {
			if ((int)$row['variant_id']) {
				$map[(int)$row['variant_id']] = (float)$row['variant_qty'];
			} else {
				$map[0] = (float)$row['product_qty'];
			}
		}

		return $map;
	}
}