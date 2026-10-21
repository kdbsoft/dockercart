<?php
/**
 * DockerCart webhooks — outbox dispatcher.
 *
 * `emit()` is the only place that fans an event out into
 * oc_dockercart_webhook_delivery. It is designed to be called *inside* the
 * business transaction (order creation, status transition, stock movement) and
 * therefore:
 *
 *   - never opens its own transaction (MySQL would implicitly commit the
 *     caller's one);
 *   - never performs HTTP (the scheduler worker does that);
 *   - never throws (a webhook misconfiguration must not roll back an order or
 *     a stock movement); failures are written to storage/logs/webhook.log.
 *
 * Subscriptions and filters are evaluated at emit time, so editing a
 * subscription does not retroactively change or drop already queued events.
 * The payload is serialized once and stored verbatim; retries reuse it.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

/** @property \DB $db */
final class Dispatcher {
	/** JSON flags used for every stored body (retries must be byte-identical). */
	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

	/** @var \Registry */
	private $registry;

	/** @var array<int, array<string, mixed>>|null request-level subscription cache */
	private static $subscriptions = null;

	/** @var \Log|null */
	private static $logger = null;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Enqueues an event for every matching subscription.
	 *
	 * @param string $type    event type (Events::catalogue())
	 * @param array  $data    thin payload
	 * @param array  $options ['full' => callable(): array] lazy full order object
	 * @return int number of enqueued deliveries
	 */
	public function emit(string $type, array $data, array $options = []): int {
		$type = trim($type);

		if ($type === '') {
			return 0;
		}

		try {
			$subscriptions = $this->subscriptions();

			if (!$subscriptions) {
				return 0;
			}

			$occurred_at = gmdate('Y-m-d H:i:s');
			$event_id = 'evt_' . Ulid::generate();
			$occurred_iso = gmdate('Y-m-d\TH:i:s\Z');

			$delivery = new Delivery($this->registry);
			$full = null;
			$enqueued = 0;

			foreach ($subscriptions as $subscription) {
				$events = json_decode((string)$subscription['events'], true);

				if (!is_array($events) || !Events::matchesAny($events, $type)) {
					continue;
				}

				$filters_raw = (string)($subscription['filters'] ?? '');
				$filters = $filters_raw !== '' ? json_decode($filters_raw, true) : [];

				if ($filters_raw !== '' && !is_array($filters)) {
					$this->log('invalid_filters subscription=' . (int)$subscription['subscription_id']);

					continue;
				}

				if (is_array($filters) && $filters && !Events::matchesFilters($filters, $data)) {
					continue;
				}

				$event_data = $data;

				if ((int)$subscription['full_payload'] === 1 && isset($options['full']) && is_callable($options['full'])) {
					if ($full === null) {
						$full = (array)call_user_func($options['full']);
					}

					if ($full) {
						$event_data['object'] = $full;
					}
				}

				$payload = json_encode([
					'id'          => $event_id,
					'type'        => $type,
					'occurred_at' => $occurred_iso,
					'data'        => $event_data,
				], self::JSON_FLAGS);

				if ($payload === false) {
					$this->log('payload_encode_failed type=' . $type . ' subscription=' . (int)$subscription['subscription_id']);

					continue;
				}

				$delivery_id = $delivery->insert(
					(int)$subscription['subscription_id'],
					$event_id,
					$type,
					(string)$payload,
					false,
					$occurred_at
				);

				if ($delivery_id > 0) {
					$enqueued++;
				}
			}

			return $enqueued;
		} catch (\Throwable $e) {
			$this->log('emit_failed type=' . $type . ' error=' . $e->getMessage());

			return 0;
		}
	}

	/**
	 * Test hook: drop the request-level subscription cache.
	 */
	public static function resetCache(): void {
		self::$subscriptions = null;
	}

	/**
	 * Enabled subscriptions, loaded once per request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function subscriptions(): array {
		if (self::$subscriptions !== null) {
			return self::$subscriptions;
		}

		$query = $this->db->query("SELECT `subscription_id`, `events`, `filters`, `full_payload` FROM `" . DB_PREFIX . "dockercart_webhook_subscription` WHERE `status` = '1'");

		self::$subscriptions = $query->rows;

		return self::$subscriptions;
	}

	private function log(string $message): void {
		try {
			if (self::$logger === null) {
				self::$logger = new \Log('webhook.log');
			}

			self::$logger->write($message);
		} catch (\Throwable $e) {
			error_log('[dockercart-webhook] ' . $message);
		}
	}
}
