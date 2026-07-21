<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

class ProductConfigurableTest extends TestCase
{
	private static $db = null;
	private static $pc = null;
	private static $testProductId = 99997;
	private static $variantIds = [];

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
        $dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
        require_once __DIR__ . '/../../../upload/system/library/config.php';
        require_once __DIR__ . '/../../../upload/system/engine/registry.php';
        require_once __DIR__ . '/../../../upload/system/library/product_configurable.php';
        $registry = new \Registry();
        $registry->set('db', $dbDriver);

        $config = new \Config();
        $config->set('config_language_id', 1);
        $registry->set('config', $config);

		$pc = new \ProductConfigurable($registry);

		self::$db = $dbDriver;
		self::$pc = $pc;

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . self::$testProductId . "', model = 'TEST-HASH', sku = '', quantity = '100', price = '0', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . self::$testProductId . "', language_id = '1', name = 'Hash Test Product'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . self::$testProductId . "', store_id = '0'");

		self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '99001', type = 'select', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '99001', language_id = '1', name = 'HashSize'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99011', option_id = '99001', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99011', language_id = '1', name = 'HashSmall', option_id = '99001'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99012', option_id = '99001', sort_order = '2'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99012', language_id = '1', name = 'HashLarge', option_id = '99001'");

		self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '99002', type = 'select', sort_order = '2'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '99002', language_id = '1', name = 'HashColor'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99021', option_id = '99002', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99021', language_id = '1', name = 'HashRed', option_id = '99002'");
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		foreach (self::$variantIds as $vid) {
			self::$db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE variant_id = '" . (int)$vid . "'");
			self::$db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE variant_id = '" . (int)$vid . "'");
		}

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id IN (SELECT variant_id FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . self::$testProductId . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_variant_value WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_configurable WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = '" . self::$testProductId . "'");

		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value_description WHERE option_id IN (99001, 99002)");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value WHERE option_id IN (99001, 99002)");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_description WHERE option_id IN (99001, 99002)");
		self::$db->query("DELETE FROM `" . DB_PREFIX . "option` WHERE option_id IN (99001, 99002)");
	}

	public function testBuildVariantHashIsDeterministic(): void
	{
		$hash1 = self::$pc->buildVariantHash([99001 => 99011, 99002 => 99021]);
		$hash2 = self::$pc->buildVariantHash([99002 => 99021, 99001 => 99011]);

		$this->assertSame($hash1, $hash2);
		$this->assertSame('99011-99021', $hash1);
	}

	public function testBuildVariantHashEmptyInput(): void
	{
		$this->assertSame('', self::$pc->buildVariantHash([]));
	}

	public function testBuildVariantHashFromValues(): void
	{
		$hash = self::$pc->buildVariantHashFromValues([
			['option_id' => 99002, 'option_value_id' => 99021],
			['option_id' => 99001, 'option_value_id' => 99011],
		]);

		$this->assertSame('99011-99021', $hash);
	}

	/** @depends testBuildVariantHashIsDeterministic */
	public function testSetConfigurableOptions(): void
	{
		self::$pc->setConfigurableOptions(self::$testProductId, [99001, 99002]);
		$this->assertTrue(self::$pc->isConfigurable(self::$testProductId));
	}

	/** @depends testSetConfigurableOptions */
	public function testAddVariantComputesHash(): void
	{
		$vid = self::$pc->addVariant(self::$testProductId, [
			'sku' => 'HASH-S-RED',
			'price' => 19.99,
			'quantity' => 10,
			'status' => 1,
			'values' => [
				['option_id' => 99001, 'option_value_id' => 99011],
				['option_id' => 99002, 'option_value_id' => 99021],
			],
		]);

		$this->assertGreaterThan(0, $vid);
		self::$variantIds[] = $vid;

		$variant = self::$pc->getVariant($vid);
		$this->assertSame('99011-99021', $variant['variant_hash']);
	}

	/** @depends testAddVariantComputesHash */
	public function testAddDuplicateVariantThrows(): void
	{
		$this->expectException(\RuntimeException::class);

		self::$pc->addVariant(self::$testProductId, [
			'sku' => 'HASH-DUP',
			'price' => 29.99,
			'quantity' => 5,
			'status' => 1,
			'values' => [
				['option_id' => 99001, 'option_value_id' => 99011],
				['option_id' => 99002, 'option_value_id' => 99021],
			],
		]);
	}

	/** @depends testAddVariantComputesHash */
	public function testResolveVariantViaHash(): void
	{
		$variant = self::$pc->resolveVariant(self::$testProductId, [
			99001 => 99011,
			99002 => 99021,
		]);

		$this->assertNotEmpty($variant);
		$this->assertSame('HASH-S-RED', $variant['sku']);
	}

	/** @depends testAddVariantComputesHash */
	public function testResolveVariantNotFound(): void
	{
		$variant = self::$pc->resolveVariant(self::$testProductId, [
			99001 => 99012,
			99002 => 99021,
		]);

		$this->assertEmpty($variant);
	}

	/** @depends testAddVariantComputesHash */
	public function testResolveVariantIgnoresInactiveVariant(): void
	{
		$vid = self::$pc->addVariant(self::$testProductId, [
			'sku' => 'HASH-INACTIVE',
			'price' => 10,
			'quantity' => 1,
			'status' => 0,
			'values' => [
				['option_id' => 99001, 'option_value_id' => 99012],
				['option_id' => 99002, 'option_value_id' => 99021],
			],
		]);
		self::$variantIds[] = $vid;

		$variant = self::$pc->resolveVariant(self::$testProductId, [
			99001 => 99012,
			99002 => 99021,
		]);

		$this->assertEmpty($variant);
	}

	/** @depends testAddVariantComputesHash */
	public function testSetDefaultAndDefaultVariantViaConfigurable(): void
	{
		$variants = self::$pc->getVariants(self::$testProductId);

		$active = null;

		foreach ($variants as $v) {
			if ((int)$v['status'] === 1) {
				$active = (int)$v['variant_id'];
				break;
			}
		}

		$this->assertNotNull($active);

		self::$pc->setDefaultVariant($active);

		$config = self::$pc->getConfigurable(self::$testProductId);
		$this->assertEquals($active, (int)$config['default_variant_id']);

		$default = self::$pc->getDefaultVariant(self::$testProductId);
		$this->assertEquals($active, (int)$default['variant_id']);
	}

	/** @depends testSetDefaultAndDefaultVariantViaConfigurable */
	public function testDeleteDefaultVariantAutoSelectsNewDefault(): void
	{
		$config = self::$pc->getConfigurable(self::$testProductId);
		$this->assertNotEmpty($config['default_variant_id']);

		$defaultId = (int)$config['default_variant_id'];

		self::$pc->deleteVariant($defaultId);
		self::$variantIds = array_filter(self::$variantIds, fn($id) => $id !== $defaultId);

		$configAfter = self::$pc->getConfigurable(self::$testProductId);

		$hasActiveVariant = false;
		$variants = self::$pc->getVariants(self::$testProductId);

		foreach ($variants as $v) {
			if ((int)$v['status'] === 1) {
				$hasActiveVariant = true;
				break;
			}
		}

		if ($hasActiveVariant) {
			$this->assertNotEmpty($configAfter['default_variant_id']);
			$this->assertNotEquals($defaultId, (int)$configAfter['default_variant_id']);
		} else {
			$this->assertNull($configAfter['default_variant_id'] ?? null);
		}
	}

	/** @depends testAddVariantComputesHash */
	public function testRebuildVariantHashes(): void
	{
		self::$db->query("UPDATE " . DB_PREFIX . "product_variant SET variant_hash = '' WHERE product_id = '" . self::$testProductId . "'");

		self::$pc->rebuildVariantHashes(self::$testProductId);

		$query = self::$db->query("SELECT variant_hash FROM " . DB_PREFIX . "product_variant WHERE product_id = '" . self::$testProductId . "' AND variant_hash != ''");

		$this->assertGreaterThan(0, $query->num_rows);
	}

	/** @depends testSetConfigurableOptions */
	public function testGetOptionValuesFilteredByProduct(): void
	{
		$values = self::$pc->getOptionValues(99001, self::$testProductId);
		$this->assertNotEmpty($values);

		foreach ($values as $v) {
			$this->assertContains((int)$v['option_value_id'], [99011, 99012]);
		}
	}

	/** @depends testSetConfigurableOptions */
	public function testGetOptionValuesWithoutProductFilter(): void
	{
		$values = self::$pc->getOptionValues(99001);
		$this->assertNotEmpty($values);
	}

	/** @depends testAddVariantComputesHash */
	public function testGetAggregatedPriceRangeWithCustomerGroup(): void
	{
		$variants = self::$pc->getVariants(self::$testProductId);
		$this->assertNotEmpty($variants);

		$vid = 0;
		foreach ($variants as $v) {
			if ((int)$v['status'] === 1) {
				$vid = (int)$v['variant_id'];
				break;
			}
		}

		$this->assertGreaterThan(0, $vid);

		self::$pc->setVariantCustomerGroupPrice($vid, 1, 9.99);

		$range = self::$pc->getAggregatedPriceRange(self::$testProductId, 1);

		$this->assertLessThanOrEqual($range['max'], $range['min']);
		$this->assertGreaterThan(0, $range['min']);

		self::$pc->deleteVariantCustomerGroupPrice($vid, 1);
	}

	/** @depends testAddVariantComputesHash */
	public function testGetAggregatedPriceRangeWithoutCustomerGroup(): void
	{
		$range = self::$pc->getAggregatedPriceRange(self::$testProductId);

		$this->assertIsArray($range);
		$this->assertArrayHasKey('min', $range);
		$this->assertArrayHasKey('max', $range);
	}

	/** @depends testAddVariantComputesHash */
	public function testSetAndGetVariantSpecials(): void
	{
		$variants = self::$pc->getVariants(self::$testProductId);
		$this->assertNotEmpty($variants);

		$vid = 0;
		foreach ($variants as $v) {
			if ((int)$v['status'] === 1) {
				$vid = (int)$v['variant_id'];
				break;
			}
		}

		$this->assertGreaterThan(0, $vid);

		$specials = array(
			array(
				'customer_group_id' => 1,
				'priority'          => 1,
				'price'             => 9.99,
				'date_start'        => '2026-01-01',
				'date_end'          => '2026-12-31',
				'auto_renew'        => 0,
			),
			array(
				'customer_group_id' => 2,
				'priority'          => 1,
				'price'             => 14.99,
				'date_start'        => '0000-00-00',
				'date_end'          => '0000-00-00',
				'auto_renew'        => 0,
			),
		);

		self::$pc->setVariantSpecials($vid, $specials);

		$saved = self::$pc->getVariantSpecials($vid);
		$this->assertCount(2, $saved);

		$found = array();
		foreach ($saved as $s) {
			$found[(int)$s['customer_group_id']] = (float)$s['price'];
		}

		$this->assertArrayHasKey(1, $found);
		$this->assertEquals(9.99, $found[1]);
		$this->assertArrayHasKey(2, $found);
		$this->assertEquals(14.99, $found[2]);

		self::$pc->deleteAllVariantSpecials($vid);
	}

	/** @depends testSetAndGetVariantSpecials */
	public function testGetVariantSpecialPrice(): void
	{
		$variants = self::$pc->getVariants(self::$testProductId);
		$this->assertNotEmpty($variants);

		$vid = 0;
		foreach ($variants as $v) {
			if ((int)$v['status'] === 1) {
				$vid = (int)$v['variant_id'];
				break;
			}
		}

		$this->assertGreaterThan(0, $vid);

		$specials = array(
			array(
				'customer_group_id' => 1,
				'priority'          => 1,
				'price'             => 7.77,
				'date_start'        => '2026-01-01',
				'date_end'          => '2099-12-31',
				'auto_renew'        => 0,
			),
		);

		self::$pc->setVariantSpecials($vid, $specials);

		$price = self::$pc->getVariantSpecialPrice($vid, 1);

		$this->assertNotNull($price);
		$this->assertEquals(7.77, $price);

		$no_price = self::$pc->getVariantSpecialPrice($vid, 999);
		$this->assertNull($no_price);

		self::$pc->deleteAllVariantSpecials($vid);
	}

	/** @depends testAddVariantComputesHash */
	public function testGetVariantsSpecials(): void
	{
		$specials = self::$pc->getVariantsSpecials(self::$testProductId);
		$this->assertIsArray($specials);
	}

	/** @depends testAddVariantComputesHash */
	public function testDeleteVariantCascadesSpecials(): void
	{
		$variants = self::$pc->getVariants(self::$testProductId);
		$this->assertNotEmpty($variants);

		$test_vid = self::$pc->addVariant(self::$testProductId, [
			'sku' => 'CASCADE-SPECIALS',
			'price' => 50.00,
			'quantity' => 5,
			'status' => 1,
			'values' => [
				['option_id' => 99001, 'option_value_id' => 99012],
				['option_id' => 99002, 'option_value_id' => 99021],
			],
		]);

		$this->assertGreaterThan(0, $test_vid);

		self::$pc->setVariantSpecials($test_vid, [
			[
				'customer_group_id' => 1,
				'priority'          => 1,
				'price'             => 25.00,
				'date_start'        => '0000-00-00',
				'date_end'          => '0000-00-00',
				'auto_renew'        => 0,
			],
		]);

		$saved = self::$pc->getVariantSpecials($test_vid);
		$this->assertCount(1, $saved);

		self::$pc->deleteVariant($test_vid);

		$after_delete = self::$pc->getVariantSpecials($test_vid);
		$this->assertCount(0, $after_delete);

		self::$variantIds = array_filter(self::$variantIds, fn($id) => $id !== $test_vid);
	}
}
