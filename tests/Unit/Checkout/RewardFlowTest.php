<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * Reward points through the real order flow: awarding on complete status
 * (with delay), idempotency, revocation on status reversal, guest orders,
 * and spending points at checkout (total/reward + confirm).
 */
class RewardFlowTest extends CheckoutTestCase
{
	private const REWARD_CUSTOMER = 99850;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_PLAIN, 100.0, 'TEST-REWARD-PLAIN');
		self::makeProduct(self::PRODUCT_DISC, 200.0, 'TEST-REWARD-DISC');

		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer SET customer_id = '" . self::REWARD_CUSTOMER . "', email = 'reward-customer@example.com', customer_group_id = '1', store_id = '0', language_id = '1', firstname = 'Reward', lastname = 'Customer', salt = '', password = 'x', status = '1', date_added = NOW()");
	}

	private function enableAutoAward(): void
	{
		self::$registry->get('config')->set('config_reward_auto_award', 1);
		self::$registry->get('config')->set('config_reward_auto_revoke', 1);
		self::$registry->get('config')->set('config_reward_delay_days', 0);
	}

	private function disableAutoAward(): void
	{
		self::$registry->get('config')->set('config_reward_auto_award', 0);
		self::$registry->get('config')->set('config_reward_auto_revoke', 0);
		self::$registry->get('config')->set('config_reward_delay_days', 0);
	}

	private function seedProductReward(int $productId, int $points): void
	{
		self::$db->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . $productId . "' AND customer_group_id = '" . self::CG_DEFAULT . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "product_reward SET product_id = '" . $productId . "', customer_group_id = '" . self::CG_DEFAULT . "', points = '" . $points . "'");
	}

	private function orderForCustomer(array $items, int $customerId): int
	{
		self::seedCart($items);

		$cart = $this->cart();
		$orderData = $this->buildOrderData($cart->getProducts());
		$orderData['customer_id'] = $customerId;
		$orderData['email'] = 'reward-customer@example.com';

		$orderModel = new \ModelCheckoutOrder(self::$registry);

		return $orderModel->addOrder($orderData);
	}

	private function rewardRows(int $orderId): array
	{
		return self::$db->query("SELECT * FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$orderId . "'")->rows;
	}

	public function testAwardOnCompleteStatus(): void
	{
		$this->enableAutoAward();
		$this->seedProductReward(self::PRODUCT_PLAIN, 50);

		$orderId = $this->orderForCustomer([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::REWARD_CUSTOMER);

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);   // complete

		$rows = $this->rewardRows($orderId);
		$this->assertCount(1, $rows);
		$this->assertEquals(50.0, (float)$rows[0]['points']);
		$this->assertEquals(self::REWARD_CUSTOMER, (int)$rows[0]['customer_id']);

		$this->disableAutoAward();
	}

	public function testAwardIsIdempotent(): void
	{
		$this->enableAutoAward();
		$this->seedProductReward(self::PRODUCT_PLAIN, 50);

		$orderId = $this->orderForCustomer([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::REWARD_CUSTOMER);

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);   // repeat complete

		$rows = $this->rewardRows($orderId);
		$this->assertCount(1, $rows, 'reward must be awarded only once');
		$this->assertEquals(50.0, (float)$rows[0]['points']);

		$this->disableAutoAward();
	}

	public function testRevokeOnReversal(): void
	{
		$this->enableAutoAward();
		$this->seedProductReward(self::PRODUCT_PLAIN, 50);

		$orderId = $this->orderForCustomer([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::REWARD_CUSTOMER);

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);

		// Reversal → revoke.
		$orderModel->addOrderHistory($orderId, 1, '', false, true);

		$rows = $this->rewardRows($orderId);
		$this->assertCount(2, $rows);
		$this->assertEquals(50.0, (float)$rows[0]['points']);
		$this->assertEquals(-50.0, (float)$rows[1]['points']);

		$this->disableAutoAward();
	}

	public function testDelayDefersAward(): void
	{
		$this->enableAutoAward();
		self::$registry->get('config')->set('config_reward_delay_days', 14);
		$this->seedProductReward(self::PRODUCT_PLAIN, 50);

		$orderId = $this->orderForCustomer([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], self::REWARD_CUSTOMER);

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);

		// Order is fresh → no award yet (delay 14 days).
		$rows = $this->rewardRows($orderId);
		$this->assertCount(0, $rows, 'award must be deferred by delay_days');

		// Simulate the order aging past the delay, then re-enter complete
		// (pending → complete again triggers the award check). date_modified
		// is bumped by addOrderHistory, so age the order AFTER the reversal.
		$orderModel->addOrderHistory($orderId, 1, '', false, true);
		self::$db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE order_id = '" . (int)$orderId . "'");
		$orderModel->addOrderHistory($orderId, 5, '', false, true);

		$rows = $this->rewardRows($orderId);
		$this->assertCount(1, $rows);
		$this->assertEquals(50.0, (float)$rows[0]['points']);

		$this->disableAutoAward();
	}

	public function testGuestOrderNotAwarded(): void
	{
		$this->enableAutoAward();
		$this->seedProductReward(self::PRODUCT_PLAIN, 50);

		$orderId = $this->orderForCustomer([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		], 0);   // guest

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 5, '', false, true);

		$rows = $this->rewardRows($orderId);
		$this->assertCount(0, $rows, 'guest orders must not earn reward points');

		$this->disableAutoAward();
	}

	public function testRewardSpendingAtCheckoutAndConfirm(): void
	{
		// Product with spend points; customer has balance.
		self::$db->query("UPDATE " . DB_PREFIX . "product SET points = '500' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");
		self::$registry->get('session')->data['reward'] = 100;
		self::$registry->get('customer')->setRewardPoints(500);

		[$orderId, $orderData] = $this->runFlow([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],   // 100
		]);

		$orderTotals = self::$db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$orderId . "'")->rows;
		$rewardTotal = $this->totalByCode($orderTotals, 'reward');
		$this->assertNotNull($rewardTotal);
		$this->assertEquals(-20.0, $this->round((float)$rewardTotal['value']));

		// Confirm: processing status → reward::confirm writes customer_reward
		// (negative row) only for logged-in customers. The runFlow order is a
		// guest (customer_id 0), so confirm skips it. Set a customer first.
		self::$db->query("UPDATE `" . DB_PREFIX . "order` SET customer_id = '" . self::REWARD_CUSTOMER . "' WHERE order_id = '" . (int)$orderId . "'");
		self::$db->query("INSERT IGNORE INTO " . DB_PREFIX . "customer_reward SET customer_id = '" . self::REWARD_CUSTOMER . "', order_id = '0', description = 'balance', points = '500', date_added = NOW()");

		$orderModel = new \ModelCheckoutOrder(self::$registry);
		$orderModel->addOrderHistory($orderId, 2, '', false, true);

		$spent = self::$db->query("SELECT * FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$orderId . "' AND points < 0")->rows;
		$this->assertCount(1, $spent);
		$this->assertEquals(-100.0, (float)$spent[0]['points']);

		unset(self::$registry->get('session')->data['reward']);
		self::$db->query("UPDATE " . DB_PREFIX . "product SET points = '0' WHERE product_id = '" . self::PRODUCT_PLAIN . "'");
	}
}
