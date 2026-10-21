<?php
/**
 * DockerCart admin — webhook subscriptions and delivery journal.
 *
 * Storage for oc_dockercart_webhook_subscription / _delivery. The HMAC secret
 * is stored encrypted with the store's `config_encryption` key and is returned
 * in plain text exactly once (create / rotate), mirroring the API key flow.
 *
 * Used by the panel (setting/webhook, setting/webhook_log) and by the REST API
 * controller (api/webhooks), so panel and API share one validation path.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

use Dockercart\Webhook\Delivery;
use Dockercart\Webhook\Events;
use Dockercart\Webhook\Secret;
use Dockercart\Webhook\Ssrf;

class ModelSettingWebhook extends Model {
	/**
	 * Subscriptions with their recent delivery counters.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getSubscriptions(array $data = []): array {
		$where = [];

		if (isset($data['filter_status']) && $data['filter_status'] !== '' && $data['filter_status'] !== null) {
			$where[] = "s.`status` = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_name'])) {
			$where[] = "s.`name` LIKE '%" . $this->db->escape((string)$data['filter_name']) . "%'";
		}

		if (!empty($data['filter_url'])) {
			$where[] = "s.`url` LIKE '%" . $this->db->escape((string)$data['filter_url']) . "%'";
		}

		if (!empty($data['filter_event'])) {
			$where[] = "s.`events` LIKE '%" . $this->db->escape('"' . (string)$data['filter_event'] . '"') . "%'";
		}

		$sql = "SELECT s.*,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d WHERE d.`subscription_id` = s.`subscription_id`) AS deliveries_total,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d WHERE d.`subscription_id` = s.`subscription_id` AND d.`status` = 'failed') AS deliveries_failed,
				(SELECT COUNT(*) FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d WHERE d.`subscription_id` = s.`subscription_id` AND d.`status` = 'pending') AS deliveries_pending
			FROM `" . DB_PREFIX . "dockercart_webhook_subscription` s";

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql .= " ORDER BY s.`subscription_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		$rows = [];

		foreach ($query->rows as $row) {
			$rows[] = $this->normalize($row);
		}

		return $rows;
	}

	public function getTotalSubscriptions(array $data = []): int {
		$where = [];

		if (isset($data['filter_status']) && $data['filter_status'] !== '' && $data['filter_status'] !== null) {
			$where[] = "`status` = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_name'])) {
			$where[] = "`name` LIKE '%" . $this->db->escape((string)$data['filter_name']) . "%'";
		}

		if (!empty($data['filter_url'])) {
			$where[] = "`url` LIKE '%" . $this->db->escape((string)$data['filter_url']) . "%'";
		}

		if (!empty($data['filter_event'])) {
			$where[] = "`events` LIKE '%" . $this->db->escape('"' . (string)$data['filter_event'] . '"') . "%'";
		}

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_webhook_subscription`";

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * @return array<string, mixed> empty array when missing
	 */
	public function getSubscription(int $subscription_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_webhook_subscription` WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		if (!$query->num_rows) {
			return [];
		}

		return $this->normalize($query->row);
	}

	/**
	 * Creates a subscription and returns the plain secret (shown once).
	 *
	 * @param array $data name, url, events, filters, full_payload, status
	 * @return array{subscription_id: int, secret: string}
	 */
	public function addSubscription(array $data): array {
		$secret = Secret::generate();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_webhook_subscription` SET
			`name` = '" . $this->db->escape((string)$data['name']) . "',
			`url` = '" . $this->db->escape((string)$data['url']) . "',
			`secret_encrypted` = '" . $this->db->escape($this->encryptSecret($secret['secret'])) . "',
			`secret_hint` = '" . $this->db->escape($secret['hint']) . "',
			`events` = '" . $this->db->escape(json_encode(array_values((array)$data['events']))) . "',
			`filters` = '" . $this->db->escape((string)($data['filters_json'] ?? '{}')) . "',
			`full_payload` = '" . (int)!empty($data['full_payload']) . "',
			`status` = '" . (int)(isset($data['status']) ? (int)$data['status'] : 1) . "',
			`created_by_api_key_id` = '" . (int)($data['created_by_api_key_id'] ?? 0) . "',
			`created_by_user_id` = '" . (int)($data['created_by_user_id'] ?? 0) . "',
			`date_added` = NOW(),
			`date_modified` = NOW()");

		\Dockercart\Webhook\Dispatcher::resetCache();

		return [
			'subscription_id' => (int)$this->db->getLastId(),
			'secret'          => $secret['secret'],
			'hint'            => $secret['hint'],
		];
	}

	/**
	 * Updates the editable fields (the secret is only changed by rotateSecret()).
	 */
	public function editSubscription(int $subscription_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
			`name` = '" . $this->db->escape((string)$data['name']) . "',
			`url` = '" . $this->db->escape((string)$data['url']) . "',
			`events` = '" . $this->db->escape(json_encode(array_values((array)$data['events']))) . "',
			`filters` = '" . $this->db->escape((string)($data['filters_json'] ?? '{}')) . "',
			`full_payload` = '" . (int)!empty($data['full_payload']) . "',
			`status` = '" . (int)(isset($data['status']) ? (int)$data['status'] : 1) . "',
			`date_modified` = NOW()
		 WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		\Dockercart\Webhook\Dispatcher::resetCache();

		// Enabling again clears the auto-disable state (the counter is reset by
		// the worker on the next successful delivery).
		if ((int)(isset($data['status']) ? (int)$data['status'] : 1) === 1) {
			$this->clearAutoDisable($subscription_id);
		}
	}

	/**
	 * Enables/disables a subscription. Enabling clears the failure counter and
	 * the auto-disable reason; disabling cancels its pending deliveries.
	 */
	public function setStatus(int $subscription_id, int $status): bool {
		$subscription = $this->getSubscription($subscription_id);

		if (!$subscription) {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
			`status` = '" . (int)$status . "',
			`date_modified` = NOW()
		 WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		if ($status === 1) {
			$this->clearAutoDisable($subscription_id);
		} else {
			(new Delivery($this->registry))->cancelPending($subscription_id);
		}

		\Dockercart\Webhook\Dispatcher::resetCache();

		return true;
	}

	/**
	 * Issues a new secret and returns it in plain text (shown once).
	 */
	public function rotateSecret(int $subscription_id): string {
		$secret = Secret::generate();

		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
			`secret_encrypted` = '" . $this->db->escape($this->encryptSecret($secret['secret'])) . "',
			`secret_hint` = '" . $this->db->escape($secret['hint']) . "',
			`date_modified` = NOW()
		 WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		return $secret['secret'];
	}

	/**
	 * Removes the subscription and its journal (the journal belongs to it).
	 */
	public function deleteSubscription(int $subscription_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_webhook_delivery` WHERE `subscription_id` = '" . (int)$subscription_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_webhook_subscription` WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		\Dockercart\Webhook\Dispatcher::resetCache();
	}

	/**
	 * Delivery journal rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDeliveries(array $data = []): array {
		$sql = "SELECT d.*, s.`name` AS `subscription_name`, s.`url` AS `subscription_url`
			FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d
			LEFT JOIN `" . DB_PREFIX . "dockercart_webhook_subscription` s ON (s.`subscription_id` = d.`subscription_id`)
			WHERE 1 = 1" . $this->deliveryFilterSql($data);

		$sql .= " ORDER BY d.`delivery_id` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		return array_map([$this, 'normalizeDelivery'], $query->rows);
	}

	public function getTotalDeliveries(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d WHERE 1 = 1" . $this->deliveryFilterSql($data);

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Journal aggregates for the current filter (independent of pagination).
	 *
	 * @return array{total:int, delivered:int, failed:int, pending:int}
	 */
	public function getDeliverySummary(array $data = []): array {
		$sql = "SELECT COUNT(*) AS total,
				SUM(d.`status` = 'delivered') AS delivered,
				SUM(d.`status` = 'failed') AS failed,
				SUM(d.`status` IN ('pending', 'sending')) AS pending
			FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d
			WHERE 1 = 1" . $this->deliveryFilterSql($data);

		$query = $this->db->query($sql);

		return [
			'total'     => (int)$query->row['total'],
			'delivered' => (int)($query->row['delivered'] ?? 0),
			'failed'    => (int)($query->row['failed'] ?? 0),
			'pending'   => (int)($query->row['pending'] ?? 0),
		];
	}

	/**
	 * @return array<string, mixed> empty array when missing
	 */
	public function getDelivery(int $delivery_id): array {
		$query = $this->db->query("SELECT d.*, s.`name` AS `subscription_name`, s.`url` AS `subscription_url`, s.`status` AS `subscription_status`
			FROM `" . DB_PREFIX . "dockercart_webhook_delivery` d
			LEFT JOIN `" . DB_PREFIX . "dockercart_webhook_subscription` s ON (s.`subscription_id` = d.`subscription_id`)
			WHERE d.`delivery_id` = '" . (int)$delivery_id . "'");

		if (!$query->num_rows) {
			return [];
		}

		return $this->normalizeDelivery($query->row);
	}

	/**
	 * Re-queues a failed delivery (same row and event id).
	 */
	public function retryDelivery(int $delivery_id): bool {
		return (new Delivery($this->registry))->reset($delivery_id);
	}

	/**
	 * Enqueues a synthetic test event.
	 *
	 * @return array{delivery_id: int, event_id: string}|null
	 */
	public function enqueueTest(int $subscription_id, string $message = ''): ?array {
		return (new Delivery($this->registry))->enqueueTest($subscription_id, $message);
	}

	/**
	 * Validates the subscription payload (shared by the panel and the API).
	 *
	 * @param array $data raw values: name, url, events, filters_json, full_payload, status
	 * @return array<string, string> field => error code (empty = valid)
	 */
	public function validate(array $data): array {
		$errors = [];

		$name = trim((string)($data['name'] ?? ''));

		if ($name === '' || mb_strlen($name) > 128) {
			$errors['name'] = 'invalid_name';
		}

		$url_check = Ssrf::validateUrl((string)($data['url'] ?? ''));

		if (!$url_check['ok']) {
			$errors['url'] = (string)$url_check['error'];
		}

		$events = Events::normalize((array)($data['events'] ?? []));

		if (!$events) {
			$errors['events'] = 'events_required';
		}

		$invalid = Events::invalidEvents($events);

		if ($invalid) {
			$errors['events'] = 'unknown_event:' . implode(',', $invalid);
		}

		$filters = Events::normalizeFilters($data['filters'] ?? ($data['filters_json'] ?? []));

		if ($filters === null) {
			$errors['filters'] = 'invalid_filters';
		} elseif ($filters) {
			$invalid_keys = Events::invalidFilterKeys($events, $filters);

			if ($invalid_keys) {
				$errors['filters'] = 'unsupported_filter:' . implode(',', $invalid_keys);
			}
		}

		return $errors;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function normalizeData(array $data): array {
		$events = Events::normalize((array)($data['events'] ?? []));
		$filters = Events::normalizeFilters($data['filters'] ?? ($data['filters_json'] ?? []));

		return [
			'name'         => trim((string)($data['name'] ?? '')),
			'url'          => trim((string)($data['url'] ?? '')),
			'events'       => $events,
			'filters_json' => json_encode($filters ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'full_payload' => (int)!empty($data['full_payload']),
			'status'       => (int)(isset($data['status']) ? (int)$data['status'] : 1) === 1 ? 1 : 0,
		];
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalize(array $row): array {
		$events = json_decode((string)($row['events'] ?? ''), true);
		$filters = json_decode((string)($row['filters'] ?? ''), true);

		$row['events_list'] = is_array($events) ? array_values(array_map('strval', $events)) : [];
		$row['filters_array'] = is_array($filters) ? $filters : [];
		$row['full_payload'] = (int)($row['full_payload'] ?? 0);
		$row['consecutive_failures'] = (int)($row['consecutive_failures'] ?? 0);
		$row['disabled_reason'] = (string)($row['disabled_reason'] ?? '');

		// The encrypted secret must never leak into a view or an API response.
		unset($row['secret_encrypted']);

		return $row;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function normalizeDelivery(array $row): array {
		$row['is_test'] = (int)($row['is_test'] ?? 0);
		$row['attempts'] = (int)($row['attempts'] ?? 0);
		$row['response_code'] = (int)($row['response_code'] ?? 0);
		$row['duration_ms'] = (int)($row['duration_ms'] ?? 0);

		return $row;
	}

	private function deliveryFilterSql(array $data): string {
		$sql = '';

		if (!empty($data['filter_subscription_id'])) {
			$sql .= " AND d.`subscription_id` = '" . (int)$data['filter_subscription_id'] . "'";
		}

		if (!empty($data['filter_event_type'])) {
			$sql .= " AND d.`event_type` = '" . $this->db->escape((string)$data['filter_event_type']) . "'";
		}

		if (!empty($data['filter_status'])) {
			$sql .= " AND d.`status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
		}

		if (!empty($data['filter_event_id'])) {
			$sql .= " AND d.`event_id` = '" . $this->db->escape((string)$data['filter_event_id']) . "'";
		}

		if (!empty($data['filter_date_from'])) {
			$sql .= " AND d.`date_added` >= '" . $this->db->escape((string)$data['filter_date_from']) . " 00:00:00'";
		}

		if (!empty($data['filter_date_to'])) {
			$sql .= " AND d.`date_added` <= '" . $this->db->escape((string)$data['filter_date_to']) . " 23:59:59'";
		}

		return $sql;
	}

	private function clearAutoDisable(int $subscription_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_subscription` SET
			`consecutive_failures` = '0',
			`disabled_reason` = '',
			`disabled_at` = NULL,
			`date_modified` = NOW()
		 WHERE `subscription_id` = '" . (int)$subscription_id . "'");
	}

	private function encryptSecret(string $secret): string {
		return Secret::encrypt($secret, (string)$this->config->get('config_encryption'));
	}
}
