<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * Coupon validation and redemption: validity window, minimum total,
 * usage limits (total/customer), product/category restrictions, fixed
 * discount clamping, confirm() history and auto-renewal.
 */
class CheckoutCouponTest extends CheckoutTestCase
{
	private const COUPON_CODE = 'TEST-COUPON-VALID';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_PLAIN, 100.0, 'TEST-COUPON-PLAIN');
	}

	protected function tearDown(): void
	{
		// Always clear coupon from session between tests.
		if (isset(self::$registry->get('session')->data['coupon'])) {
			unset(self::$registry->get('session')->data['coupon']);
		}

		parent::tearDown();
	}

	private function makeCoupon(array $overrides = []): int
	{
		// Remove any previous coupon with the same code (getCoupon picks the
		// first row without ordering, so duplicates would shadow the override).
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_product WHERE coupon_id IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '" . self::COUPON_CODE . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_category WHERE coupon_id IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '" . self::COUPON_CODE . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_history WHERE coupon_id IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '" . self::COUPON_CODE . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon_description WHERE coupon_id IN (SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '" . self::COUPON_CODE . "')");
		self::$db->query("DELETE FROM " . DB_PREFIX . "coupon WHERE code = '" . self::COUPON_CODE . "'");

		$fields = [
			'name' => self::COUPON_CODE,
			'code' => self::COUPON_CODE,
			'type' => 'P',
			'discount' => 10.0,
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
		self::$db->query("INSERT INTO " . DB_PREFIX . "coupon_description SET coupon_id = '" . $id . "', language_id = '1', name = '" . self::COUPON_CODE . "'");

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

	private function couponModel(): \ModelExtensionTotalCoupon
	{
		// getCoupon()/getTotal() read the cart from the registry.
		self::$registry->set('cart', $this->cart());

		return new \ModelExtensionTotalCoupon(self::$registry);
	}

	private function seedOneProductCart(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);
	}

	public function testValidCouponReturnsInfo(): void
	{
		$this->makeCoupon();
		$this->seedOneProductCart();

		$info = $this->couponModel()->getCoupon(self::COUPON_CODE);

		$this->assertNotEmpty($info);
		$this->assertEquals(self::COUPON_CODE, $info['code']);
		$this->assertEquals('P', $info['type']);
		$this->assertEquals(10.0, (float)$info['discount']);
	}

	public function testUnknownCouponRejected(): void
	{
		$this->seedOneProductCart();

		$info = $this->couponModel()->getCoupon('NO-SUCH-COUPON');

		$this->assertEmpty($info);
	}

	public function testDisabledCouponRejected(): void
	{
		$this->makeCoupon(['status' => 0]);
		$this->seedOneProductCart();

		$info = $this->couponModel()->getCoupon(self::COUPON_CODE);

		$this->assertEmpty($info);
	}

	public function testExpiredCouponRejected(): void
	{
		$this->makeCoupon(['date_start' => '2000-01-01', 'date_end' => '2000-01-02']);
		$this->seedOneProductCart();

		$info = $this->couponModel()->getCoupon(self::COUPON_CODE);

		$this->assertEmpty($info);
	}

	public function testMinimumTotalRejectedWhenCartBelow(): void
	{
		$this->makeCoupon(['total' => 150.0]);
		$this->seedOneProductCart();   // cart 100 < 150

		$info = $this->couponModel()->getCoupon(self::COUPON_CODE);

		$this->assertEmpty($info);
	}

	public function testUsesTotalLimitReached(): void
	{
		$couponId = $this->makeCoupon(['uses_total' => 2]);
		$this->seedOneProductCart();

		// Simulate 2 previous redemptions.
		self::$db->query("INSERT INTO " . DB_PREFIX . "coupon_history SET coupon_id = '" . $couponId . "', order_id = '99990', customer_id = '0', amount = '-10.00', date_added = NOW()");
		self::$db->query("INSERT INTO " . DB_PREFIX . "coupon_history SET coupon_id = '" . $couponId . "', order_id = '99991', customer_id = '0', amount = '-10.00', date_added = NOW()");

		$info = $this->couponModel()->getCoupon(self::COUPON_CODE);

		$this->assertEmpty($info);
	}

	public function testFixedCouponClampedToSubTotal(): void
	{
		// Fixed discount larger than the cart → clamped to sub_total.
		$this->makeCoupon(['type' => 'F', 'discount' => 500.0]);
		self::$registry->get('session')->data['coupon'] = self::COUPON_CODE;
		$this->seedOneProductCart();

		$totals = $this->buildOrderData($this->cart()->getProducts())['totals'];
		$coupon = $this->totalByCode($totals, 'coupon');

		$this->assertNotNull($coupon);
		$this->assertEquals(-100.0, $this->round((float)$coupon['value']));
	}

	public function testCouponRestrictedToProductOnlyAppliesToThatProduct(): void
	{
		self::makeProduct(self::PRODUCT_DISC, 200.0, 'TEST-COUPON-DISC');
		$couponId = $this->makeCoupon(['type' => 'P', 'discount' => 10.0]);
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "coupon_product SET coupon_id = '" . $couponId . "', product_id = '" . self::PRODUCT_PLAIN . "'");

		self::$registry->get('session')->data['coupon'] = self::COUPON_CODE;
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
			['product_id' => self::PRODUCT_DISC, 'quantity' => 1],    // 200
		]);

		$totals = $this->buildOrderData($this->cart()->getProducts())['totals'];
		$coupon = $this->totalByCode($totals, 'coupon');

		// 10% of the restricted product (100) = -10; the other product is untouched.
		$this->assertNotNull($coupon);
		$this->assertEquals(-10.0, $this->round((float)$coupon['value']));
	}

	public function testConfirmWritesHistoryAndUnconfirmClears(): void
	{
		$couponId = $this->makeCoupon(['type' => 'P', 'discount' => 10.0]);
		self::$registry->get('session')->data['coupon'] = self::COUPON_CODE;

		[$orderId, $orderData] = $this->runFlow([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$orderTotals = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$orderId . "'")->rows;
		$couponTotal = $this->totalByCode($orderTotals, 'coupon');
		$this->assertNotNull($couponTotal);

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 2, '', false, true);

		$history = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "coupon_history WHERE coupon_id = '" . $couponId . "' AND order_id = '" . (int)$orderId . "'")->row['c'];
		$this->assertEquals(1, (int)$history);

		// Reversal clears history.
		$orderModel->addOrderHistory($orderId, 1, '', false, true);
		$history = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "coupon_history WHERE coupon_id = '" . $couponId . "' AND order_id = '" . (int)$orderId . "'")->row['c'];
		$this->assertEquals(0, (int)$history);
	}
}
