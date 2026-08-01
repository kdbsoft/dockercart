<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;

class OrderLocalizerTest extends TestCase
{
	private static $db = null;
	private static $config = null;
	private static $localizer = null;

	private static $testProductId = 99998;
	private static $testOptionId = 99101;
	private static $testProductOptionId = 990011;
	private static $testOptionValueIds = [99111, 99112];
	private static $testProductOptionValueIds = [990111, 990112];
	private static $testPaymentMethodId = 99201;
	private static $testShippingMethodId = 99202;
	private static $testCountryId = 99101;
	private static $testZoneId = 99102;

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
		require_once __DIR__ . '/../../../upload/system/library/order_localizer.php';

		$registry = new \Registry();
		$registry->set('db', $dbDriver);

		$config = new \Config();
		$config->set('config_language_id', 1);
		$registry->set('config', $config);

		// Language stub: no language files in the unit environment, so
		// language-file based lookups deterministically fall back to stored.
		$registry->set('language', new class {
			public function load(string $file): bool
			{
				return false;
			}

			public function get(string $key): string
			{
				return $key;
			}
		});

		self::$db = $dbDriver;
		self::$config = $config;
		self::$localizer = new \OrderLocalizer($registry);

		self::seed();
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_universal_shipping_description WHERE method_id = '" . self::$testShippingMethodId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_universal_payment_description WHERE method_id = '" . self::$testPaymentMethodId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "zone_description WHERE zone_id = '" . self::$testZoneId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "zone WHERE zone_id = '" . self::$testZoneId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "country_description WHERE country_id = '" . self::$testCountryId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "country WHERE country_id = '" . self::$testCountryId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_option_value_id IN (" . implode(',', self::$testProductOptionValueIds) . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '" . self::$testProductOptionId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value_description WHERE option_value_id IN (" . implode(',', self::$testOptionValueIds) . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value WHERE option_value_id IN (" . implode(',', self::$testOptionValueIds) . ")");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_description WHERE option_id = '" . self::$testOptionId . "'");
		self::$db->query("DELETE FROM `" . DB_PREFIX . "option` WHERE option_id = '" . self::$testOptionId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . self::$testProductId . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = '" . self::$testProductId . "'");
	}

	private static function seed(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . self::$testProductId . "', model = 'TEST-L10N', sku = '', quantity = '100', price = '0', status = '1', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . self::$testProductId . "', language_id = '1', name = 'L10N Test Product'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . self::$testProductId . "', language_id = '3', name = 'Тестовый товар L10N'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . self::$testProductId . "', store_id = '0'");

		self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '" . self::$testOptionId . "', type = 'select', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '" . self::$testOptionId . "', language_id = '1', name = 'Size'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '" . self::$testOptionId . "', language_id = '3', name = 'Розмір'");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99111', option_id = '" . self::$testOptionId . "', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99111', language_id = '1', name = 'Small', option_id = '" . self::$testOptionId . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99111', language_id = '3', name = 'Малий', option_id = '" . self::$testOptionId . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '99112', option_id = '" . self::$testOptionId . "', sort_order = '2'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99112', language_id = '1', name = 'Large', option_id = '" . self::$testOptionId . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '99112', language_id = '3', name = 'Великий', option_id = '" . self::$testOptionId . "'");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option SET product_option_id = '" . self::$testProductOptionId . "', product_id = '" . self::$testProductId . "', option_id = '" . self::$testOptionId . "', value = '', required = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '990111', product_option_id = '" . self::$testProductOptionId . "', product_id = '" . self::$testProductId . "', option_id = '" . self::$testOptionId . "', option_value_id = '99111', quantity = '10', subtract = '0', price = '0.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0.0000', weight_prefix = '+'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '990112', product_option_id = '" . self::$testProductOptionId . "', product_id = '" . self::$testProductId . "', option_id = '" . self::$testOptionId . "', option_value_id = '99112', quantity = '10', subtract = '0', price = '0.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0.0000', weight_prefix = '+'");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_universal_payment_description SET method_id = '" . self::$testPaymentMethodId . "', language_id = '1', name = 'Test COD', description = 'Pay on delivery'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_universal_payment_description SET method_id = '" . self::$testPaymentMethodId . "', language_id = '3', name = 'Наложенный платеж тест', description = ''");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_universal_shipping_description SET method_id = '" . self::$testShippingMethodId . "', language_id = '1', name = 'Test Pickup', description = '', delivery_time = ''");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_universal_shipping_description SET method_id = '" . self::$testShippingMethodId . "', language_id = '3', name = 'Самовывоз тест', description = '', delivery_time = '2-3 дня'");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "country SET country_id = '" . self::$testCountryId . "', name = 'Testland', iso_code_2 = 'TT', iso_code_3 = 'TST', address_format = '', phone_format = '', postcode_required = '0', status = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "country_description SET country_id = '" . self::$testCountryId . "', language_id = '1', name = 'Testland', address_format = ''");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "country_description SET country_id = '" . self::$testCountryId . "', language_id = '3', name = 'Тестландия', address_format = ''");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "zone SET zone_id = '" . self::$testZoneId . "', country_id = '" . self::$testCountryId . "', name = 'Test Region', code = 'TTR', status = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "zone_description SET zone_id = '" . self::$testZoneId . "', language_id = '1', name = 'Test Region'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "zone_description SET zone_id = '" . self::$testZoneId . "', language_id = '3', name = 'Тестовый регион'");
	}

	private static function setLanguage(int $languageId): void
	{
		self::$config->set('config_language_id', $languageId);
	}

	public function testPaymentMethodTitleResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$title = self::$localizer->paymentMethodTitle([
			'payment_code' => 'dockercart_universal.dockercart_universal_' . self::$testPaymentMethodId,
			'payment_method' => 'Stored Title',
		]);

		$this->assertSame('Наложенный платеж тест', $title);
	}

	public function testPaymentMethodTitleFallsBackOnUnknownCode(): void
	{
		$title = self::$localizer->paymentMethodTitle([
			'payment_code' => 'unknown.code',
			'payment_method' => 'Stored Title',
		]);

		$this->assertSame('Stored Title', $title);
	}

	public function testPaymentMethodTitleFallsBackOnMissingDescription(): void
	{
		$title = self::$localizer->paymentMethodTitle([
			'payment_code' => 'dockercart_universal.dockercart_universal_999999',
			'payment_method' => 'Stored Title',
		]);

		$this->assertSame('Stored Title', $title);
	}

	public function testPaymentEntryTitleResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$title = self::$localizer->paymentEntryTitle([
			'payment_code' => 'dockercart_universal.dockercart_universal_' . self::$testPaymentMethodId,
			'payment_method' => 'Stored Title',
		]);

		$this->assertSame('Наложенный платеж тест', $title);
	}

	public function testShippingMethodTitleResolvesWithDeliveryTime(): void
	{
		self::setLanguage(3);
		$title = self::$localizer->shippingMethodTitle([
			'shipping_code' => 'dockercart_universal.dockercart_universal_' . self::$testShippingMethodId,
			'shipping_method' => 'Stored Title',
		]);

		$this->assertSame('Самовывоз тест (2-3 дня)', $title);
	}

	public function testShippingMethodTitleNoDeliveryTime(): void
	{
		self::setLanguage(1);
		$title = self::$localizer->shippingMethodTitle([
			'shipping_code' => 'dockercart_universal.dockercart_universal_' . self::$testShippingMethodId,
			'shipping_method' => 'Stored Title',
		]);

		$this->assertSame('Test Pickup', $title);
	}

	public function testShippingMethodTitleFallsBackOnUnknownCode(): void
	{
		$title = self::$localizer->shippingMethodTitle([
			'shipping_code' => 'dockercart_novapost.branch',
			'shipping_method' => 'Stored Shipping',
		]);

		$this->assertSame('Stored Shipping', $title);
	}

	public function testProductNameResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$name = self::$localizer->productName([
			'product_id' => self::$testProductId,
			'name' => 'Stored Name',
		]);

		$this->assertSame('Тестовый товар L10N', $name);
	}

	public function testProductNameFallsBackWhenProductMissing(): void
	{
		$name = self::$localizer->productName([
			'product_id' => 999999,
			'name' => 'Stored Name',
		]);

		$this->assertSame('Stored Name', $name);
	}

	public function testOptionNameResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$name = self::$localizer->optionName([
			'product_option_id' => self::$testProductOptionId,
			'name' => 'Stored Option',
		]);

		$this->assertSame('Розмір', $name);
	}

	public function testOptionValueResolvesForSelectType(): void
	{
		self::setLanguage(3);
		$value = self::$localizer->optionValue([
			'product_option_value_id' => 990111,
			'type' => 'select',
			'value' => 'Stored Value',
		]);

		$this->assertSame('Малий', $value);
	}

	public function testOptionValueKeepsFreeTextForTextType(): void
	{
		$value = self::$localizer->optionValue([
			'product_option_value_id' => 0,
			'type' => 'text',
			'value' => 'Free text input',
		]);

		$this->assertSame('Free text input', $value);
	}

	public function testTotalTitleShippingUsesResolvedMethod(): void
	{
		self::setLanguage(3);
		$title = self::$localizer->totalTitle([
			'code' => 'shipping',
			'title' => 'Stored Shipping Total',
		], 'Самовывоз тест (2-3 дня)');

		$this->assertSame('Самовывоз тест (2-3 дня)', $title);
	}

	public function testTotalTitleUnknownCodeKeepsStored(): void
	{
		$title = self::$localizer->totalTitle([
			'code' => 'custom_code',
			'title' => 'Custom Total',
		]);

		$this->assertSame('Custom Total', $title);
	}

	public function testTotalTitleKnownCodeFallsBackWithoutLanguageFile(): void
	{
		self::setLanguage(1);
		$title = self::$localizer->totalTitle([
			'code' => 'sub_total',
			'title' => 'Sub-Total',
		]);

		$this->assertSame('Sub-Total', $title);
	}

	public function testCountryNameResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$name = self::$localizer->countryName([
			'payment_country_id' => self::$testCountryId,
			'payment_country' => 'Stored Country',
		], 'payment');

		$this->assertSame('Тестландия', $name);
	}

	public function testCountryNameFallsBackWhenMissing(): void
	{
		self::setLanguage(1);
		$name = self::$localizer->countryName([
			'payment_country_id' => 0,
			'payment_country' => 'Stored Country',
		], 'payment');

		$this->assertSame('Stored Country', $name);
	}

	public function testZoneNameResolvesByLanguage(): void
	{
		self::setLanguage(3);
		$name = self::$localizer->zoneName([
			'shipping_zone_id' => self::$testZoneId,
			'shipping_zone' => 'Stored Zone',
		], 'shipping');

		$this->assertSame('Тестовый регион', $name);
	}

	public function testHistoryCommentResolvesPaymentMethodMarker(): void
	{
		self::setLanguage(1);
		$comment = self::$localizer->historyComment([
			'comment_key' => 'order_payment_method',
			'comment_params' => json_encode(['code' => 'dockercart_universal.dockercart_universal_' . self::$testPaymentMethodId]),
			'comment' => 'Наложенный платеж',
		]);

		$this->assertSame("Test COD\n\nPay on delivery", $comment);
	}

	public function testHistoryCommentResolvesWithoutDescription(): void
	{
		self::setLanguage(3);
		$comment = self::$localizer->historyComment([
			'comment_key' => 'order_payment_method',
			'comment_params' => json_encode(['code' => 'dockercart_universal.dockercart_universal_' . self::$testPaymentMethodId]),
			'comment' => 'Наложенный платеж',
		]);

		$this->assertSame('Наложенный платеж тест', $comment);
	}

	public function testHistoryCommentReturnsNullForPlainComment(): void
	{
		$comment = self::$localizer->historyComment([
			'comment_key' => '',
			'comment' => 'Some note',
		]);

		$this->assertNull($comment);
	}

	public function testHistoryCommentFallsBackWhenCodeGone(): void
	{
		self::setLanguage(1);
		$comment = self::$localizer->historyComment([
			'comment_key' => 'order_payment_method',
			'comment_params' => json_encode(['code' => 'dockercart_universal.dockercart_universal_999999']),
			'comment' => 'Наложенный платеж',
		]);

		$this->assertNull($comment);
	}
}
