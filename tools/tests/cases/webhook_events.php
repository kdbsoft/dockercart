<?php
/**
 * Event catalogue, wildcard matching and filter evaluation.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Events;

return [
	'catalogue: ships the three implemented events' => static function (): void {
		Assert::same(['order.created', 'order.status_changed', 'stock.changed'], Events::implemented());
	},

	'catalogue: every event has a domain, description and data fields' => static function (): void {
		foreach (Events::catalogue() as $event) {
			Assert::true(strpos($event['type'], '.') !== false, $event['type'] . ' must be namespaced');
			Assert::true($event['domain'] !== '', $event['type'] . ' needs a domain');
			Assert::true($event['description'] !== '', $event['type'] . ' needs a description');
			Assert::true(count($event['data_fields']) > 0, $event['type'] . ' needs data fields');
			Assert::true(in_array($event['domain'], Events::domains(), true), $event['type'] . ' domain must be listed');
		}
	},

	'catalogue: filter fields are a subset of the payload fields' => static function (): void {
		foreach (Events::catalogue() as $event) {
			foreach ($event['filter_fields'] as $field) {
				Assert::true(
					in_array($field, $event['data_fields'], true),
					$event['type'] . ' filters on "' . $field . '" which is not in data_fields'
				);
			}
		}
	},

	'catalogue: implemented entries carry the documented payload keys' => static function (): void {
		$expected = [
			'order.created'        => ['order_id', 'store_id', 'customer_id', 'order_status_id', 'total', 'currency_code', 'payment_code', 'shipping_code', 'item_count', 'warehouse_ids', 'version'],
			'order.status_changed' => ['order_id', 'store_id', 'from_status_id', 'to_status_id', 'override', 'source', 'is_cancelled', 'is_final', 'is_terminal', 'version'],
			'stock.changed'        => ['warehouse_id', 'product_id', 'variant_id', 'delta', 'quantity', 'type', 'reference', 'order_id', 'transfer_id', 'movement_id', 'version'],
		];

		foreach (Events::catalogue() as $event) {
			if (!$event['implemented']) {
				continue;
			}

			Assert::true(isset($expected[$event['type']]), 'unexpected implemented event ' . $event['type']);
			Assert::same($expected[$event['type']], $event['data_fields'], $event['type'] . ' payload contract changed');
		}
	},

	'matches: exact type' => static function (): void {
		Assert::true(Events::matches('order.created', 'order.created'));
		Assert::false(Events::matches('order.created', 'order.updated'));
	},

	'matches: domain wildcard does not leak into prefixed domains' => static function (): void {
		Assert::true(Events::matches('order.*', 'order.created'));
		Assert::true(Events::matches('order.*', 'order.status_changed'));
		Assert::false(Events::matches('order.*', 'order_line.created'));
		Assert::false(Events::matches('order.*', 'stock.changed'));
	},

	'matches: global wildcard matches everything' => static function (): void {
		Assert::true(Events::matches('*', 'order.created'));
		Assert::true(Events::matches('*', 'webhook.test'));
	},

	'matches: empty pattern never matches' => static function (): void {
		Assert::false(Events::matches('', 'order.created'));
	},

	'matchesAny: any of the patterns' => static function (): void {
		Assert::true(Events::matchesAny(['stock.changed', 'order.*'], 'order.created'));
		Assert::false(Events::matchesAny(['stock.changed'], 'order.created'));
		Assert::false(Events::matchesAny([], 'order.created'));
	},

	'normalize: trims, deduplicates and drops non-scalars' => static function (): void {
		Assert::same(
			['order.created', 'stock.changed'],
			Events::normalize([' order.created ', 'order.created', 'stock.changed', ['nested'], null]
			)
		);
	},

	'invalidEvents: accepts implemented events and valid wildcards' => static function (): void {
		Assert::same([], Events::invalidEvents(['order.created', 'order.*', 'stock.changed', '*']));
	},

	'invalidEvents: rejects planned-only and unknown events' => static function (): void {
		Assert::same(['product.created'], Events::invalidEvents(['product.created']));
		Assert::same(['order.nope'], Events::invalidEvents(['order.nope']));
		Assert::same(['nope.*'], Events::invalidEvents(['nope.*']));
	},

	'invalidWildcards: unknown domain' => static function (): void {
		Assert::same(['nope.*'], Events::invalidWildcards(['order.*', 'nope.*']));
		Assert::same([], Events::invalidWildcards(['order.*', '*']));
	},

	'allowedFilterKeys: union over the subscribed events' => static function (): void {
		$order = Events::allowedFilterKeys(['order.created']);

		Assert::true(in_array('store_id', $order, true));
		Assert::true(in_array('payment_code', $order, true));
		Assert::false(in_array('warehouse_id', $order, true), 'order.created must not accept stock filters');

		$both = Events::allowedFilterKeys(['order.created', 'stock.changed']);

		Assert::true(in_array('warehouse_id', $both, true));
	},

	'invalidFilterKeys: reports unsupported keys' => static function (): void {
		Assert::same(['warehouse_id'], Events::invalidFilterKeys(['order.created'], ['warehouse_id' => 1]));
		Assert::same([], Events::invalidFilterKeys(['stock.changed'], ['warehouse_id' => 1]));
	},

	'normalizeFilters: empty values' => static function (): void {
		Assert::same([], Events::normalizeFilters(null));
		Assert::same([], Events::normalizeFilters(''));
		Assert::same([], Events::normalizeFilters([]));
		Assert::same([], Events::normalizeFilters('{}'));
	},

	'normalizeFilters: keeps scalar types' => static function (): void {
		Assert::same(
			['warehouse_id' => 2, 'type' => 'inbound', 'flag' => 1],
			Events::normalizeFilters(['warehouse_id' => 2, 'type' => 'inbound', 'flag' => true])
		);
	},

	'normalizeFilters: accepts a JSON string' => static function (): void {
		Assert::same(['store_id' => 1], Events::normalizeFilters('{"store_id":1}'));
	},

	'normalizeFilters: accepts the HTML-escaped JSON a panel form posts' => static function (): void {
		$posted = htmlspecialchars('{"warehouse_id":2}', ENT_COMPAT, 'UTF-8');

		Assert::same(['warehouse_id' => 2], Events::normalizeFilters($posted));
	},

	'normalizeFilters: rejects malformed input' => static function (): void {
		Assert::same(null, Events::normalizeFilters('{oops'));
		Assert::same(null, Events::normalizeFilters(['a', 'b']));
		Assert::same(null, Events::normalizeFilters(['store_id' => ['nested' => 1]]));
		Assert::same(null, Events::normalizeFilters(['store_id' => []]));
	},

	'matchesFilters: empty filter matches everything' => static function (): void {
		Assert::true(Events::matchesFilters([], ['order_id' => 1]));
	},

	'matchesFilters: scalar equality is type tolerant' => static function (): void {
		Assert::true(Events::matchesFilters(['order_id' => '123'], ['order_id' => 123]));
		Assert::true(Events::matchesFilters(['store_id' => 0], ['store_id' => '0']));
		Assert::false(Events::matchesFilters(['store_id' => 1], ['store_id' => '0']));
	},

	'matchesFilters: array filter means any-of' => static function (): void {
		Assert::true(Events::matchesFilters(['to_status_id' => [128, 129]], ['to_status_id' => 129]));
		Assert::false(Events::matchesFilters(['to_status_id' => [128, 129]], ['to_status_id' => 133]));
	},

	'matchesFilters: scalar filter matches inside an array value' => static function (): void {
		Assert::true(Events::matchesFilters(['warehouse_ids' => 2], ['warehouse_ids' => [1, 2, 3]]));
		Assert::false(Events::matchesFilters(['warehouse_ids' => 9], ['warehouse_ids' => [1, 2, 3]]));
	},

	'matchesFilters: missing data key never matches' => static function (): void {
		Assert::false(Events::matchesFilters(['warehouse_id' => 1], ['order_id' => 5]));
	},

	'matchesFilters: booleans compare as 1/0' => static function (): void {
		Assert::true(Events::matchesFilters(['is_terminal' => true], ['is_terminal' => true]));
		Assert::true(Events::matchesFilters(['is_terminal' => 1], ['is_terminal' => true]));
		Assert::false(Events::matchesFilters(['is_terminal' => false], ['is_terminal' => true]));
	},

	'isList: distinguishes objects from lists' => static function (): void {
		Assert::true(Events::isList([]));
		Assert::true(Events::isList(['a', 'b']));
		Assert::false(Events::isList(['a' => 1]));
	},
];
