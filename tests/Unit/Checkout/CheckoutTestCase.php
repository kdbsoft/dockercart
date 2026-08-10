<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

use PHPUnit\Framework\TestCase;

/**
 * Shared bootstrap for checkout/pricing DB integration tests.
 *
 * Builds a Registry with a real DB connection (DB_* env, skipped when
 * unavailable — same convention as the other DB tests) plus the libraries
 * and models involved in the storefront checkout flow:
 * cart pricing, BXGY, product configurable variants, total pipeline,
 * order creation and reward points.
 */
abstract class CheckoutTestCase extends TestCase
{
	protected static $db = null;
	protected static $registry = null;
	protected static $cart = null;

	/** Test session id — non-empty so the order_claim guard works. */
	public const TEST_SESSION = 'checkout-test-session';

	/** Fixed test ids (no collisions with existing data). */
	protected const CG_DEFAULT = 1;
	protected const CG_TEST = 99810;
	protected const CG_GROUP = 99811;

	protected const PRODUCT_PLAIN = 99701;
	protected const PRODUCT_DISC = 99702;
	protected const PRODUCT_VARIANT = 99703;
	protected const PRODUCT_TRIGGER = 99704;
	protected const PRODUCT_REWARD = 99705;
	protected const PRODUCT_GIFT = 99706;
	protected const PRODUCT_OPTION = 99707;

	protected const TAX_CLASS = 99820;
	protected const TAX_RATE = 99821;
	protected const GEO_ZONE = 99822;
	protected const ZONE_TO_GEO = 99823;

	protected const VARIANT_A = 99710;
	protected const VARIANT_B = 99711;

	protected const OPTION_ID = 99830;
	protected const OPTION_VALUE_ID = 99831;
	protected const PRODUCT_OPTION_ID = 99832;
	protected const PRODUCT_OPTION_VALUE_ID = 99833;

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

		self::bootstrapRegistry($host, $user, $pass, $name, $port);
		self::seedBase();
	}

	public static function tearDownAfterClass(): void
	{
		if (!self::$db) {
			return;
		}

		self::cleanup();
	}

	protected static function bootstrapRegistry(string $host, string $user, string $pass, string $name, int $port): void
	{
		// Upload dir: tests live in <repo>/tests; the app root is the repo
		// root, and upload/ sits inside it. On the host that is
		// <repo>/upload, inside the docker container the app root itself is
		// mounted at /var/www/html (the upload dir contents are the webroot).
		$upload = dirname(__DIR__, 3) . '/upload/';

		if (!is_dir($upload)) {
			$upload = '/var/www/html/';
		}

		$root = $upload;

		require_once $root . 'system/library/db/mysqli.php';

		if (!defined('DIR_APPLICATION')) {
			define('DIR_APPLICATION', $root . 'catalog/');
		}

		if (!defined('DIR_SYSTEM')) {
			define('DIR_SYSTEM', $root . 'system/');
		}

		if (!defined('DIR_MODIFICATION')) {
			define('DIR_MODIFICATION', sys_get_temp_dir() . '/dctest_mod/');
		}

		if (!function_exists('modification')) {
			// Loader/model code calls the global modification() function; this
			// file lives in a namespace, so load it from a global-scope file.
			require_once __DIR__ . '/global_functions.php';
		}

		if (!function_exists('utf8_strtolower')) {
			require_once $root . 'system/helper/utf8.php';
		}

		require_once $root . 'system/library/config.php';
		require_once $root . 'system/engine/registry.php';
		require_once $root . 'system/engine/loader.php';
		require_once $root . 'system/engine/proxy.php';
		require_once $root . 'system/engine/model.php';
		require_once $root . 'system/library/cart/tax.php';
		require_once $root . 'system/library/cart/currency.php';
		require_once $root . 'system/library/cart/cart.php';
		require_once $root . 'system/library/bxgy.php';
		require_once $root . 'system/library/product_bundle.php';
		require_once $root . 'system/library/product_configurable.php';
		require_once $root . 'system/library/dockercart_stock_reservation.php';
		require_once $root . 'system/library/dockercart_reward.php';
		require_once $root . 'catalog/model/setting/extension.php';
		require_once $root . 'catalog/model/catalog/product.php';
		require_once $root . 'catalog/model/checkout/order.php';
		require_once $root . 'catalog/model/checkout/dockercart_checkout.php';

		foreach (['sub_total', 'shipping', 'tax', 'coupon', 'reward', 'credit', 'voucher', 'handling', 'low_order_fee', 'product_bundle', 'bxgy', 'total'] as $total_code) {
			require_once $root . 'catalog/model/extension/total/' . $total_code . '.php';
		}

		$dbDriver = new \DB\MySQLi($host, $user, $pass, $name, $port);
		$registry = new \Registry();
		$registry->set('db', $dbDriver);

		$config = new \Config();
		$config->set('config_language_id', 1);
		$config->set('config_store_id', 0);
		$config->set('config_customer_group_id', self::CG_DEFAULT);
		$config->set('config_tax', 0);
		$config->set('config_currency', 'UAH');
		$config->set('config_currency_id', 1);
		$config->set('config_country_id', 0);
		$config->set('config_zone_id', 0);
		$config->set('config_tax_customer', 'payment');
		$config->set('config_tax_default', 'store');
		$config->set('config_processing_status', [2]);
		$config->set('config_complete_status', [5]);
		$config->set('config_stock_checkout', 0);
		$config->set('config_affiliate_auto', 0);
		$config->set('config_reward_auto_award', 0);
		$config->set('config_reward_auto_revoke', 0);
		$config->set('config_reward_delay_days', 0);

		// Total pipeline: enable all registered total extensions with a
		// sensible order (sub_total → shipping → tax → coupon/reward/voucher
		// → total), matching the default store configuration.
		$totalOrder = ['sub_total' => 1, 'shipping' => 3, 'tax' => 5, 'coupon' => 6, 'reward' => 7, 'credit' => 8, 'voucher' => 9, 'handling' => 10, 'low_order_fee' => 11, 'product_bundle' => 12, 'bxgy' => 13, 'total' => 99];

		foreach ($totalOrder as $code => $sort) {
			$config->set('total_' . $code . '_status', 1);
			$config->set('total_' . $code . '_sort_order', $sort);
		}

		$registry->set('config', $config);

		$registry->set('load', new \Loader($registry));
		$registry->set('tax', new \Cart\Tax($registry));

		$registry->set('session', new class() {
			public $data = ['currency' => 'UAH'];

			public function getId()
			{
				return \Tests\Unit\Checkout\CheckoutTestCase::TEST_SESSION;
			}
		});

		$registry->set('event', new class() {
			public function trigger(...$args)
			{
				return null;
			}
		});

		$registry->set('language', new class() {
			private $data = [];
			private $sub = [];

			public function load(string $file, string $key = ''): bool
			{
				if ($key !== '') {
					// OpenCart Language::load($file, $key) stores a sub-language
					// object under $this->data[$key]; total modules read
					// $this->language->get($key)->get('text_...').
					$sub = new class() {
						public function get(string $k): string
						{
							if ($k === 'text_coupon') {
								return 'Coupon (%s)';
							}

							if ($k === 'text_reward') {
								return 'Reward Points (%s)';
							}

							if ($k === 'text_voucher') {
								return 'Gift Certificate (%s)';
							}

							return $k;
						}

						public function load(string $f): bool
						{
							return false;
						}
					};

					$this->sub[$key] = $sub;
					$this->data[$key] = $sub;
				}

				return true;
			}

			public function get(string $key)
			{
				if ($key === 'text_gift') {
					return 'Gift';
				}

				if ($key === 'text_bxgy_free_badge') {
					return 'BXGY: Free';
				}

				if ($key === 'text_bxgy_percent_badge') {
					return 'BXGY: -%s%%';
				}

				if ($key === 'text_order_id') {
					return 'Order ID';
				}

				if ($key === 'text_reward_refunded') {
					return 'Reward refunded';
				}

				if ($key === 'decimal_point') {
					return '.';
				}

				if ($key === 'thousand_point') {
					return ',';
				}

				if (isset($this->data[$key])) {
					return $this->data[$key];
				}

				return '.';
			}
		});

		// Currency needs language in the registry at construction time.
		$registry->set('currency', new \Cart\Currency($registry));

		$registry->set('customer', new class() {
			private $rewardPoints = 0;

			public function isLogged(): bool
			{
				return false;
			}

			public function getId()
			{
				return 0;
			}

			public function getRewardPoints(): int
			{
				return $this->rewardPoints;
			}

			public function setRewardPoints(int $points): void
			{
				$this->rewardPoints = $points;
			}
		});

		$request = new \stdClass();
		$request->server = [
			'REMOTE_ADDR' => '127.0.0.1',
			'HTTP_USER_AGENT' => 'PHPUnit',
			'HTTP_ACCEPT_LANGUAGE' => 'en',
		];
		$request->cookie = [];
		$request->get = [];
		$request->post = [];
		$registry->set('request', $request);

		$registry->set('cache', new class() {
			public function get(string $key)
			{
				return false;
			}

			public function set(string $key, $value, int $expire = 0): void
			{
			}

			public function delete(string $key): void
			{
			}
		});

		self::$db = $dbDriver;
		self::$registry = $registry;
		self::$cart = new \Cart\Cart($registry);
	}

	/**
	 * Minimal static seed: test customer groups and tax fixtures.
	 * Products are seeded per test via makeProduct()/makeVariant().
	 */
	protected static function seedBase(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer_group SET customer_group_id = '" . self::CG_TEST . "', approval = '0', discount_percent = '0.00', markup_percent = '0.00', sort_order = '98'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer_group SET customer_group_id = '" . self::CG_GROUP . "', approval = '0', discount_percent = '10.00', markup_percent = '0.00', sort_order = '97'");

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "geo_zone SET geo_zone_id = '" . self::GEO_ZONE . "', name = 'Test Geo Zone', description = 'Checkout tests', date_modified = NOW(), date_added = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "zone_to_geo_zone SET zone_to_geo_zone_id = '" . self::ZONE_TO_GEO . "', country_id = '0', zone_id = '0', geo_zone_id = '" . self::GEO_ZONE . "', date_added = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "tax_rate SET tax_rate_id = '" . self::TAX_RATE . "', geo_zone_id = '" . self::GEO_ZONE . "', name = 'Test VAT', rate = '20.0000', type = 'P', language_id = '1', date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "tax_rate_to_customer_group SET tax_rate_id = '" . self::TAX_RATE . "', customer_group_id = '" . self::CG_DEFAULT . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "tax_rate_to_customer_group SET tax_rate_id = '" . self::TAX_RATE . "', customer_group_id = '" . self::CG_TEST . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "tax_class SET tax_class_id = '" . self::TAX_CLASS . "', title = 'Test Tax Class', description = 'Checkout tests', date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "tax_rule SET tax_rule_id = '" . (self::TAX_RATE + 1) . "', tax_class_id = '" . self::TAX_CLASS . "', tax_rate_id = '" . self::TAX_RATE . "', based = 'payment', priority = '1'");

		// Load tax rates the way the storefront startup does (payment/store
		// address fall back to store defaults; zone_to_geo_zone 0/0 matches all).
		// Must run AFTER the fixtures above are inserted.
		self::$registry->get('tax')->setPaymentAddress((int)self::$registry->get('config')->get('config_country_id'), (int)self::$registry->get('config')->get('config_zone_id'));
		self::$registry->get('tax')->setStoreAddress((int)self::$registry->get('config')->get('config_country_id'), (int)self::$registry->get('config')->get('config_zone_id'));
	}

	protected static function cleanup(): void
	{
		$db = self::$db;
		$prefix = DB_PREFIX;

		$orderIds = $db->query("SELECT order_id FROM `" . $prefix . "order` WHERE email IN ('checkout-test@example.com', 'reward-customer@example.com')")->rows;
		$ids = [];

		foreach ($orderIds as $row) {
			$ids[] = (int)$row['order_id'];
		}

		$in = $ids ? implode(',', $ids) : '0';

		$db->query("DELETE FROM " . $prefix . "order_claim WHERE session_id = '" . self::TEST_SESSION . "'");
		$db->query("DELETE FROM " . $prefix . "order_option WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "order_product WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "order_total WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "order_history WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "order_voucher WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "order WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "customer_reward WHERE order_id IN (" . $in . ")");

		$db->query("DELETE FROM " . $prefix . "product_configurable WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");

		$db->query("DELETE FROM " . $prefix . "cart WHERE session_id = '" . self::TEST_SESSION . "'");
		$db->query("DELETE FROM " . $prefix . "stock_reservation WHERE session_id = '" . self::TEST_SESSION . "'");

		$db->query("DELETE FROM " . $prefix . "product_to_store WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_description WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_gift WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_bxgy WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_bxgy WHERE reward_product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_discount WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_special WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product_reward WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "dockercart_product_customer_group_price WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");
		$db->query("DELETE FROM " . $prefix . "product WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_DISC . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_GIFT . "', '" . self::PRODUCT_OPTION . "')");

		$db->query("DELETE FROM " . $prefix . "dockercart_product_variant_discount WHERE variant_id IN ('" . self::VARIANT_A . "', '" . self::VARIANT_B . "')");
		$db->query("DELETE FROM " . $prefix . "dockercart_product_variant_special WHERE variant_id IN ('" . self::VARIANT_A . "', '" . self::VARIANT_B . "')");
		$db->query("DELETE FROM " . $prefix . "dockercart_product_variant_customer_group_price WHERE variant_id IN ('" . self::VARIANT_A . "', '" . self::VARIANT_B . "')");
		$db->query("DELETE FROM " . $prefix . "product_variant_value WHERE variant_id IN ('" . self::VARIANT_A . "', '" . self::VARIANT_B . "')");
		$db->query("DELETE FROM " . $prefix . "product_variant WHERE variant_id IN ('" . self::VARIANT_A . "', '" . self::VARIANT_B . "')");

		$db->query("DELETE FROM " . $prefix . "product_option_value WHERE product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "'");
		$db->query("DELETE FROM " . $prefix . "product_option WHERE product_option_id = '" . self::PRODUCT_OPTION_ID . "'");
		$db->query("DELETE FROM " . $prefix . "option_value_description WHERE option_value_id = '" . self::OPTION_VALUE_ID . "'");
		$db->query("DELETE FROM " . $prefix . "option_value WHERE option_value_id = '" . self::OPTION_VALUE_ID . "'");
		$db->query("DELETE FROM " . $prefix . "option_description WHERE option_id = '" . self::OPTION_ID . "'");
		$db->query("DELETE FROM `" . $prefix . "option` WHERE option_id = '" . self::OPTION_ID . "'");
		$db->query("DELETE FROM " . $prefix . "dockercart_product_option_value_customer_group_price WHERE product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "'");

		$db->query("DELETE FROM " . $prefix . "tax_rule WHERE tax_class_id = '" . self::TAX_CLASS . "'");
		$db->query("DELETE FROM " . $prefix . "tax_class WHERE tax_class_id = '" . self::TAX_CLASS . "'");
		$db->query("DELETE FROM " . $prefix . "tax_rate_to_customer_group WHERE tax_rate_id = '" . self::TAX_RATE . "'");
		$db->query("DELETE FROM " . $prefix . "tax_rate WHERE tax_rate_id = '" . self::TAX_RATE . "'");
		$db->query("DELETE FROM " . $prefix . "zone_to_geo_zone WHERE zone_to_geo_zone_id = '" . self::ZONE_TO_GEO . "'");
		$db->query("DELETE FROM " . $prefix . "geo_zone WHERE geo_zone_id = '" . self::GEO_ZONE . "'");

		$db->query("DELETE FROM " . $prefix . "customer_group WHERE customer_group_id IN ('" . self::CG_TEST . "', '" . self::CG_GROUP . "')");

		$db->query("DELETE FROM " . $prefix . "coupon_history WHERE order_id IN (" . $in . ")");
		$db->query("DELETE FROM " . $prefix . "coupon WHERE coupon_id IN (SELECT coupon_id FROM " . $prefix . "coupon_description WHERE name LIKE 'TEST-COUPON%')");
		$db->query("DELETE FROM " . $prefix . "coupon_description WHERE name LIKE 'TEST-COUPON%'");
		$db->query("DELETE FROM " . $prefix . "coupon_product WHERE coupon_id NOT IN (SELECT coupon_id FROM " . $prefix . "coupon)");
		$db->query("DELETE FROM " . $prefix . "coupon_category WHERE coupon_id NOT IN (SELECT coupon_id FROM " . $prefix . "coupon)");
		$db->query("DELETE FROM " . $prefix . "customer_reward WHERE customer_id = '0' AND description LIKE '%checkout-test%'");
	}

	/** Create a base product row (no catalog rules). */
	protected static function makeProduct(int $productId, float $price, string $model, int $quantity = 100, int $taxClassId = 0, int $subtract = 1): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product SET product_id = '" . $productId . "', model = '" . $model . "', sku = '', quantity = '" . (float)$quantity . "', price = '" . (float)$price . "', status = '1', tax_class_id = '" . $taxClassId . "', subtract = '" . $subtract . "', date_available = NOW(), date_added = NOW(), date_modified = NOW()");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_description SET product_id = '" . $productId . "', language_id = '1', name = 'Test " . $model . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_to_store SET product_id = '" . $productId . "', store_id = '0'");
	}

	/** Create a configurable variant (variant_value NOT set — cart uses variant_id directly). */
	protected static function makeVariant(int $variantId, int $productId, float $price, string $hash, int $quantity = 100, int $subtract = 1): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_variant SET variant_id = '" . $variantId . "', product_id = '" . $productId . "', sku = '', model = '', upc = '', ean = '', jan = '', isbn = '', mpn = '', price = '" . (float)$price . "', quantity = '" . (float)$quantity . "', subtract = '" . $subtract . "', weight = '0', weight_class_id = '1', image = '', variant_hash = '" . $hash . "', sort_order = '0', status = '1'");
	}

	/** Insert cart rows directly (bypasses Cart::add to keep test data deterministic). */
	protected static function seedCart(array $items, int $customerGroupId = self::CG_DEFAULT): void
	{
		self::$db->query("DELETE FROM " . DB_PREFIX . "cart WHERE session_id = '" . self::TEST_SESSION . "'");
		self::$registry->get('config')->set('config_customer_group_id', $customerGroupId);

		foreach ($items as $item) {
			$option = json_encode($item['option'] ?? []);
			// JSON contains double quotes; escape them for SQL single quotes
			// (real_escape_string would double-escape them again).
			$optionEsc = str_replace(['\\', '"'], ['\\\\', '\\"'], $option);

			self::$db->query("INSERT INTO " . DB_PREFIX . "cart SET customer_id = '0', session_id = '" . self::TEST_SESSION . "', product_id = '" . (int)$item['product_id'] . "', recurring_id = '0', quantity = '" . (float)$item['quantity'] . "', `option` = '" . $optionEsc . "', date_added = NOW()");
		}
	}

	/** Fresh cart facade (cart->getProducts() cache is per instance). */
	protected function cart(): \Cart\Cart
	{
		return new \Cart\Cart(self::$registry);
	}

	/**
	 * Storefront order-data assembly equivalent to the controller's
	 * prepareOrderData(): cart products with BXGY discounts + gift lines,
	 * then the total pipeline (sub_total/coupon/reward/voucher/tax/total).
	 */
	protected function buildOrderData(array $cartProducts, array $totalsData = []): array
	{
		$orderData = [
			'invoice_prefix' => 'INV-',
			'store_id' => 0,
			'store_name' => 'Test Store',
			'store_url' => 'http://localhost/',
			'customer_id' => 0,
			'customer_group_id' => (int)self::$registry->get('config')->get('config_customer_group_id'),
			'firstname' => 'Checkout',
			'lastname' => 'Test',
			'email' => 'checkout-test@example.com',
			'telephone' => '1234567890',
			'payment_firstname' => 'Checkout',
			'payment_lastname' => 'Test',
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
			'shipping_firstname' => 'Checkout',
			'shipping_lastname' => 'Test',
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
			'total' => 0,
			'order_status_id' => 1,
			'affiliate_id' => 0,
			'commission' => 0,
			'marketing_id' => 0,
			'tracking' => '',
			'language_id' => 1,
			'currency_id' => 1,
			'currency_code' => 'UAH',
			'currency_value' => 1.0,
			'ip' => '127.0.0.1',
			'forwarded_ip' => '',
			'user_agent' => 'PHPUnit',
			'accept_language' => 'en',
			'products' => [],
			'vouchers' => [],
			'totals' => [],
		];

		$bxgy = new \Bxgy(self::$registry);
		$discounts = $bxgy->getPerProductDiscountsFor($cartProducts);

		foreach ($cartProducts as $product) {
			$price = (float)$product['price'];
			$tax = isset($product['tax']) ? (float)$product['tax'] : 0.0;

			$bxgyKey = (int)$product['product_id'] . ':' . (int)($product['variant_id'] ?? 0);

			if (isset($discounts[$bxgyKey])) {
				$perUnit = (float)$discounts[$bxgyKey]['per_unit'];
				$units = (int)$discounts[$bxgyKey]['units'];
				$lineDiscount = $perUnit * min($units, (int)$product['quantity']);

				if ($price > 0 && $lineDiscount > 0) {
					$newPriceTotal = max(0, $price * (int)$product['quantity'] - $lineDiscount);
					$newPrice = (int)$product['quantity'] > 0 ? $newPriceTotal / (int)$product['quantity'] : 0.0;

					if ($price > 0) {
						$tax = $tax * ($newPrice / $price);
					}

					$price = $newPrice;
				}
			}

			$orderData['products'][] = [
				'product_id' => (int)$product['product_id'],
				'variant_id' => isset($product['variant_id']) ? (int)$product['variant_id'] : 0,
				'variant_sku' => isset($product['variant_sku']) ? $product['variant_sku'] : '',
				'name' => $product['name'],
				'model' => isset($product['model']) ? $product['model'] : '',
				'quantity' => (float)$product['quantity'],
				'price' => $price,
				'total' => $price * (float)$product['quantity'],
				'tax' => $tax,
				'reward' => isset($product['reward']) ? (int)$product['reward'] : 0,
				'option' => isset($product['option']) ? $product['option'] : [],
			];
		}

		// Gift lines (price 0, no tax/reward). Configurable gift products use
		// their default variant, mirroring the checkout controller.
		$productModel = new \ModelCatalogProduct(self::$registry);
		$giftsMap = $productModel->getProductGiftsByIds(array_column($cartProducts, 'product_id'));

		$pc = new \ProductConfigurable(self::$registry);

		foreach ($cartProducts as $cartProduct) {
			$gifts = isset($giftsMap[(int)$cartProduct['product_id']]) ? $giftsMap[(int)$cartProduct['product_id']] : [];

			foreach ($gifts as $gift) {
				if ((float)$cartProduct['quantity'] < (float)$gift['minimum_quantity']) {
					continue;
				}

				$giftVariant = $pc->getGiftVariantData((int)$gift['gift_product_id']);

				if ($pc->isConfigurable((int)$gift['gift_product_id']) && empty($giftVariant)) {
					continue;
				}

				$giftName = $gift['name'];

				if (!empty($giftVariant)) {
					$giftName = $giftVariant['label'] !== '' ? $giftName . ' (' . $giftVariant['label'] . ')' : $giftName;
				}

				$orderData['products'][] = [
					'product_id' => (int)$gift['gift_product_id'],
					'variant_id' => isset($giftVariant['variant_id']) ? (int)$giftVariant['variant_id'] : 0,
					'variant_sku' => isset($giftVariant['variant_sku']) ? $giftVariant['variant_sku'] : '',
					'name' => 'Gift: ' . $giftName,
					'model' => isset($giftVariant['model']) ? $giftVariant['model'] : '',
					'quantity' => 1,
					'price' => 0,
					'total' => 0,
					'tax' => 0,
					'reward' => 0,
					'option' => isset($giftVariant['option']) ? $giftVariant['option'] : [],
				];
			}
		}

		// Total pipeline.
		$cart = $this->cart();
		self::$registry->set('cart', $cart);

		$totals = [];
		$taxes = $cart->getTaxes();
		$total = 0.0;
		$totalData = [
			'totals' => &$totals,
			'taxes' => &$taxes,
			'total' => &$total,
		];

		$extensionModel = new \ModelSettingExtension(self::$registry);
		$results = $extensionModel->getExtensions('total');
		$sortOrder = [];

		foreach ($results as $key => $value) {
			$sortOrder[$key] = self::$registry->get('config')->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sortOrder, SORT_ASC, $results);

		foreach ($results as $result) {
			if (!self::$registry->get('config')->get('total_' . $result['code'] . '_status')) {
				continue;
			}

			$modelClass = 'ModelExtensionTotal' . preg_replace('/[^a-zA-Z0-9]/', '', $result['code']);
			$model = new $modelClass(self::$registry);
			$model->getTotal($totalData);
		}

		foreach ($totals as $t) {
			$orderData['totals'][] = [
				'code' => isset($t['code']) ? $t['code'] : '',
				'title' => isset($t['title']) ? $t['title'] : (isset($t['text']) ? $t['text'] : ''),
				'value' => isset($t['value']) ? (float)$t['value'] : 0,
				'sort_order' => isset($t['sort_order']) ? (int)$t['sort_order'] : 0,
			];
		}

		$orderData['total'] = $total;

		return $orderData;
	}

	/**
	 * Full checkout cycle: seed cart → build order data → addOrder →
	 * addOrderHistory to the given status. Returns [order_id, order_data].
	 */
	protected function runFlow(array $items, int $statusId = 1, int $customerGroupId = self::CG_DEFAULT): array
	{
		self::seedCart($items, $customerGroupId);
		$cart = $this->cart();
		$products = $cart->getProducts();

		self::assertNotEmpty($products, 'cart must have products');

		$orderData = $this->buildOrderData($products);
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderId = $orderModel->addOrder($orderData);

		self::assertGreaterThan(0, $orderId);

		$orderModel->addOrderHistory($orderId, $statusId, '', false, true);

		return [$orderId, $orderData];
	}

	/** Assert helper: totals row by code. */
	protected function totalByCode(array $rows, string $code): ?array
	{
		foreach ($rows as $row) {
			if ($row['code'] === $code) {
				return $row;
			}
		}

		return null;
	}

	/** Round helper for decimal comparisons. */
	protected function round(float $value, int $precision = 2): float
	{
		return round($value, $precision);
	}
}
