<?php
/**
 * DockerCart customer (storefront) REST API — checkout.
 *
 *   POST   /v1/checkout                          open a checkout session
 *   GET    /v1/checkout                          the customer's current open session (404 when none)
 *   GET    /v1/checkout/{checkout_id}            full state
 *   PATCH  /v1/checkout/{checkout_id}            addresses / methods / comment / terms
 *   DELETE /v1/checkout/{checkout_id}            cancel (releases the stock hold)
 *   GET    /v1/checkout/{checkout_id}/shipping-methods
 *   GET    /v1/checkout/{checkout_id}/payment-methods
 *   POST   /v1/checkout/{checkout_id}/confirm    201 + the created order
 *   POST   /v1/checkout/{checkout_id}/payment    start (or replay) the payment
 *
 * The session lives in oc_api_checkout; see Dockercart\Api\StoreCheckout for the
 * state machine and the order write path, and Dockercart\Api\StorePayment for the
 * payment initialisation that runs the storefront payment extension.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\StoreCheckout;
use Dockercart\Api\StorePayment;

class ControllerStoreApiCheckout extends \Dockercart\Api\Controller {
	public function index() {
		$checkout = $this->storeCheckout();

		$row = $checkout->latest();

		if (!$row) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$this->respond($checkout->state($row));
	}

	public function create() {
		$body = $this->body();

		$this->rejectFields(['customer_id', 'shipping_method', 'payment_method', 'shipping_title', 'payment_title', 'total', 'currency_code', 'currency_value'], $body);

		$checkout = $this->storeCheckout();

		$state = $checkout->open($body);

		$this->context()->etag = $this->etagFor($state);

		$this->respondCreated($state, '/store-api/v1/checkout/' . (int)$state['checkout_id']);
	}

	public function get() {
		$checkout = $this->storeCheckout();

		$row = $checkout->find($this->param('checkout_id'));

		$state = $checkout->state($row);

		$this->respondResource($state);
	}

	public function update() {
		$body = $this->body();

		$this->rejectFields(['customer_id', 'shipping_method', 'payment_method', 'shipping_title', 'payment_title', 'total', 'currency_code', 'currency_value'], $body);

		$checkout = $this->storeCheckout();

		$row = $checkout->find($this->param('checkout_id'));

		$state = $checkout->update((int)$row['api_checkout_id'], $body);

		$this->respondResource($state);
	}

	public function delete() {
		$this->storeCheckout()->cancel($this->param('checkout_id'));

		$this->respondNoContent();
	}

	public function shippingMethods() {
		$this->respond($this->storeCheckout()->shippingMethods($this->param('checkout_id')));
	}

	public function paymentMethods() {
		$this->respond($this->storeCheckout()->paymentMethods($this->param('checkout_id')));
	}

	/**
	 * Places the order. 201 + Location: /store-api/v1/orders/{order_id}.
	 */
	public function confirm() {
		$body = $this->body();

		$this->rejectFields(['customer_id', 'shipping_method', 'payment_method', 'shipping_title', 'payment_title', 'total', 'currency_code', 'currency_value'], $body);

		$order = $this->storeCheckout()->confirm($this->param('checkout_id'), $body);

		$order_id = (int)$order['order_id'];

		$this->context()->etag = $this->etagFor($order);

		$this->respondCreated($order, '/store-api/v1/orders/' . $order_id);
	}

	/**
	 * Starts the payment of the confirmed order through the chosen payment
	 * extension and answers what the client has to do: open `redirect_url`
	 * (`kind` external/internal), render `html`, or show `instructions`.
	 *
	 * The answer is stored on the checkout, so repeating the call replays it
	 * (`refresh=1` re-runs the module while the order has not moved on).
	 */
	public function payment() {
		$body = $this->body();

		$refresh = filter_var($this->query('refresh', false), FILTER_VALIDATE_BOOLEAN);

		$payment = new StorePayment($this->registry, $this->customerPrincipal()->getCustomerId());

		$this->respond($payment->start($this->param('checkout_id'), $body, $refresh));
	}

	/**
	 * Fields the server owns: the client must never be able to name a customer,
	 * a price or an order total (same policy as POST /v1/returns).
	 */
	private function rejectFields(array $fields, array $body): void {
		$errors = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $body)) {
				$errors[$field] = ['is not accepted by the API; it is taken from the cart and the catalogue'];
			}
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}
	}

	private function storeCheckout(): StoreCheckout {
		return new StoreCheckout($this->registry, $this->customerPrincipal()->getCustomerId());
	}
}
