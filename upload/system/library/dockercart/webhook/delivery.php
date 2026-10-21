<?php
/**
 * DockerCart webhooks — delivery outbox helpers.
 *
 * The outbox row is created inside the business transaction (Dispatcher), so
 * it can never be lost between COMMIT and the first send. This class owns the
 * row format, the retry backoff and the manual retry reset; the HTTP loop and
 * the status transitions live in bin/dockercart_webhook_deliver.php.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

/** @property \DB $db */
final class Delivery {
	public const STATUS_PENDING   = 'pending';
	public const STATUS_SENDING   = 'sending';
	public const STATUS_DELIVERED = 'delivered';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';

	/**
	 * Delay after a failed attempt: 1 m, 5 m, 30 m, 2 h, 12 h.
	 * Index = attempts - 1; past the table the delivery is failed.
	 */
	public const BACKOFF = [60, 300, 1800, 7200, 43200];

	/** Synthetic event emitted by the "send test event" button. */
	public const TEST_EVENT = 'webhook.test';

	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Inserts one outbox row. Returns the delivery id, or 0 when the unique
	 * (subscription_id, event_id) key already contains the event.
	 */
	public function insert(int $subscription_id, string $event_id, string $event_type, string $payload, bool $is_test, string $occurred_at): int {
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "dockercart_webhook_delivery` SET
			`subscription_id` = '" . (int)$subscription_id . "',
			`event_id` = '" . $this->db->escape($event_id) . "',
			`event_type` = '" . $this->db->escape($event_type) . "',
			`payload` = '" . $this->db->escape($payload) . "',
			`is_test` = '" . ($is_test ? 1 : 0) . "',
			`status` = '" . self::STATUS_PENDING . "',
			`attempts` = '0',
			`next_attempt_at` = NOW(),
			`occurred_at` = '" . $this->db->escape($occurred_at) . "',
			`date_added` = NOW(),
			`date_modified` = NOW()");

		if ($this->db->countAffected() < 1) {
			return 0;
		}

		return (int)$this->db->getLastId();
	}

	/**
	 * Enqueues a synthetic `webhook.test` event for an enabled subscription.
	 *
	 * @return array{delivery_id: int, event_id: string}|null null when the
	 *         subscription does not exist or is disabled
	 */
	public function enqueueTest(int $subscription_id, string $message = ''): ?array {
		$query = $this->db->query("SELECT `subscription_id`, `name`, `status` FROM `" . DB_PREFIX . "dockercart_webhook_subscription` WHERE `subscription_id` = '" . (int)$subscription_id . "'");

		if (!$query->num_rows || (int)$query->row['status'] !== 1) {
			return null;
		}

		$event_id = 'evt_' . Ulid::generate();
		$occurred_at = gmdate('Y-m-d H:i:s');

		$payload = json_encode([
			'id'          => $event_id,
			'type'        => self::TEST_EVENT,
			'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
			'data'        => [
				'subscription_id' => (int)$subscription_id,
				'message'         => $message !== '' ? $message : 'DockerCart webhook test event',
			],
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

		if ($payload === false) {
			return null;
		}

		$delivery_id = $this->insert((int)$subscription_id, $event_id, self::TEST_EVENT, (string)$payload, true, $occurred_at);

		if ($delivery_id === 0) {
			return null;
		}

		return [
			'delivery_id' => $delivery_id,
			'event_id'    => $event_id,
		];
	}

	/**
	 * Manual retry: only failed rows can be re-queued. Reuses the same row and
	 * event id (the receiver is expected to deduplicate by event_id).
	 */
	public function reset(int $delivery_id): bool {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
			`status` = '" . self::STATUS_PENDING . "',
			`attempts` = '0',
			`next_attempt_at` = NOW(),
			`locked_until` = NULL,
			`response_code` = '0',
			`response_body` = NULL,
			`duration_ms` = '0',
			`last_error` = '',
			`date_modified` = NOW()
		 WHERE `delivery_id` = '" . (int)$delivery_id . "' AND `status` = '" . self::STATUS_FAILED . "'");

		return $this->db->countAffected() > 0;
	}

	/**
	 * Cancels still-pending rows of a subscription (disabled/deleted). Rows that
	 * are currently being sent are left to the worker.
	 */
	public function cancelPending(int $subscription_id): int {
		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_webhook_delivery` SET
			`status` = '" . self::STATUS_CANCELLED . "',
			`last_error` = 'subscription_disabled',
			`date_modified` = NOW()
		 WHERE `subscription_id` = '" . (int)$subscription_id . "'
		   AND `status` = '" . self::STATUS_PENDING . "'");

		return (int)$this->db->countAffected();
	}

	/**
	 * Seconds to wait after the given (failed) attempt, or 0 when the attempt
	 * budget is exhausted.
	 */
	public static function backoff(int $attempts, int $max_attempts): int {
		if ($attempts >= $max_attempts) {
			return 0;
		}

		return self::BACKOFF[min($attempts, count(self::BACKOFF)) - 1] ?? (int)end(self::BACKOFF);
	}

	/**
	 * Is the failure final (retrying the identical body cannot fix it)?
	 *
	 * Final: URL syntax problems (`invalid_*`, `credentials_not_allowed`), a
	 * blocked address (`ssrf_blocked`), an unreadable secret
	 * (`secret_unavailable`) and 4xx answers other than 408/425/429.
	 * Retryable: network errors, timeouts, 429 and 5xx.
	 */
	public static function isPermanent(string $error, int $status): bool {
		if (in_array($error, ['ssrf_blocked', 'secret_unavailable'], true)) {
			return true;
		}

		if (strpos($error, 'invalid_') === 0 || $error === 'credentials_not_allowed') {
			return true;
		}

		if ($status >= 400 && $status < 500) {
			return !in_array($status, [408, 425, 429], true);
		}

		return false;
	}

	/**
	 * Global limits from the store settings (raw config values, so this is
	 * testable without a registry and applies the same clamping as the worker).
	 *
	 * @return array{max_attempts: int, timeout: int, disable_after: int, enabled: bool}
	 */
	public static function limits($max_attempts, $timeout, $disable_after, $status): array {
		return [
			'max_attempts'  => $max_attempts === null || $max_attempts === '' ? 6 : max(1, min(20, (int)$max_attempts)),
			'timeout'       => $timeout === null || $timeout === '' ? 10 : max(1, min(60, (int)$timeout)),
			'disable_after' => $disable_after === null || $disable_after === '' ? 4 : max(1, min(100, (int)$disable_after)),
			'enabled'       => $status === null || $status === '' ? true : ((int)$status === 1),
		];
	}

	/**
	 * SQL expression for the next attempt, always relative to the database clock.
	 *
	 * The CLI worker does not run the panel's `SET time_zone` statement, so a
	 * PHP-side timestamp would be off by the store's UTC offset (a delivery
	 * would silently wait hours). Every comparison in the worker is DB-side, so
	 * the schedule must be computed DB-side as well.
	 */
	public static function nextAttemptSql(int $attempts, int $max_attempts): string {
		$delay = self::backoff($attempts, $max_attempts);

		return $delay > 0 ? 'DATE_ADD(NOW(), INTERVAL ' . (int)$delay . ' SECOND)' : 'NOW()';
	}
}
