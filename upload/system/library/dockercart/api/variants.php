<?php
/**
 * DockerCart admin REST API — configurable product variants (read only).
 *
 * Everything goes through ProductConfigurable — the same library the panel and
 * the storefront use — so option axes, variant hashes, prices and stock behave
 * identically:
 *
 *   getConfigurableOptions()  axis options + their values (language aware)
 *   getVariants()             variants (enabled only, exactly like the storefront)
 *   getAggregatedPriceRange() min/max price across variants
 *   getAggregatedStock()      total stock / in-stock counters
 *
 * Variants are deliberately **not writable** over the API: the variant hash is
 * built from the option axes (`ProductConfigurable::buildVariantHash()`), so a
 * wrong write would silently break `resolveVariant()` lookups. Axes, hash
 * rebuilds and axis-option pricing (always 0) stay panel-only operations; the
 * API covers stock/price through the product endpoints.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Variants {
	private \Registry $registry;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * @param array $options pricing context for the storefront; omit it to keep the
	 *                       admin representation (stored prices, product currency)
	 *                       exactly as before:
	 *                       customer_group_id (int), normalize (bool), tax (bool),
	 *                       tax_class_id (int)
	 * @return array{product_id: int, currency_id: int, is_configurable: bool, default_variant_id: int,
	 *               options: array, aggregated: array, variants: array}
	 */
	public function forProduct(int $product_id, array $options = []): array {
		$configurable = new \ProductConfigurable($this->registry);

		$info = $configurable->getConfigurable($product_id);

		$default_variant_id = (int)($info['default_variant_id'] ?? 0);

		// Variant prices are denominated in the product's currency (0 = store
		// default), not the store base currency — surface it so clients can
		// interpret every amount in this payload.
		$currency_id = $this->productCurrencyId($product_id);

		$customer_group_id = array_key_exists('customer_group_id', $options) ? $options['customer_group_id'] : null;
		$normalize = !empty($options['normalize']);
		$tax = !empty($options['tax']);
		$tax_class_id = (int)($options['tax_class_id'] ?? 0);

		$calculator = null;

		if ($customer_group_id !== null) {
			$calculator = new \ProductPricingCalculator($this->registry, ['customer_group_id' => (int)$customer_group_id]);
		}

		$options_data = [];

		foreach ($configurable->getConfigurableOptions($product_id) as $option) {
			$values = [];

			foreach ((array)($option['values'] ?? []) as $value) {
				$values[] = [
					'option_value_id' => (int)$value['option_value_id'],
					'name'            => (string)($value['name'] ?? ''),
				];
			}

			$options_data[] = [
				'option_id' => (int)$option['option_id'],
				'name'      => (string)($option['name'] ?? ''),
				'type'      => (string)($option['type'] ?? ''),
				'values'    => $values,
			];
		}

		$variants = [];

		foreach ($configurable->getVariants($product_id) as $variant) {
			$values = [];

			foreach ((array)($variant['values'] ?? []) as $value) {
				$values[] = [
					'option_id'       => (int)$value['option_id'],
					'option_value_id' => (int)$value['option_value_id'],
					'value_name'      => (string)($value['name'] ?? ''),
				];
			}

			$price = $variant['price'];
			$special = null;
			$base_price = null;

			if ($calculator !== null) {
				$pricing = $calculator->calculate($product_id, (int)$variant['variant_id'], 1);

				$price = $pricing['price'];
				$base_price = $pricing['base_price'];
				$special = $pricing['special'];
			}

			$row = [
				'variant_id'      => (int)$variant['variant_id'],
				'product_id'      => (int)$variant['product_id'],
				'sku'             => (string)$variant['sku'],
				'model'           => (string)$variant['model'],
				'upc'             => (string)$variant['upc'],
				'ean'             => (string)$variant['ean'],
				'jan'             => (string)$variant['jan'],
				'isbn'            => (string)$variant['isbn'],
				'mpn'             => (string)$variant['mpn'],
				'price'           => $this->formatMoney($price, $currency_id, $tax_class_id, $normalize, $tax),
				'quantity'        => Serializer::quantity($variant['quantity']),
				'subtract'        => (int)$variant['subtract'],
				'weight'          => Serializer::quantity($variant['weight']),
				'weight_class_id' => (int)$variant['weight_class_id'],
				'length'          => Serializer::quantity($variant['length']),
				'width'           => Serializer::quantity($variant['width']),
				'height'          => Serializer::quantity($variant['height']),
				'image'           => (string)$variant['image'],
				'sort_order'      => (int)$variant['sort_order'],
				'status'          => (int)$variant['status'],
				'is_default'      => $default_variant_id === (int)$variant['variant_id'],
				'values'          => $values,
			];

			if ($calculator !== null) {
				$row['base_price'] = $this->formatMoney($base_price, $currency_id, $tax_class_id, $normalize, $tax);
				$row['special'] = $special !== null ? $this->formatMoney($special, $currency_id, $tax_class_id, $normalize, $tax) : null;
			}

			$variants[] = $row;
		}

		$price_range = $customer_group_id !== null
			? $configurable->getAggregatedPriceRange($product_id, (int)$customer_group_id)
			: $configurable->getAggregatedPriceRange($product_id);

		$stock = $configurable->getAggregatedStock($product_id);

		return [
			'product_id'         => $product_id,
			'currency_id'        => $currency_id,
			'is_configurable'    => $configurable->isConfigurable($product_id),
			'default_variant_id' => $default_variant_id,
			'options'            => $options_data,
			'aggregated'         => [
				'price_min'         => $this->formatMoney($price_range['min'] ?? 0, $currency_id, $tax_class_id, $normalize, $tax),
				'price_max'         => $this->formatMoney($price_range['max'] ?? 0, $currency_id, $tax_class_id, $normalize, $tax),
				'total_stock'       => Serializer::quantity($stock['total_stock'] ?? 0),
				'variants_in_stock' => (int)($stock['variants_in_stock'] ?? 0),
				'total_variants'    => (int)($stock['total_variants'] ?? 0),
			],
			'variants'           => $variants,
		];
	}

	/**
	 * Formats a stored amount, optionally normalising it to the store base
	 * currency and applying tax (storefront pricing context).
	 *
	 * @param mixed $amount
	 */
	private function formatMoney($amount, int $currency_id, int $tax_class_id, bool $normalize, bool $tax): string {
		$value = (float)$amount;

		if ($normalize) {
			$value = (float)$this->registry->get('currency')->convertProductPrice($value, $currency_id);
		}

		if ($tax && $this->registry->get('config')->get('config_tax')) {
			$value = (float)$this->registry->get('tax')->calculate($value, $tax_class_id, true);
		}

		return Serializer::money($value);
	}

	/**
	 * oc_product.currency_id (0 => store default) for the product.
	 */
	private function productCurrencyId(int $product_id): int {
		$db = $this->registry->get('db');

		$query = $db->query("SELECT `currency_id` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$product_id . "'");

		return $query->num_rows ? (int)$query->row['currency_id'] : 0;
	}
}
