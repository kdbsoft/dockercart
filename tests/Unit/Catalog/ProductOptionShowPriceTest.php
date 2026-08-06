<?php
declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;

class ProductOptionShowPriceTest extends TestCase
{
    private static $db = null;
    private static $registry = null;
    private static $model = null;
    private static $testProductId = 99996;
    private static $testOptionId = 99903;
    private static $testOptionValueId = 99931;

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
        require_once __DIR__ . '/../../../upload/catalog/model/catalog/product.php';

        $dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
        $registry = new \Registry();
        $registry->set('db', $dbDriver);

        $config = new \Config();
        $config->set('config_language_id', 1);
        $registry->set('config', $config);

        // getProductOptions() uses ProductConfigurable($this->registry); the
        // library only touches the db + config for isConfigurable().
        require_once __DIR__ . '/../../../upload/system/library/product_configurable.php';
        $registry->set('product_configurable', null);

        $loader = new class {
            public function model($route): void
            {
            }
        };
        $registry->set('load', $loader);

        self::$db = $dbDriver;
        self::$registry = $registry;
        self::$model = new \ModelCatalogProduct($registry);

        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . self::$testProductId . "', model = 'TEST-OPTION-PRICE', sku = '', quantity = '100', price = '0', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . self::$testProductId . "', language_id = '1', name = 'Test Option Price Product'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . self::$testProductId . "', store_id = '0'");

        self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '" . self::$testOptionId . "', type = 'select', sort_order = '1', status = '1', show_option_price = '0'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '" . self::$testOptionId . "', language_id = '1', name = 'ShowPriceOff'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '" . self::$testOptionValueId . "', option_id = '" . self::$testOptionId . "', sort_order = '1'");
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '" . self::$testOptionValueId . "', language_id = '1', name = 'Val', option_id = '" . self::$testOptionId . "'");

        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option SET product_id = '" . self::$testProductId . "', option_id = '" . self::$testOptionId . "', value = '', required = '1'");
        $po_id = self::$db->getLastId();
        self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$po_id . "', product_id = '" . self::$testProductId . "', option_id = '" . self::$testOptionId . "', option_value_id = '" . self::$testOptionValueId . "', price = '5.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+', is_hit = '0', sort_order = '1'");
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$db) {
            return;
        }

        self::$db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . self::$testProductId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = '" . self::$testProductId . "'");

        self::$db->query("DELETE FROM " . DB_PREFIX . "option_value_description WHERE option_id = '" . self::$testOptionId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "option_value WHERE option_id = '" . self::$testOptionId . "'");
        self::$db->query("DELETE FROM " . DB_PREFIX . "option_description WHERE option_id = '" . self::$testOptionId . "'");
        self::$db->query("DELETE FROM `" . DB_PREFIX . "option` WHERE option_id = '" . self::$testOptionId . "'");
    }

    public function testShowOptionPriceFlagIsReturned(): void
    {
        $options = self::$model->getProductOptions(self::$testProductId);

        $this->assertCount(1, $options);
        $this->assertSame('0', $options[0]['show_option_price']);
        $this->assertCount(1, $options[0]['product_option_value']);
        $this->assertSame('5.0000', $options[0]['product_option_value'][0]['price']);
    }
}
