<?php
/**
 * DockerCart customer (storefront) REST API — cart.
 *
 *   GET    /v1/cart                        cart state (lines, totals, applied discounts)
 *   DELETE /v1/cart                        empty the cart
 *   POST   /v1/cart/items                  add a line
 *   PATCH  /v1/cart/items/{cart_id}        change the quantity of a line
 *   DELETE /v1/cart/items/{cart_id}        drop a line
 *   POST   /v1/cart/coupon                 apply a coupon        DELETE /v1/cart/coupon
 *   POST   /v1/cart/voucher                apply a gift voucher  DELETE /v1/cart/voucher
 *   POST   /v1/cart/reward                 spend reward points   DELETE /v1/cart/reward
 *   GET    /v1/cart/shipping-methods       quotes for an address
 *   GET    /v1/cart/payment-methods        methods for the cart total
 *
 * Every endpoint answers with the whole cart state, so a client never has to
 * re-read it after a mutation. The cart is the customer's real cart (shared with
 * the website) — see Dockercart\Api\CartScope.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\StoreCart;
use Dockercart\Api\StoreMethods;

class ControllerStoreApiCart extends \Dockercart\Api\Controller {
	public function index() {
		$this->respond($this->storeCart()->state());
	}

	public function clear() {
		$this->storeCart()->clear();

		$this->respond($this->storeCart()->state());
	}

	public function addItem() {
		$body = $this->body();

		$data = $this->validateRequest($body, [
			'product_id' => ['required', 'int', 'min:1'],
			'quantity'   => ['float'],
			'variant_id' => ['int', 'min:0'],
			'options'    => ['array'],
		]);

		if (isset($data['quantity']) && (float)$data['quantity'] <= 0) {
			throw new HttpException(Error::VALIDATION_FAILED, ['quantity' => ['must be greater than 0']]);
		}

		$input = [
			'product_id' => (int)$data['product_id'],
			'variant_id' => (int)($data['variant_id'] ?? 0),
			'options'    => (array)($data['options'] ?? []),
		];

		// Omitted quantity means "the product minimum" (see StoreCart::addItem).
		if (isset($data['quantity'])) {
			$input['quantity'] = (float)$data['quantity'];
		}

		$cart = $this->storeCart()->addItem($input);

		$this->respond($cart, 201);
	}

	public function updateItem() {
		$data = $this->validateRequest($this->body(), [
			'quantity' => ['required', 'float', 'min:0'],
		]);

		$this->respond($this->storeCart()->updateItem($this->param('cart_id'), (float)$data['quantity']));
	}

	public function removeItem() {
		$this->respond($this->storeCart()->removeItem($this->param('cart_id')));
	}

	public function coupon() {
		$data = $this->validateRequest($this->body(), [
			'coupon' => ['required', 'string', 'max:64'],
		]);

		$this->respond($this->storeCart()->applyCoupon(trim((string)$data['coupon'])));
	}

	public function removeCoupon() {
		$this->respond($this->storeCart()->removeCoupon());
	}

	public function voucher() {
		$data = $this->validateRequest($this->body(), [
			'voucher' => ['required', 'string', 'max:64'],
		]);

		$this->respond($this->storeCart()->applyVoucher(trim((string)$data['voucher'])));
	}

	public function removeVoucher() {
		$this->respond($this->storeCart()->removeVoucher());
	}

	public function reward() {
		$data = $this->validateRequest($this->body(), [
			'reward' => ['required', 'int', 'min:1'],
		]);

		$this->respond($this->storeCart()->applyReward((int)$data['reward']));
	}

	public function removeReward() {
		$this->respond($this->storeCart()->removeReward());
	}

	/**
	 * Shipping quotes for an address: either a saved address (?address_id=N) or
	 * a raw country/zone/postcode pair (what a checkout form has before the
	 * customer commits an address).
	 */
	public function shippingMethods() {
		$methods = new StoreMethods($this->registry);

		$this->respond(array_map([$this, 'publicQuote'], $methods->shippingQuotes($this->quoteAddress())));
	}

	public function paymentMethods() {
		$methods = new StoreMethods($this->registry);

		$rows = [];

		foreach ($methods->paymentMethods($this->quoteAddress(), (float)$this->registry->get('cart')->getTotal()) as $method) {
			unset($method['method']);

			$rows[] = $method;
		}

		$this->respond($rows);
	}

	/**
	 * Address used for a standalone quote. Only the fields the shipping/payment
	 * extensions look at (geo zones, postcode based rates) are required.
	 */
	private function quoteAddress(): array {
		$address_id = $this->queryInt('address_id');

		if ($address_id > 0) {
			$principal = $this->customerPrincipal();

			$address = new \Dockercart\Api\StoreAddress($this->registry, $principal->getCustomerId());

			return $address->get($address_id);
		}

		return [
			'firstname'  => $this->queryString('firstname'),
			'lastname'   => $this->queryString('lastname'),
			'company'    => $this->queryString('company'),
			'address_1'  => $this->queryString('address_1'),
			'address_2'  => $this->queryString('address_2'),
			'city'       => $this->queryString('city'),
			'postcode'   => $this->queryString('postcode'),
			'country_id' => $this->queryInt('country_id'),
			'zone_id'    => $this->queryInt('zone_id'),
		];
	}

	private function publicQuote(array $quote): array {
		unset($quote['quote']);

		if (array_key_exists('cost', $quote) && $quote['cost'] !== null) {
			$quote['cost'] = \Dockercart\Api\Serializer::money($quote['cost']);
		}

		return $quote;
	}

	private function storeCart(): StoreCart {
		return new StoreCart($this->registry);
	}
}
