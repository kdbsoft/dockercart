<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

class BxgyTest extends TestCase
{
	private static $db = null;
	private static $registry = null;
	private static $bxgy = null;
	private static $testTriggerId = 99601;
	private static $testRewardId = 99602;
	private static $testRewardId2 = 99603;

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

		// Global-scope helpers (modification() etc.) — must be loaded outside
		// this namespace or the framework Loader/Model cannot see them.
		require_once __DIR__ . '/../Checkout/global_functions.php';

		$dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
		require_once __DIR__ . '/../../../upload/system/library/config.php';
		require_once __DIR__ . '/../../../upload/system/engine/registry.php';
		require_once __DIR__ . '/../../../upload/system/library/cart/tax.php';
		require_once __DIR__ . '/../../../upload/system/library/cart/currency.php';
		require_once __DIR__ . '/../../../upload/system/library/bxgy.php';

		$registry = new \Registry();
		$registry->set('db', $dbDriver);
		$config = new \Config();
		$config->set('config_language_id', 1);
		$config->set('config_customer_group_id', 1);
		$config->set('config_tax', 0);
		$registry->set('config', $config);
		$registry->set('tax', new \Cart\Tax($registry));
		$registry->set('session', new class {
			public $data = ['currency' => 'UAH'];
		});
		$registry->set('language', new class {
			public function load(string $file): bool
			{
				return false;
			}

			public function get(string $key): string
			{
				if ($key === 'text_bxgy_free_badge') {
					return 'BXGY: Free';
				}

				if ($key === 'text_bxgy_percent_badge') {
					return 'BXGY: -%s%%';
				}

				return '.';
			}
		});
		$registry->set('currency', new \Cart\Currency($registry));

		self::$db = $dbDriver;
		self::$registry = $registry;
		self::$bxgy = new \Bxgy($registry);

		self::seed();
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id IN (" . (int)self::$testTriggerId . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id IN (" . (int)self::$testRewardId . ", " . (int)self::$testRewardId2 . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id IN (" . (int)self::$testTriggerId . ", " . (int)self::$testRewardId . ", " . (int)self::$testRewardId2 . ")");
	}

	private static function seed(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . (int)self::$testTriggerId . "', model = 'TEST-BXGY-TRIGGER', sku = '', quantity = '100', price = '10.0000', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . (int)self::$testRewardId . "', model = 'TEST-BXGY-REWARD', sku = '', quantity = '100', price = '50.0000', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . (int)self::$testRewardId2 . "', model = 'TEST-BXGY-REWARD2', sku = '', quantity = '100', price = '40.0000', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)self::$testRewardId . "', language_id = '1', name = 'BXGY Reward'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)self::$testRewardId2 . "', language_id = '1', name = 'BXGY Reward 2'");

		// Free rule: 2 triggers -> 1 free reward
		self::$db->query("INSERT INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . (int)self::$testTriggerId . "', reward_product_id = '" . (int)self::$testRewardId . "', trigger_quantity = '2', discount_type = 'free', discount_value = '0.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		// Percentage rule: 1 trigger -> 50% off reward 2
		self::$db->query("INSERT INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . (int)self::$testTriggerId . "', reward_product_id = '" . (int)self::$testRewardId2 . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '50.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		// Expired rule: reward 2 with 100% discount, past end date (must not apply)
		self::$db->query("INSERT INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . (int)self::$testTriggerId . "', reward_product_id = '" . (int)self::$testRewardId2 . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '100.00', date_start = '2000-01-01', date_end = '2000-01-02', date_added = NOW()");
	}

	private function products(array $items): array
	{
		$out = [];

		foreach ($items as $pid => $qty) {
			$row = self::$db->query("SELECT product_id, price, tax_class_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$pid . "'")->row;
			$out[] = [
				'product_id'   => (int)$pid,
				'quantity'     => (int)$qty,
				'price'        => (float)$row['price'],
				'tax_class_id' => (int)$row['tax_class_id'],
			];
		}

		return $out;
	}

	public function testFreeRuleAppliesWhenTriggerMet(): void
	{
		$discounts = self::$bxgy->getPerProductDiscountsFor($this->products([
			self::$testTriggerId => 2,
			self::$testRewardId  => 1,
		]));

		$this->assertArrayHasKey(self::$testRewardId, $discounts);
		$this->assertEquals(50.0, (float)$discounts[self::$testRewardId]['discount_amount']);
		$this->assertNotSame('', $discounts[self::$testRewardId]['text']);
		$this->assertStringContainsString('Free', $discounts[self::$testRewardId]['text']);
	}

	public function testFreeRuleNotAppliedWithoutTrigger(): void
	{
		$discounts = self::$bxgy->getPerProductDiscountsFor($this->products([
			self::$testTriggerId => 1,
			self::$testRewardId  => 1,
		]));

		$this->assertArrayNotHasKey(self::$testRewardId, $discounts);
	}

	public function testPercentageRuleApplies(): void
	{
		$discounts = self::$bxgy->getPerProductDiscountsFor($this->products([
			self::$testTriggerId => 1,
			self::$testRewardId2 => 1,
		]));

		$this->assertArrayHasKey(self::$testRewardId2, $discounts);
		// 50% of 40 = 20
		$this->assertEquals(20.0, (float)$discounts[self::$testRewardId2]['discount_amount']);
	}

	public function testTriggerSetsCappedByRewardQuantity(): void
	{
		// 5 triggers -> 2 full sets (2 triggers each); reward qty is 1 -> 1 set
		$discounts = self::$bxgy->getPerProductDiscountsFor($this->products([
			self::$testTriggerId => 5,
			self::$testRewardId  => 1,
		]));

		$this->assertArrayHasKey(self::$testRewardId, $discounts);
		// free = full reward price * 1 set
		$this->assertEquals(50.0, (float)$discounts[self::$testRewardId]['discount_amount']);
	}

	public function testExpiredRuleIgnored(): void
	{
		// Without an active percentage rule, reward 2 must have NO discount
		// (the expired 100% rule is ignored).
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id = '" . (int)self::$testTriggerId . "' AND reward_product_id = '" . (int)self::$testRewardId2 . "' AND discount_value = '50.00'");

		$discounts = self::$bxgy->getPerProductDiscountsFor($this->products([
			self::$testTriggerId => 1,
			self::$testRewardId2 => 1,
		]));

		$this->assertArrayNotHasKey(self::$testRewardId2, $discounts);

		// Restore for other tests
		self::$db->query("INSERT INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . (int)self::$testTriggerId . "', reward_product_id = '" . (int)self::$testRewardId2 . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '50.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");
	}
}
