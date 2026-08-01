<?php
declare(strict_types=1);

namespace Tests\Unit\Transaction;

use PHPUnit\Framework\TestCase;

/**
 * DB-backed regression tests for race-condition fixes:
 *  - duplicate orders on double-submit (order claim table)
 *  - single-use customer tokens
 *  - concurrent failed-login attempts (unique email)
 *  - concurrent abandoned-cart saves (unique session + recovered)
 *
 * Skips when no database is reachable (same convention as the other DB tests).
 */
class RaceSafetyTest extends TestCase
{
	private static $db = null;
	private static $registry = null;

	private const TEST_EMAIL = 'race-safety-test@example.com';
	private const TEST_SESSION_1 = 'race-safety-session-1';
	private const TEST_SESSION_2 = 'race-safety-session-2';

	public static function setUpBeforeClass(): void
	{
		$host = getenv('DB_HOSTNAME') ?: 'localhost';
		$user = getenv('DB_USERNAME') ?: 'dockercart';
		$pass = getenv('DB_PASSWORD') ?: 'dockercart_password';
		$name = getenv('DB_DATABASE') ?: 'dockercart';
		$port = (int)(getenv('DB_PORT') ?: 3306);
		$prefix = getenv('DB_PREFIX') ?: 'oc_';

		if (!defined('DB_PREFIX')) {
			define('DB_PREFIX', $prefix);
		}

		if (!class_exists(\mysqli::class)) {
			self::markTestSkipped('mysqli extension not available');

			return;
		}

		try {
			$con = new \mysqli($host, $user, $pass, $name, $port);

			if ($con->connect_errno) {
				self::markTestSkipped('Database connection not available: ' . $con->connect_error);

				return;
			}

			$con->close();
		} catch (\mysqli_sql_exception $e) {
			self::markTestSkipped('Database connection not available: ' . $e->getMessage());

			return;
		}

		require_once __DIR__ . '/../../../upload/system/library/db/mysqli.php';
		require_once __DIR__ . '/../../../upload/system/library/config.php';
		require_once __DIR__ . '/../../../upload/system/engine/registry.php';
		require_once __DIR__ . '/../../../upload/system/engine/model.php';

		if (!function_exists('utf8_strtolower')) {
			require_once __DIR__ . '/../../../upload/system/helper/utf8.php';
		}

		$dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
		$registry = new \Registry();
		$registry->set('db', $dbDriver);

		$config = new \Config();
		$registry->set('config', $config);

		// Minimal stand-in for the framework Loader: order model only calls
		// load->model() to make sure the voucher model file is loaded, and the
		// test data contains no vouchers.
		$loader = new class {
			public function model($route): void {
			}
		};
		$registry->set('load', $loader);

		$request = new \stdClass();
		$request->server = ['REMOTE_ADDR' => '127.0.0.1'];
		$registry->set('request', $request);

		$customer = new class {
			public function isLogged(): bool {
				return false;
			}

			public function getId() {
				return 0;
			}
		};
		$registry->set('customer', $customer);

		self::$db = $dbDriver;
		self::$registry = $registry;
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		$db = self::$db;

		$db->query("DELETE FROM " . DB_PREFIX . "order_claim WHERE session_id IN ('" . self::TEST_SESSION_1 . "', '" . self::TEST_SESSION_2 . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order_product WHERE order_id IN (SELECT order_id FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_id IN (SELECT order_id FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order_voucher WHERE order_id IN (SELECT order_id FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order_total WHERE order_id IN (SELECT order_id FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order_history WHERE order_id IN (SELECT order_id FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "')");
		$db->query("DELETE FROM " . DB_PREFIX . "order WHERE email = '" . self::TEST_EMAIL . "'");

		$db->query("DELETE FROM " . DB_PREFIX . "customer_login WHERE email = '" . self::TEST_EMAIL . "'");
		$db->query("DELETE FROM " . DB_PREFIX . "customer WHERE email = '" . self::TEST_EMAIL . "'");

		$abandoned = $db->query("SHOW TABLES LIKE '" . DB_PREFIX . "dockercart_checkout_abandoned'");
		if ($abandoned->num_rows) {
			$db->query("DELETE FROM " . DB_PREFIX . "dockercart_checkout_abandoned WHERE session_id IN ('" . self::TEST_SESSION_1 . "', '" . self::TEST_SESSION_2 . "')");
		}
	}

	private function buildOrderData(): array
	{
		return [
			'invoice_prefix' => 'INV-',
			'store_id' => 0,
			'store_name' => 'Test Store',
			'store_url' => 'http://localhost/',
			'customer_id' => 0,
			'customer_group_id' => 1,
			'firstname' => 'Race',
			'lastname' => 'Safety',
			'email' => self::TEST_EMAIL,
			'telephone' => '1234567890',
			'payment_firstname' => 'Race',
			'payment_lastname' => 'Safety',
			'payment_company' => '',
			'payment_address_1' => 'Test St 1',
			'payment_address_2' => '',
			'payment_city' => 'Testville',
			'payment_postcode' => '12345',
			'payment_country' => 'Testland',
			'payment_country_id' => 0,
			'payment_zone' => '',
			'payment_zone_id' => 0,
			'payment_address_format' => '',
			'payment_method' => 'Test Payment',
			'payment_code' => 'test_payment',
			'shipping_firstname' => 'Race',
			'shipping_lastname' => 'Safety',
			'shipping_company' => '',
			'shipping_address_1' => 'Test St 1',
			'shipping_address_2' => '',
			'shipping_city' => 'Testville',
			'shipping_postcode' => '12345',
			'shipping_country' => 'Testland',
			'shipping_country_id' => 0,
			'shipping_zone' => '',
			'shipping_zone_id' => 0,
			'shipping_address_format' => '',
			'shipping_method' => 'Test Shipping',
			'shipping_code' => 'test_shipping',
			'comment' => '',
			'total' => 10.00,
			'order_status_id' => 1,
			'affiliate_id' => 0,
			'commission' => 0,
			'marketing_id' => 0,
			'tracking' => '',
			'language_id' => 1,
			'currency_id' => 1,
			'currency_code' => 'USD',
			'currency_value' => 1.0,
			'ip' => '127.0.0.1',
			'forwarded_ip' => '',
			'user_agent' => 'PHPUnit',
			'accept_language' => 'en',
			'products' => [],
			'vouchers' => [],
			'totals' => [
				['code' => 'sub_total', 'title' => 'Sub-Total', 'value' => 10.00, 'sort_order' => 1],
				['code' => 'total', 'title' => 'Total', 'value' => 10.00, 'sort_order' => 9],
			],
		];
	}

	public function testOrderClaimTableExists(): void
	{
		$query = self::$db->query("SHOW TABLES LIKE '" . DB_PREFIX . "order_claim'");

		$this->assertGreaterThan(0, $query->num_rows, 'oc_order_claim table must exist (migration 20260801_order_claim_table.sql)');
	}

	public function testAddOrderIsIdempotentPerSession(): void
	{
		require_once __DIR__ . '/../../../upload/catalog/model/checkout/order.php';
		$model = new \ModelCheckoutOrder(self::$registry);

		$old = session_id();
		session_id(self::TEST_SESSION_1);

		try {
			$data = $this->buildOrderData();
			$first = $model->addOrder($data);
			$second = $model->addOrder($data);

			$this->assertGreaterThan(0, $first);
			$this->assertSame($first, $second, 'repeated addOrder for the same session must reuse the order');

			$count = self::$db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$first . "'");
			$this->assertEquals(1, (int)$count->row['total']);
		} finally {
			session_id((string)$old);
		}
	}

	public function testAddOrderDifferentSessionsCreateDifferentOrders(): void
	{
		require_once __DIR__ . '/../../../upload/catalog/model/checkout/order.php';
		$model = new \ModelCheckoutOrder(self::$registry);

		$old = session_id();
		session_id(self::TEST_SESSION_1);

		try {
			$first = $model->addOrder($this->buildOrderData());
			session_id(self::TEST_SESSION_2);
			$second = $model->addOrder($this->buildOrderData());

			$this->assertNotSame($first, $second, 'different sessions must produce different orders');
		} finally {
			session_id((string)$old);
		}
	}

	public function testGetCustomerByTokenIsSingleUse(): void
	{
		require_once __DIR__ . '/../../../upload/catalog/model/account/customer.php';
		$model = new \ModelAccountCustomer(self::$registry);

		self::$db->query("INSERT INTO " . DB_PREFIX . "customer SET email = '" . self::TEST_EMAIL . "', customer_group_id = '1', store_id = '0', language_id = '1', firstname = 'Race', lastname = 'Safety', salt = '', password = 'x', status = '1', token = 'RACE-TOKEN-1', date_added = NOW()");
		$customer_id = (int)self::$db->getLastId();

		$first = $model->getCustomerByToken('RACE-TOKEN-1');
		$this->assertNotEmpty($first);
		$this->assertEquals($customer_id, (int)$first['customer_id']);

		$second = $model->getCustomerByToken('RACE-TOKEN-1');
		$this->assertEmpty($second, 'a consumed token must not be usable twice');

		$row = self::$db->query("SELECT token FROM " . DB_PREFIX . "customer WHERE customer_id = '" . $customer_id . "'");
		$this->assertEquals('', $row->row['token']);

		self::$db->query("DELETE FROM " . DB_PREFIX . "customer WHERE customer_id = '" . $customer_id . "'");
	}

	public function testGetCustomerByTokenDoesNotWipeOtherTokens(): void
	{
		require_once __DIR__ . '/../../../upload/catalog/model/account/customer.php';
		$model = new \ModelAccountCustomer(self::$registry);

		self::$db->query("INSERT INTO " . DB_PREFIX . "customer SET email = '" . self::TEST_EMAIL . "a', customer_group_id = '1', store_id = '0', language_id = '1', firstname = 'Race', lastname = 'Safety', salt = '', password = 'x', status = '1', token = 'RACE-TOKEN-A', date_added = NOW()");
		$id_a = (int)self::$db->getLastId();
		self::$db->query("INSERT INTO " . DB_PREFIX . "customer SET email = '" . self::TEST_EMAIL . "b', customer_group_id = '1', store_id = '0', language_id = '1', firstname = 'Race', lastname = 'Safety', salt = '', password = 'x', status = '1', token = 'RACE-TOKEN-B', date_added = NOW()");
		$id_b = (int)self::$db->getLastId();

		// Consuming B's token must not touch A's token.
		$result = $model->getCustomerByToken('RACE-TOKEN-B');
		$this->assertEquals($id_b, (int)$result['customer_id']);

		$row_a = self::$db->query("SELECT token FROM " . DB_PREFIX . "customer WHERE customer_id = '" . $id_a . "'");
		$this->assertEquals('RACE-TOKEN-A', $row_a->row['token'], 'consuming one token must not clear other customers tokens');

		// Invalid tokens must not clear anything.
		$model->getCustomerByToken('RACE-TOKEN-NOPE');
		$row_b = self::$db->query("SELECT token FROM " . DB_PREFIX . "customer WHERE customer_id = '" . $id_b . "'");
		$this->assertEquals('', $row_b->row['token']);

		self::$db->query("DELETE FROM " . DB_PREFIX . "customer WHERE customer_id IN ('" . $id_a . "', '" . $id_b . "')");
	}

	public function testAddLoginAttemptMergesConcurrentIncrements(): void
	{
		require_once __DIR__ . '/../../../upload/catalog/model/account/customer.php';
		$model = new \ModelAccountCustomer(self::$registry);

		self::$db->query("DELETE FROM " . DB_PREFIX . "customer_login WHERE email = '" . self::TEST_EMAIL . "'");

		$model->addLoginAttempt(self::TEST_EMAIL);
		$model->addLoginAttempt(self::TEST_EMAIL);
		$model->addLoginAttempt(self::TEST_EMAIL);

		$rows = self::$db->query("SELECT total FROM " . DB_PREFIX . "customer_login WHERE email = '" . self::TEST_EMAIL . "'");
		$this->assertEquals(1, $rows->num_rows, 'concurrent login attempts must merge into a single row');
		$this->assertEquals(3, (int)$rows->row['total']);
	}

	public function testSaveAbandonedCartIsIdempotentPerSession(): void
	{
		$table = DB_PREFIX . 'dockercart_checkout_abandoned';
		$exists = self::$db->query("SHOW TABLES LIKE '" . $table . "'");

		if (!$exists->num_rows) {
			$this->markTestSkipped('oc_dockercart_checkout_abandoned table not present (module not installed)');
		}

		$index = self::$db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'ux_session_recovered'");
		if (!$index->num_rows) {
			$this->markTestSkipped('ux_session_recovered index not present (migration not applied)');
		}

		require_once __DIR__ . '/../../../upload/catalog/model/checkout/dockercart_checkout.php';
		$model = new \ModelCheckoutDockerCartCheckout(self::$registry);

		$old = session_id();
		session_id(self::TEST_SESSION_1);

		try {
			$data = [
				'email' => self::TEST_EMAIL,
				'telephone' => '1234567890',
				'cart' => ['product_id' => 1, 'quantity' => 2],
				'address' => ['city' => 'Testville'],
				'step' => 'confirm',
			];

			$first = $model->saveAbandonedCart($data);
			$second = $model->saveAbandonedCart($data);

			$this->assertGreaterThan(0, $first);
			$this->assertSame($first, $second, 'repeated saves for the same session must reuse the abandoned-cart row');

			$rows = self::$db->query("SELECT COUNT(*) AS total FROM `" . $table . "` WHERE session_id = '" . self::TEST_SESSION_1 . "' AND recovered = '0'");
			$this->assertEquals(1, (int)$rows->row['total']);
		} finally {
			session_id((string)$old);
		}
	}

	public function testSaveAbandonedCartDifferentSessionsCreateDifferentRows(): void
	{
		$table = DB_PREFIX . 'dockercart_checkout_abandoned';
		$exists = self::$db->query("SHOW TABLES LIKE '" . $table . "'");

		if (!$exists->num_rows) {
			$this->markTestSkipped('oc_dockercart_checkout_abandoned table not present (module not installed)');
		}

		$index = self::$db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = 'ux_session_recovered'");
		if (!$index->num_rows) {
			$this->markTestSkipped('ux_session_recovered index not present (migration not applied)');
		}

		require_once __DIR__ . '/../../../upload/catalog/model/checkout/dockercart_checkout.php';
		$model = new \ModelCheckoutDockerCartCheckout(self::$registry);

		$old = session_id();
		session_id(self::TEST_SESSION_1);

		try {
			$data = ['email' => self::TEST_EMAIL, 'step' => 'cart'];

			$first = $model->saveAbandonedCart($data);
			session_id(self::TEST_SESSION_2);
			$second = $model->saveAbandonedCart($data);

			$this->assertNotSame($first, $second, 'different sessions must produce different abandoned-cart rows');
		} finally {
			session_id((string)$old);
		}
	}
}
