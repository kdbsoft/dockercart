<?php
/**
 * DockerCart customer (storefront) REST API — effective access resolver.
 *
 * Access is granted in two layers:
 *   1. the customer group (oc_customer_group.api_status / api_scopes);
 *   2. a per-customer override (oc_customer.api_status / api_scopes) that wins:
 *      -1 = inherit the group, 0 = force off, 1 = force on. When forced on, the
 *      customer's own scope list replaces the group's (NULL = inherit the group
 *      scopes).
 *
 * A key may additionally be narrowed: its effective scopes are the intersection
 * of its stored scopes with the customer's current effective scopes, so a key
 * can never keep a grant that was revoked later.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CustomerAccess {
	/**
	 * Resolves the effective access of a customer.
	 *
	 * @return array{enabled: bool, scopes: string[], source: string, customer_group_id: int, api_status: int}
	 */
	public static function resolve($db, int $customer_id): array {
		$disabled = [
			'enabled'           => false,
			'scopes'            => [],
			'source'            => 'none',
			'customer_group_id' => 0,
			'api_status'        => -1,
		];

		if ($customer_id <= 0) {
			return $disabled;
		}

		$query = $db->query("SELECT `customer_id`, `customer_group_id`, `status`, `api_status`, `api_scopes` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . $customer_id . "'");

		if (!$query->num_rows) {
			return $disabled;
		}

		$customer = $query->row;
		$api_status = (int)$customer['api_status'];
		$customer_group_id = (int)$customer['customer_group_id'];

		$group = self::group($db, $customer_group_id);

		// Per-customer override wins over the group.
		if ($api_status === 0) {
			return [
				'enabled'           => false,
				'scopes'            => [],
				'source'            => 'customer',
				'customer_group_id' => $customer_group_id,
				'api_status'        => 0,
			];
		}

		if ($api_status === 1) {
			$scopes = $customer['api_scopes'] === null
				? $group['scopes']
				: CustomerScopes::decode($customer['api_scopes']);

			return [
				'enabled'           => true,
				'scopes'            => $scopes,
				'source'            => 'customer',
				'customer_group_id' => $customer_group_id,
				'api_status'        => 1,
			];
		}

		// api_status = -1: inherit the group setting entirely.
		return [
			'enabled'           => $group['status'] === 1,
			'scopes'            => $group['scopes'],
			'source'            => 'group',
			'customer_group_id' => $customer_group_id,
			'api_status'        => -1,
		];
	}

	/**
	 * Whether the customer currently has API access enabled (any scope).
	 */
	public static function isEnabledForCustomer($db, int $customer_id): bool {
		return self::resolve($db, $customer_id)['enabled'];
	}

	/**
	 * Scopes a specific key may use: its own narrowing ∩ the customer's current
	 * effective scopes. An empty/NULL key scope list inherits the full set.
	 *
	 * @param array{scopes: string[]} $access
	 * @return string[]
	 */
	public static function effectiveKeyScopes(array $key, array $access): array {
		$customer_scopes = CustomerScopes::sanitize($access['scopes'] ?? []);

		$key_scopes = CustomerScopes::decode($key['scopes'] ?? null);

		if (!$key_scopes) {
			return $customer_scopes;
		}

		return array_values(array_intersect($customer_scopes, $key_scopes));
	}

	/**
	 * Group access record.
	 *
	 * @return array{status: int, scopes: string[]}
	 */
	private static function group($db, int $customer_group_id): array {
		if ($customer_group_id <= 0) {
			return ['status' => 0, 'scopes' => []];
		}

		$query = $db->query("SELECT `api_status`, `api_scopes` FROM `" . DB_PREFIX . "customer_group` WHERE `customer_group_id` = '" . $customer_group_id . "'");

		if (!$query->num_rows) {
			return ['status' => 0, 'scopes' => []];
		}

		return [
			'status' => (int)$query->row['api_status'],
			'scopes' => CustomerScopes::decode($query->row['api_scopes']),
		];
	}
}
