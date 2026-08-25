<?php
/**
 * DockerCart Warehouse Transfer admin model: transfers between warehouses.
 */

declare(strict_types=1);

class ModelWarehouseTransfer extends Model {
	public function addTransfer(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_transfer` SET
			`transfer_no` = '" . $this->db->escape((string)($data['transfer_no'] ?? $this->nextNumber())) . "',
			`from_warehouse_id` = '" . (int)$data['from_warehouse_id'] . "',
			`to_warehouse_id` = '" . (int)$data['to_warehouse_id'] . "',
			`status` = 'pending',
			`note` = '" . $this->db->escape((string)($data['note'] ?? '')) . "',
			`created_by` = '" . (int)($this->user ? $this->user->getId() : 0) . "',
			`date_added` = NOW(), `date_modified` = NOW()");

		$transfer_id = (int)$this->db->getLastId();

		foreach ($data['items'] ?? [] as $item) {
			if ((int)$item['product_id'] <= 0 || (float)$item['quantity'] <= 0) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_transfer_item` SET
				`transfer_id` = '" . (int)$transfer_id . "',
				`product_id` = '" . (int)$item['product_id'] . "',
				`variant_id` = '" . (int)($item['variant_id'] ?? 0) . "',
				`quantity` = '" . (float)$item['quantity'] . "'");
		}

		$this->cache->delete('warehouse');

		return $transfer_id;
	}

	public function getTransfer(int $transfer_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_transfer` WHERE `transfer_id` = '" . (int)$transfer_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	public function getTransferItems(int $transfer_id): array {
		$query = $this->db->query("SELECT ti.*, pd.name AS product_name, p.model AS product_model, pv.sku AS variant_sku FROM `" . DB_PREFIX . "warehouse_transfer_item` ti LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = ti.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') LEFT JOIN `" . DB_PREFIX . "product_variant` pv ON (pv.variant_id = ti.variant_id) WHERE ti.`transfer_id` = '" . (int)$transfer_id . "'");

		return $query->rows;
	}

	public function getTransfers(array $data = []): array {
		$sql = "SELECT t.*, wf.`name` AS from_name, wt.`name` AS to_name, u.`username` AS creator,
				COALESCE(ti.`items_count`, 0) AS items_count, COALESCE(ti.`total_quantity`, 0) AS total_quantity
			FROM `" . DB_PREFIX . "warehouse_transfer` t
			LEFT JOIN `" . DB_PREFIX . "warehouse` wf ON (wf.warehouse_id = t.from_warehouse_id)
			LEFT JOIN `" . DB_PREFIX . "warehouse` wt ON (wt.warehouse_id = t.to_warehouse_id)
			LEFT JOIN (
				SELECT `transfer_id`, COUNT(*) AS items_count, SUM(`quantity`) AS total_quantity
				FROM `" . DB_PREFIX . "warehouse_transfer_item`
				GROUP BY `transfer_id`
			) ti ON (ti.`transfer_id` = t.`transfer_id`)
			LEFT JOIN `" . DB_PREFIX . "user` u ON (u.`user_id` = t.created_by)
			WHERE 1 = 1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND t.`status` = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$sql .= " ORDER BY t.`date_added` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalTransfers(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "warehouse_transfer` t WHERE 1 = 1";

		if (!empty($data['filter_status'])) {
			$sql .= " AND t.`status` = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Advance a transfer status. On 'completed' the stock really moves.
	 */
	public function updateStatus(int $transfer_id, string $status): void {
		$allowed = ['pending', 'in_transit', 'completed', 'cancelled'];

		if (!in_array($status, $allowed, true)) {
			return;
		}

		$transfer = $this->getTransfer($transfer_id);

		if (!$transfer) {
			return;
		}

		// A completed/cancelled transfer cannot be re-opened.
		if (in_array($transfer['status'], ['completed', 'cancelled'], true) && $status !== $transfer['status']) {
			return;
		}

		if ($status === 'completed' && $transfer['status'] !== 'completed') {
			$items = $this->getTransferItems($transfer_id);

			$warehouse = new \DockercartWarehouse($this->registry);

			foreach ($items as $item) {
				$warehouse->moveStock(
					(int)$transfer['from_warehouse_id'],
					(int)$transfer['to_warehouse_id'],
					(int)$item['product_id'],
					(int)$item['variant_id'],
					(float)$item['quantity'],
					[
						'reference' => $transfer['transfer_no'],
						'transfer_id' => (int)$transfer_id,
						'user_id' => (int)($this->user ? $this->user->getId() : 0),
					]
				);
			}
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse_transfer` SET `status` = '" . $this->db->escape($status) . "', `date_completed` = " . ($status === 'completed' ? 'NOW()' : 'NULL') . ", `date_modified` = NOW() WHERE `transfer_id` = '" . (int)$transfer_id . "'");
	}

	public function deleteTransfer(int $transfer_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_transfer_item` WHERE `transfer_id` = '" . (int)$transfer_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_transfer` WHERE `transfer_id` = '" . (int)$transfer_id . "'");
	}

	protected function nextNumber(): string {
		return 'TR-' . date('Ymd') . '-' . mt_rand(1000, 9999);
	}
}