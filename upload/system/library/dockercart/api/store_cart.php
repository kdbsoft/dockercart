<?php
/**
 * DockerCart customer (storefront) REST API — cart.
 *
 * The API cart *is* the customer's cart: Cart\Cart keeps lines in oc_cart keyed
 * by (customer_id, session_id) and the store API starts every cart request with
 * the customer's own scope id (Dockercart\Api\CartScope), so adding an item in
 * an app shows up in the browser cart and vice versa.
 *
 * Line validation mirrors upload/catalog/controller/checkout/cart.php::add()
 * (visibility, call-for-price, configurable variant, stock, minimum quantity and
 * the per-type option contract) and the response shape mirrors the storefront
 * checkout line (price, total, tax, options, warehouse estimate is added by the
 * checkout representation once a shipping choice exists).
 *
 * Coupon, gift voucher and reward points are applied the same way the storefront
 * does it — they live in the session of the cart scope, because that is where
 * the storefront total extensions read them from.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreCart {
	private \Registry $registry;

	private ?StoreTotals $totals = null;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
	}

	/* ─────────────────────────────── state ─────────────────────────────── */

	/**
	 * The whole cart: lines, grand total, applied discounts and warnings.
	 */
	public function state(): array {
		$products = $this->lines();

		$totals = $this->totals();

		$lines = [];
		$units = 0.0;
		$warnings = [];

		$quantity_per_product = [];

		foreach ($products as $product) {
			$product_id = (int)$product['product_id'];

			$quantity_per_product[$product_id] = ($quantity_per_product[$product_id] ?? 0) + (float)$product['quantity'];
		}

		foreach ($products as $product) {
			$units += (float)$product['quantity'];

			$product_id = (int)$product['product_id'];
			$minimum = (float)($product['minimum'] ?? 1);

			if ($minimum > 0 && $quantity_per_product[$product_id] < $minimum) {
				$warnings[] = [
					'code'       => 'minimum_quantity',
					'cart_id'    => (int)$product['cart_id'],
					'product_id' => $product_id,
					'name'       => Serializer::decodeText($product['name'] ?? ''),
					'minimum'    => Serializer::quantity($minimum),
					'quantity'   => Serializer::quantity($quantity_per_product[$product_id]),
				];
			}

			if (empty($product['stock'])) {
				$warnings[] = [
					'code'       => 'out_of_stock',
					'cart_id'    => (int)$product['cart_id'],
					'product_id' => $product_id,
					'name'       => Serializer::decodeText($product['name'] ?? ''),
				];
			}

			$lines[] = $this->line($product);
		}

		$raw = $totals->raw();

		return [
			'cart'      => $lines,
			'items'     => count($lines),
			'units'     => Serializer::quantity($units),
			'weight'    => round((float)$this->cart()->getWeight(), 4),
			'totals'    => $totals->lines(),
			'total'     => Serializer::money($raw['total']),
			'total_text' => $totals->format($raw['total']),
			'currency_code' => (string)$this->config()->get('config_currency'),
			'has_shipping'  => (bool)$this->cart()->hasShipping(),
			'has_stock'     => (bool)$this->cart()->hasStock(),
			'has_download'  => (bool)$this->cart()->hasDownload(),
			'applied'   => $this->applied(),
			'warnings'  => $warnings,
		];
	}

	/**
	 * Raw cart lines (Cart\Cart::getProducts()).
	 */
	public function lines(): array {
		return (array)$this->cart()->getProducts();
	}

	public function isEmpty(): bool {
		return !$this->cart()->hasProducts() && empty($this->session()->data['vouchers']);
	}

	public function itemsCount(): int {
		return count($this->lines());
	}

	/* ──────────────────────────── cart writes ──────────────────────────── */

	/**
	 * Adds a line (or increases the quantity of an identical one).
	 *
	 * @param array $input product_id, quantity?, variant_id?, options?
	 * @return array the resulting cart state
	 */
	public function addItem(array $input): array {
		$product_id = (int)($input['product_id'] ?? 0);
		$variant_id = (int)($input['variant_id'] ?? 0);
		$options = $input['options'] ?? [];

		if (!is_array($options)) {
			throw new HttpException(Error::VALIDATION_FAILED, ['options' => ['must be an object']]);
		}

		$product = $this->productRow($product_id);

		$minimum = (float)$product['minimum'] > 0 ? (float)$product['minimum'] : 1.0;
		$step = (float)($product['quantity_step'] ?? 0) > 0 ? (float)($product['quantity_step'] ?? 0) : 1.0;
		$preorder = !empty($product['preorder']);
		$errors = [];

		$quantity = isset($input['quantity']) ? (float)$input['quantity'] : $minimum;

		if ($quantity <= 0) {
			$errors['quantity'] = ['must be greater than 0'];
		} elseif ($quantity < $minimum) {
			$errors['quantity'] = ['must be at least ' . Serializer::quantity($minimum)];
		} elseif ($quantity != $minimum) {
			// Quantities above the minimum must follow the configured step
			// (the storefront's product page enforces the same rule).
			$offset = ($quantity - $minimum) / $step;

			if (abs($offset - round($offset)) > 0.0001) {
				$errors['quantity'] = ['must be ' . Serializer::quantity($minimum) . ' + a multiple of ' . Serializer::quantity($step)];
			}
		}

		if (!empty($product['call_for_price'])
			|| ($this->config()->get('dockercart_theme_call_for_price_status') && (float)$product['price'] <= 0)) {
			$errors['product_id'] = ['this product is not sold with an online price (call for price)'];
		}

		$pc = new \ProductConfigurable($this->registry);

		if ($pc->isConfigurable($product_id)) {
			if ($variant_id <= 0) {
				$errors['variant_id'] = ['required for a configurable product'];
			} else {
				$variant = $pc->getVariant($variant_id);

				if (!$variant || empty($variant['status']) || (int)$variant['product_id'] !== $product_id) {
					$errors['variant_id'] = ['not a variant of this product'];
				} elseif ((float)$variant['quantity'] <= 0 && !$preorder && !$this->config()->get('config_stock_checkout')) {
					$errors['variant_id'] = ['this variant is out of stock'];
				}
			}
		} elseif ($variant_id > 0) {
			$errors['variant_id'] = ['the product has no variants'];
		} elseif ((float)$product['quantity'] <= 0 && !$preorder && !$this->config()->get('config_stock_checkout')) {
			$errors['product_id'] = ['out of stock'];
		}

		$option = $this->normalizeOptions($product_id, $options, $variant_id, $errors);

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}

		$this->cart()->add($product_id, $quantity, $option);

		$this->forgetMethods();

		return $this->state();
	}

	public function updateItem(int $cart_id, float $quantity): array {
		$line = $this->findLine($cart_id);

		if ($quantity <= 0) {
			$this->removeItem($cart_id);

			return $this->state();
		}

		$minimum = (float)($line['minimum'] ?? 1) > 0 ? (float)$line['minimum'] : 1.0;
		$step = (float)($line['quantity_step'] ?? 0) > 0 ? (float)($line['quantity_step'] ?? 0) : 1.0;

		if ($quantity < $minimum) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'quantity' => ['must be at least ' . Serializer::quantity($minimum)],
			]);
		}

		// Quantities above the minimum must follow the configured step, exactly like
		// the storefront cart's edit action and StoreCart::addItem().
		$offset = ($quantity - $minimum) / $step;

		if (abs($offset - round($offset)) > 0.0001) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'quantity' => ['must be ' . Serializer::quantity($minimum) . ' + a multiple of ' . Serializer::quantity($step)],
			]);
		}

		$this->cart()->update($cart_id, $quantity);

		$this->forgetMethods();

		return $this->state();
	}

	public function removeItem(int $cart_id): array {
		$this->findLine($cart_id);

		$this->cart()->remove($cart_id);

		$this->forgetMethods();

		return $this->state();
	}

	public function clear(): void {
		$this->cart()->clear();

		$session = $this->session();

		foreach (['coupon', 'voucher', 'reward', 'vouchers', 'shipping_method', 'shipping_methods', 'payment_method', 'payment_methods', 'comment'] as $key) {
			unset($session->data[$key]);
		}

		$reservation = new \DockercartStockReservation($this->registry);

		if ($reservation->isEnabled()) {
			$reservation->releaseSession();
		}
	}

	/* ─────────────────────────────── discounts ─────────────────────────────── */

	public function applyCoupon(string $code): array {
		$model = CatalogModels::load($this->registry, 'extension/total/coupon');

		if (!$model->getCoupon($code)) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'coupon' => ['the coupon is invalid, expired or reached its usage limit'],
			]);
		}

		$this->session()->data['coupon'] = $code;

		return $this->state();
	}

	public function removeCoupon(): array {
		unset($this->session()->data['coupon']);

		return $this->state();
	}

	public function applyVoucher(string $code): array {
		$model = CatalogModels::load($this->registry, 'extension/total/voucher');

		if (!$model->getVoucher($code)) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'voucher' => ['the gift voucher is invalid, expired or already used'],
			]);
		}

		$this->session()->data['voucher'] = $code;

		return $this->state();
	}

	public function removeVoucher(): array {
		unset($this->session()->data['voucher']);

		return $this->state();
	}

	public function applyReward(int $points): array {
		if ($points <= 0) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'reward' => ['must be greater than 0'],
			]);
		}

		$available = (int)$this->customer()->getRewardPoints();
		$earnable = 0;

		foreach ($this->lines() as $product) {
			$earnable += (int)($product['points'] ?? 0);
		}

		if ($points > $available) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'reward' => ['must not exceed the available balance (' . $available . ')'],
			]);
		}

		if ($points > $earnable) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'reward' => ['must not exceed the points the cart can spend (' . $earnable . ')'],
			]);
		}

		$this->session()->data['reward'] = $points;

		return $this->state();
	}

	public function removeReward(): array {
		unset($this->session()->data['reward']);

		return $this->state();
	}

	/**
	 * Coupon / voucher / reward points currently applied to the cart.
	 */
	public function applied(): array {
		$session = $this->session();

		return [
			'coupon'  => isset($session->data['coupon']) ? (string)$session->data['coupon'] : null,
			'voucher' => isset($session->data['voucher']) ? (string)$session->data['voucher'] : null,
			'reward'  => isset($session->data['reward']) ? (int)$session->data['reward'] : 0,
			'vouchers' => isset($session->data['vouchers']) ? count((array)$session->data['vouchers']) : 0,
		];
	}

	/* ─────────────────────────────── helpers ─────────────────────────────── */

	public function totals(): StoreTotals {
		if ($this->totals === null) {
			$this->totals = new StoreTotals($this->registry);
		}

		return $this->totals;
	}

	/**
	 * One cart line in the API shape.
	 */
	public function line(array $product): array {
		$price = (float)$product['price'];
		$quantity = (float)$product['quantity'];
		$tax_class_id = (int)$product['tax_class_id'];

		$options = [];

		foreach ((array)$product['option'] as $option) {
			$options[] = [
				'product_option_id'       => (int)($option['product_option_id'] ?? 0),
				'product_option_value_id' => (int)($option['product_option_value_id'] ?? 0),
				'option_id'               => (int)($option['option_id'] ?? 0),
				'option_value_id'         => (int)($option['option_value_id'] ?? 0),
				'name'                    => (string)($option['name'] ?? ''),
				'value'                   => (string)($option['value'] ?? ''),
				'type'                    => (string)($option['type'] ?? ''),
			];
		}

		return [
			'cart_id'        => (int)$product['cart_id'],
			'product_id'     => (int)$product['product_id'],
			'variant_id'     => (int)($product['variant_id'] ?? 0),
			'variant_sku'    => (string)($product['variant_sku'] ?? ''),
			'name'           => Serializer::decodeText($product['name'] ?? ''),
			'model'          => (string)($product['model'] ?? ''),
			'image'          => (string)($product['image'] ?? ''),
			'thumb_url'      => $this->thumbUrl((string)($product['image'] ?? '')),
			'quantity'       => Serializer::quantity($quantity),
			'minimum'        => Serializer::quantity($product['minimum'] ?? 1),
			'quantity_step'  => Serializer::quantity($product['quantity_step'] ?? 1),
			'price'          => $this->displayPrice($price, $tax_class_id),
			'price_net'      => Serializer::money($price),
			'total'          => $this->displayPrice($price * $quantity, $tax_class_id),
			'total_net'      => Serializer::money($price * $quantity),
			'tax'            => Serializer::money($this->tax()->getTax($price, $tax_class_id)),
			'tax_class_id'   => $tax_class_id,
			'weight'         => round((float)($product['weight'] ?? 0), 4),
			'reward_points'  => (int)($product['reward'] ?? 0),
			'shipping'       => !empty($product['shipping']),
			'in_stock'       => !empty($product['stock']),
			'preorder'       => !empty($product['preorder']),
			'options'        => $options,
			'bxgy'           => !empty($product['bxgy_applied']) ? [
				'text'           => (string)($product['bxgy_text'] ?? ''),
				'original_price' => (string)($product['bxgy_original_price'] ?? ''),
			] : null,
		];
	}

	/**
	 * Product row the cart add checks against (storefront visibility rules).
	 */
	public function productRow(int $product_id): array {
		if ($product_id <= 0) {
			throw new HttpException(Error::VALIDATION_FAILED, ['product_id' => ['required']]);
		}

		$db = $this->db();

		$query = $db->query("SELECT p.`product_id`, p.`price`, p.`quantity`, p.`minimum`, p.`quantity_step`, p.`subtract`, p.`preorder`, p.`call_for_price`, p.`shipping`, p.`status` FROM `" . DB_PREFIX . "product` p WHERE p.`product_id` = '" . (int)$product_id . "' AND p.`status` = '1' AND p.`date_available` <= NOW() AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_to_store` p2s WHERE p2s.`product_id` = p.`product_id` AND p2s.`store_id` = '" . (int)$this->config()->get('config_store_id') . "')");

		if (!$query->num_rows) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $query->row + ['product_id' => $product_id];
	}

	/**
	 * Cart line by id, 404 when it is not part of the customer's cart.
	 */
	private function findLine(int $cart_id): array {
		foreach ($this->lines() as $product) {
			if ((int)$product['cart_id'] === $cart_id) {
				return $product;
			}
		}

		throw new HttpException(Error::NOT_FOUND);
	}

	/**
	 * Normalizes the option payload onto the shape Cart\Cart stores:
	 * { product_option_id: product_option_value_id } for value options,
	 * { product_option_id: "text" } for text/date/file options.
	 *
	 * @return array<string, mixed>
	 */
	private function normalizeOptions(int $product_id, array $input, int $variant_id, array &$errors): array {
		$db = $this->db();

		$query = $db->query("SELECT po.`product_option_id`, po.`option_id`, po.`required`, o.`type`, od.`name` FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.`option_id` = o.`option_id`) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.`option_id` = od.`option_id` AND od.`language_id` = '" . (int)$this->config()->get('config_language_id') . "') WHERE po.`product_id` = '" . (int)$product_id . "'");

		$options = [];

		foreach ($query->rows as $row) {
			$options[(int)$row['product_option_id']] = $row;
		}

		$axes = [];

		$axis_query = $db->query("SELECT `option_id` FROM `" . DB_PREFIX . "product_configurable_option` WHERE `product_id` = '" . (int)$product_id . "'");

		foreach ($axis_query->rows as $row) {
			$axes[(int)$row['option_id']] = true;
		}

		$normalized = [];

		foreach ($input as $key => $value) {
			$product_option_id = (int)$key;

			if (!isset($options[$product_option_id])) {
				$errors['options.' . $key] = ['is not an option of this product'];

				continue;
			}

			$option = $options[$product_option_id];

			// A variant pins the axis combination, so axis values are optional.
			if (isset($axes[(int)$option['option_id']]) && $variant_id > 0) {
				continue;
			}

			if (is_array($value)) {
				$errors['options.' . $key] = ['expects a single value'];

				continue;
			}

			$type = (string)$option['type'];

			if (in_array($type, ['select', 'radio', 'color'], true)) {
				$product_option_value_id = (int)$value;

				$value_query = $db->query("SELECT `product_option_value_id` FROM `" . DB_PREFIX . "product_option_value` WHERE `product_option_value_id` = '" . (int)$product_option_value_id . "' AND `product_option_id` = '" . (int)$product_option_id . "'");

				if (!$value_query->num_rows) {
					$errors['options.' . $key] = ['is not a value of this option'];

					continue;
				}

				$normalized[$product_option_id] = $product_option_value_id;

				continue;
			}

			$normalized[$product_option_id] = (string)$value;
		}

		foreach ($options as $product_option_id => $option) {
			// A variant already implies the axis values.
			if (isset($axes[(int)$option['option_id']]) && $variant_id > 0) {
				continue;
			}

			if (empty($option['required'])) {
				continue;
			}

			if (!isset($normalized[$product_option_id]) || $normalized[$product_option_id] === '') {
				$errors['options.' . $product_option_id] = ['option "' . (string)$option['name'] . '" is required'];
			}
		}

		if ($variant_id > 0) {
			$normalized['variant_id'] = $variant_id;
		}

		return $normalized;
	}

	/**
	 * Drop the selected shipping/payment methods: they depend on the cart
	 * contents, so any change invalidates them (the storefront does the same).
	 */
	private function forgetMethods(): void {
		$session = $this->session();

		unset($session->data['shipping_method'], $session->data['shipping_methods'], $session->data['payment_method'], $session->data['payment_methods']);
	}

	private function displayPrice(float $price, int $tax_class_id): string {
		if ($this->config()->get('config_tax')) {
			$price = (float)$this->tax()->calculate($price, $tax_class_id, true);
		}

		return Serializer::money($price);
	}

	private function thumbUrl(string $image): string {
		if ($image === '') {
			return '';
		}

		try {
			$theme = (string)$this->config()->get('config_theme');
			$width = (int)$this->config()->get('theme_' . $theme . '_image_cart_width');
			$height = (int)$this->config()->get('theme_' . $theme . '_image_cart_height');

			if ($width <= 0) {
				$width = 100;
			}

			if ($height <= 0) {
				$height = 100;
			}

			// The panel image model builds catalog URLs (HTTP/HTTPS_CATALOG),
			// so it is safe to reuse here.
			$load = $this->registry->get('load');
			$load->model('tool/image');

			$resized = (string)$this->registry->get('model_tool_image')->resize($image, $width, $height);

			return $resized;
		} catch (\Throwable $exception) {
			return '';
		}
	}

	private function cart(): \Cart\Cart {
		return $this->registry->get('cart');
	}

	private function session() {
		return $this->registry->get('session');
	}

	private function customer() {
		return $this->registry->get('customer');
	}

	private function config() {
		return $this->registry->get('config');
	}

	private function db() {
		return $this->registry->get('db');
	}

	private function tax() {
		return $this->registry->get('tax');
	}
}
