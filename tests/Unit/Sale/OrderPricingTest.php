<?php
declare(strict_types=1);

namespace Tests\Unit\Sale;

use PHPUnit\Framework\TestCase;

class OrderPricingTest extends TestCase
{
	private static $db = null;
	private static $registry = null;
	private static $model = null;
	private static $testProductId = 99701;
	private static $testProductId2 = 99702;
	private static $testOrderId = 99601;
	private static $testCustomerGroupId = 99801;
	private static $testOrderProductId = 0;

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

		if (!defined('DIR_APPLICATION')) {
			define('DIR_APPLICATION', __DIR__ . '/../../../upload/admin/');
		}

		if (!defined('DIR_SYSTEM')) {
			define('DIR_SYSTEM', __DIR__ . '/../../../upload/system/');
		}

		if (!defined('DIR_MODIFICATION')) {
			define('DIR_MODIFICATION', sys_get_temp_dir() . '/dctest_mod/');
		}

		if (!function_exists('modification')) {
			function modification($file)
			{
				return $file;
			}
		}

		$dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
		require_once __DIR__ . '/../../../upload/system/library/config.php';
		require_once __DIR__ . '/../../../upload/system/engine/registry.php';
		require_once __DIR__ . '/../../../upload/system/engine/loader.php';
		require_once __DIR__ . '/../../../upload/system/engine/proxy.php';
		require_once __DIR__ . '/../../../upload/system/engine/model.php';
		require_once __DIR__ . '/../../../upload/system/library/cart/tax.php';
		require_once __DIR__ . '/../../../upload/system/library/cart/currency.php';
		require_once __DIR__ . '/../../../upload/system/library/bxgy.php';
		require_once __DIR__ . '/../../../upload/system/library/product_configurable.php';
		require_once __DIR__ . '/../../../upload/admin/model/sale/order.php';
		require_once __DIR__ . '/../../../upload/admin/model/catalog/product.php';

		$registry = new \Registry();
		$registry->set('db', $dbDriver);
		$registry->set('load', new \Loader($registry));
		$config = new \Config();
		$config->set('config_language_id', 1);
		$config->set('config_tax', 0);
		$registry->set('config', $config);
		$registry->set('tax', new \Cart\Tax($registry));
		$registry->set('session', new class {
			public $data = ['currency' => 'UAH'];
		});
		$registry->set('event', new class {
			public function trigger(...$args): void {}
		});

		// Language stub: no language files in the unit environment. text_gift
		// must resolve to a readable label for gift line names.
		$registry->set('language', new class {
			public function load(string $file): bool
			{
				return false;
			}

			public function get(string $key): string
			{
				if ($key === 'text_gift') {
					return 'Gift';
				}

				return '.';
			}
		});
		$registry->set('currency', new \Cart\Currency($registry));

		self::$db = $dbDriver;
		self::$registry = $registry;
		self::$model = new \ModelSaleOrder($registry);

		self::seed();
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		if (self::$testOrderProductId) {
			self::$db->query("DELETE FROM " . DB_PREFIX . "order_product_override WHERE order_product_id = '" . (int)self::$testOrderProductId . "'");
			self::$db->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_product_id = '" . (int)self::$testOrderProductId . "'");
			self::$db->query("DELETE FROM " . DB_PREFIX . "order_product WHERE order_product_id = '" . (int)self::$testOrderProductId . "'");
		}

		self::$db->query("DELETE FROM " . DB_PREFIX . "order WHERE order_id = '" . (int)self::$testOrderId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_customer_group_price WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id IN (" . (int)self::$testProductId . ", " . (int)self::$testProductId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "customer_group WHERE customer_group_id = '" . (int)self::$testCustomerGroupId . "'");
	}

	private static function seed(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer_group SET customer_group_id = '" . (int)self::$testCustomerGroupId . "', approval = '0', discount_percent = '0.00', markup_percent = '0.00', sort_order = '99'");

		// Product 1: plain product with price 100, no catalog rules initially.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . (int)self::$testProductId . "', model = 'TEST-PRICE-1', sku = '', quantity = '100', price = '100.0000', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)self::$testProductId . "', language_id = '1', name = 'Test Pricing Product 1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)self::$testProductId . "', store_id = '0'");

		// Product 2: plain product with price 200 and catalog rules (special, discount, group price).
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . (int)self::$testProductId2 . "', model = 'TEST-PRICE-2', sku = '', quantity = '100', price = '200.0000', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)self::$testProductId2 . "', language_id = '1', name = 'Test Pricing Product 2'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)self::$testProductId2 . "', store_id = '0'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', priority = '1', price = '150.0000', date_start = '0000-00-00', date_end = '0000-00-00'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', quantity = '1', priority = '1', price = '190.0000', date_start = '0000-00-00', date_end = '0000-00-00'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', quantity = '5', priority = '1', price = '120.0000', date_start = '0000-00-00', date_end = '0000-00-00'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_customer_group_price SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', price = '180.0000'");

		// Order with the test customer group.
		self::$db->query("INSERT INTO " . DB_PREFIX . "order SET order_id = '" . (int)self::$testOrderId . "', store_id = '0', customer_group_id = '" . (int)self::$testCustomerGroupId . "', payment_country_id = '0', shipping_country_id = '0', currency_code = 'UAH', currency_value = '1.00000000', order_status_id = '1', date_added = NOW(), date_modified = NOW()");
	}

	private function pricing(int $productId, int $quantity): array
	{
		return self::$model->calculateProductPricing(self::$testOrderId, $productId, $quantity, []);
	}

	public function testBasePriceWhenNoCatalogRules(): void
	{
		$p = $this->pricing(self::$testProductId, 1);

		$this->assertIsArray($p);
		$this->assertEquals(100.0, (float)$p['price']);
	}

	public function testGroupPercentApplied(): void
	{
		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET discount_percent = '10.00' WHERE customer_group_id = '" . (int)self::$testCustomerGroupId . "'");

		$p = $this->pricing(self::$testProductId, 1);

		$this->assertEquals(90.0, round((float)$p['price'], 4));

		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET discount_percent = '0.00' WHERE customer_group_id = '" . (int)self::$testCustomerGroupId . "'");
	}

	public function testSpecialApplies(): void
	{
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)self::$testProductId2 . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_customer_group_price WHERE product_id = '" . (int)self::$testProductId2 . "'");

		$p = $this->pricing(self::$testProductId2, 1);

		$this->assertEquals(150.0, (float)$p['price']);
	}

	public function testQuantityDiscountBeatsSpecial(): void
	{
		// Re-add discount tiers (removed in testSpecialApplies) and group price.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', quantity = '1', priority = '1', price = '190.0000', date_start = '0000-00-00', date_end = '0000-00-00'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', quantity = '5', priority = '1', price = '120.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		$p = $this->pricing(self::$testProductId2, 5);

		$this->assertEquals(120.0, (float)$p['price']);
	}

	public function testCustomerGroupPriceSkipsGroupPercent(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_customer_group_price SET product_id = '" . (int)self::$testProductId2 . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', price = '180.0000'");
		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET discount_percent = '10.00' WHERE customer_group_id = '" . (int)self::$testCustomerGroupId . "'");

		$p = $this->pricing(self::$testProductId2, 1);

		// Special (150) is cheaper than the group price (180), so it wins.
		// The storefront applies the same rule: only the cheapest active price applies.
		$this->assertEquals(150.0, (float)$p['price']);

		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET discount_percent = '0.00' WHERE customer_group_id = '" . (int)self::$testCustomerGroupId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_customer_group_price WHERE product_id = '" . (int)self::$testProductId2 . "'");
	}

	public function testManualQuantitySetsOverride(): void
	{
		self::$db->query("INSERT INTO " . DB_PREFIX . "order_product SET order_id = '" . (int)self::$testOrderId . "', product_id = '" . (int)self::$testProductId . "', name = 'Test', model = 'TEST-PRICE-1', quantity = '1', price = '100.0000', total = '100.0000', tax = '0.0000', reward = '0'");
		self::$testOrderProductId = (int)self::$db->getLastId();

		$this->assertTrue(self::$model->updateOrderProductQuantity(self::$testOrderProductId, self::$testOrderId, 2));

		$overrides = self::$model->getOrderProductOverrides(self::$testOrderId);
		$this->assertArrayHasKey(self::$testOrderProductId, $overrides);

		$row = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_product_id = '" . (int)self::$testOrderProductId . "'")->row;
		$this->assertEquals(2, (float)$row['quantity']);
		$this->assertEquals(100.0, (float)$row['price']);
		$this->assertEquals(200.0, (float)$row['total']);
	}

	public function testRestoreClearsOverrideAndRecomputes(): void
	{
		$this->assertTrue((bool)self::$testOrderProductId);

		// Give product 1 a special so the restore actually changes the price.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)self::$testProductId . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', priority = '1', price = '70.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		// Give product 1 a quantity discount (qty 2) that is cheaper than the special.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)self::$testProductId . "', customer_group_id = '" . (int)self::$testCustomerGroupId . "', quantity = '2', priority = '1', price = '63.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		$restored = self::$model->restoreOrderProductPrice(self::$testOrderProductId, self::$testOrderId);

		$this->assertIsArray($restored);
		// The line has quantity 2, so the qty-2 discount (63) beats the special (70).
		$this->assertEquals(63.0, round((float)$restored['price'], 4));

		$overrides = self::$model->getOrderProductOverrides(self::$testOrderId);
		$this->assertArrayNotHasKey(self::$testOrderProductId, $overrides);

		$row = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_product_id = '" . (int)self::$testOrderProductId . "'")->row;
		$this->assertEquals(63.0, round((float)$row['price'], 4));
		$this->assertEquals(126.0, round((float)$row['total'], 4));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)self::$testProductId . "'");
	}

	public function testManualPriceSetsOverride(): void
	{
		$this->assertTrue((bool)self::$testOrderProductId);

		$this->assertTrue(self::$model->updateOrderProductPrice(self::$testOrderProductId, self::$testOrderId, 55.5));

		$overrides = self::$model->getOrderProductOverrides(self::$testOrderId);
		$this->assertArrayHasKey(self::$testOrderProductId, $overrides);

		$row = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_product_id = '" . (int)self::$testOrderProductId . "'")->row;
		$this->assertEquals(55.5, (float)$row['price']);
		$this->assertEquals(111.0, (float)$row['total']);
	}

	public function testBxgyDiscountAppliedToRewardProduct(): void
	{
		// Product 1 (price 100) is the trigger, product 2 (price 200) is the reward.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . (int)self::$testProductId . "', reward_product_id = '" . (int)self::$testProductId2 . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '25.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		// Insert a trigger line for product 1 into the order.
		self::$db->query("INSERT INTO " . DB_PREFIX . "order_product SET order_id = '" . (int)self::$testOrderId . "', product_id = '" . (int)self::$testProductId . "', name = 'Test', model = 'TEST-PRICE-1', quantity = '1', price = '100.0000', total = '100.0000', tax = '0.0000', reward = '0'");

		// Reward product 2 in the same order: the BXGY percentage discount
		// applies to the actual catalog price (150, after the special), so
		// 25% of 150 = 37.5 off -> 112.5.
		$p = $this->pricing(self::$testProductId2, 1);

		$this->assertIsArray($p);
		$this->assertEquals(112.5, round((float)$p['price'], 4));
		$this->assertTrue($p['bxgy_applied']);

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id = '" . (int)self::$testProductId . "'");
	}

	public function testAddProductAddsGiftLineWhenMinimumMet(): void
	{
		// Product 1 gives product 2 as a gift at quantity >= 2.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_gift SET product_id = '" . (int)self::$testProductId . "', gift_product_id = '" . (int)self::$testProductId2 . "', minimum_quantity = '2', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		$order_product_id = self::$model->addProductToOrder(self::$testOrderId, self::$testProductId, 2, []);

		$this->assertGreaterThan(0, $order_product_id);

		$gift = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)self::$testOrderId . "' AND product_id = '" . (int)self::$testProductId2 . "' AND price = '0' AND total = '0' ORDER BY order_product_id DESC LIMIT 1")->row;

		$this->assertNotEmpty($gift);
		$this->assertEquals(0, (float)$gift['price']);
		$this->assertEquals(1, (float)$gift['quantity']);
		$this->assertStringContainsString('gift', strtolower($gift['name']));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_gift WHERE product_id = '" . (int)self::$testProductId . "'");
	}

	public function testAddProductSkipsGiftBelowMinimum(): void
	{
		// Re-add gift rule, quantity 1 < minimum 2 -> no gift line.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_gift SET product_id = '" . (int)self::$testProductId . "', gift_product_id = '" . (int)self::$testProductId2 . "', minimum_quantity = '2', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		$before = (int)self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)self::$testOrderId . "' AND product_id = '" . (int)self::$testProductId2 . "' AND price = '0'")->row['c'];

		self::$model->addProductToOrder(self::$testOrderId, self::$testProductId, 1, []);

		$after = (int)self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)self::$testOrderId . "' AND product_id = '" . (int)self::$testProductId2 . "' AND price = '0'")->row['c'];

		$this->assertSame($before, $after);

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_gift WHERE product_id = '" . (int)self::$testProductId . "'");
	}
}
