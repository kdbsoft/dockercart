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
	 * Calculate per-line BXGY discounts for cart display and order creation.
	 *
	 * Lines are keyed by "product_id:variant_id" so that when the reward
	 * product has multiple cart lines (different variants), the discount is
	 * distributed across lines instead of being applied to every line.
	 * Trigger quantities are summed per product across all variants.
	 *
	 * @param array $cart_products from $this->cart->getProducts()
	 * @return array ["pid:vid" => ['per_unit' => float, 'units' => int, 'text' => string, 'original_price' => float, 'original_price_formatted' => string]]
	 */
	public function getPerProductDiscounts(array $cart_products) {
		$rules_by_reward = $this->getActiveRulesByReward($cart_products);

		if (empty($rules_by_reward)) {
			return [];
		}

		$this->language->load('checkout/cart');

		// Count trigger products in cart (product_id => total quantity, all variants).
		$trigger_qty = [];

		foreach ($cart_products as $product) {
			$pid = (int) $product['product_id'];
			$trigger_qty[$pid] = ($trigger_qty[$pid] ?? 0) + (int) $product['quantity'];
		}

		// Group reward lines by product, keeping per-line variant info.
		$reward_lines = [];

		foreach ($cart_products as $product) {
			$reward_id = (int) $product['product_id'];

			if (!isset($rules_by_reward[$reward_id])) {
				continue;
			}

			$line = [
				'product_id' => $reward_id,
				'variant_id' => (int) ($product['variant_id'] ?? 0),
				'quantity'   => (int) $product['quantity'],
				'price'      => (float) $product['price'],
				'tax_class_id' => (int) ($product['tax_class_id'] ?? 0),
			];

			$reward_lines[$reward_id][] = $line;
		}

		if (empty($reward_lines)) {
			return [];
		}

		$discounts = [];

		foreach ($reward_lines as $reward_id => $lines) {
			$total_reward_qty = 0;

			foreach ($lines as $line) {
				$total_reward_qty += $line['quantity'];
			}

			$best_total = 0;
			$best_distribution = null;
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

				$eligible_sets = (int) min($trigger_sets, $total_reward_qty);

				// Distribute the discount greedily over lines, most expensive first.
				$sorted = $lines;
				usort($sorted, function ($a, $b) {
					return $b['price'] <=> $a['price'];
				});

				$remaining = $eligible_sets;
				$distribution = [];
				$rule_total = 0;

				foreach ($sorted as $line) {
					$key = $line['product_id'] . ':' . $line['variant_id'];
					$take = (int) min($remaining, $line['quantity']);

					if ($take < 1) {
						continue;
					}

					$per_unit = $this->calculateDiscount($line['price'], $rule['discount_type'], (float) $rule['discount_value'], 1);

					if ($per_unit <= 0) {
						continue;
					}

					$distribution[$key] = [
						'per_unit' => $per_unit,
						'units'    => $take,
					];

					$rule_total += $per_unit * $take;
					$remaining -= $take;

					if ($remaining < 1) {
						break;
					}
				}

				if ($rule_total > $best_total) {
					$best_total = $rule_total;
					$best_distribution = $distribution;
					$best_text = $this->formatDiscountText($rule['discount_type'], (float) $rule['discount_value']);
				}
			}

			if ($best_total > 0 && $best_distribution !== null) {
				foreach ($lines as $line) {
					$key = $line['product_id'] . ':' . $line['variant_id'];

					if (!isset($best_distribution[$key])) {
						continue;
					}

					$discounts[$key] = [
						'per_unit' => $best_distribution[$key]['per_unit'],
						'units'    => $best_distribution[$key]['units'],
						'text'     => $best_text,
						'original_price' => $line['price'],
						'original_price_formatted' => $this->currency->format(
							$this->tax->calculate($line['price'], $line['tax_class_id'], $this->config->get('config_tax')),
							$this->session->data['currency']
						),
					];
				}
			}
		}

		return $discounts;
	}

	/**
	 * Calculate per-line BXGY discounts for an explicit product list
	 * (works outside the storefront cart, e.g. admin order editing).
	 *
	 * @param array $cart_products list of products with product_id/quantity/price/tax_class_id
	 * @return array ["pid:vid" => ['per_unit' => float, 'units' => int, 'text' => string]]
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

		foreach ($cart_products as $product) {
			$key = (int) $product['product_id'] . ':' . (int) ($product['variant_id'] ?? 0);

			if (isset($discounts[$key])) {
				$total += $discounts[$key]['per_unit'] * min($discounts[$key]['units'], (int) $product['quantity']);
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
			return $text ?: 'Second item: free';
		} elseif ($discount_type === 'percentage') {
			$format = $this->language->get('text_bxgy_percent_badge');
			$format = $format ?: 'Second item: -%d%%';
			return sprintf($format, (int) $discount_value);
		}

		return 'Second item discount';
	}
}
