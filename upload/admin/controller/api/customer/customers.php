<?php
/**
 * DockerCart admin REST API — customers (read only).
 *
 *   GET /v1/customers        list (filters + pagination)
 *   GET /v1/customers/{id}   single customer: addresses, aggregates, custom fields
 *
 * The panel already owns every write path (registration, account editing,
 * approvals), so the API stays read-only here. Credentials are never published:
 * password, salt, token, code/code_expire, cart, wishlist, ip and remember_token
 * are dropped from the representation, so a database leak cannot be amplified
 * through the API.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiCustomerCustomers extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('customer/customer');

		$pagination = $this->pagination();

		$filters = [
			'filter_name'       => $this->queryString('name'),
			'filter_email'      => $this->queryString('email'),
			'filter_date_added' => $this->queryString('date_added'),
			'filter_date_start' => $this->queryString('date_start'),
			'filter_date_end'   => $this->queryString('date_end'),
			'start'             => $pagination['start'],
			'limit'             => $pagination['limit'],
		];

		if ($this->queryString('customer_group_id') !== '') {
			$filters['filter_customer_group_id'] = $this->queryInt('customer_group_id');
		}

		if ($this->queryString('newsletter') !== '') {
			$filters['filter_newsletter'] = $this->queryInt('newsletter');
		}

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$sorting = $this->sorting(
			[
				'name'           => 'name',
				'email'          => 'c.email',
				'customer_group' => 'customer_group',
				'status'         => 'c.status',
				'date_added'     => 'c.date_added',
			],
			'name',
			'ASC'
		);

		$filters['sort'] = $sorting['sort'];
		$filters['order'] = $sorting['order'];

		$rows = [];

		foreach ($this->model_customer_customer->getCustomers($filters) as $customer) {
			$rows[] = $this->customerSummary($customer);
		}

		$total = (int)$this->model_customer_customer->getTotalCustomers($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$this->respondResource($this->customerResponse($this->param('customer_id')));
	}

	/**
	 * The single-customer representation used by GET and its ETag.
	 */
	private function customerResponse(int $customer_id): array {
		$customer = $this->requireCustomer($customer_id);

		$stats = $this->model_customer_customer->getCustomerStats($customer_id);

		$response = $this->customerSummary($customer);

		// getCustomer() does not join the group description (only the list query does).
		if ($response['customer_group'] === '') {
			$response['customer_group'] = $this->customerGroupName($response['customer_group_id']);
		}

		return array_merge($response, [
			'language'     => $this->languageMap()->code((int)$customer['language_id']),
			'fax'          => (string)($customer['fax'] ?? ''),
			'custom_field' => json_decode((string)($customer['custom_field'] ?? '[]'), true) ?: [],
			'addresses'    => $this->addressRows($customer),
			'stats'        => [
				'order_count'   => (int)$stats['order_count'],
				'order_total'   => Serializer::money($stats['order_total']),
				'first_order'   => Serializer::datetime($stats['first_order'] ?? ''),
				'last_order'    => Serializer::datetime($stats['last_order'] ?? ''),
				'reward_points' => (int)$this->model_customer_customer->getRewardTotal($customer_id),
				'store_credit'  => Serializer::money($this->model_customer_customer->getTransactionTotal($customer_id)),
				'address_count' => (int)$this->model_customer_customer->getTotalAddressesByCustomerId($customer_id),
			],
		]);
	}

	private function customerSummary(array $customer): array {
		return [
			'customer_id'        => (int)$customer['customer_id'],
			'name'               => (string)($customer['name'] ?? trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''))),
			'firstname'          => (string)$customer['firstname'],
			'lastname'           => (string)$customer['lastname'],
			'email'              => (string)$customer['email'],
			'telephone'          => (string)($customer['telephone'] ?? ''),
			'company'            => (string)($customer['company'] ?? ''),
			'tax_number'         => (string)($customer['tax_number'] ?? ''),
			'customer_group_id'  => (int)$customer['customer_group_id'],
			'customer_group'     => (string)($customer['customer_group'] ?? ''),
			'store_id'           => (int)($customer['store_id'] ?? 0),
			'status'             => (int)$customer['status'],
			'approved'           => (int)($customer['safe'] ?? 0),
			'newsletter'         => (int)($customer['newsletter'] ?? 0),
			'date_added'         => Serializer::datetime($customer['date_added'] ?? ''),
		];
	}

	/**
	 * Addresses with resolved country/zone names, the default one flagged.
	 */
	private function addressRows(array $customer): array {
		$default_address_id = (int)($customer['address_id'] ?? 0);
		$rows = [];

		foreach ($this->model_customer_customer->getAddresses((int)$customer['customer_id']) as $address) {
			$rows[] = [
				'address_id'   => (int)$address['address_id'],
				'firstname'    => (string)$address['firstname'],
				'lastname'     => (string)$address['lastname'],
				'company'      => (string)$address['company'],
				'address_1'    => (string)$address['address_1'],
				'address_2'    => (string)$address['address_2'],
				'city'         => (string)$address['city'],
				'postcode'     => (string)$address['postcode'],
				'country_id'   => (int)$address['country_id'],
				'country'      => (string)$address['country'],
				'zone_id'      => (int)$address['zone_id'],
				'zone'         => (string)$address['zone'],
				'custom_field' => $address['custom_field'] ?? [],
				'is_default'   => (int)$address['address_id'] === $default_address_id,
			];
		}

		return $rows;
	}

	/**
	 * Name of a customer group in the request language ('' when missing).
	 */
	private function customerGroupName(int $customer_group_id): string {
		$this->load->model('customer/customer_group');

		$descriptions = $this->model_customer_customer_group->getCustomerGroupDescriptions($customer_group_id);

		return (string)($descriptions[(int)$this->config->get('config_language_id')]['name'] ?? '');
	}

	private function requireCustomer(int $customer_id): array {
		$this->load->model('customer/customer');

		$customer = $this->model_customer_customer->getCustomer($customer_id);

		if (!$customer) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $customer;
	}
}
