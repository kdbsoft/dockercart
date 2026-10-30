<?php
/**
 * DockerCart customer (storefront) REST API — cart totals.
 *
 * Runs the storefront's own total pipeline (sub_total, shipping, tax, coupon,
 * voucher, reward, credit, handling, low order fee, BXGY, bundle, total) over
 * the API cart, so a checkout placed through the API produces exactly the
 * amount the website would produce for the same cart and addresses.
 *
 * The extension models are the storefront ones (upload/catalog/model/extension/
 * total/*), loaded through Dockercart\Api\CatalogModels. They read the cart,
 * the customer, the tax rates and the applied coupon / voucher / reward points
 * from the registry, which is why the API keeps that state in the same places
 * the storefront uses (Cart\Cart + session data).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreTotals {
	private \Registry $registry;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
	}

	/**
	 * Raw totals exactly as ModelCheckoutOrder::addOrder() expects them plus the
	 * collected taxes and the grand total.
	 *
	 * @return array{totals: array<int, array{code: string, title: string, value: string|float, sort_order: int}>, taxes: array, total: float}
	 */
	public function raw(): array {
		$totals = [];
		$taxes = $this->cart()->getTaxes();
		$total = 0;

		// The extension models take the totals array by reference.
		$total_data = [
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total,
		];

		foreach ($this->enabledCodes() as $code) {
			$model = CatalogModels::load($this->registry, 'extension/total/' . $code);

			$model->getTotal($total_data);
		}

		usort($totals, static function ($left, $right) {
			return (int)($left['sort_order'] ?? 0) <=> (int)($right['sort_order'] ?? 0);
		});

		return [
			'totals' => $totals,
			'taxes'  => (array)$taxes,
			'total'  => (float)$total,
		];
	}

	/**
	 * Presentation shape: one row per total with the amount as a fixed-point
	 * string in the store base currency plus the formatted text.
	 *
	 * @return array<int, array{code: string, title: string, value: string, text: string, sort_order: int}>
	 */
	public function lines(): array {
		$lines = [];

		foreach ($this->raw()['totals'] as $total) {
			$value = (float)($total['value'] ?? 0);

			$lines[] = [
				'code'       => (string)($total['code'] ?? ''),
				'title'      => (string)($total['title'] ?? ''),
				'value'      => Serializer::money($value),
				'text'       => $this->format($value),
				'sort_order' => (int)($total['sort_order'] ?? 0),
			];
		}

		return $lines;
	}

	public function total(): float {
		return $this->raw()['total'];
	}

	/**
	 * Enabled total extensions in their configured sort order.
	 *
	 * Read straight from oc_extension: the admin ModelSettingExtension owns the
	 * same registry key as the catalog one, so the catalog API is not reachable
	 * from here (and the panel model now carries getExtensions() for the models
	 * that load it while they run).
	 *
	 * @return string[]
	 */
	public function enabledCodes(): array {
		$db = $this->registry->get('db');
		$config = $this->registry->get('config');

		$query = $db->query("SELECT `code` FROM `" . DB_PREFIX . "extension` WHERE `type` = 'total'");

		$sort = [];

		foreach ($query->rows as $row) {
			$code = (string)$row['code'];

			if (!$config->get('total_' . $code . '_status')) {
				continue;
			}

			$sort[$code] = (float)$config->get('total_' . $code . '_sort_order');
		}

		asort($sort, SORT_NUMERIC);

		return array_keys($sort);
	}

	/**
	 * A single total row by code (null when the pipeline produced none).
	 */
	public function line(string $code): ?float {
		foreach ($this->raw()['totals'] as $total) {
			if ((string)($total['code'] ?? '') === $code) {
				return (float)($total['value'] ?? 0);
			}
		}

		return null;
	}

	/**
	 * Amount formatted in the store base currency.
	 */
	public function format(float $value): string {
		return (string)$this->currency()->format($value, (string)$this->registry->get('config')->get('config_currency'));
	}

	private function cart(): \Cart\Cart {
		return $this->registry->get('cart');
	}

	private function currency(): \Cart\Currency {
		return $this->registry->get('currency');
	}
}
