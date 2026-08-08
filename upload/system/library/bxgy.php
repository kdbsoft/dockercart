<?php
/** @property \DB $db
 * @property \Config $config
 * @property \Cart $cart
 * @property \Currency $currency
 * @property \Tax $tax
 * @property \Session $session
 * @property \Language $language */
class Bxgy {
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * Get all active BXGY rules for the given cart/order products.
	 * Returns array keyed by reward_product_id => array of rules.
	 *
	 * @param array $products list of products with at least 'product_id'
	 */
	public function getActiveRulesByReward(array $products = [], $store_id = 0) {
		if (empty($products)) {
			$products = $this->cart->getProducts();
		}

		if (empty($products)) {
			return [];
		}

		$product_ids = [];

		foreach ($products as $product) {
			$product_ids[] = (int) $product['product_id'];
		}

		$customer_group_id = (int) $this->config->get('config_customer_group_id');

		$sql = "SELECT bx.*, pd.name AS reward_product_name, p.price AS reward_price, p.tax_class_id FROM "
			. DB_PREFIX . "product_bxgy bx "
			. "LEFT JOIN " . DB_PREFIX . "product p ON (bx.reward_product_id = p.product_id) "
			. "LEFT JOIN " . DB_PREFIX . "product_description pd ON (bx.reward_product_id = pd.product_id AND pd.language_id = '" . (int) $this->config->get('config_language_id') . "') "
			. "WHERE bx.product_id IN (" . implode(',', $product_ids) . ") "
			. "AND p.status = '1' "
			. "AND (bx.date_start = '0000-00-00' OR bx.date_start <= CURDATE()) "
			. "AND (bx.date_end = '0000-00-00' OR bx.date_end >= CURDATE()) "
			. "ORDER BY bx.trigger_quantity ASC, bx.discount_type ASC";

		$query = $this->db->query($sql);

		$rules_by_reward = [];

		foreach ($query->rows as $rule) {
			$reward_id = (int) $rule['reward_product_id'];

			if (!isset($rules_by_reward[$reward_id])) {
				$rules_by_reward[$reward_id] = [];
			}

			$rules_by_reward[$reward_id][] = $rule;
		}

		return $rules_by_reward;
	}

	/**
	 * Calculate per-product BXGY discounts for cart display.
	 *
	 * @param array $cart_products from $this->cart->getProducts()
	 * @return array [product_id => ['discount_amount' => float, 'original_price' => float, 'text' => string]]
	 */
	public function getPerProductDiscounts(array $cart_products) {
		$rules_by_reward = $this->getActiveRulesByReward($cart_products);

		if (empty($rules_by_reward)) {
			return [];
		}

		$this->language->load('checkout/cart');
		$customer_group_id = (int) $this->config->get('config_customer_group_id');

		// Count trigger products in cart (product_id => total quantity)
		$trigger_qty = [];

		foreach ($cart_products as $product) {
			$pid = (int) $product['product_id'];

			if (!isset($trigger_qty[$pid])) {
				$trigger_qty[$pid] = 0;
			}

			$trigger_qty[$pid] += (int) $product['quantity'];
		}

		$discounts = [];

		// For each reward product in the cart, find the best applicable rule
		foreach ($cart_products as $product) {
			$reward_id = (int) $product['product_id'];

			if (!isset($rules_by_reward[$reward_id])) {
				continue;
			}

			$price = (float) $product['price'];
			$tax_class_id = (int) ($product['tax_class_id'] ?? 0);
			$reward_qty = $trigger_qty[$reward_id] ?? 0;
			$best_discount = 0;
			$best_text = '';

			foreach ($rules_by_reward[$reward_id] as $rule) {
				$trigger_pid = (int) $rule['product_id'];
				$trigger_sets = 0;

				if (isset($trigger_qty[$trigger_pid])) {
					$trigger_sets = (int) floor($trigger_qty[$trigger_pid] / (int) $rule['trigger_quantity']);
				}

				if ($trigger_sets < 1) {
					continue;
				}

				$eligible_sets = (int) min($trigger_sets, $reward_qty);

				$candidate_discount = $this->calculateDiscount(
					$price,
					$rule['discount_type'],
					(float) $rule['discount_value'],
					$eligible_sets
				);

				if ($candidate_discount > $best_discount) {
					$best_discount = $candidate_discount;
					$best_text = $this->formatDiscountText($rule['discount_type'], (float) $rule['discount_value']);
				}
			}

			if ($best_discount > 0) {
				$discounts[$reward_id] = [
					'discount_amount' => $best_discount,
					'original_price' => $price,
					'text' => $best_text,
					'original_price_formatted' => $this->currency->format(
						$this->tax->calculate($price, $tax_class_id, $this->config->get('config_tax')),
						$this->session->data['currency']
					),
				];
			}
		}

		return $discounts;
	}

	/**
	 * Calculate per-product BXGY discounts for an explicit product list
	 * (works outside the storefront cart, e.g. admin order editing).
	 *
	 * @param array $cart_products list of products with product_id/quantity/price/tax_class_id
	 * @return array [product_id => ['discount_amount' => float, 'original_price' => float, 'text' => string]]
	 */
	public function getPerProductDiscountsFor(array $cart_products) {
		return $this->getPerProductDiscounts($cart_products);
	}

	/**
	 * Calculate total BXGY discount amount for the cart totals line.
	 *
	 * @param array $cart_products from $this->cart->getProducts()
	 * @return float total discount amount
	 */
	public function getTotalDiscount(array $cart_products) {
		$discounts = $this->getPerProductDiscounts($cart_products);
		$total = 0;

		foreach ($discounts as $reward_id => $info) {
			// Find the quantity of this reward product in cart
			foreach ($cart_products as $product) {
				if ((int) $product['product_id'] === $reward_id) {
					$total += $info['discount_amount'] * (int) $product['quantity'];
					break;
				}
			}
		}

		return $total;
	}

	/**
	 * Calculate discount for a single reward item.
	 */
	private function calculateDiscount($price, $discount_type, $discount_value, $trigger_sets) {
		if ($discount_type === 'free') {
			return (float) $price * $trigger_sets;
		} elseif ($discount_type === 'percentage') {
			return (float) $price * ((float) $discount_value / 100) * $trigger_sets;
		}

		return 0;
	}

	/**
	 * Format discount text for display.
	 */
	private function formatDiscountText($discount_type, $discount_value) {
		if ($discount_type === 'free') {
			$text = $this->language->get('text_bxgy_free_badge');
			return $text ?: 'BXGY: Free';
		} elseif ($discount_type === 'percentage') {
			$format = $this->language->get('text_bxgy_percent_badge');
			$format = $format ?: 'BXGY: -%d%%';
			return sprintf($format, (int) $discount_value);
		}

		return 'BXGY';
	}
}
