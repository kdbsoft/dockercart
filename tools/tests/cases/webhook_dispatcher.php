<?php
/**
 * Outbox dispatcher: fan-out, filters, payloads and safety guarantees.
 *
 * Runs against a fake DB, so these tests prove the contract that matters for the
 * business transactions: no nested transaction, no HTTP, no exception escaping.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Dispatcher;

/**
 * Builds a registry whose `db` is the fake and remembers the last queries.
 */
function webhook_test_registry(FakeDb $db): \Registry {
	$registry = new \Registry();
	$registry->set('db', $db);
	$registry->set('log', new \Log('webhook-tests.log'));

	return $registry;
}

/**
 * @param array<int, array<string, mixed>> $subscriptions
 */
function webhook_test_subscriptions(array $subscriptions): FakeDb {
	$db = new FakeDb();
	$db->pushResult($subscriptions);

	return $db;
}

function webhook_test_delivery_inserts(FakeDb $db): array {
	return array_values(array_filter($db->queries, static function (string $sql): bool {
		return stripos($sql, 'INSERT IGNORE INTO') !== false && stripos($sql, 'dockercart_webhook_delivery') !== false;
	}));
}

return [
	'dispatcher: no subscriptions means no work' => static function (): void {
		$db = webhook_test_subscriptions([]);
		Dispatcher::resetCache();

		Assert::same(0, (new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1]));
		Assert::same([], webhook_test_delivery_inserts($db));
	},

	'dispatcher: writes one outbox row per matching subscription' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
			['subscription_id' => 2, 'events' => '["order.*"]', 'filters' => null, 'full_payload' => 0],
			['subscription_id' => 3, 'events' => '["stock.changed"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		$enqueued = (new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1234]);

		Assert::same(2, $enqueued);
		Assert::count(2, webhook_test_delivery_inserts($db));
	},

	'dispatcher: payload carries id, type, occurred_at (UTC) and data' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.status_changed"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		(new Dispatcher(webhook_test_registry($db)))->emit('order.status_changed', [
			'order_id'       => 77,
			'from_status_id' => 132,
			'to_status_id'   => 133,
			'version'        => 991,
		]);

		$insert = webhook_test_delivery_inserts($db)[0] ?? '';

		Assert::contains("'order.status_changed'", $insert);
		Assert::contains('evt_', $insert);
		Assert::contains('\\"order_id\\":77', $insert);
		Assert::contains('\\"version\\":991', $insert);
		Assert::contains('occurred_at', $insert);
		Assert::true((bool)preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/', $insert), 'occurred_at must be an ISO-8601 UTC instant');
	},

	'dispatcher: the same event id is reused for every subscription' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
			['subscription_id' => 2, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		(new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 5]);

		$ids = [];

		foreach (webhook_test_delivery_inserts($db) as $insert) {
			preg_match("/evt_[0-9A-Z]{26}/", $insert, $matches);
			$ids[] = $matches[0] ?? '';
		}

		Assert::count(2, $ids);
		Assert::same($ids[0], $ids[1], 'the event id must identify the event, not the delivery');
	},

	'dispatcher: filters are evaluated at emit time' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["stock.changed"]', 'filters' => '{"warehouse_id":2}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();
		$dispatcher = new Dispatcher(webhook_test_registry($db));

		Assert::same(0, $dispatcher->emit('stock.changed', ['warehouse_id' => 1]));
		Assert::same(1, $dispatcher->emit('stock.changed', ['warehouse_id' => 2]));
	},

	'dispatcher: a scalar filter matches inside an array value' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{"warehouse_ids":2}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();
		$dispatcher = new Dispatcher(webhook_test_registry($db));

		Assert::same(0, $dispatcher->emit('order.created', ['warehouse_ids' => [1]]));
		Assert::same(1, $dispatcher->emit('order.created', ['warehouse_ids' => [1, 2]]));
	},

	'dispatcher: full payload is built once and only for opted-in subscriptions' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 1],
			['subscription_id' => 2, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		$calls = 0;

		(new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 9], [
			'full' => static function () use (&$calls): array {
				$calls++;

				return ['order_id' => 9, 'total' => '42.5000'];
			},
		]);

		$inserts = webhook_test_delivery_inserts($db);

		Assert::count(2, $inserts);
		Assert::same(1, $calls, 'the full payload must be built once per emit');
		Assert::contains('\\"object\\"', $inserts[0], 'the opted-in subscription gets data.object');
		Assert::notContains('\\"object\\"', $inserts[1], 'the thin subscription must stay thin');
	},

	'dispatcher: no full payload when the callable is absent' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 1],
		]);
		Dispatcher::resetCache();

		Assert::same(1, (new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1]));
		Assert::notContains('\\"object\\"', webhook_test_delivery_inserts($db)[0]);
	},

	'dispatcher: malformed subscription JSON is skipped, not fatal' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => 'not-json', 'filters' => '{}', 'full_payload' => 0],
			['subscription_id' => 2, 'events' => '["order.created"]', 'filters' => 'not-json', 'full_payload' => 0],
			['subscription_id' => 3, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		$enqueued = (new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1]);

		Assert::same(1, $enqueued, 'only the healthy subscription may receive the event');
	},

	'dispatcher: never opens a transaction and never sends' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();

		(new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1]);

		Assert::false($db->hasTransactionStatement(), 'emit() must run inside the caller transaction');
		Assert::false($db->hasHttpStatement(), 'emit() must not perform HTTP work');
	},

	'dispatcher: a database failure is swallowed (webhooks never break an order)' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0]]);
		$db->failOn('INSERT IGNORE INTO');
		Dispatcher::resetCache();

		Assert::same(0, (new Dispatcher(webhook_test_registry($db)))->emit('order.created', ['order_id' => 1]));
	},

	'dispatcher: an empty event type is ignored' => static function (): void {
		$db = webhook_test_subscriptions([]);
		Dispatcher::resetCache();

		Assert::same(0, (new Dispatcher(webhook_test_registry($db)))->emit('  ', ['order_id' => 1]));
		Assert::count(0, $db->queries);
	},

	'dispatcher: the subscription list is cached per request' => static function (): void {
		$db = webhook_test_subscriptions([
			['subscription_id' => 1, 'events' => '["order.created"]', 'filters' => '{}', 'full_payload' => 0],
		]);
		Dispatcher::resetCache();
		$dispatcher = new Dispatcher(webhook_test_registry($db));

		$dispatcher->emit('order.created', ['order_id' => 1]);
		$dispatcher->emit('order.created', ['order_id' => 2]);

		$selects = array_filter($db->queries, static function (string $sql): bool {
			return stripos($sql, 'FROM `oc_dockercart_webhook_subscription`') !== false && stripos($sql, 'SELECT') === 0;
		});

		Assert::count(1, $selects, 'subscriptions must be read once per request');
	},

	'delivery: enqueueTest queues a signed webhook.test row' => static function (): void {
		$db = new FakeDb();
		// SELECT of the subscription, then the INSERT answer.
		$db->pushResult([['subscription_id' => 5, 'name' => 'ERP', 'status' => 1]]);
		Dispatcher::resetCache();

		$queued = (new \Dockercart\Webhook\Delivery(webhook_test_registry($db)))->enqueueTest(5, 'panel test');

		Assert::true(is_array($queued), 'a test event must be queued');
		Assert::true($queued['delivery_id'] > 0);
		Assert::same(30, strlen($queued['event_id']));

		$insert = implode("\n", webhook_test_delivery_inserts($db));

		Assert::contains('webhook.test', $insert);
		Assert::contains("`is_test` = '1'", $insert);
		Assert::contains('panel test', $insert);
	},

	'delivery: enqueueTest refuses a disabled or unknown subscription' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['subscription_id' => 5, 'name' => 'ERP', 'status' => 0]]);
		Dispatcher::resetCache();

		Assert::same(null, (new \Dockercart\Webhook\Delivery(webhook_test_registry($db)))->enqueueTest(5, 'x'));

		$db = new FakeDb();
		$db->pushResult([]);
		Dispatcher::resetCache();

		Assert::same(null, (new \Dockercart\Webhook\Delivery(webhook_test_registry($db)))->enqueueTest(404, 'x'));
	},

	'delivery: reset re-queues only failed rows' => static function (): void {
		$db = new FakeDb();
		$delivery = new \Dockercart\Webhook\Delivery(webhook_test_registry($db));

		// countAffected() is 0 by default -> nothing was updated.
		Assert::false($delivery->reset(10));

		$update = implode("\n", $db->queries);

		Assert::contains("`status` = 'failed'", $update, 'the retry must target failed rows only');
		Assert::contains("`attempts` = '0'", $update);
		Assert::contains("`next_attempt_at` = NOW()", $update);
	},

	'delivery: cancelPending only touches pending rows of one subscription' => static function (): void {
		$db = new FakeDb();
		$delivery = new \Dockercart\Webhook\Delivery(webhook_test_registry($db));

		$delivery->cancelPending(7);

		$update = implode("\n", $db->queries);

		Assert::contains("`subscription_id` = '7'", $update);
		Assert::contains("AND `status` = 'pending'", $update);
		Assert::contains('subscription_disabled', $update);
	},
];
