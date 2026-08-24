<?php
/**
 * DockerCart Warehouse (core)
 *
 * Multi-warehouse + dropship allocation, stock source-of-truth management and
 * working-schedule helpers. oc_product.quantity / oc_product_variant.quantity
 * are kept as denormalised SUM caches across warehouses and rewritten on every
 * mutation via recomputeTotals(); the source of truth is always oc_warehouse_stock.
 *
 * config_warehouse_enabled=0 => getWarehouses()/getDefaultWarehouseId() still
 * work (so admin can configure the feature) but allocation returns the legacy
 * single logical warehouse and adjustStock() short-circuits.
 *
 * Dropship warehouses are "unlimited": an owned row with unlimited=1 never
 * blocks allocation (available = +INF). A per-line lead_time can override the
 * warehouse supplier_lead_time for estimated ship date purposes.
 */

declare(strict_types=1);

/** @property \DB $db
 * @property \Config $config
 * @property \Session $session
 * @property \Language $language */
class DockercartWarehouse {
	/** Sentinel quantity for an "unlimited" (dropship) stock row. */
	public const UNLIMITED = 999999;

	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Is the multi-warehouse feature enabled for the storefront?
	 */
	public function isEnabled(): bool {
		return (bool)$this->config->get('config_warehouse_enabled');
	}

	/**
	 * Ordered list of active warehouses (status=1) by priority DESC, sort_order
	 * ASC. When the feature is disabled only the legacy default is returned.
	 */
	public function getWarehouses(): array {
		$sql = "SELECT w.*, COALESCE(wd.name, w.name) AS name, COALESCE(wd.city, w.city) AS city, COALESCE(wd.address_1, w.address_1) AS address_1, COALESCE(wd.address_2, w.address_2) AS address_2 FROM `" . DB_PREFIX . "warehouse` w LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE w.`status` = '1' ORDER BY w.`priority` DESC, w.`sort_order` ASC, w.`warehouse_id` ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * All warehouses regardless of status (admin lists).
	 */
	public function getAllWarehouses(): array {
		$sql = "SELECT w.*, COALESCE(wd.name, w.name) AS name, COALESCE(wd.city, w.city) AS city, COALESCE(wd.address_1, w.address_1) AS address_1, COALESCE(wd.address_2, w.address_2) AS address_2 FROM `" . DB_PREFIX . "warehouse` w LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "') ORDER BY w.`is_default` DESC, w.`priority` DESC, w.`sort_order` ASC, w.`warehouse_id` ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Single warehouse by id, or null.
	 */
	public function getWarehouse(int $warehouse_id): ?array {
		$sql = "SELECT w.*, COALESCE(wd.name, w.name) AS name, COALESCE(wd.city, w.city) AS city, COALESCE(wd.address_1, w.address_1) AS address_1, COALESCE(wd.address_2, w.address_2) AS address_2 FROM `" . DB_PREFIX . "warehouse` w LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE w.`warehouse_id` = '" . (int)$warehouse_id . "'";

		$query = $this->db->query($sql);

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Default warehouse id (1 when the feature is off / a default is unset).
	 */
	public function getDefaultWarehouseId(): int {
		$def = $this->config->get('config_warehouse_default_id');

		if ($def) {
			return (int)$def;
		}

		$query = $this->db->query("SELECT `warehouse_id` FROM `" . DB_PREFIX . "warehouse` WHERE `is_default` = '1' ORDER BY `warehouse_id` ASC LIMIT 1");

		if ($query->num_rows) {
			return (int)$query->row['warehouse_id'];
		}

		return 1;
	}

	/**
	 * Available quantity of a line on a warehouse = stock - held reservations
	 * of that warehouse. Dropship (unlimited) rows return +INF. When warehouses
	 * are disabled all stock is treated as available on the default warehouse.
	 *
	 * @return float PHP_FLOAT_MAX for unlimited
	 */
	public function getAvailableForLine(int $product_id, int $variant_id, int $warehouse_id): float {
		$stock_query = $this->db->query("SELECT `quantity`, `unlimited` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

		$stock = (float)($stock_query->num_rows ? $stock_query->row['quantity'] : 0);
		$unlimited = (bool)($stock_query->num_rows ? $stock_query->row['unlimited'] : 0);

		if ($unlimited) {
			return PHP_FLOAT_MAX;
		}

		$reserved_query = $this->db->query("SELECT COALESCE(SUM(`quantity`), 0) AS reserved FROM `" . DB_PREFIX . "stock_reservation` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "' AND (order_id IS NOT NULL OR expires_at > NOW())");

		return max(0.0, $stock - (float)$reserved_query->row['reserved']);
	}

	/**
	 * Total stock of a product line across all warehouses (source of truth).
	 * Unlimited rows contribute the UNLIMITED sentinel.
	 */
	public function getStockSum(int $product_id, int $variant_id = 0): float {
		$query = $this->db->query("SELECT SUM(`quantity`) AS total, MIN(`unlimited`) AS unlimited FROM `" . DB_PREFIX . "warehouse_stock` WHERE `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "'");

		if (!$query->num_rows) {
			return 0.0;
		}

		if ((int)$query->row['unlimited']) {
			return (float)self::UNLIMITED;
		}

		return (float)($query->row['total'] ?? 0);
	}

	/**
	 * Catalog-facing quantity edit: set the total stock of a line by applying
	 * the difference to the DEFAULT warehouse. Keeps product-form / inline-edit
	 * / variant-matrix writes consistent with the warehouse source of truth
	 * instead of touching the denormalised cache directly. No-op when the
	 * total already matches; the stock row is auto-created by adjustStock().
	 */
	public function setTotalQuantity(int $product_id, float $new_total, int $variant_id = 0, array $context = []): void {
		$delta = $new_total - $this->getStockSum($product_id, $variant_id);

		if (abs($delta) < 0.0001) {
			return;
		}

		$this->adjustStock($this->getDefaultWarehouseId(), $product_id, $variant_id, $delta, 'adjustment', $context);
	}

	/**
	 * Allocation of cart lines to warehouses.
	 *
	 * Lines: array of ['product_id'=>int,'variant_id'=>int,'quantity'=>float,
	 *                   'subtract'=>bool,'name'=>string,'warehouse_id'=>?int]
	 *
	 * Behaviour (config_warehouse_enabled): candidates are active warehouses
	 * whose available >= qty (dropship rows participate when
	 * config_warehouse_dropship_checkout, ordered priority DESC). Default is
	 * "one warehouse per order": the first candidate that can take the whole
	 * cart wins. When config_warehouse_split_allowed, falls back to per-line
	 * allocation. A forced warehouse (seller moved a line, or pickup chosen)
	 * is preferred and reported separately.
	 *
	 * @return array{alloc: array<string,int>, lines: array<string,array>,
	 *                split: bool, estimate: array<string,string>}
	 */
	public function allocate(array $lines): array {
		$alloc = [];
		$estimate = [];
		$split = false;

		if (!$this->isEnabled()) {
			// Legacy single logical warehouse.
			$default = $this->getDefaultWarehouseId();

			foreach ($lines as $idx => $line) {
				$alloc[$idx] = (int)($line['warehouse_id'] > 0 ? $line['warehouse_id'] : $default);
			}

			return ['alloc' => $alloc, 'lines' => $lines, 'split' => false, 'estimate' => $estimate];
		}

		$warehouses = $this->getWarehouses();
		$candidates = [];

		foreach ($warehouses as $w) {
			if ($w['type'] === 'dropship' && !$this->config->get('config_warehouse_dropship_checkout')) {
				continue;
			}

			$candidates[] = $w;
		}

		if (!$candidates) {
			// No usable warehouses: fall back to default (best effort).
			$default = $this->getDefaultWarehouseId();

			foreach ($lines as $idx => $line) {
				$alloc[$idx] = $default;
			}

			return ['alloc' => $alloc, 'lines' => $lines, 'split' => false, 'estimate' => $estimate];
		}

		// Can one warehouse close the whole cart? Any line explicitly forced to
		// a specific warehouse disables whole-cart satisfaction.
		$forced_all_good = true;
		$any_forced = false;

		foreach ($lines as $idx => $line) {
			if (!empty($line['warehouse_id'])) {
				$any_forced = true;

				if (!$this->lineFits($line, (int)$line['warehouse_id'], $candidates)) {
					$forced_all_good = false;
				}
			}
		}

		if (!$any_forced || $forced_all_good) {
			foreach ($candidates as $w) {
				if ($this->cartFits($lines, $w)) {
					$warehouse_id = (int)$w['warehouse_id'];
					$split = false;

					foreach ($lines as $idx => $line) {
						$alloc[$idx] = $warehouse_id;
						$estimate[$idx] = $this->estimateShipDate($warehouse_id, $line, null);
					}

					return ['alloc' => $alloc, 'lines' => $lines, 'split' => $split, 'estimate' => $estimate];
				}
			}
		}

		// Split mode invented per-line (or a forced warehouse that could not
		// take everything): allocate each line to the best possible warehouse.
		$split = true;

		foreach ($lines as $idx => $line) {
			$forced = (int)($line['warehouse_id'] ?? 0);

			if ($forced > 0) {
				$alloc[$idx] = $forced;
				$estimate[$idx] = $this->estimateShipDate($forced, $line, null);
				continue;
			}

			$best = null;

			foreach ($candidates as $w) {
				if ($this->lineFits($line, (int)$w['warehouse_id'], $candidates)) {
					$best = (int)$w['warehouse_id'];
					break;
				}
			}

			$alloc[$idx] = $best ?: $this->getDefaultWarehouseId();
			$estimate[$idx] = $this->estimateShipDate($alloc[$idx], $line, null);
		}

		return ['alloc' => $alloc, 'lines' => $lines, 'split' => $split, 'estimate' => $estimate];
	}

	/**
	 * Does a single cart all fit on the given warehouse?
	 */
	protected function cartFits(array $lines, array $warehouse): bool {
		foreach ($lines as $line) {
			// A line forced to another warehouse can never sit on $warehouse.
			if (!empty($line['warehouse_id']) && (int)$line['warehouse_id'] !== (int)$warehouse['warehouse_id']) {
				return false;
			}

			if ($line['subtract'] && !$this->lineFits($line, (int)$warehouse['warehouse_id'], [$warehouse])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Can a single line be taken by the given warehouse? Non-subtract lines
	 * (no stock tracking / preorder) always fit.
	 */
	protected function lineFits(array $line, int $warehouse_id, array $candidates = []): bool {
		if (empty($line['subtract'])) {
			return true;
		}

		if ($candidates) {
			$found = false;

			foreach ($candidates as $w) {
				if ((int)$w['warehouse_id'] === $warehouse_id) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				return false;
			}
		}

		return $this->getAvailableForLine((int)$line['product_id'], (int)$line['variant_id'], $warehouse_id) >= (float)$line['quantity'];
	}

	/**
	 * Estimated ship date for a line from a warehouse: today + prepare_days
	 * working days (through the schedule + holidays), plus a dropship lead time
	 * override when the stock row or supplier carries one.
	 */
	public function estimateShipDate(int $warehouse_id, array $line, ?string $from = null): string {
		$from = $from ?: date('Y-m-d');
		$warehouse = $this->getWarehouse($warehouse_id);

		if (!$warehouse) {
			return $from;
		}

		// Lead time = stock-row override > supplier lead time > 0.
		$stock = $this->db->query("SELECT `lead_time` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$line['product_id'] . "' AND `variant_id` = '" . (int)$line['variant_id'] . "'");

		$lead_time = $stock->num_rows ? max(0, (int)$stock->row['lead_time']) : 0;

		if ($lead_time <= 0) {
			$lead_time = (int)$warehouse['supplier_lead_time'];
		}

		$prepare_days = (int)$warehouse['prepare_days'] + $lead_time;

		$dt = new \DateTime($from);
		$days = 0;

		while ($days < $prepare_days) {
			$dt->modify('+1 day');

			if ($this->isWorkingDay($warehouse_id, $dt)) {
				$days++;
			}
		}

		return $dt->format('Y-m-d');
	}

	/**
	 * Nearest pickup slot for a warehouse with self-pickup: the next open
	 * window (day of week + time) from the reference instant. Returns null when
	 * pickup is not configured or the schedule has no windows.
	 */
	public function nextPickupSlot(int $warehouse_id, ?string $from = null): ?array {
		$warehouse = $this->getWarehouse($warehouse_id);

		if (!$warehouse || !$warehouse['allow_pickup']) {
			return null;
		}

		$from = $from ?: date('Y-m-d H:i:s');

		if (!preg_match('/\d{2}:\d{2}/', (string)$from)) {
			$from .= ' 00:00:00';
		}

		$dt = new \DateTime($from);

		// Look ahead a day at a time until we find an open day with a window.
		for ($i = 0; $i < 30; $i++) {
			$dow = (int)$dt->format('N'); // 1=Mon..7=Sun

			if (!$this->isWorkingDay($warehouse_id, $dt)) {
				$dt->modify('+1 day');

				continue;
			}

			$schedule = $this->db->query("SELECT `schedule_id` FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `day_of_week` = '" . (int)$dow . "' AND `is_open` = '1'");

			if (!$schedule->num_rows) {
				// No explicit schedule row (isWorkingDay fell back to the
				// default Mon-Sat): assume a whole working day 09:00-18:00.
				return [
					'date' => $dt->format('Y-m-d'),
					'time_from' => '09:00',
					'time_to' => '18:00',
				];
			}

			$windows = $this->db->query("SELECT `time_from`, `time_to` FROM `" . DB_PREFIX . "warehouse_schedule_window` WHERE `schedule_id` = '" . (int)$schedule->row['schedule_id'] . "' ORDER BY `time_from` ASC");

			// No explicit windows => open the whole working day (09:00-18:00).
			if (!$windows->num_rows) {
				return [
					'date' => $dt->format('Y-m-d'),
					'time_from' => '09:00',
					'time_to' => '18:00',
				];
			}

			$today = $dt->format('Y-m-d');
			$now_time = $dt->format('H:i:s');

			foreach ($windows->rows as $window) {
				// Prefer a window later today, else the first window of the day.
				if ($window['time_from'] > $now_time) {
					return [
						'date' => $today,
						'time_from' => substr((string)$window['time_from'], 0, 5),
						'time_to' => substr((string)$window['time_to'], 0, 5),
					];
				}
			}

			// All today's windows already passed: first window tomorrow.
			$first = $windows->rows[0];

			// Advance the reference to the start of the next day.
			$dt->modify('+1 day')->setTime(0, 0);

			return [
				'date' => $dt->format('Y-m-d'),
				'time_from' => substr((string)$first['time_from'], 0, 5),
				'time_to' => substr((string)$first['time_to'], 0, 5),
			];
		}

		return null;
	}

	/**
	 * Is the given date a working day for the warehouse? A warehouse-specific
	 * holiday overrides the week schedule; a shared holiday (warehouse_id=0)
	 * applies to every warehouse that has no specific holiday for that date.
	 */
	public function isWorkingDay(int $warehouse_id, \DateTime $date): bool {
		$date_str = $date->format('Y-m-d');
		$dow = (int)$date->format('N');

		// Holidays: specific for the warehouse, else shared.
		$holiday = $this->db->query("SELECT `is_open` FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `date` = '" . $this->db->escape($date_str) . "' AND (`warehouse_id` = '" . (int)$warehouse_id . "' OR `warehouse_id` = '0') ORDER BY CASE WHEN `warehouse_id` = '" . (int)$warehouse_id . "' THEN 0 ELSE 1 END LIMIT 1");

		if ($holiday->num_rows) {
			return (bool)$holiday->row['is_open'];
		}

		// No specific schedule row => assume open unless a weekday row says closed.
		$schedule = $this->db->query("SELECT `is_open` FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `day_of_week` = '" . (int)$dow . "'");

		if ($schedule->num_rows) {
			return (bool)$schedule->row['is_open'];
		}

		// Default: closed on Sunday, open Mon-Sat.
		return $dow !== 7;
	}

	/**
	 * Atomic stock mutation. Locks the stock row, applies a signed delta,
	 * writes a movement journal entry and, when tracking is enabled, rewrites
	 * the denormalised product/variant quantity caches.
	 *
	 * @return float resulting quantity
	 */
	public function adjustStock(int $warehouse_id, int $product_id, int $variant_id, float $delta, string $type, array $context = []): float {
		$warehouse_id = (int)$warehouse_id;
		$product_id = (int)$product_id;
		$variant_id = (int)$variant_id;

		// Configurable products carry no head-level stock: their variants do.
		if (!$variant_id && $this->isConfigurableProduct($product_id)) {
			return 0.0;
		}

		$allowed = ['inbound', 'outbound', 'adjustment', 'transfer_in', 'transfer_out', 'order_subtract', 'order_restock', 'return'];

		if (!in_array($type, $allowed, true)) {
			$type = 'adjustment';
		}

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_stock` (`warehouse_id`, `product_id`, `variant_id`, `quantity`, `unlimited`, `lead_time`)
				VALUES ('" . (int)$warehouse_id . "', '" . (int)$product_id . "', '" . (int)$variant_id . "', '0', '0', '0')
				ON DUPLICATE KEY UPDATE `stock_id` = LAST_INSERT_ID(`stock_id`)");

			$lock = $this->db->query("SELECT `stock_id`, `quantity`, `unlimited` FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `product_id` = '" . (int)$product_id . "' AND `variant_id` = '" . (int)$variant_id . "' FOR UPDATE");

			$new_qty = max(0.0, (float)$lock->row['quantity'] + $delta);

			$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_stock` SET `quantity` = '" . (float)$new_qty . "' WHERE `stock_id` = '" . (int)$lock->row['stock_id'] . "'");

			$this->recordMovement($warehouse_id, $product_id, $variant_id, $delta, $type, $context);

			// Denormalised cache.
			if ($this->isEnabled()) {
				$this->recomputeTotals($product_id);
			}

			$this->db->query("COMMIT");
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");

			throw $e;
		}

		return $new_qty;
	}

	/**
	 * Move stock between warehouses atomically (transfer completion / manual
	 * order-line move). Writes transfer_out/transfer_in movements and, when a
	 * transfer exists, marks its items fulfilled.
	 */
	public function moveStock(int $from_warehouse_id, int $to_warehouse_id, int $product_id, int $variant_id, float $quantity, array $context = []): bool {
		if ($from_warehouse_id === $to_warehouse_id || $quantity <= 0) {
			return false;
		}

		$this->db->query("START TRANSACTION");

		try {
			$this->adjustStock($from_warehouse_id, $product_id, $variant_id, -$quantity, 'transfer_out', $context);
			$this->adjustStock($to_warehouse_id, $product_id, $variant_id, $quantity, 'transfer_in', $context);

			$this->db->query("COMMIT");
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");

			throw $e;
		}

		return true;
	}

	/**
	 * Recompute the denormalised product/variant quantity cache from the stock
	 * source of truth. Drift detection lives in auditAll() and the admin stock
	 * screen "Recalculate"; this method only rewrites the caches and never
	 * writes movement rows.
	 */
	public function recomputeTotals(int $product_id): void {
		$product_id = (int)$product_id;
		$sentinel = (int)(string)self::UNLIMITED;
		$is_configurable = $this->isConfigurableProduct($product_id);

		// Sum per variant across warehouses (join for variant status).
		$variant_rows = $this->db->query("SELECT ws.`variant_id`, MIN(ws.`unlimited`) AS has_unlimited, SUM(ws.`quantity`) AS total, MAX(pv.`status`) AS variant_status FROM `" . DB_PREFIX . "warehouse_stock` ws LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.`variant_id` = ws.`variant_id`) WHERE ws.`product_id` = '" . (int)$product_id . "' GROUP BY ws.`variant_id`");

		$variant_totals = [];

		foreach ($variant_rows->rows as $row) {
			$vid = (int)$row['variant_id'];

			$variant_totals[$vid] = [
				'unlimited' => (bool)$row['has_unlimited'],
				'total' => (float)$row['total'],
				'active' => $vid === 0 || (int)$row['variant_status'] === 1,
			];
		}

		if ($is_configurable) {
			// Configurable products carry no head-level stock: the admin card
			// displays SUM(active variants), so the cache must match exactly.
			unset($variant_totals[0]);

			$product_qty = 0;
			$any_unlimited_active = false;

			foreach ($variant_totals as $info) {
				if (!$info['active']) {
					continue;
				}

				if ($info['unlimited']) {
					$any_unlimited_active = true;
					break;
				}

				$product_qty += (int)round($info['total']);
			}

			if ($any_unlimited_active) {
				$product_qty = $sentinel;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . $product_qty . "' WHERE `product_id` = '" . (int)$product_id . "'");
		} elseif (isset($variant_totals[0])) {
			// Simple (variant_id=0) total feeds oc_product.quantity.
			$product_qty = $sentinel;

			if (!$variant_totals[0]['unlimited']) {
				$product_qty = (int)round($variant_totals[0]['total']);

				foreach ($variant_totals as $vid => $info) {
					if ($vid === 0 || $info['unlimited']) {
						continue;
					}

					$product_qty += (int)round($info['total']);
				}
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . (int)$product_qty . "' WHERE `product_id` = '" . (int)$product_id . "'");
		}

		foreach ($variant_totals as $vid => $info) {
			if ($vid === 0) {
				continue;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "product_variant` SET `quantity` = '" . (float)$info['total'] . "' WHERE `variant_id` = '" . (int)$vid . "'");
		}
	}

	/**
	 * Whether the product is configurable (its variants sell, not the head row).
	 */
	public function isConfigurableProduct(int $product_id): bool {
		$query = $this->db->query("SELECT `product_id` FROM `" . DB_PREFIX . "product_configurable` WHERE `product_id` = '" . (int)$product_id . "' AND `is_configurable` = '1'");

		return $query->num_rows > 0;
	}

	/**
	 * Write a stock movement journal row.
	 */
	protected function recordMovement(int $warehouse_id, int $product_id, int $variant_id, float $delta, string $type, array $context): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_stock_movement` SET
			`warehouse_id` = '" . (int)$warehouse_id . "',
			`product_id` = '" . (int)$product_id . "',
			`variant_id` = '" . (int)$variant_id . "',
			`type` = '" . $this->db->escape($type) . "',
			`quantity` = '" . (float)$delta . "',
			`reference` = '" . $this->db->escape((string)($context['reference'] ?? '')) . "',
			`order_id` = '" . (int)($context['order_id'] ?? 0) . "',
			`transfer_id` = '" . (int)($context['transfer_id'] ?? 0) . "',
			`user_id` = '" . (int)($context['user_id'] ?? 0) . "',
			`comment` = '" . $this->db->escape((string)($context['comment'] ?? '')) . "',
			`date_added` = NOW()");
	}

	/**
	 * Daily audit: recompute every product cache and report how many cached
	 * values drifted from the warehouse source of truth. Report-only: drift is
	 * healed by the recompute but never journaled — the movement log is a
	 * physical-stock history, not a cache-changelog. The same check on demand:
	 * admin stock screen "Recalculate" (ModelWarehouseStock::recalculate()).
	 *
	 * @return array{checked:int, drifted:int}
	 */
	public function auditAll(): array {
		$products = $this->db->query("SELECT DISTINCT `product_id` FROM `" . DB_PREFIX . "warehouse_stock`");

		$checked = 0;
		$drifted = 0;

		foreach ($products->rows as $row) {
			$product_id = (int)$row['product_id'];

			$before = $this->getCachedQuantities($product_id);

			$this->recomputeTotals($product_id);

			$after = $this->getCachedQuantities($product_id);

			foreach ($after as $vid => $qty) {
				if (!isset($before[$vid]) || abs((float)$before[$vid] - (float)$qty) > 0.0001) {
					$drifted++;
				}
			}

			$checked++;
		}

		return ['checked' => $checked, 'drifted' => $drifted];
	}

	/**
	 * Cached product/variant quantities keyed by variant id (0 = head row).
	 *
	 * @return array<int, float>
	 */
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