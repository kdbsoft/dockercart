<?php
/**
 * DockerCart customer (storefront) REST API — authenticated principal.
 *
 * Stands in for Cart\Customer inside the store-api application: it is registered
 * in the registry as "customer", so any shared model that reads
 * $this->customer->getId() / isLogged() keeps working. Unlike the panel's
 * session customer, it is built from a bearer token and carries the effective
 * scope list of the request.
 *
 * The api_key_id is kept for the request journal (the customer id alone would
 * not say which key was used).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CustomerPrincipal {
	private int $customer_id;
	private int $customer_group_id;
	private int $api_key_id;
	private array $scopes;
	private string $firstname;
	private string $lastname;
	private string $email;
	private string $telephone;
	private int $address_id;

	private ?\Registry $registry = null;

	/**
	 * @param array    $customer oc_customer row
	 * @param string[] $scopes   effective scopes of the request
	 */
	public function __construct(array $customer, array $scopes, int $api_key_id) {
		$this->customer_id = (int)($customer['customer_id'] ?? 0);
		$this->customer_group_id = (int)($customer['customer_group_id'] ?? 0);
		$this->firstname = (string)($customer['firstname'] ?? '');
		$this->lastname = (string)($customer['lastname'] ?? '');
		$this->email = (string)($customer['email'] ?? '');
		$this->telephone = (string)($customer['telephone'] ?? '');
		$this->address_id = (int)($customer['address_id'] ?? 0);
		$this->scopes = array_values($scopes);
		$this->api_key_id = $api_key_id;
	}

	/**
	 * Mirrors Cart\Customer::isLogged().
	 */
	public function isLogged() {
		return true;
	}

	/**
	 * Mirrors Cart\Customer::getId().
	 */
	public function getId() {
		return $this->customer_id;
	}

	public function getCustomerId(): int {
		return $this->customer_id;
	}

	/**
	 * Mirrors Cart\Customer::getGroupId().
	 */
	public function getGroupId() {
		return $this->customer_group_id;
	}

	/**
	 * Customer group of the principal (pricing context of the request).
	 */
	public function getCustomerGroupId(): int {
		return $this->customer_group_id;
	}

	public function getFirstName() {
		return $this->firstname;
	}

	public function getLastName() {
		return $this->lastname;
	}

	public function getEmail() {
		return $this->email;
	}

	public function getTelephone() {
		return $this->telephone;
	}

	public function getAddressId() {
		return $this->address_id;
	}

	/**
	 * Mirrors Cart\Customer::getRewardPoints() — the total reward points of the
	 * customer. The storefront total/reward extension reads it from here.
	 */
	public function getRewardPoints() {
		$db = $this->registry()->get('db');

		$query = $db->query("SELECT SUM(`points`) AS total FROM `" . DB_PREFIX . "customer_reward` WHERE `customer_id` = '" . $this->customer_id . "'");

		return $query->row['total'];
	}

	/**
	 * Mirrors Cart\Customer::getBalance() — the store credit balance, read by the
	 * storefront total/credit extension.
	 */
	public function getBalance() {
		$db = $this->registry()->get('db');

		$query = $db->query("SELECT SUM(`amount`) AS total FROM `" . DB_PREFIX . "customer_transaction` WHERE `customer_id` = '" . $this->customer_id . "'");

		return $query->row['total'];
	}

	/**
	 * Display name used by the panel's list labels.
	 */
	public function getUserName() {
		return trim($this->firstname . ' ' . $this->lastname);
	}

	/**
	 * @return string[]
	 */
	public function getScopes(): array {
		return $this->scopes;
	}

	/**
	 * Whether the request may use a scope.
	 */
	public function can(string $scope): bool {
		return in_array($scope, $this->scopes, true);
	}

	public function getApiKeyId(): int {
		return $this->api_key_id;
	}

	/**
	 * Registry of the request — set by the startup pre-action so the model
	 * mirrors (reward points, balance) can reach the database.
	 */
	public function setRegistry(\Registry $registry): void {
		$this->registry = $registry;
	}

	private function registry(): \Registry {
		if (!$this->registry instanceof \Registry) {
			throw new \RuntimeException('The customer principal has no registry');
		}

		return $this->registry;
	}
}
