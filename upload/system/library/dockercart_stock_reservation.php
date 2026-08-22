<?php
/**
 * DockerCart Stock Reservation
 *
 * Holds product quantities for a configurable window when a customer enters
 * the checkout flow, so two customers cannot both check out the last item
 * under high traffic. Unbound holds (order_id IS NULL) expire via expires_at
 * and are swept by the scheduler task dockercart_reservation_cleanup. Once an
 * order is created the holds are bound to its order_id and no longer depend on
 * expires_at: they persist until stock is actually subtracted
 * (processing/complete), the order is cancelled or refunded, or the cleanup
 * sweep releases holds of orders stale beyond config_stock_reserve_stale_days
 * that never reached a fulfilled status.
 *
 * Availability math: available = stock - SUM(active holds), where active holds
 * are unbound rows of OTHER sessions plus ALL order-bound rows (an order has
 * committed the stock, even from the same session's later checkout). Bound
 * rows count regardless of expires_at.
 *
 * Configuration:
 *   - config_stock_reserve_enabled    (global toggle)
 *   - config_stock_reserve_minutes    (global hold window, default 30)
 *   - config_stock_reserve_stale_days (release bound holds of orders not in a
 *                                      fulfilled status after N days; 0 = off)
 *   - oc_dockercart_universal_payment.reserve_minutes (per-method override:
 *     NULL = global, 0 = no hold for this method, N = custom minutes)
 */

declare(strict_types=1);

/** @property \DB $db
 * @property \Config $config
 * @property \Session $session */
class DockercartStockReservation {
	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Is the reservation feature enabled globally?
	 */
	public function isEnabled(): bool {
		if (!$this->config->get('config_stock_reserve_enabled')) {
			return false;
		}

		// When out-of-stock checkout is explicitly allowed, holds make no sense.
		if ($this->config->get('config_stock_checkout')) {
			return false;
		}

		return true;
	}

	/**
	 * Global hold window in minutes (default 30 when unset/invalid).
	 */
	public function getDefaultTtlMinutes(): int {
		$minutes = (int)$this->config->get('config_stock_reserve_minutes');

		return $minutes > 0 ? $minutes : 30;
	}

	/**
	 * Per-payment-method hold window. Payment quote code format is
	 * "dockercart_universal.dockercart_universal_<method_id>".
	 * Returns null when the method has no override (use global), 0 when the
	 * method must not hold stock, or N custom minutes.
	 */
	public function getMethodTtlMinutes(?string $payment_code): ?int {
		if (!$payment_code) {
			return null;
		}

		$parts = explode('.', $payment_code);
		$last = end($parts);

		if (strpos((string)$last, 'dockercart_universal_') !== 0) {
			return null;
		}

		$method_id = (int)substr((string)$last, strlen('dockercart_universal_'));

		if ($method_id <= 0) {
			return null;
		}

		$query = $this->db->query("SELECT reserve_minutes FROM `" . DB_PREFIX . "dockercart_universal_payment` WHERE method_id = '" . (int)$method_id . "'");

		if (!$query->num_rows || $query->row['reserve_minutes'] === null || $query->row['reserve_minutes'] === '') {
			return null;
		}

		return (int)$query->row['reserve_minutes'];
	}

	/**
	 * Reserved quantities keyed by "product_id:variant_id" for the given
	 * product ids (all variants of those products are included). By default the
	 * caller's own unbound holds are excluded (they do not block the own cart);
	 * order-bound holds always count. Pass $exclude_session = false to count
	 * every active hold — used by the admin, which inspects stock from the
	 * seller's perspective and must see holds made in the same browser session.
	 *
	 * @param array $product_ids
	 * @param string|null $session_id
	 * @param bool $exclude_session
	 * @return array<string, float>
	 */
	public function getReservedByProductIds(array $product_ids, ?string $session_id = null, bool $exclude_session = true): array {
		$product_ids = array_values(array_unique(array_map('intval', $product_ids)));
		$product_ids = array_filter($product_ids, function ($id) {
			return $id > 0;
		});

		if (empty($product_ids)) {
			return [];
		}

		$session_id = $session_id ?: (string)$this->session->getId();

		$in = implode(',', $product_ids);

		$session_filter = $exclude_session
			? " AND (order_id IS NOT NULL OR session_id <> '" . $this->db->escape($session_id) . "')"
			: '';

		$query = $this->db->query("SELECT product_id, variant_id, SUM(quantity) AS reserved FROM `" . DB_PREFIX . "stock_reservation` WHERE (order_id IS NOT NULL OR expires_at > NOW()) AND product_id IN (" . $in . ")" . $session_filter . " GROUP BY product_id, variant_id");

		$map = [];

		foreach ($query->rows as $row) {
			$map[(int)$row['product_id'] . ':' . (int)$row['variant_id']] = (float)$row['reserved'];
		}

		return $map;
	}

	/**
	 * Reserved quantity summed per product (ignoring variant), keyed by
	 * product_id. Used by the admin product picker to show how much of a
	 * simple product (or a whole configurable product's stock) is currently
	 * held in active checkout reservations.
	 *
	 * @param array $product_ids
	 * @return array<int, float>
	 */
	public function getReservedTotalByProductIds(array $product_ids): array {
		$product_ids = array_values(array_unique(array_map('intval', $product_ids)));
		$product_ids = array_filter($product_ids, function ($id) {
			return $id > 0;
		});

		if (empty($product_ids)) {
			return [];
		}

		$in = implode(',', $product_ids);

		$query = $this->db->query("SELECT product_id, SUM(quantity) AS reserved FROM `" . DB_PREFIX . "stock_reservation` WHERE (order_id IS NOT NULL OR expires_at > NOW()) AND product_id IN (" . $in . ") GROUP BY product_id");

		$map = [];

		foreach ($query->rows as $row) {
			$map[(int)$row['product_id']] = (float)$row['reserved'];
		}

		return $map;
	}

	/**
	 * Atomically (re)create holds for the given cart lines. Existing unbound
	 * holds of the session are replaced, refreshing expires_at. Lines that
	 * cannot be held — no stock tracking (subtract = 0), preorders, a deleted
	 * product, or a removed/disabled variant — are skipped and never fail, so a
	 * single stale cart line cannot sink the whole batch.
	 *
	 * @param array $lines each line: ['product_id' => int, 'variant_id' => int, 'quantity' => float]
	 * @return array failed lines (insufficient available stock)
	 */
	public function reserve(array $lines, int $ttl_minutes, ?string $session_id = null): array {
		$session_id = $session_id ?: (string)$this->session->getId();
		$failed = [];

		// Lock product rows in a deterministic order to avoid deadlocks between
		// concurrent checkouts.
		usort($lines, function ($a, $b) {
			$by_product = (int)$a['product_id'] <=> (int)$b['product_id'];

			if ($by_product !== 0) {
				return $by_product;
			}

			return (int)$a['variant_id'] <=> (int)$b['variant_id'];
		});

		$this->db->query("START TRANSACTION");

		try {
			// Replace the session's previous unbound holds (covers cart edits
			// and lines removed from the cart meanwhile).
			$this->db->query("DELETE FROM `" . DB_PREFIX . "stock_reservation` WHERE session_id = '" . $this->db->escape($session_id) . "' AND order_id IS NULL");

			foreach ($lines as $line) {
				if (!$this->reserveLine($line, $ttl_minutes, $session_id)) {
					$failed[] = $line;
				}
			}

			$this->db->query("COMMIT");
		} catch (\Throwable $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return $failed;
	}

	/**
	 * Reserve a single line inside the calling transaction.
	 */
	private function reserveLine(array $line, int $ttl_minutes, string $session_id): bool {
		$product_id = (int)$line['product_id'];
		$variant_id = (int)$line['variant_id'];
		$quantity = (float)$line['quantity'];

		if ($quantity <= 0) {
			return true;
		}

		$product_query = $this->db->query("SELECT quantity, subtract, preorder FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$product_id . "' FOR UPDATE");

		// Product gone (deleted after being added to cart) — nothing to hold.
		// The cart's stock gate already blocks such lines before we get here.
		if (!$product_query->num_rows) {
			return true;
		}

		$preorder = (int)$product_query->row['preorder'];

		// Preorder items are not subject to stock holds.
		if ($preorder) {
			return true;
		}

		$stock_quantity = (float)$product_query->row['quantity'];
		$subtract = (int)$product_query->row['subtract'];

		if ($variant_id > 0) {
			$variant_query = $this->db->query("SELECT quantity, subtract FROM `" . DB_PREFIX . "product_variant` WHERE variant_id = '" . (int)$variant_id . "' AND product_id = '" . (int)$product_id . "' AND status = '1' FOR UPDATE");

			// Variant removed or disabled since it was added to the cart:
			// it cannot be held, so skip it (do not fail the whole batch).
			if (!$variant_query->num_rows) {
				return true;
			}

			$stock_quantity = (float)$variant_query->row['quantity'];
			$subtract = (int)$variant_query->row['subtract'];
		}

		// Stock not tracked for this line — nothing to hold.
		if (!$subtract) {
			return true;
		}

		$reserved_query = $this->db->query("SELECT COALESCE(SUM(quantity), 0) AS reserved FROM `" . DB_PREFIX . "stock_reservation` WHERE product_id = '" . (int)$product_id . "' AND variant_id = '" . (int)$variant_id . "' AND (order_id IS NOT NULL OR expires_at > NOW()) AND (order_id IS NOT NULL OR session_id <> '" . $this->db->escape($session_id) . "')");

		$available = $stock_quantity - (float)$reserved_query->row['reserved'];

		if ($available < $quantity) {
			return false;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "stock_reservation` SET session_id = '" . $this->db->escape($session_id) . "', product_id = '" . (int)$product_id . "', variant_id = '" . (int)$variant_id . "', quantity = '" . (float)$quantity . "', order_id = NULL, expires_at = DATE_ADD(NOW(), INTERVAL " . (int)$ttl_minutes . " MINUTE), date_added = NOW()");

		return true;
	}

	/**
	 * Release all unbound holds of a session.
	 */
	public function releaseSession(?string $session_id = null): void {
		$session_id = $session_id ?: (string)$this->session->getId();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "stock_reservation` WHERE session_id = '" . $this->db->escape($session_id) . "' AND order_id IS NULL");
	}

	/**
	 * Bind the session's unbound holds to a created order, so they persist
	 * until stock is subtracted or the order is cancelled/refunded.
	 */
	public function bindToOrder(string $session_id, int $order_id): void {
		if ($order_id <= 0) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "stock_reservation` SET order_id = '" . (int)$order_id . "' WHERE session_id = '" . $this->db->escape($session_id) . "' AND order_id IS NULL");
	}

	/**
	 * Release all holds bound to an order (stock was subtracted or restocked).
	 */
	public function releaseOrder(int $order_id): void {
		if ($order_id <= 0) {
			return;
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "stock_reservation` WHERE order_id = '" . (int)$order_id . "'");
	}

	/**
	 * Extend the session's unbound holds to the given window. Called when a
	 * payment method with its own reserve_minutes is selected.
	 */
	public function applyMethodTtl(string $session_id, int $ttl_minutes): void {
		if ($ttl_minutes <= 0) {
			$this->releaseSession($session_id);
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "stock_reservation` SET expires_at = DATE_ADD(NOW(), INTERVAL " . (int)$ttl_minutes . " MINUTE) WHERE session_id = '" . $this->db->escape($session_id) . "' AND order_id IS NULL");
	}
}
