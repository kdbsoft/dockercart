<?php
/**
 * Order line currency handling: order_product amounts are stored in the store
 * base currency, while BXGY distribution is computed in each product's own
 * currency. This guards the conversion step that used to be missing — a line
 * whose product has a non-default currency was re-priced with the raw
 * product-currency amount on every quantity/price edit.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!class_exists('ProductPricingCalculator', false)) {
	/**
	 * Stand-in for the real calculator: returns a fixed product-currency price
	 * so the test can assert the base-currency conversion applied on top.
	 */
	class ProductPricingCalculator {
		public static float $price = 0.0;
		public static float $option_price = 0.0;

		public function __construct($registry, array $config = []) {
		}

		public function calculate($product_id, $variant_id = 0, $quantity = 1, array $options = []) {
			return [
				'price'        => self::$price,
				'option_price' => self::$option_price,
			];
		}
	}
}

if (!class_exists('Bxgy', false)) {
	/** Stand-in for the promo library: no active BXGY discount. */
	class Bxgy {
		public function __construct($registry) {
		}

		public function getPerProductDiscountsFor(array $cart_products) {
			return [];
		}
	}
}

/**
 * Registry with a real Cart\Currency (fed from FakeDb) and a FakeDb-backed
 * ModelSaleOrder.
 *
 * @param array<int, array<string, mixed>> $currencies oc_currency rows
 */
function order_currency_test_model(array $currencies, FakeDb $db = null): ModelSaleOrder {
	$db = $db ?: new FakeDb();

	// Consumed by the Cart\Currency constructor.
	$db->pushResult($currencies);

	$config = new \Config();
	$config->load('default');
	$config->set('config_currency', 'UAH');

	$language = new class {
		public function get($key) {
			return '';
		}
	};

	$registry = new \Registry();
	$registry->set('db', $db);
	$registry->set('config', $config);
	$registry->set('language', $language);
	$registry->set('log', new \Log('order-currency-tests.log'));

	require_once DIR_SYSTEM . 'library/cart/currency.php';

	$registry->set('currency', new \Cart\Currency($registry));

	if (!class_exists('ModelSaleOrder', false)) {
		require_once DIR_APPLICATION . 'model/sale/order.php';
	}

	return new ModelSaleOrder($registry);
}

return [
	'convertProductPrice converts product currency into the store base currency' => static function (): void {
		$model = order_currency_test_model([
			['currency_id' => 1, 'code' => 'UAH', 'title' => 'Hryvnia', 'symbol_left' => '', 'symbol_right' => '₴', 'decimal_place' => 0, 'value' => 1.0],
			['currency_id' => 3, 'code' => 'EUR', 'title' => 'Euro', 'symbol_left' => '€', 'symbol_right' => '', 'decimal_place' => 2, 'value' => 2.0],
		]);

		// 10 EUR at rate 2.0 (base value 1.0) => 5 UAH.
		Assert::same(5.0, $model->currency->convertProductPrice(10.0, 3));
		// No explicit product currency => already base, unchanged.
		Assert::same(10.0, $model->currency->convertProductPrice(10.0, 0));
	},

	'BXGY recalculation stores base-currency prices, not product-currency ones' => static function (): void {
		$db = new FakeDb();

		// 1) Cart\Currency rows: base UAH (1.0), product currency EUR (2.0).
		// (pushed inside the helper before the model is built)
		// 2) order customer group
		// 3) order_product_override (empty)
		// 4) order_product line: stored base price 5 UAH, qty 2
		// 5) order_option (none)
		// 6) product currency: product 5041 is priced in EUR (id 3)
		$model = order_currency_test_model([
			['currency_id' => 1, 'code' => 'UAH', 'title' => 'Hryvnia', 'symbol_left' => '', 'symbol_right' => '₴', 'decimal_place' => 0, 'value' => 1.0],
			['currency_id' => 3, 'code' => 'EUR', 'title' => 'Euro', 'symbol_left' => '€', 'symbol_right' => '', 'decimal_place' => 2, 'value' => 2.0],
		], $db);

		$db->pushResult([['customer_group_id' => 1]]);       // order
		$db->pushResult([]);                                 // overrides
		$db->pushResult([                                   // order lines
			['order_product_id' => 11, 'product_id' => 5041, 'variant_id' => 0, 'quantity' => 2, 'price' => 5.0],
		]);
		$db->pushResult([]);                                 // order options
		$db->pushResult([                                   // product currency
			['product_id' => 5041, 'currency_id' => 3],
		]);

		// The calculator returns the product-currency price (10 EUR)...
		ProductPricingCalculator::$price = 10.0;
		ProductPricingCalculator::$option_price = 0.0;

		$model->recalculateBxgyForOrderLines(1);

		$updates = array_values(array_filter($db->queries, static function (string $sql): bool {
			return stripos($sql, 'UPDATE') === 0 && strpos($sql, 'order_product') !== false;
		}));

		Assert::count(1, $updates, 'one non-overridden line is re-priced');
		// ...and must be persisted converted to base (10 EUR * 0.5 = 5 UAH).
		Assert::contains("price = '5'", $updates[0]);
		Assert::contains("total = '10'", $updates[0]);
	},
];
