<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * Cart pricing: how Cart::getProducts() resolves the final per-unit price
 * from every promo mechanism — specials, quantity discounts, customer-group
 * prices (per-product and global %), variant prices (base/special/discount/
 * group) and options (incl. option-value group price).
 */
class CartPricingTest extends CheckoutTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_PLAIN, 100.0, 'TEST-CART-PLAIN');
		self::makeProduct(self::PRODUCT_DISC, 200.0, 'TEST-CART-DISC');
		self::makeProduct(self::PRODUCT_VARIANT, 1000.0, 'TEST-CART-VARIANT');
		self::makeProduct(self::PRODUCT_TRIGGER, 50.0, 'TEST-CART-TRIGGER');
		self::makeProduct(self::PRODUCT_REWARD, 80.0, 'TEST-CART-REWARD');
		self::makeProduct(self::PRODUCT_OPTION, 60.0, 'TEST-CART-OPTION');

		self::makeVariant(self::VARIANT_A, self::PRODUCT_VARIANT, 500.0, 'test-variant-a');
		self::makeVariant(self::VARIANT_B, self::PRODUCT_VARIANT, 300.0, 'test-variant-b');
	}

	private function priceOf(array $products, int $productId): float
	{
		foreach ($products as $p) {
			if ((int)$p['product_id'] === $productId) {
				return (float)$p['price'];
			}
		}

		$this->fail('product ' . $productId . ' not found in cart products');
	}

	private function productLine(array $products, int $productId): array
	{
		foreach ($products as $p) {
			if ((int)$p['product_id'] === $productId) {
				return $p;
			}
		}

		$this->fail('product ' . $productId . ' not found in cart products');
	}

	public function testBasePrice(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		]);

		$products = $this->cart()->getProducts();

		$this->assertCount(1, $products);
		$this->assertEquals(100.0, $this->priceOf($products, self::PRODUCT_PLAIN));
		$this->assertEquals(100.0, $this->cart()->getSubTotal());
	}

	public function testSpecialPriceApplies(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_special SET product_id = '" . self::PRODUCT_PLAIN . "', customer_group_id = '" . self::CG_DEFAULT . "', priority = '1', price = '70.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		]);

		$this->assertEquals(70.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_PLAIN));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . self::PRODUCT_PLAIN . "'");
	}

	public function testQuantityDiscountTiers(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . self::PRODUCT_DISC . "', customer_group_id = '" . self::CG_DEFAULT . "', quantity = '2', priority = '1', price = '180.0000', date_start = '0000-00-00', date_end = '0000-00-00'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_discount SET product_id = '" . self::PRODUCT_DISC . "', customer_group_id = '" . self::CG_DEFAULT . "', quantity = '5', priority = '1', price = '150.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		self::seedCart([
			['product_id' => self::PRODUCT_DISC, 'quantity' => 1],
		]);

		// qty 1 → no tier applies (quantity=2 is the first tier).
		$this->assertEquals(200.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_DISC));

		self::seedCart([
			['product_id' => self::PRODUCT_DISC, 'quantity' => 2],
		]);

		$this->assertEquals(180.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_DISC));

		self::seedCart([
			['product_id' => self::PRODUCT_DISC, 'quantity' => 5],
		]);

		$this->assertEquals(150.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_DISC));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . self::PRODUCT_DISC . "'");
	}

	public function testPerProductGroupPriceOverridesGlobalPercent(): void
	{
		// Per-product group price for the test group.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_customer_group_price SET product_id = '" . self::PRODUCT_PLAIN . "', customer_group_id = '" . self::CG_TEST . "', price = '85.0000'");

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::CG_TEST);

		// The test group has discount_percent = 0, but even if it had a
		// percent, per-product group price wins (skips the global %).
		$this->assertEquals(85.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_PLAIN));

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_customer_group_price WHERE product_id = '" . self::PRODUCT_PLAIN . "' AND customer_group_id = '" . self::CG_TEST . "'");
	}

	public function testGlobalGroupPercentAppliesWhenNoPerProductPrice(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::CG_GROUP);

		// CG_GROUP has discount_percent = 10.
		$this->assertEquals(90.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_PLAIN));
	}

	public function testGlobalGroupMarkupApplies(): void
	{
		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET markup_percent = '25.00' WHERE customer_group_id = '" . self::CG_TEST . "'");

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::CG_TEST);

		$this->assertEquals(125.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_PLAIN));

		self::$db->query("UPDATE " . DB_PREFIX . "customer_group SET markup_percent = '0.00' WHERE customer_group_id = '" . self::CG_TEST . "'");
	}

	public function testVariantBasePrice(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],
		]);

		$line = $this->productLine($this->cart()->getProducts(), self::PRODUCT_VARIANT);

		$this->assertEquals(self::VARIANT_A, (int)$line['variant_id']);
		$this->assertEquals(500.0, (float)$line['price']);
	}

	public function testVariantSpecialPrice(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_variant_special SET variant_id = '" . self::VARIANT_A . "', customer_group_id = '" . self::CG_DEFAULT . "', priority = '1', price = '400.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		self::seedCart([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],
		]);

		$this->assertEquals(400.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_VARIANT));

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_special WHERE variant_id = '" . self::VARIANT_A . "'");
	}

	public function testVariantDiscountByQuantity(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_variant_discount SET variant_id = '" . self::VARIANT_A . "', customer_group_id = '" . self::CG_DEFAULT . "', quantity = '2', priority = '1', price = '350.0000', date_start = '0000-00-00', date_end = '0000-00-00'");

		self::seedCart([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 2, 'option' => ['variant_id' => self::VARIANT_A]],
		]);

		$this->assertEquals(350.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_VARIANT));

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_discount WHERE variant_id = '" . self::VARIANT_A . "'");
	}

	public function testVariantCustomerGroupPrice(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_variant_customer_group_price SET variant_id = '" . self::VARIANT_A . "', customer_group_id = '" . self::CG_TEST . "', price = '450.0000'");

		self::seedCart([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],
		], self::CG_TEST);

		$this->assertEquals(450.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_VARIANT));

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_variant_customer_group_price WHERE variant_id = '" . self::VARIANT_A . "'");
	}

	public function testOptionPriceAddsToBase(): void
	{
		self::seedOption();

		self::seedCart([
			['product_id' => self::PRODUCT_OPTION, 'quantity' => 1, 'option' => [self::PRODUCT_OPTION_ID => self::PRODUCT_OPTION_VALUE_ID]],
		]);

		$this->assertEquals(60.0 + 15.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_OPTION));

		self::cleanupOption();
	}

	public function testOptionValueGroupPriceOverrides(): void
	{
		self::seedOption();
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "dockercart_product_option_value_customer_group_price SET product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "', customer_group_id = '" . self::CG_TEST . "', price = '25.0000'");

		self::seedCart([
			['product_id' => self::PRODUCT_OPTION, 'quantity' => 1, 'option' => [self::PRODUCT_OPTION_ID => self::PRODUCT_OPTION_VALUE_ID]],
		], self::CG_TEST);

		// Option group price 25 replaces the default option price 15.
		$this->assertEquals(60.0 + 25.0, $this->priceOf($this->cart()->getProducts(), self::PRODUCT_OPTION));

		self::$db->query("DELETE FROM " . DB_PREFIX . "dockercart_product_option_value_customer_group_price WHERE product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "'");
		self::cleanupOption();
	}

	private static function seedOption(): void
	{
		self::$db->query("INSERT IGNORE INTO `" . DB_PREFIX . "option` SET option_id = '" . self::OPTION_ID . "', type = 'select', sort_order = '1', status = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_description SET option_id = '" . self::OPTION_ID . "', language_id = '1', name = 'Test Option'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value SET option_value_id = '" . self::OPTION_VALUE_ID . "', option_id = '" . self::OPTION_ID . "', sort_order = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "option_value_description SET option_value_id = '" . self::OPTION_VALUE_ID . "', language_id = '1', name = 'Test Value'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option SET product_option_id = '" . self::PRODUCT_OPTION_ID . "', product_id = '" . self::PRODUCT_OPTION . "', option_id = '" . self::OPTION_ID . "', `value` = '', required = '1'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "', product_option_id = '" . self::PRODUCT_OPTION_ID . "', product_id = '" . self::PRODUCT_OPTION . "', option_id = '" . self::OPTION_ID . "', option_value_id = '" . self::OPTION_VALUE_ID . "', quantity = '100', subtract = '1', price = '15.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0', weight_prefix = '+', sort_order = '1'");
	}

	private static function cleanupOption(): void
	{
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_option_value_id = '" . self::PRODUCT_OPTION_VALUE_ID . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '" . self::PRODUCT_OPTION_ID . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value_description WHERE option_value_id = '" . self::OPTION_VALUE_ID . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_value WHERE option_value_id = '" . self::OPTION_VALUE_ID . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "option_description WHERE option_id = '" . self::OPTION_ID . "'");
		self::$db->query("DELETE FROM `" . DB_PREFIX . "option` WHERE option_id = '" . self::OPTION_ID . "'");
	}
}
