<?php
/**
 * Full order payload (`data.object`): shape, language metadata and privacy.
 *
 * OrderPayload reads the DB directly, so this case uses the fake DB with a
 * documented query order.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\OrderPayload;

/**
 * Fake DB pre-loaded with one order, its options/products/totals/payments and
 * the order language. Query order is exactly the order OrderPayload::full()
 * issues them.
 */
function webhook_order_payload_db(): FakeDb {
	$db = new FakeDb();

	// 1) the order row
	$db->pushResult([[
		'order_id' => 1234, 'invoice_no' => 7, 'invoice_prefix' => 'INV-', 'store_id' => 0,
		'store_name' => 'Test store', 'customer_id' => 9, 'customer_group_id' => 1,
		'order_status_id' => 132, 'currency_code' => 'EUR', 'currency_value' => 1.0,
		'total' => 42.5, 'paid_amount' => 40.0, 'payment_code' => 'cod', 'shipping_code' => 'flat.flat',
		'tracking_number' => 'TRACK1', 'language_id' => 3,
		'date_added' => '2026-10-21 10:15:00', 'date_modified' => '2026-10-21 10:16:00',
		// PII that must never be copied into the payload:
		'firstname' => 'Test', 'lastname' => 'Buyer', 'email' => 'buyer@example.com',
		'telephone' => '555', 'payment_address_1' => 'street 1', 'shipping_address_1' => 'street 2',
		'comment' => 'leave at the door', 'ip' => '127.0.0.1', 'user_agent' => 'agent',
	]]);

	// 2) SHOW TABLES language -> exists
	$db->pushResult([['Tables_in_db (oc_language)' => 'oc_language']]);
	// 3) language row of the order
	$db->pushResult([['code' => 'uk-ua', 'name' => 'Українська']]);
	// 4) SHOW TABLES order_option -> exists
	$db->pushResult([['Tables_in_db (oc_order_option)' => 'oc_order_option']]);
	// 5) options
	$db->pushResult([['order_product_id' => 500, 'name' => 'Розмір', 'value' => 'XL', 'type' => 'select']]);
	// 6) order products
	$db->pushResult([[
		'order_product_id' => 500, 'product_id' => 42, 'variant_id' => 8, 'variant_sku' => 'SKU-8',
		'name' => 'Футболка', 'model' => 'TS-1', 'quantity' => 2, 'price' => 20.0, 'total' => 40.0,
		'tax' => 0, 'reward' => 0, 'warehouse_id' => 1, 'warehouse_name' => 'Склад', 'estimate_date' => '2026-10-25',
	]]);
	// 7) order totals
	$db->pushResult([
		['code' => 'sub_total', 'title' => 'Проміжний підсумок', 'value' => 40.0, 'sort_order' => 1],
		['code' => 'total', 'title' => 'Разом', 'value' => 42.5, 'sort_order' => 9],
	]);
	// 8) SHOW TABLES order_payment -> exists
	$db->pushResult([['Tables_in_db (oc_order_payment)' => 'oc_order_payment']]);
	// 9) payments
	$db->pushResult([[
		'order_payment_id' => 33, 'amount' => 40.0, 'payment_method' => 'Готівка', 'payment_code' => 'cod',
		'reference' => 'REF-1', 'comment' => 'operator note', 'date_added' => '2026-10-21 10:16:00',
	]]);

	return $db;
}

function webhook_order_payload(FakeDb $db): OrderPayload {
	$registry = new \Registry();
	$registry->set('db', $db);
	$registry->set('log', new \Log('webhook-tests.log'));

	return new OrderPayload($registry);
}

return [
	'payload: exposes the order header, products, totals and payments' => static function (): void {
		$payload = webhook_order_payload(webhook_order_payload_db())->full(1234);

		Assert::same(1234, $payload['order_id']);
		Assert::same('42.5000', $payload['total'], 'money is a fixed point string');
		Assert::same('40.0000', $payload['paid_amount']);
		Assert::same(1, count($payload['products']));
		Assert::same(2, count($payload['totals']));
		Assert::same(1, count($payload['payments']));
		Assert::same('XL', $payload['products'][0]['options'][0]['value']);
	},

	'payload: carries no personal data and no free-text notes' => static function (): void {
		$payload = webhook_order_payload(webhook_order_payload_db())->full(1234);

		foreach (['firstname', 'lastname', 'email', 'telephone', 'payment_address', 'shipping_address', 'comment', 'ip', 'user_agent'] as $field) {
			Assert::false(array_key_exists($field, $payload), 'the payload must not expose ' . $field);
		}

		Assert::notContains('buyer@example.com', json_encode($payload));
		Assert::notContains('street 1', json_encode($payload));
		Assert::notContains('leave at the door', json_encode($payload));

		// Payment comments are free text and are dropped as well.
		Assert::false(array_key_exists('comment', $payload['payments'][0]), 'payment comments must not be forwarded');
	},

	'payload: snapshot texts are kept verbatim with their language' => static function (): void {
		$payload = webhook_order_payload(webhook_order_payload_db())->full(1234);

		// The order was placed in uk-ua: names/titles stay in that language.
		Assert::same('Футболка', $payload['products'][0]['name']);
		Assert::same('Разом', $payload['totals'][1]['title']);
		Assert::same('uk-ua', $payload['language_code'], 'the receiver must know which language the snapshot is in');
		Assert::same('Українська', $payload['language_name']);
		Assert::same(3, $payload['language_id']);
	},

	'payload: an unknown order yields an empty object' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([]); // the order lookup finds nothing

		Assert::same([], webhook_order_payload($db)->full(999));
	},

	'payload: English and other locales are not rewritten' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([[
			'order_id' => 1, 'store_id' => 0, 'store_name' => 'Shop', 'customer_id' => 0, 'customer_group_id' => 1,
			'order_status_id' => 1, 'currency_code' => 'USD', 'currency_value' => 1.0, 'total' => 10.0,
			'paid_amount' => 0, 'language_id' => 1, 'date_added' => '2026-10-21 10:00:00', 'date_modified' => '2026-10-21 10:00:00',
		]]);
		$db->pushResult([]);          // SHOW TABLES language -> absent
		$db->pushResult([]);          // SHOW TABLES order_option -> absent
		$db->pushResult([]);          // products -> none
		$db->pushResult([]);          // totals -> none
		$db->pushResult([]);          // SHOW TABLES order_payment -> absent

		$payload = webhook_order_payload($db)->full(1);

		Assert::same('', $payload['language_code'], 'no language table means no invented language');
		Assert::same([], $payload['products']);
		Assert::same([], $payload['payments']);
	},
];
