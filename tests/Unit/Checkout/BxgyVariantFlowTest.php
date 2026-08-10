<?php
declare(strict_types=1);

namespace Tests\Unit\Checkout;

/**
 * BXGY and gift promotions against configurable (variant) products:
 * per-line discount distribution (pid:vid keys), trigger aggregation
 * across variants, and gifts resolved to the default variant.
 */
class BxgyVariantFlowTest extends CheckoutTestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		if (!self::$db) {
			return;
		}

		self::makeProduct(self::PRODUCT_TRIGGER, 50.0, 'TEST-BXGY-TRIGGER');
		self::makeProduct(self::PRODUCT_REWARD, 80.0, 'TEST-BXGY-REWARD');
		self::makeProduct(self::PRODUCT_GIFT, 30.0, 'TEST-BXGY-GIFT');
		self::makeProduct(self::PRODUCT_VARIANT, 1000.0, 'TEST-BXGY-VARIANT');
		self::makeProduct(self::PRODUCT_PLAIN, 40.0, 'TEST-BXGY-PLAIN');

		// Configurable trigger product with two variants (A = 50, B = 70).
		self::makeVariant(self::VARIANT_A, self::PRODUCT_VARIANT, 50.0, 'bxgy-variant-a');
		self::makeVariant(self::VARIANT_B, self::PRODUCT_VARIANT, 70.0, 'bxgy-variant-b');
		self::db()->query("INSERT IGNORE INTO " . DB_PREFIX . "product_configurable SET product_id = '" . self::PRODUCT_VARIANT . "', is_configurable = '1', default_variant_id = '" . self::VARIANT_A . "'");
	}

	private static function db(): \DB\MySQLi
	{
		return self::$db;
	}

	private static function addBxgy(int $triggerProduct, int $rewardProduct, int $triggerQty, string $type, float $value): void
	{
		self::db()->query("INSERT IGNORE INTO " . DB_PREFIX . "product_bxgy SET product_id = '" . $triggerProduct . "', reward_product_id = '" . $rewardProduct . "', trigger_quantity = '" . $triggerQty . "', discount_type = '" . $type . "', discount_value = '" . $value . "', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");
	}

	private static function clearBxgy(): void
	{
		self::db()->query("DELETE FROM " . DB_PREFIX . "product_bxgy WHERE product_id IN ('" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_PLAIN . "') OR reward_product_id IN ('" . self::PRODUCT_REWARD . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_GIFT . "')");
	}

	private static function addGift(int $triggerProduct, int $giftProduct, int $minimumQuantity): void
	{
		self::db()->query("INSERT IGNORE INTO " . DB_PREFIX . "product_gift SET product_id = '" . $triggerProduct . "', gift_product_id = '" . $giftProduct . "', minimum_quantity = '" . $minimumQuantity . "', date_start = '0000-00-00', date_end = '0000-00-00', date_added = NOW()");
	}

	private static function clearGifts(): void
	{
		self::db()->query("DELETE FROM " . DB_PREFIX . "product_gift WHERE product_id IN ('" . self::PRODUCT_TRIGGER . "', '" . self::PRODUCT_VARIANT . "', '" . self::PRODUCT_PLAIN . "')");
	}

	private static function orderLines(int $orderId): array
	{
		return self::db()->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "' ORDER BY order_product_id")->rows;
	}

	private function rewardLine(array $lines): array
	{
		foreach ($lines as $line) {
			if ((int)$line['product_id'] === self::PRODUCT_REWARD) {
				return $line;
			}
		}

		$this->fail('reward line not found in order');
	}

	public function testFreeBxgyDiscountsOnlyMostExpensiveRewardLine(): void
	{
		self::addBxgy(self::PRODUCT_TRIGGER, self::PRODUCT_REWARD, 2, 'free', 0);

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 2],                       // trigger: 50 × 2
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],  // 50
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_B]],  // 70
		]);

		$lines = self::orderLines($orderId);
		$rewardLines = array_values(array_filter($lines, static fn(array $l): bool => (int)$l['product_id'] === self::PRODUCT_REWARD));

		// One eligible set → the most expensive variant (B, 70) is free,
		// the cheaper line keeps its price.
		$this->assertCount(2, $rewardLines);
		$this->assertCount(1, array_filter($rewardLines, static fn(array $l): bool => (float)$l['price'] === 0.0));
		$this->assertCount(1, array_filter($rewardLines, static fn(array $l): bool => (float)$l['price'] === 50.0));

		self::clearBxgy();
	}

	public function testPercentageBxgyAveragesAcrossRewardLineQuantity(): void
	{
		self::addBxgy(self::PRODUCT_TRIGGER, self::PRODUCT_REWARD, 1, 'percentage', 25);

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_TRIGGER, 'quantity' => 1],                       // trigger
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 2, 'option' => ['variant_id' => self::VARIANT_A]],  // 50 × 2
		]);

		$line = $this->rewardLine(self::orderLines($orderId));
		// 1 eligible set of 1 unit: (50 + 37.5) / 2 = 43.75 per unit.
		$this->assertEquals(43.75, $this->round((float)$line['price'], 4));
		$this->assertEquals(87.5, $this->round((float)$line['total'], 4));

		self::clearBxgy();
	}

	public function testTriggerQuantityAggregatesAcrossVariants(): void
	{
		self::addBxgy(self::PRODUCT_VARIANT, self::PRODUCT_REWARD, 2, 'percentage', 50);

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_A]],  // 50
			['product_id' => self::PRODUCT_VARIANT, 'quantity' => 1, 'option' => ['variant_id' => self::VARIANT_B]],  // 70
			['product_id' => self::PRODUCT_REWARD, 'quantity' => 1],                       // 80 → 40 after 50%
		]);

		$line = $this->rewardLine(self::orderLines($orderId));
		$this->assertEquals(40.0, $this->round((float)$line['price']));

		self::clearBxgy();
	}

	public function testGiftUsesDefaultVariant(): void
	{
		// Gift product = the configurable product (default variant A).
		self::addGift(self::PRODUCT_PLAIN, self::PRODUCT_VARIANT, 1);

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		]);

		$lines = self::orderLines($orderId);
		$this->assertCount(2, $lines);

		$giftLine = $lines[1];
		$this->assertEquals(self::PRODUCT_VARIANT, (int)$giftLine['product_id']);
		$this->assertEquals(0.0, (float)$giftLine['price']);
		$this->assertEquals(self::VARIANT_A, (int)$giftLine['variant_id']);
		$this->assertEquals('', (string)$giftLine['variant_sku']);
		$this->assertStringContainsString('Gift', (string)$giftLine['name']);

		self::clearGifts();
	}

	public function testGiftSkippedWhenDefaultVariantOutOfStock(): void
	{
		// Configurable gift product: default variant A out of stock (subtract=1).
		self::db()->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = '0' WHERE variant_id = '" . self::VARIANT_A . "'");

		self::addGift(self::PRODUCT_PLAIN, self::PRODUCT_VARIANT, 1);

		[$orderId] = $this->runFlow([
			['product_id' => self::PRODUCT_PLAIN, 'quantity' => 1],
		]);

		$lines = self::orderLines($orderId);
		$this->assertCount(1, $lines);
		$this->assertEquals(self::PRODUCT_PLAIN, (int)$lines[0]['product_id']);

		self::clearGifts();
		self::db()->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = '100' WHERE variant_id = '" . self::VARIANT_A . "'");
	}
}
