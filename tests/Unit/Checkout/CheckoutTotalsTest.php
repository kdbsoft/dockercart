<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * Total pipeline: how the storefront checkout assembles order totals from
 * the registered total extensions — sub_total, coupon (F/P/shipping),
 * reward points, gift lines, tax — and their interaction with BXGY.
 */
class CheckoutTotalsTest extends CheckoutTestCase
{
	private const COUPON_F = 'TEST-COUPON-F';
	private const COUPON_P = 'TEST-COUPON-P';
	private const COUPON_SHIP = 'TEST-COUPON-SHIP';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_PLAIN, 100.0, 'TEST-TOTALS-PLAIN');
		self::makeProduct(self::PRODUCT_DISC, 200.0, 'TEST-TOTALS-DISC');
		self::makeProduct(self::PRODUCT_TRIGGER, 50.0, 'TEST-TOTALS-TRIGGER');
		self::makeProduct(self::PRODUCT_REWARD, 80.0, 'TEST-TOTALS-REWARD');
		self::makeProduct(self::PRODUCT_GIFT, 30.0, 'TEST-TOTALS-GIFT');
	}

	private function makeCoupon(string $code, string $type, float $discount, array $overrides = []): int
	{
		$fields = [
			'name' => $code,
			'code' => $code,
			'type' => $type,
			'discount' => (float)$discount,
			'logged' => 0,
			'shipping' => 0,
			'total' => 0,
			'date_start' => '0000-00-00',
			'date_end' => '0000-00-00',
			'uses_total' => 10,
			'uses_customer' => 10,
			'status' => 1,
			'auto_renew' => 0,
			'date_added' => 'NOW()',
		];

		foreach ($overrides as $k => $v) {
			$fields[$k] = $v;
		}

		$cols = [];
		$vals = [];

		foreach ($fields as $k => $v) {
			$cols[] = '`' . $k . '`';
			$vals[] = is_string($v) && $v !== 'NOW()' ? "'" . self::$db->escape($v) . "'" : (string)$v;
		}

		self::$db->query("INSERT INTO " . DB_PREFIX . "coupon SET " . implode(', ', array_map(static fn($c, $v) => $c . ' = ' . $v, $cols, $vals)));
		$id = (int)self::$db->getLastId();
		self::$db->query("INSERT INTO " . DB_PREFIX . "coupon_description SET coupon_id = '" . $id . "', language_id = '1', name = '" . $code . "'");

		return $id;
	}

	private function deleteCoupons(): void
	{
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_product WHERE coupon_id NOT IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon)");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_category WHERE coupon_id NOT IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon)");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_history WHERE coupon_id NOT IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon)");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_description WHERE name LIKE 'TEST-COUPON%'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon WHERE name LIKE 'TEST-COUPON%'");
	}

	private function totals(): array
	{
		$cart = $this->cart();
		$products = $cart->getProducts();
		$orderData = $this->buildOrderData($products);

		return $orderData['totals'];
	}

	public function testBaseSubTotalAndTotal(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		]);

		$totals = $this->totals();

		$sub = $this->totalByCode($totals, 'sub_total');
		$total = $this->totalByCode($totals, 'total');

		$this->assertNotNull($sub);
		$this->assertNotNull($total);
		$this->assertEquals(100.0, $this->round((float)$sub['value']));
		$this->assertEquals(100.0, $this->round((float)$total['value']));
	}

	public function testCouponFixedDistributesProportionally(): void
	{
		$this->makeCoupon(self::COUPON_F, 'F', 30.0);
		self::$registry->get('session')->data['coupon'] = self::COUPON_F;

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 2],   // 200
			['product_id' => self::PRODUCT_DISC, 'quantity' => 1],    // 200
		]);

		$totals = $this->totals();

		$coupon = $this->totalByCode($totals, 'coupon');
		$total = $this->totalByCode($totals, 'total');

		// sub_total = 400, fixed 30 → coupon -30, total 370.
		$this->assertNotNull($coupon);
		$this->assertEquals(-30.0, $this->round((float)$coupon['value']));
		$this->assertEquals(370.0, $this->round((float)$total['value']));

		unset(self::$registry->get('session')->data['coupon']);
	}

	public function testCouponPercentAppliesToSubTotal(): void
	{
		$this->makeCoupon(self::COUPON_P, 'P', 10.0);
		self::$registry->get('session')->data['coupon'] = self::COUPON_P;

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
			['product_id' => self::PRODUCT_DISC, 'quantity' => 1],    // 200
		]);

		$totals = $this->totals();

		$coupon = $this->totalByCode($totals, 'coupon');
		$total = $this->totalByCode($totals, 'total');

		$this->assertEquals(-30.0, $this->round((float)$coupon['value']));
		$this->assertEquals(270.0, $this->round((float)$total['value']));

		unset(self::$registry->get('session')->data['coupon']);
	}

	public function testCouponWithShippingOptionIncludesShippingCost(): void
	{
		$this->makeCoupon(self::COUPON_SHIP, 'P', 10.0, ['shipping' => 1]);
		self::$registry->get('session')->data['coupon'] = self::COUPON_SHIP;
		self::$registry->get('session')->data['shipping_method'] = [
			'code' => 'flat.flat',
			'title' => 'Flat',
			'cost' => 50.0,
			'tax_class_id' => 0,
			'text' => '50.00',
		];

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$totals = $this->totals();

		$coupon = $this->totalByCode($totals, 'coupon');
		$shipping = $this->totalByCode($totals, 'shipping');
		$total = $this->totalByCode($totals, 'total');

		// 10% of 100 = 10 + shipping cost 50 = -60.
		$this->assertNotNull($coupon);
		$this->assertEquals(-60.0, $this->round((float)$coupon['value']));
		$this->assertEquals(50.0, $this->round((float)$shipping['value']));
		// total = 100 + 50 - 60 = 90.
		$this->assertEquals(90.0, $this->round((float)$total['value']));

		unset(self::$registry->get('session')->data['coupon']);
		unset(self::$registry->get('session')->data['shipping_method']);
	}

	public function testGiftLineDoesNotAffectSubTotal(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_gift SET product_id = '" . self::PRODUCT_TRIGGER . "', gift_product_id = '" . self::PRODUCT_GIFT . "', minimum_quantity = '1', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		self::seedCart([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 1],   // 50
		]);

		$cart = $this->cart();
		$products = $cart->getProducts();
		$orderData = $this->buildOrderData($products);
		$totals = $orderData['totals'];

		// Gift line is in order products (price 0) but not in totals.
		$this->assertCount(2, $orderData['products']);
		$gift = $orderData['products'][1];
		$this->assertEquals(0, $this->round((float)$gift['price']));

		$sub = $this->totalByCode($totals, 'sub_total');
		$total = $this->totalByCode($totals, 'total');

		$this->assertEquals(50.0, $this->round((float)$sub['value']));
		$this->assertEquals(50.0, $this->round((float)$total['value']));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_gift WHERE product_id = '" . self::PRODUCT_TRIGGER . "'");
	}

	public function testRewardPointsSpend(): void
	{
		// 100 reward points = 100 currency units off (1 point = 1 unit here).
		self::$registry->get('session')->data['reward'] = 100;
		self::$registry->get('customer')->setRewardPoints(500);

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$totals = $this->totals();

		$reward = $this->totalByCode($totals, 'reward');
		$total = $this->totalByCode($totals, 'total');

		// Product has no reward points (points_total = 0) → reward line value 0.
		$this->assertNotNull($reward);
		$this->assertEquals(0.0, $this->round((float)$reward['value']));
		$this->assertEquals(100.0, $this->round((float)$total['value']));

		unset(self::$registry->get('session')->data['reward']);
	}

	public function testRewardPointsAppliedWhenProductHasPoints(): void
	{
		// Product points (used for reward spending) come from oc_product.points.
		self::$db->query("UPDATE " . DB_PREFIX . "product SET points = '500' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");

		self::$registry->get('session')->data['reward'] = 100;
		self::$registry->get('customer')->setRewardPoints(500);

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$totals = $this->totals();

		$reward = $this->totalByCode($totals, 'reward');
		$total = $this->totalByCode($totals, 'total');

		$this->assertNotNull($reward);
		// 100 points / 500 points_total × 100 = 20 discount.
		$this->assertEquals(-20.0, $this->round((float)$reward['value']));
		$this->assertEquals(80.0, $this->round((float)$total['value']));

		unset(self::$registry->get('session')->data['reward']);
		self::$db->query("UPDATE " . DB_PREFIX . "product SET points = '0' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");
	}

	public function testTaxIncludedWithBxgyDiscount(): void
	{
		// Sanity: tax fixtures must exist (seeded by CheckoutTestCase).
		$taxRate = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "tax_rate WHERE tax_rate_id = '" . self::TAX_RATE . "'")->row['c'];
		$this->assertGreaterThan(0, (int)$taxRate, 'tax_rate fixture missing');
		$rates = self::$registry->get('tax')->getRates(100, self::TAX_CLASS);
		$this->assertNotEmpty($rates, 'tax rates not loaded for TAX_CLASS');

		// Enable tax for this test: apply tax class to every product in cart.
		self::$registry->get('config')->set('config_tax', 1);
		self::$db->query("UPDATE " . DB_PREFIX . "product SET tax_class_id = '" . self::TAX_CLASS . "' WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "')");

		// BXGY: trigger gives 50% off reward.
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . self::PRODUCT_TRIGGER . "', reward_product_id = '" . self::PRODUCT_REWARD . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '50.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		self::seedCart([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 1],   // 50
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 1],    // 80 → 40 after BXGY
		]);

		$cart = $this->cart();
		$products = $cart->getProducts();
		$orderData = $this->buildOrderData($products);

		// Reward product: 50% off → 40. Tax on 40 at 20% = 8.
		$rewardLine = null;

		foreach ($orderData['products'] as $p) {
			if ((int)$p['product_id'] === self::PRODUCT_REWARD) {
				$rewardLine = $p;
				break;
			}
		}

		$this->assertNotNull($rewardLine);
		$this->assertEquals(40.0, $this->round((float)$rewardLine['price']));

		$taxTotals = array_filter($orderData['totals'], static fn($t) => $t['code'] === 'tax');
		$taxTotal = array_sum(array_map(static fn($t) => (float)$t['value'], $taxTotals));

		// Trigger 50×20% = 10; reward 80×20% = 16 (tax computed on the
		// pre-BXGY price, matching the storefront pipeline); total tax 26.
		$this->assertEquals(26.0, $this->round($taxTotal));

		// Reset.
		self::$registry->get('config')->set('config_tax', 0);
		self::$db->query("UPDATE " . DB_PREFIX . "product SET tax_class_id = '0' WHERE product_id IN ('" . self::PRODUCT_PLAIN . "', '" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_REWARD . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id = '" . self::PRODUCT_TRIGGER . "'");
	}
}
