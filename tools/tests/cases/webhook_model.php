<?php
/**
 * Admin model: validation, normalization and the guarantee that secrets never
 * leak back through the normalized row.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Loads the admin webhook model with a fake DB and a real Config.
 */
function webhook_test_model(): ModelSettingWebhook {
	if (!class_exists('ModelSettingWebhook', false)) {
		require_once DIR_APPLICATION . 'model/setting/webhook.php';
	}

	$config = new \Config();
	$config->load('default');
	$config->set('config_encryption', str_repeat('k', 64));

	$registry = new \Registry();
	$registry->set('db', new FakeDb());
	$registry->set('config', $config);
	$registry->set('log', new \Log('webhook-tests.log'));

	return new ModelSettingWebhook($registry);
}

return [
	'validate: a complete valid subscription passes' => static function (): void {
		$model = webhook_test_model();

		Assert::same([], $model->validate([
			'name'    => 'ERP order sync',
			'url'     => 'https://erp.example.com/hooks/dockercart',
			'events'  => ['order.created', 'order.status_changed'],
			'filters' => ['store_id' => 0],
		]));
	},

	'validate: wildcards are accepted' => static function (): void {
		$model = webhook_test_model();

		Assert::same([], $model->validate([
			'name'   => 'Everything',
			'url'    => 'https://erp.example.com/hooks',
			'events' => ['order.*', 'stock.*', '*'],
		]));
	},

	'validate: name is required and bounded' => static function (): void {
		$model = webhook_test_model();

		Assert::same('invalid_name', $model->validate(['name' => '', 'url' => 'https://a.example.com/h', 'events' => ['order.created']])['name'] ?? null);
		Assert::same('invalid_name', $model->validate(['name' => str_repeat('x', 129), 'url' => 'https://a.example.com/h', 'events' => ['order.created']])['name'] ?? null);
	},

	'validate: at least one event is required' => static function (): void {
		$model = webhook_test_model();

		Assert::same('events_required', $model->validate(['name' => 'x', 'url' => 'https://a.example.com/h', 'events' => []])['events'] ?? null);
	},

	'validate: unknown and not-yet-implemented events are refused' => static function (): void {
		$model = webhook_test_model();

		Assert::same('unknown_event:order.nope', $model->validate(['name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.nope']])['events'] ?? null);
		Assert::same('unknown_event:product.created', $model->validate(['name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['product.created']])['events'] ?? null);
	},

	'validate: private and malformed endpoints are refused' => static function (): void {
		$model = webhook_test_model();

		Assert::same('ssrf_blocked', $model->validate(['name' => 'x', 'url' => 'http://10.0.0.5/hook', 'events' => ['order.created']])['url'] ?? null);
		Assert::same('ssrf_blocked', $model->validate(['name' => 'x', 'url' => 'http://localhost/hook', 'events' => ['order.created']])['url'] ?? null);
		Assert::same('credentials_not_allowed', $model->validate(['name' => 'x', 'url' => 'https://u:p@a.example.com/h', 'events' => ['order.created']])['url'] ?? null);
		Assert::same('invalid_scheme', $model->validate(['name' => 'x', 'url' => 'ftp://a.example.com/h', 'events' => ['order.created']])['url'] ?? null);
		Assert::same('invalid_port', $model->validate(['name' => 'x', 'url' => 'https://a.example.com:8443/h', 'events' => ['order.created']])['url'] ?? null);
	},

	'validate: filter keys must be supported by the selected events' => static function (): void {
		$model = webhook_test_model();

		Assert::same('unsupported_filter:nope', $model->validate([
			'name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.created'], 'filters' => ['nope' => 1],
		])['filters'] ?? null);

		Assert::same('unsupported_filter:warehouse_id', $model->validate([
			'name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.created'], 'filters' => ['warehouse_id' => 1],
		])['filters'] ?? null);

		Assert::same([], $model->validate([
			'name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.created'], 'filters' => ['warehouse_ids' => 1],
		]));
	},

	'validate: filter documents of the wrong shape are refused' => static function (): void {
		$model = webhook_test_model();

		foreach (['{oops', '["a"]', '{"store_id":{"nested":1}}'] as $filters) {
			Assert::same('invalid_filters', $model->validate([
				'name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.created'], 'filters' => $filters,
			])['filters'] ?? null, 'filters: ' . $filters);
		}
	},

	'normalizeData: trims, normalizes events and filters' => static function (): void {
		$model = webhook_test_model();

		$data = $model->normalizeData([
			'name'         => '  ERP  ',
			'url'          => ' https://erp.example.com/h ',
			'events'       => [' order.created ', 'order.created', 'stock.changed'],
			'filters'      => '{"warehouse_id":2}',
			'full_payload' => '1',
			'status'       => '0',
		]);

		Assert::same('ERP', $data['name']);
		Assert::same('https://erp.example.com/h', $data['url']);
		Assert::same(['order.created', 'stock.changed'], $data['events']);
		Assert::same('{"warehouse_id":2}', $data['filters_json']);
		Assert::same(1, $data['full_payload']);
		Assert::same(0, $data['status']);
	},

	'normalizeData: defaults status to enabled and filters to an empty object' => static function (): void {
		$model = webhook_test_model();

		$data = $model->normalizeData(['name' => 'x', 'url' => 'https://a.example.com/h', 'events' => ['order.created']]);

		Assert::same(1, $data['status']);
		Assert::same(0, $data['full_payload']);
		Assert::same('[]', $data['filters_json']);
	},

	'normalize: never exposes the encrypted secret' => static function (): void {
		$model = webhook_test_model();

		$method = new \ReflectionMethod($model, 'normalize');
		$method->setAccessible(true);

		$row = $method->invoke($model, [
			'subscription_id'  => 3,
			'name'             => 'ERP',
			'url'              => 'https://erp.example.com/h',
			'secret_encrypted' => 'ciphertext',
			'secret_hint'      => 'whsec_abcd',
			'events'           => '["order.created"]',
			'filters'          => '{"store_id":1}',
			'full_payload'     => '1',
		]);

		Assert::false(array_key_exists('secret_encrypted', $row), 'the ciphertext must not reach a view or an API response');
		Assert::same(['order.created'], $row['events_list']);
		Assert::same(['store_id' => 1], $row['filters_array']);
		Assert::same('whsec_abcd', $row['secret_hint']);
	},
];
