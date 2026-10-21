<?php
/**
 * DockerCart admin REST API — Idempotency-Key support.
 *
 * A client that retries a POST (timeout, CI retry, flaky network) sends the same
 * `Idempotency-Key: <8..128 printable chars>` header. The first request executes
 * normally and its answer is stored in oc_api_idempotency; a retry with the same
 * key, method and path is answered from that row (`Idempotent-Replay: true`)
 * instead of creating a second product/category/comment.
 *
 * Rules:
 *   - scoped to the API key, so two integrations cannot collide;
 *   - a reused key with a *different* body is rejected (409 idempotency_conflict)
 *     instead of silently returning the old answer;
 *   - a concurrent retry while the first is still running gets
 *     409 idempotency_in_progress (a 5 minute lock is taken when the request
 *     starts, so a crashed request can be retried after it expires);
 *   - 5xx answers are not stored (the client should be able to retry them).
 *
 * Rows live 24 hours and are purged by the daily `api_log_cleanup` task.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Idempotency {
	private const TTL_HOURS = 24;
	private const LOCK_MINUTES = 5;
	private const MAX_KEY_LENGTH = 128;

	private \DB $db;

	public function __construct(\Registry $registry) {
		$this->db = $registry->get('db');
	}

	/**
	 * Called before the controller runs.
	 *
	 * @return array|null replay payload when a stored answer exists, null when the
	 *                    request must execute (a lock row has been taken)
	 */
	public function begin(Context $context): ?array {
		if (!$this->applies($context)) {
			return null;
		}

		$key = $this->key($context);

		$row = $this->find($context, $key);

		if ($row) {
			if (!hash_equals((string)$row['request_hash'], $context->request_hash)) {
				throw new HttpException(Error::IDEMPOTENCY_CONFLICT);
			}

			if ((int)$row['status_code'] > 0) {
				return [
					'status'  => (int)$row['status_code'],
					'body'    => (string)$row['response_body'],
					'headers' => json_decode((string)($row['response_headers'] ?? '[]'), true) ?: [],
				];
			}

			if (!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time()) {
				throw new HttpException(Error::IDEMPOTENCY_IN_PROGRESS);
			}

			// Stale lock (the original request died): take it over.
			$this->db->query("UPDATE `" . DB_PREFIX . "api_idempotency` SET
				`locked_until` = DATE_ADD(NOW(), INTERVAL " . self::LOCK_MINUTES . " MINUTE),
				`date_modified` = NOW()
				WHERE `api_idempotency_id` = '" . (int)$row['api_idempotency_id'] . "'");

			$context->idempotency_id = (int)$row['api_idempotency_id'];

			return null;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "api_idempotency` SET
			`api_key_id` = '" . (int)($context->key['api_key_id'] ?? 0) . "',
			`idempotency_key` = '" . $this->db->escape($key) . "',
			`method` = '" . $this->db->escape($context->method) . "',
			`path` = '" . $this->db->escape($context->path) . "',
			`request_hash` = '" . $this->db->escape($context->request_hash) . "',
			`status_code` = '0',
			`locked_until` = DATE_ADD(NOW(), INTERVAL " . self::LOCK_MINUTES . " MINUTE),
			`date_added` = NOW(),
			`date_modified` = NOW()
			ON DUPLICATE KEY UPDATE `date_modified` = NOW()");

		$inserted = (int)$this->db->getLastId();

		if ($inserted === 0) {
			// Lost a race with a concurrent retry: it owns the lock.
			$row = $this->find($context, $key);

			if ($row && (int)$row['status_code'] > 0 && hash_equals((string)$row['request_hash'], $context->request_hash)) {
				return [
					'status'  => (int)$row['status_code'],
					'body'    => (string)$row['response_body'],
					'headers' => json_decode((string)($row['response_headers'] ?? '[]'), true) ?: [],
				];
			}

			throw new HttpException(Error::IDEMPOTENCY_IN_PROGRESS);
		}

		$context->idempotency_id = $inserted;

		return null;
	}

	/**
	 * Stores the answer of the current request (called from Response::send()).
	 */
	public function store(Context $context, string $body, int $status, array $headers = []): void {
		if ($context->idempotency_id <= 0 || $context->replay_payload !== null) {
			return;
		}

		$id = (int)$context->idempotency_id;

		if ($status >= 500) {
			// Transient failure: drop the lock so the client may retry.
			$this->db->query("DELETE FROM `" . DB_PREFIX . "api_idempotency` WHERE `api_idempotency_id` = '" . $id . "'");

			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "api_idempotency` SET
			`status_code` = '" . (int)$status . "',
			`response_body` = '" . $this->db->escape($body) . "',
			`response_headers` = '" . $this->db->escape(json_encode(array_values($headers))) . "',
			`locked_until` = NULL,
			`date_modified` = NOW()
			WHERE `api_idempotency_id` = '" . $id . "'");
	}

	/**
	 * Deletes rows older than the retention window.
	 */
	public function purgeExpired(): int {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_idempotency` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . self::TTL_HOURS . " HOUR)");

		return (int)$this->db->countAffected();
	}

	private function applies(Context $context): bool {
		if ($context->method !== 'POST' || $context->key === null) {
			return false;
		}

		return trim($this->rawKey($context)) !== '';
	}

	/**
	 * Validates and returns the header value (8..128 printable characters).
	 */
	private function key(Context $context): string {
		$key = $this->rawKey($context);

		if (strlen($key) < 8 || strlen($key) > self::MAX_KEY_LENGTH || !preg_match('/^[\x21-\x7E]+$/', $key)) {
			throw new HttpException(Error::BAD_REQUEST, [
				'Idempotency-Key' => ['must be 8-128 printable ASCII characters'],
			]);
		}

		return $key;
	}

	private function rawKey(Context $context): string {
		return trim((string)($context->idempotency_header ?? ''));
	}

	private function find(Context $context, string $key): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_idempotency`
			WHERE `api_key_id` = '" . (int)($context->key['api_key_id'] ?? 0) . "'
				AND `idempotency_key` = '" . $this->db->escape($key) . "'
				AND `method` = '" . $this->db->escape($context->method) . "'
				AND `path` = '" . $this->db->escape($context->path) . "'");

		return $query->num_rows ? $query->row : null;
	}
}
