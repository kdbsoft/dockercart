<?php
/**
 * DockerCart customer (storefront) REST API — address book.
 *
 *   GET    /store-api/v1/addresses                list (oldest first, `is_default` flags the default)
 *   POST   /store-api/v1/addresses                create
 *   GET    /store-api/v1/addresses/{address_id}   single
 *   PUT    /store-api/v1/addresses/{address_id}   update (omitted fields keep their value)
 *   DELETE /store-api/v1/addresses/{address_id}   delete
 *
 * A saved address can be referenced by id from a checkout session
 * (`{"payment_address": {"address_id": 3}}`), so a client can offer the
 * customer's address book instead of a form.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\StoreAddress;

class ControllerStoreApiAddresses extends \Dockercart\Api\Controller {
	public function index() {
		$this->respond($this->storeAddress()->all());
	}

	public function get() {
		$this->respondResource($this->storeAddress()->get($this->param('address_id')));
	}

	public function add() {
		$address = $this->storeAddress()->create($this->body());

		$this->context()->etag = $this->etagFor($address);

		$this->respondCreated($address, '/store-api/v1/addresses/' . (int)$address['address_id']);
	}

	public function edit() {
		$checkout = $this->storeAddress();

		$address_id = $this->param('address_id');

		$this->assertPreconditions($checkout->get($address_id));

		$address = $checkout->update($address_id, $this->body());

		$this->respondResource($address);
	}

	public function delete() {
		$checkout = $this->storeAddress();

		$address_id = $this->param('address_id');

		$this->assertPreconditions($checkout->get($address_id));

		$checkout->delete($address_id);

		$this->respondNoContent();
	}

	private function storeAddress(): StoreAddress {
		return new StoreAddress($this->registry, $this->customerPrincipal()->getCustomerId());
	}
}
