<?php
declare(strict_types=1);

namespace Tests\Unit\Product;

use PHPUnit\Framework\TestCase;

class ConfigurableVariantTest extends TestCase
{
    private static $db = null;
    private static $registry = null;
    private static $pc = null;
    private static $testProductId = 99998;
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

        try {
            $con = new \mysqli($host, $user, $pass, $name, $port);

            if ($con->connect_errno) {
                self::markTestSkipped('Database connection not available: ' . $con->connect_error);

                return;
            }
        } catch (\mysqli_sql_exception $e) {
            self::markTestSkipped('Database connection not available: ' . $e->getMessage());

            return;
        }

        require_once __DIR__ . '/../../../upload/system/library/db/mysqli.php';
        $dbDriver = new \DB\MySQLi($con);
        require_once __DIR__ . '/../../../upload/system/engine/registry.php';
        $registry = new \Registry();
        $registry->set('db', $dbDriver);

        $config = new \stdClass();
        $config->config_language_id = 1;
        $registry->set('config', $config);

        $pc = new \ProductConfigurable($registry);

        self::$db = $dbDriver;
        self::$registry = $registry;
        self::$pc = $pc;

        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . self::$testProductId . "', model = 'TEST-VARIANT', sku = '', quantity = '100', price = '0', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . self::$testProductId . "', language_id = '1', name = 'Test Configurable Product'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . self::$testProductId . "', store_id = '0'");

        self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '99901', type = 'select', sort_order = '1'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '99901', language_id = '1', name = 'Size'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99911', option_id = '99901', sort_order = '1'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99911', language_id = '1', name = 'Small', option_id = '99901'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99912', option_id = '99901', sort_order = '2'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99912', language_id = '1', name = 'Large', option_id = '99901'");

        self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '99902', type = 'select', sort_order = '2'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '99902', language_id = '1', name = 'Color'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99921', option_id = '99902', sort_order = '1'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99921', language_id = '1', name = 'Red', option_id = '99902'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99922', option_id = '99902', sort_order = '2'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99922', language_id = '1', name = 'Blue', option_id = '99902'");
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

        self::$db->query("DELETE FROM " . DB_PREFIX . "option_value_description WHERE option_id IN (99901, 99902)");
        self::$db->query("DELETE FROM " . DB_PREFIX . "option_value WHERE option_id IN (99901, 99902)");
        self::$db->query("DELETE FROM " . DB_PREFIX . "option_description WHERE option_id IN (99901, 99902)");
        self::$db->query("DELETE FROM `" . DB_PREFIX . "option` WHERE option_id IN (99901, 99902)");
    }

    public function testSetConfigurableOptions(): void
    {
        self::$pc->setConfigurableOptions(self::$testProductId, [99901, 99902]);
        $this->assertTrue(self::$pc->isConfigurable(self::$testProductId));

        $axes = self::$pc->getConfigurableOptions(self::$testProductId);
        $this->assertCount(2, $axes);
        $this->assertEquals('Size', $axes[0]['name']);
        $this->assertEquals('Color', $axes[1]['name']);
    }

    /** @depends testSetConfigurableOptions */
    public function testAddVariant(): void
    {
        $vid = self::$pc->addVariant(self::$testProductId, [
            'sku' => 'TEST-S-RED',
            'price' => 19.99,
            'quantity' => 10,
            'subtract' => 1,
            'status' => 1,
            'values' => [
                ['option_id' => 99901, 'option_value_id' => 99911],
                ['option_id' => 99902, 'option_value_id' => 99921],
            ],
        ]);

        $this->assertGreaterThan(0, $vid);
        self::$variantIds[] = $vid;

        $variant = self::$pc->getVariant($vid);
        $this->assertEquals('TEST-S-RED', $variant['sku']);
        $this->assertEquals(19.99, (float)$variant['price']);
        $this->assertCount(2, $variant['values']);
    }

    /** @depends testAddVariant */
    public function testAddMoreVariants(): void
    {
        self::$variantIds[] = self::$pc->addVariant(self::$testProductId, [
            'sku' => 'TEST-L-RED',
            'price' => 24.99,
            'quantity' => 5,
            'values' => [
                ['option_id' => 99901, 'option_value_id' => 99912],
                ['option_id' => 99902, 'option_value_id' => 99921],
            ],
        ]);

        self::$variantIds[] = self::$pc->addVariant(self::$testProductId, [
            'sku' => 'TEST-S-BLUE',
            'price' => 19.99,
            'quantity' => 0,
            'values' => [
                ['option_id' => 99901, 'option_value_id' => 99911],
                ['option_id' => 99902, 'option_value_id' => 99922],
            ],
        ]);

        $variants = self::$pc->getVariants(self::$testProductId);
        $this->assertCount(3, $variants);
    }

    /** @depends testAddMoreVariants */
    public function testResolveVariant(): void
    {
        $variant = self::$pc->resolveVariant(self::$testProductId, [
            99901 => 99911,
            99902 => 99921,
        ]);

        $this->assertNotEmpty($variant);
        $this->assertEquals('TEST-S-RED', $variant['sku']);
    }

    /** @depends testAddMoreVariants */
    public function testResolveVariantNotFound(): void
    {
        $variant = self::$pc->resolveVariant(self::$testProductId, [
            99901 => 99911,
            99902 => 99999,
        ]);

        $this->assertEmpty($variant);
    }

    /** @depends testAddMoreVariants */
    public function testAggregatedPriceRange(): void
    {
        $range = self::$pc->getAggregatedPriceRange(self::$testProductId);
        $this->assertEquals(19.99, $range['min']);
        $this->assertEquals(24.99, $range['max']);
    }

    /** @depends testAddMoreVariants */
    public function testAggregatedStock(): void
    {
        $stock = self::$pc->getAggregatedStock(self::$testProductId);
        $this->assertEquals(15.0, $stock['total_stock']);
        $this->assertEquals(2, $stock['variants_in_stock']);
    }

    /** @depends testAddMoreVariants */
    public function testCustomerGroupPrice(): void
    {
        $variants = self::$pc->getVariants(self::$testProductId);
        $this->assertNotEmpty($variants);
        $vid = (int)$variants[0]['variant_id'];

        self::$pc->setVariantCustomerGroupPrice($vid, 2, 14.99);
        $this->assertEquals(14.99, self::$pc->getVariantCustomerGroupPrice($vid, 2));
        $this->assertNull(self::$pc->getVariantCustomerGroupPrice($vid, 1));

        self::$pc->setVariantCustomerGroupPrice($vid, 2, 12.99);
        $this->assertEquals(12.99, self::$pc->getVariantCustomerGroupPrice($vid, 2));

        self::$pc->deleteVariantCustomerGroupPrice($vid, 2);
        $this->assertNull(self::$pc->getVariantCustomerGroupPrice($vid, 2));
    }

    /** @depends testAddMoreVariants */
    public function testSetDefaultVariant(): void
    {
        $variants = self::$pc->getVariants(self::$testProductId);
        $this->assertCount(3, $variants);

        $firstId = (int)$variants[0]['variant_id'];
        $lastId = (int)$variants[2]['variant_id'];

        self::$pc->setDefaultVariant($firstId);

        $config = self::$pc->getConfigurable(self::$testProductId);
        $this->assertEquals($firstId, (int)$config['default_variant_id']);

        self::$pc->setDefaultVariant($lastId);

        $first = self::$pc->getVariant($firstId);
        $this->assertEquals(0, (int)$first['is_default']);
        $this->assertEquals(1, (int)self::$pc->getVariant($lastId)['is_default']);
    }

    /** @depends testAddMoreVariants */
    public function testDeleteVariant(): void
    {
        $variants = self::$pc->getVariants(self::$testProductId);
        $this->assertCount(3, $variants);

        $vid = (int)$variants[0]['variant_id'];
        self::$pc->deleteVariant($vid);

        self::$variantIds = array_filter(self::$variantIds, fn($id) => $id !== $vid);
        $this->assertEmpty(self::$pc->getVariant($vid));

        $this->assertCount(2, self::$pc->getVariants(self::$testProductId));
    }

    /** @depends testDeleteVariant */
    public function testUpdateVariant(): void
    {
        $variants = self::$pc->getVariants(self::$testProductId);
        $this->assertCount(2, $variants);

        $vid = (int)$variants[0]['variant_id'];
        self::$pc->updateVariant($vid, [
            'sku' => 'UPDATED-SKU',
            'price' => 29.99,
            'quantity' => 99,
            'status' => 1,
            'values' => [
                ['option_id' => 99901, 'option_value_id' => 99911],
                ['option_id' => 99902, 'option_value_id' => 99921],
            ],
        ]);

        $updated = self::$pc->getVariant($vid);
        $this->assertEquals('UPDATED-SKU', $updated['sku']);
        $this->assertEquals(29.99, (float)$updated['price']);
        $this->assertEquals(99.0, (float)$updated['quantity']);
    }

    /** @depends testUpdateVariant */
    public function testSetConfigurableOptionsZeroesExistingNonZeroValues(): void
    {
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . self::$testProductId . "'");

        self::$db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . self::$testProductId . "', option_id = '99901', value = '', required = '1'");
        $po_id = self::$db->getLastId();
        self::$db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$po_id . "', product_id = '" . self::$testProductId . "', option_id = '99901', option_value_id = '99911', quantity = '50', subtract = '1', price = '10.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+'");

        $before = self::$db->query("SELECT price, quantity, subtract FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "' AND option_id = '99901' AND option_value_id = '99911'");
        $this->assertEquals('10.0000', $before->row['price']);
        $this->assertEquals('50', $before->row['quantity']);
        $this->assertEquals('1', $before->row['subtract']);

        self::$pc->setConfigurableOptions(self::$testProductId, [99901]);

        $after = self::$db->query("SELECT price, quantity, subtract FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "' AND option_id = '99901' AND option_value_id = '99911'");
        $this->assertEquals('0', $after->row['price']);
        $this->assertEquals('0', $after->row['quantity']);
        $this->assertEquals('0', $after->row['subtract']);
    }

    /** @depends testSetConfigurableOptionsZeroesExistingNonZeroValues */
    public function testNewAxisProductOptionValuesAreZeroed(): void
    {
        self::$pc->setConfigurableOptions(self::$testProductId, [99902]);

        $pov = self::$db->query("SELECT price, quantity FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "' AND option_id = '99902'");
        $this->assertGreaterThan(0, $pov->num_rows);

        foreach ($pov->rows as $row) {
            $this->assertEquals('0', $row['price']);
            $this->assertEquals('0', $row['quantity']);
        }
    }

    /** @depends testNewAxisProductOptionValuesAreZeroed */
    public function testDisableConfigurableZeroesAxisValues(): void
    {
        $vid = self::$pc->addVariant(self::$testProductId, [
            'sku' => 'DISABLE-TEST',
            'price' => 15.00,
            'quantity' => 3,
            'values' => [
                ['option_id' => 99902, 'option_value_id' => 99921],
            ],
        ]);
        self::$variantIds[] = $vid;

        $this->assertCount(1, self::$pc->getVariants(self::$testProductId));

        self::$pc->disableConfigurable(self::$testProductId);

        $pov = self::$db->query("SELECT price, quantity FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "' AND option_id = '99902'");
        foreach ($pov->rows as $row) {
            $this->assertEquals('0', $row['price']);
            $this->assertEquals('0', $row['quantity']);
        }

        self::$pc->deleteAllVariants(self::$testProductId);
        $this->assertEmpty(self::$pc->getVariants(self::$testProductId));
        $this->assertFalse(self::$pc->isConfigurable(self::$testProductId));
    }

    /** @depends testDisableConfigurableZeroesAxisValues */
    public function testSetConfigurablePreservesZeroValues(): void
    {
        self::$pc->setConfigurable(self::$testProductId, 1);
        $this->assertTrue(self::$pc->isConfigurable(self::$testProductId));

        self::$pc->setConfigurable(self::$testProductId, 0);
        $this->assertFalse(self::$pc->isConfigurable(self::$testProductId));
    }

    public function testNotConfigurableByDefault(): void
    {
        $this->assertFalse(self::$pc->isConfigurable(1));
    }
}
