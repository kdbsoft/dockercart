<?php
/**
 * DockerCart webhooks — event catalogue and matching.
 *
 * The catalogue is the single source of truth for what a subscription may
 * subscribe to, which data fields each event carries and which of them can be
 * used as a filter. Only implemented events may be subscribed explicitly;
 * wildcards (`order.*`, `*`) are allowed and intentionally also cover roadmap
 * events, so an integration can opt into future order events.
 *
 * Planned events are listed with `implemented => false` so
 * GET /v1/webhooks/events can document them without accepting a subscription
 * that would never fire.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

final class Events {
	/**
	 * Event catalogue.
	 *
	 * `data_fields` is the thin payload contract, `filter_fields` the subset a
	 * subscription may filter on (validated at save time, matched at emit time).
	 */
	private const CATALOGUE = [
		'order.created' => [
			'domain'        => 'order',
			'description_key' => 'event_order_created',
			'implemented'   => true,
			'description'   => 'A new order was created at checkout.',
			'data_fields'   => ['order_id', 'store_id', 'customer_id', 'order_status_id', 'total', 'currency_code', 'payment_code', 'shipping_code', 'item_count', 'warehouse_ids', 'version'],
			'filter_fields' => ['store_id', 'customer_id', 'order_status_id', 'currency_code', 'payment_code', 'shipping_code', 'warehouse_ids'],
		],
		'order.status_changed' => [
			'domain'        => 'order',
			'description_key' => 'event_order_status_changed',
			'implemented'   => true,
			'description'   => 'An order moved to another status through a validated transition (or a payment callback).',
			'data_fields'   => ['order_id', 'store_id', 'from_status_id', 'to_status_id', 'override', 'source', 'is_cancelled', 'is_final', 'is_terminal', 'version'],
			'filter_fields' => ['store_id', 'from_status_id', 'to_status_id', 'source', 'override', 'is_cancelled', 'is_final', 'is_terminal'],
		],
		'stock.changed' => [
			'domain'        => 'stock',
			'description_key' => 'event_stock_changed',
			'implemented'   => true,
			'description'   => 'A warehouse stock movement was journaled (order, return, transfer, manual adjustment, API batch).',
			'data_fields'   => ['warehouse_id', 'product_id', 'variant_id', 'delta', 'quantity', 'type', 'reference', 'order_id', 'transfer_id', 'movement_id', 'version'],
			'filter_fields' => ['warehouse_id', 'product_id', 'variant_id', 'type', 'order_id', 'transfer_id'],
		],

		// ------------------------------------------------------------------
		// Roadmap: listed for discovery, not accepted as an explicit
		// subscription yet.
		// ------------------------------------------------------------------
		'stock.low' => [
			'domain'        => 'stock',
			'description_key' => 'event_stock_low',
			'implemented'   => false,
			'description'   => 'A warehouse row dropped to or below its low-stock threshold.',
			'data_fields'   => ['warehouse_id', 'product_id', 'variant_id', 'quantity', 'low_stock', 'movement_id'],
			'filter_fields' => ['warehouse_id', 'product_id', 'variant_id'],
		],
		'order.payment_received' => [
			'domain'        => 'order',
			'description_key' => 'event_order_payment_received',
			'implemented'   => false,
			'description'   => 'A payment was recorded in the order payment journal.',
			'data_fields'   => ['order_id', 'order_payment_id', 'amount', 'paid_amount', 'payment_code', 'reference', 'version'],
			'filter_fields' => ['order_id', 'payment_code'],
		],
		'order.payment_refunded' => [
			'domain'        => 'order',
			'description_key' => 'event_order_payment_refunded',
			'implemented'   => false,
			'description'   => 'A payment reversal or refund was recorded in the order payment journal.',
			'data_fields'   => ['order_id', 'order_payment_id', 'amount', 'paid_amount', 'payment_code', 'reference', 'reason', 'version'],
			'filter_fields' => ['order_id', 'payment_code', 'reason'],
		],
		'order.shipment_created' => [
			'domain'        => 'order',
			'description_key' => 'event_order_shipment_created',
			'implemented'   => false,
			'description'   => 'A tracking number (full or partial shipment) was added to an order.',
			'data_fields'   => ['order_id', 'shipment_id', 'tracking_number', 'items', 'version'],
			'filter_fields' => ['order_id'],
		],
		'product.created' => [
			'domain'        => 'product',
			'description_key' => 'event_product_created',
			'implemented'   => false,
			'description'   => 'A product was created in the catalog.',
			'data_fields'   => ['product_id', 'model', 'sku', 'status', 'price', 'version'],
			'filter_fields' => ['product_id', 'status'],
		],
		'product.updated' => [
			'domain'        => 'product',
			'description_key' => 'event_product_updated',
			'implemented'   => false,
			'description'   => 'A product was updated (including API PATCH).',
			'data_fields'   => ['product_id', 'changed', 'status', 'price', 'version'],
			'filter_fields' => ['product_id', 'status'],
		],
		'product.deleted' => [
			'domain'        => 'product',
			'description_key' => 'event_product_deleted',
			'implemented'   => false,
			'description'   => 'A product was deleted.',
			'data_fields'   => ['product_id', 'model', 'sku', 'status'],
			'filter_fields' => ['product_id'],
		],
		'return.created' => [
			'domain'        => 'return',
			'description_key' => 'event_return_created',
			'implemented'   => false,
			'description'   => 'A return request was created.',
			'data_fields'   => ['return_id', 'order_id', 'type', 'return_status_id', 'amount'],
			'filter_fields' => ['order_id', 'type', 'return_status_id'],
		],
		'return.completed' => [
			'domain'        => 'return',
			'description_key' => 'event_return_completed',
			'implemented'   => false,
			'description'   => 'A return reached the Complete status (stock and money already moved).',
			'data_fields'   => ['return_id', 'order_id', 'type', 'refunded', 'amount', 'version'],
			'filter_fields' => ['order_id', 'type', 'refunded'],
		],
		'customer.created' => [
			'domain'        => 'customer',
			'description_key' => 'event_customer_created',
			'implemented'   => false,
			'description'   => 'A customer account was created.',
			'data_fields'   => ['customer_id', 'customer_group_id', 'status', 'newsletter'],
			'filter_fields' => ['customer_id', 'customer_group_id', 'status'],
		],
		'customer.updated' => [
			'domain'        => 'customer',
			'description_key' => 'event_customer_updated',
			'implemented'   => false,
			'description'   => 'A customer account was updated.',
			'data_fields'   => ['customer_id', 'customer_group_id', 'status'],
			'filter_fields' => ['customer_id', 'customer_group_id', 'status'],
		],
		'warehouse.transfer_completed' => [
			'domain'        => 'warehouse',
			'description_key' => 'event_transfer_completed',
			'implemented'   => false,
			'description'   => 'A stock transfer between warehouses was completed.',
			'data_fields'   => ['transfer_id', 'transfer_no', 'from_warehouse_id', 'to_warehouse_id', 'items'],
			'filter_fields' => ['transfer_id', 'from_warehouse_id', 'to_warehouse_id'],
		],
		'dropship.supplier_status_changed' => [
			'domain'        => 'dropship',
			'description_key' => 'event_supplier_status_changed',
			'implemented'   => false,
			'description'   => 'The supplier status of a dropshipping order line changed.',
			'data_fields'   => ['order_id', 'order_product_id', 'from', 'to', 'tracking'],
			'filter_fields' => ['order_id', 'from', 'to'],
		],
		'review.created' => [
			'domain'        => 'review',
			'description_key' => 'event_review_created',
			'implemented'   => false,
			'description'   => 'A product review was submitted.',
			'data_fields'   => ['review_id', 'product_id', 'customer_id', 'rating', 'status'],
			'filter_fields' => ['product_id', 'customer_id', 'status'],
		],
		'price.changed' => [
			'domain'        => 'price',
			'description_key' => 'event_price_changed',
			'implemented'   => false,
			'description'   => 'A product or variant price changed (separate from product.updated).',
			'data_fields'   => ['product_id', 'variant_id', 'from', 'to', 'currency_code'],
			'filter_fields' => ['product_id', 'variant_id'],
		],
		'config.changed' => [
			'domain'        => 'config',
			'description_key' => 'event_config_changed',
			'implemented'   => false,
			'description'   => 'Store settings were saved (keys only, never values).',
			'data_fields'   => ['code', 'keys'],
			'filter_fields' => ['code'],
		],
	];

	/**
	 * Full catalogue with the event type as `type`.
	 *
	 * `description` is the canonical English text (API/OpenAPI surface, which is
	 * English by contract) while `description_key` is the admin language key the
	 * panel translates with — see catalogueTranslated().
	 *
	 * @return array<int, array{type: string, domain: string, description_key: string, implemented: bool, description: string, data_fields: array<int, string>, filter_fields: array<int, string>}>
	 */
	public static function catalogue(): array {
		$rows = [];

		foreach (self::CATALOGUE as $type => $event) {
			$rows[] = [
				'type'            => (string)$type,
				'domain'          => (string)$event['domain'],
				'description_key' => (string)$event['description_key'],
				'implemented'     => (bool)$event['implemented'],
				'description'     => (string)$event['description'],
				'data_fields'     => array_values($event['data_fields']),
				'filter_fields'   => array_values($event['filter_fields']),
			];
		}

		return $rows;
	}

	/**
	 * Catalogue with localized descriptions — the panel renders this, so the
	 * event list follows the operator's language like every other label.
	 *
	 * The translator receives a language key and returns its text; when a key is
	 * missing the key itself is returned by Language::get(), which keeps the gap
	 * visible instead of silently falling back to English.
	 *
	 * @param callable(string): string $translate
	 * @return array<int, array<string, mixed>>
	 */
	public static function catalogueTranslated(callable $translate): array {
		$rows = [];

		foreach (self::catalogue() as $event) {
			$event['description'] = (string)$translate($event['description_key']);

			$rows[] = $event;
		}

		return $rows;
	}

	/**
	 * Implemented event types (explicit subscription targets).
	 *
	 * @return array<int, string>
	 */
	public static function implemented(): array {
		$types = [];

		foreach (self::CATALOGUE as $type => $event) {
			if ($event['implemented']) {
				$types[] = (string)$type;
			}
		}

		return $types;
	}

	/**
	 * Known domains (for wildcard validation).
	 *
	 * @return array<int, string>
	 */
	public static function domains(): array {
		return array_values(array_unique(array_map(static function (array $event): string {
			return (string)$event['domain'];
		}, self::CATALOGUE)));
	}

	public static function isImplemented(string $type): bool {
		return isset(self::CATALOGUE[$type]) && (bool)self::CATALOGUE[$type]['implemented'];
	}

	public static function isKnown(string $type): bool {
		return isset(self::CATALOGUE[$type]);
	}

	/**
	 * Does a subscription pattern (`order.*`, `*`, exact type) match an event?
	 */
	public static function matches(string $pattern, string $type): bool {
		$pattern = trim($pattern);

		if ($pattern === '*' || $pattern === '') {
			return $pattern === '*';
		}

		if (substr($pattern, -2) === '.*') {
			$domain = substr($pattern, 0, -2);

			return $type === $domain || strpos($type, $domain . '.') === 0;
		}

		return $pattern === $type;
	}

	/**
	 * @param array<int, string> $patterns
	 */
	public static function matchesAny(array $patterns, string $type): bool {
		foreach ($patterns as $pattern) {
			if (self::matches((string)$pattern, $type)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes the posted subscription event list (strings, unique, trimmed).
	 *
	 * @param array $events raw posted array
	 * @return array<int, string>
	 */
	public static function normalize(array $events): array {
		$normalized = [];

		foreach ($events as $event) {
			if (!is_scalar($event)) {
				continue;
			}

			$event = trim((string)$event);

			if ($event !== '' && !in_array($event, $normalized, true)) {
				$normalized[] = $event;
			}
		}

		return $normalized;
	}

	/**
	 * Returns the posted entries that are neither an implemented event nor a
	 * valid wildcard (`*` or `<known-domain>.*`).
	 *
	 * @param array<int, string> $events normalized list
	 * @return array<int, string>
	 */
	public static function invalidEvents(array $events): array {
		$invalid = [];

		foreach ($events as $event) {
			if (self::isImplemented($event) || self::isWildcard($event)) {
				continue;
			}

			$invalid[] = $event;
		}

		return $invalid;
	}

	/**
	 * @return array<int, string>
	 */
	public static function invalidWildcards(array $events): array {
		$invalid = [];

		foreach ($events as $event) {
			if (substr($event, -2) === '.*') {
				$domain = substr($event, 0, -2);

				if (!in_array($domain, self::domains(), true)) {
					$invalid[] = $event;
				}
			}
		}

		return $invalid;
	}

	/**
	 * Filter keys allowed for a subscription (union over its patterns).
	 *
	 * @param array<int, string> $events normalized list
	 * @return array<int, string>
	 */
	public static function allowedFilterKeys(array $events): array {
		$allowed = [];

		foreach (self::CATALOGUE as $type => $event) {
			if (!self::matchesAny($events, (string)$type)) {
				continue;
			}

			foreach ($event['filter_fields'] as $field) {
				$allowed[$field] = true;
			}
		}

		return array_keys($allowed);
	}

	/**
	 * Filter keys that the subscription could never match.
	 *
	 * @param array<int, string> $events  normalized list
	 * @param array<string, mixed> $filters
	 * @return array<int, string>
	 */
	public static function invalidFilterKeys(array $events, array $filters): array {
		$allowed = self::allowedFilterKeys($events);
		$invalid = [];

		foreach (array_keys($filters) as $key) {
			if (!in_array((string)$key, $allowed, true)) {
				$invalid[] = (string)$key;
			}
		}

		return $invalid;
	}

	/**
	 * Normalizes/validates a filter document. Returns null when it is not an
	 * object of scalars / lists of scalars.
	 *
	 * @param mixed $filters
	 * @return array<string, mixed>|null
	 */
	public static function normalizeFilters($filters): ?array {
		// A JSON string is accepted as well (the panel posts a textarea, the API
		// accepts either a JSON object or an equivalent JSON string).
		if (is_string($filters)) {
			// Request::clean() runs htmlspecialchars() over $_POST, so a JSON string
			// posted from the panel arrives as `{&quot;a&quot;:1}`. Decoding entities is
			// safe here because valid JSON never contains them intentionally.
			$filters = html_entity_decode(trim($filters), ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($filters === '') {
				return [];
			}

			$decoded = json_decode($filters, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				return null;
			}

			$filters = $decoded;
		}

		if ($filters === null || $filters === []) {
			return [];
		}

		if (!is_array($filters) || self::isList($filters)) {
			return null;
		}

		$normalized = [];

		foreach ($filters as $key => $value) {
			$key = trim((string)$key);

			if ($key === '') {
				return null;
			}

			if (is_array($value)) {
				if (!self::isList($value)) {
					return null;
				}

				$list = [];

				foreach ($value as $item) {
					if (!is_scalar($item)) {
						return null;
					}

					$list[] = is_bool($item) ? (int)$item : $item;
				}

				if (!$list) {
					return null;
				}

				$normalized[$key] = $list;
			} elseif (is_scalar($value)) {
				// Numbers stay numbers so an API round trip keeps its types; the
				// matcher compares scalars as strings, so 2 still matches "2".
				$normalized[$key] = is_bool($value) ? (int)$value : $value;
			} else {
				return null;
			}
		}

		return $normalized;
	}

	/**
	 * Evaluates a normalized filter document against the event data.
	 *
	 * Scalar vs scalar: string comparison (so 0 matches "0").
	 * Array filter: any-of. Data array + scalar filter: membership.
	 *
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $data
	 */
	public static function matchesFilters(array $filters, array $data): bool {
		foreach ($filters as $key => $expected) {
			if (!array_key_exists($key, $data)) {
				return false;
			}

			$actual = $data[$key];

			if (is_array($expected)) {
				if (!self::intersects($expected, $actual)) {
					return false;
				}

				continue;
			}

			if (is_array($actual)) {
				if (!in_array((string)$expected, array_map('strval', $actual), true)) {
					return false;
				}

				continue;
			}

			if (self::scalarToString($actual) !== self::scalarToString($expected)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Valid JSON object check for a filter document (used by validators).
	 */
	public static function isList(array $value): bool {
		if (!$value) {
			return true;
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @param array<int, string> $expected
	 * @param mixed $actual
	 */
	private static function intersects(array $expected, $actual): bool {
		$actual_list = is_array($actual) ? array_map('strval', $actual) : [self::scalarToString($actual)];

		foreach ($expected as $item) {
			if (in_array((string)$item, $actual_list, true)) {
				return true;
			}
		}

		return false;
	}

	private static function scalarToString($value): string {
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		return (string)$value;
	}

	private static function isWildcard(string $event): bool {
		if ($event === '*') {
			return true;
		}

		if (substr($event, -2) !== '.*') {
			return false;
		}

		return in_array(substr($event, 0, -2), self::domains(), true);
	}
}
