<?php
/**
 * Customer API keys — per-account cap and customer scoping.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

require_once UPLOAD_DIR . '/admin/model/setting/api_key.php';
require_once UPLOAD_DIR . '/catalog/model/account/api_key.php';

/**
 * Registry with a FakeDb and the customer key cap.
 */
function customer_api_keys_registry(FakeDb $db, int $max_keys = 0): \Registry {
	$registry = new \Registry();

	$config = new \Config();
	$config->set('config_api_customer_max_keys', $max_keys);

	$registry->set('db', $db);
	$registry->set('config', $config);

	return $registry;
}

return [
	'customer api: the per-account key cap defaults to 100' => static function (): void {
		$model = new ModelSettingApiKey(customer_api_keys_registry(new FakeDb(), 0));

		Assert::same(100, $model->getMaxCustomerKeys());

		$model = new ModelSettingApiKey(customer_api_keys_registry(new FakeDb(), 25));

		Assert::same(25, $model->getMaxCustomerKeys());
	},

	'customer api: the admin model refuses a key at the cap' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['total' => 99]]);

		$model = new ModelSettingApiKey(customer_api_keys_registry($db, 100));

		Assert::true($model->canAddCustomerKey(7));

		$db = new FakeDb();
		$db->pushResult([['total' => 100]]);

		$model = new ModelSettingApiKey(customer_api_keys_registry($db, 100));

		Assert::false($model->canAddCustomerKey(7));
	},

	'customer api: the account model refuses a key at the cap' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['total' => 100]]);

		$model = new ModelAccountApiKey(customer_api_keys_registry($db, 100));

		Assert::false($model->canAdd(7));

		$db = new FakeDb();
		$db->pushResult([['total' => 1]]);

		$model = new ModelAccountApiKey(customer_api_keys_registry($db, 100));

		Assert::true($model->canAdd(7));
	},

	'customer api: a customer key is stored with its customer_id and scopes' => static function (): void {
		$db = new FakeDb();

		$model = new ModelSettingApiKey(customer_api_keys_registry($db, 100));

		$created = $model->addKey([
			'name'        => 'Integration',
			'customer_id' => 7,
			'scopes'      => ['orders:read'],
		]);

		Assert::same('dcapi_', substr($created['token'], 0, 6));

		$sql = $db->queries[0] ?? '';

		Assert::contains("`customer_id` = '7'", $sql, 'the key must be bound to the customer');
		Assert::contains("`user_id` = '0'", $sql, 'a customer key has no admin owner');
		Assert::contains("`scopes` = '", $sql, 'the key scope list must be stored');
		Assert::contains('orders:read', $sql, 'the key scope list must be stored');
	},

	'customer api: the account model scopes every mutation to the customer' => static function (): void {
		$db = new FakeDb();

		$model = new ModelAccountApiKey(customer_api_keys_registry($db, 100));

		$model->deleteKey(7, 42);

		$sql = $db->queries[0] ?? '';

		Assert::contains("`api_key_id` = '42'", $sql);
		Assert::contains("`customer_id` = '7'", $sql, 'a customer must not delete another customer\'s key');
	},

	'customer api: the central admin list excludes customer keys' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([]);

		$model = new ModelSettingApiKey(customer_api_keys_registry($db, 100));

		$model->getKeys();

		Assert::contains("k.`customer_id` = '0'", (string)($db->queries[0] ?? ''), 'the admin list must stay admin-only');
	},

	'customer api: the two API surfaces reject each other\'s keys' => static function (): void {
		$admin = (string)file_get_contents(UPLOAD_DIR . '/admin/controller/startup/api.php');
		$store = (string)file_get_contents(UPLOAD_DIR . '/admin/controller/startup/store_api.php');

		Assert::contains("customer_id'] ?? 0) > 0", $admin, 'the admin API must reject customer keys');
		Assert::contains('$customer_id <= 0', $store, 'the store API must reject admin/shared keys');
	},
];
