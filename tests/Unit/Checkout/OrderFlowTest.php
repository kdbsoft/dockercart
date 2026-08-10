<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * End-to-end order flow: cart → order data → ModelCheckoutOrder::addOrder →
 * addOrderHistory. Verifies persisted order products/totals, stock
 * subtraction/restock, coupon confirm/unconfirm and reward awarding.
 */
class OrderFlowTest extends CheckoutTestCase
{
	private const COUPON_FLOW = 'TEST-COUPON-FLOW';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_PLAIN, 100.0, 'TEST-FLOW-PLAIN', 100, 0, 1);
		self::makeProduct(self::PRODUCT_DISC, 200.0, 'TEST-FLOW-DISC', 50, 0, 1);
		self::makeProduct(self::PRODUCT_TRIGGER, 50.0, 'TEST-FLOW-TRIGGER', 100, 0, 1);
		self::makeProduct(self::PRODUCT_REWARD, 80.0, 'TEST-FLOW-REWARD', 100, 0, 1);
		self::makeProduct(self::PRODUCT_GIFT, 30.0, 'TEST-FLOW-GIFT', 100, 0, 1);
		self::makeProduct(self::PRODUCT_VARIANT, 1000.0, 'TEST-FLOW-VARIANT');

		self::makeVariant(self::VARIANT_A, self::PRODUCT_VARIANT, 500.0, 'flow-variant-a');
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

	public function testAddOrderPersistsProductsTotalsAndGift(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_gift SET product_id = '" . self::PRODUCT_TRIGGER . "', gift_product_id = '" . self::PRODUCT_GIFT . "', minimum_quantity = '1', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 1],   // 50
		]);

		$order = self::$db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$orderId . "'")->row;
		$this->assertNotEmpty($order);
		$this->assertEquals('checkout-test@example.com', $order['email']);

		$products = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "' ORDER BY order_product_id")->rows;
		$this->assertCount(2, $products);

		// Trigger line.
		$this->assertEquals(self::PRODUCT_TRIGGER, (int)$products[0]['product_id']);
		$this->assertEquals(50.0, (float)$products[0]['price']);
		$this->assertEquals(50.0, (float)$products[0]['total']);

		// Gift line: price 0.
		$this->assertEquals(self::PRODUCT_GIFT, (int)$products[1]['product_id']);
		$this->assertEquals(0.0, (float)$products[1]['price']);
		$this->assertEquals(0.0, (float)$products[1]['total']);

		$totals = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$orderId . "' ORDER BY sort_order")->rows;
		$sub = $this->totalByCode($totals, 'sub_total');
		$total = $this->totalByCode($totals, 'total');

		$this->assertNotNull($sub);
		$this->assertEquals(50.0, $this->round((float)$sub['value']));
		$this->assertNotNull($total);
		$this->assertEquals(50.0, $this->round((float)$total['value']));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_gift WHERE product_id = '" . self::PRODUCT_TRIGGER . "'");
	}

	public function testAddOrderWithBxgyAppliesDiscountToOrderProduct(): void
	{
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . self::PRODUCT_TRIGGER . "', reward_product_id = '" . self::PRODUCT_REWARD . "', trigger_quantity = '1', discount_type = 'percentage', discount_value = '25.00', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 1],   // 50
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 1],    // 80 → 60 after 25%
		]);

		$products = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "' ORDER BY order_product_id")->rows;
		$this->assertCount(2, $products);

		$rewardLine = null;

		foreach ($products as $p) {
			if ((int)$p['product_id'] === self::PRODUCT_REWARD) {
				$rewardLine = $p;
				break;
			}
		}

		$this->assertNotNull($rewardLine);
		$this->assertEquals(60.0, $this->round((float)$rewardLine['price']));
		$this->assertEquals(60.0, $this->round((float)$rewardLine['total']));

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id = '" . self::PRODUCT_TRIGGER . "'");
	}

	public function testAddOrderHistorySubtractsStockAndReleasesHolds(): void
	{
		// Reset stock so the test is independent of other tests' side effects.
		self::$db->query("UPDATE " . DB_PREFIX . "product SET quantity = '100' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");

		// Product with subtract=1, qty 100; cart 3 → after processing status stock 97.
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 3],
		]);

		// Reserve stock like checkout does.
		$reservation = new \DockercartStockReservation(self::$registry);
		$failed = $reservation->reserve([['product_id' => self::PRODUCT_PLAIN, 'variant_id' => 0, 'quantity' => 3]], $reservation->getDefaultTtlMinutes());
		$this->assertEmpty($failed);

		$cart = $this->cart();
		$orderData = $this->buildOrderData($cart->getProducts());
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderId = $orderModel->addOrder($orderData);

		// Processing status (2) → subtract stock + release holds.
		$orderModel->addOrderHistory($orderId, 2, '', false, true);

		$row = self::$db->query("SELECT quantity FROM " . DB_PREFIX . "product WHERE product_id = '" . self::PRODUCT_PLAIN . "'")->row;
		$this->assertEquals(97.0, (float)$row['quantity']);

		$holds = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "stock_reservation WHERE order_id = '" . (int)$orderId . "'")->row['c'];
		$this->assertEquals(0, (int)$holds);
	}

	public function testOrderRestockOnStatusReversal(): void
	{
		// Reset stock so the test is independent of other tests' side effects.
		self::$db->query("UPDATE " . DB_PREFIX . "product SET quantity = '100' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");

		// Processing (2) → back to pending (1): stock restored.
		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 2],
		]);

		$cart = $this->cart();
		$orderData = $this->buildOrderData($cart->getProducts());
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderId = $orderModel->addOrder($orderData);

		$orderModel->addOrderHistory($orderId, 2, '', false, true);
		$row = self::$db->query("SELECT quantity FROM " . DB_PREFIX . "product WHERE product_id = '" . self::PRODUCT_PLAIN . "'")->row;
		$this->assertEquals(98.0, (float)$row['quantity']);

		$orderModel->addOrderHistory($orderId, 1, '', false, true);
		$row = self::$db->query("SELECT quantity FROM " . DB_PREFIX . "product WHERE product_id = '" . self::PRODUCT_PLAIN . "'")->row;
		$this->assertEquals(100.0, (float)$row['quantity']);
	}

	public function testCouponConfirmWritesHistoryAndUnconfirmClears(): void
	{
		$this->makeCoupon(self::COUPON_FLOW, 'P', 10.0);
		self::$registry->get('session')->data['coupon'] = self::COUPON_FLOW;

		[$orderId, $orderData] = $this->runFlow([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		// The order total for the coupon carries the code in the title.
		$orderTotals = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$orderId . "'")->rows;
		$couponTotal = $this->totalByCode($orderTotals, 'coupon');
		$this->assertNotNull($couponTotal);
		$this->assertStringContainsString(self::COUPON_FLOW, (string)$couponTotal['title']);
		$this->assertEquals(-10.0, $this->round((float)$couponTotal['value']));

		// confirm() writes coupon_history.
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 2, '', false, true);

		$history = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "coupon_history WHERE order_id = '" . (int)$orderId . "'")->row['c'];
		$this->assertEquals(1, (int)$history);

		// Reversal → unconfirm clears history.
		$orderModel->addOrderHistory($orderId, 1, '', false, true);
		$history = self::$db->query("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "coupon_history WHERE order_id = '" . (int)$orderId . "'")->row['c'];
		$this->assertEquals(0, (int)$history);

		unset(self::$registry->get('session')->data['coupon']);
	}

	public function testRewardAwardedOnCompleteAndRevokedOnReversal(): void
	{
		// Customer with reward auto-award enabled.
		self::$registry->get('config')->set('config_reward_auto_award', 1);
		self::$registry->get('config')->set('config_reward_auto_revoke', 1);
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer SET customer_id = '99850', email = 'reward-customer@example.com', customer_group_id = '1', store_id = '0', language_id = '1', firstname = 'Reward', lastname = 'Customer', salt = '', password = 'x', status = '1', date_added = NOW()");

		// Product reward points: product.points are spend points; the earning
		// reward comes from oc_product_reward (reward column of order_product).
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_reward SET product_id = '" . self::PRODUCT_PLAIN . "', customer_group_id = '" . self::CG_DEFAULT . "', points = '50'");

		self::seedCart([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$cart = $this->cart();
		$orderData = $this->buildOrderData($cart->getProducts());
		$orderData['customer_id'] = 99850;
		$orderData['email'] = 'reward-customer@example.com';
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderId = $orderModel->addOrder($orderData);

		// Complete status (5) → award.
		$orderModel->addOrderHistory($orderId, 5, '', false, true);

		$reward = self::$db->query("SELECT * FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$orderId . "' AND customer_id = '99850'")->rows;
		$this->assertCount(1, $reward);
		$this->assertEquals(50.0, (float)$reward[0]['points']);

		// Reversal → revoke.
		$orderModel->addOrderHistory($orderId, 1, '', false, true);
		$reward = self::$db->query("SELECT * FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$orderId . "' AND customer_id = '99850'")->rows;
		$this->assertCount(2, $reward);
		$this->assertEquals(-50.0, (float)$reward[1]['points']);

		self::$db->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . self::PRODUCT_PLAIN . "'");
		self::$db->query("DELETE FROM " . DB_PREFIX . "customer WHERE customer_id = '99850'");
		self::$registry->get('config')->set('config_reward_auto_award', 0);
		self::$registry->get('config')->set('config_reward_auto_revoke', 0);
	}

	public function testVariantLinePersistsVariantId(): void
	{
		self::seedCart([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],
		]);

		$cart = $this->cart();
		$products = $cart->getProducts();
		$this->assertNotEmpty($products);
		$this->assertEquals(self::VARIANT_A, (int)$products[0]['variant_id']);

		$orderData = $this->buildOrderData($products);
		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderId = $orderModel->addOrder($orderData);

		$line = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "'")->row;
		$this->assertNotEmpty($line);
		$this->assertEquals(self::VARIANT_A, (int)$line['variant_id']);
		$this->assertEquals(500.0, $this->round((float)$line['price']));
	}
}
