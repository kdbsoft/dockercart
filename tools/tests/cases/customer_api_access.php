<?php
/**
 * Customer API effective access resolution.
 *
 * The customer override wins over the group; a key can only narrow the result.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\CustomerAccess;
use Dockercart\Api\CustomerScopes;

/**
 * FakeDb with a queued customer row and group row.
 */
function customer_api_access_db(array $customer, array $group = []): FakeDb {
	$db = new FakeDb();

	$db->pushResult([$customer]);

	if ($group) {
		$db->pushResult([$group]);
	}

	return $db;
}

return [
	'customer api: a customer override of "off" wins over an enabled group' => static function (): void {
		$db = customer_api_access_db(
			['customer_id' => 7, 'customer_group_id' => 2, 'status' => 1, 'api_status' => 0, 'api_scopes' => null],
			['api_status' => 1, 'api_scopes' => '["orders:read","returns:read"]']
		);

		$access = CustomerAccess::resolve($db, 7);

		Assert::false($access['enabled']);
		Assert::same([], $access['scopes']);
		Assert::same('customer', $access['source']);
	},

	'customer api: a customer override of "on" inherits the group scopes when it has none' => static function (): void {
		$db = customer_api_access_db(
			['customer_id' => 7, 'customer_group_id' => 2, 'status' => 1, 'api_status' => 1, 'api_scopes' => null],
			['api_status' => 0, 'api_scopes' => '["orders:read","returns:read"]']
		);

		$access = CustomerAccess::resolve($db, 7);

		Assert::true($access['enabled']);
		Assert::same(['orders:read', 'returns:read'], $access['scopes']);
		Assert::same('customer', $access['source']);
	},

	'customer api: a customer override of "on" replaces the group scopes' => static function (): void {
		$db = customer_api_access_db(
			['customer_id' => 7, 'customer_group_id' => 2, 'status' => 1, 'api_status' => 1, 'api_scopes' => '["returns:write"]'],
			['api_status' => 1, 'api_scopes' => '["orders:read","returns:read","returns:write"]']
		);

		$access = CustomerAccess::resolve($db, 7);

		Assert::true($access['enabled']);
		Assert::same(['returns:write'], $access['scopes']);
	},

	'customer api: inherit takes the group setting' => static function (): void {
		$db = customer_api_access_db(
			['customer_id' => 7, 'customer_group_id' => 2, 'status' => 1, 'api_status' => -1, 'api_scopes' => '["returns:write"]'],
			['api_status' => 1, 'api_scopes' => '["orders:read"]']
		);

		$access = CustomerAccess::resolve($db, 7);

		Assert::true($access['enabled']);
		Assert::same(['orders:read'], $access['scopes']);
		Assert::same('group', $access['source']);
	},

	'customer api: inherit of a disabled group is disabled' => static function (): void {
		$db = customer_api_access_db(
			['customer_id' => 7, 'customer_group_id' => 2, 'status' => 1, 'api_status' => -1, 'api_scopes' => null],
			['api_status' => 0, 'api_scopes' => null]
		);

		$access = CustomerAccess::resolve($db, 7);

		Assert::false($access['enabled']);
		Assert::same([], $access['scopes']);
	},

	'customer api: an unknown customer is disabled' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([]);

		Assert::false(CustomerAccess::resolve($db, 7)['enabled']);
		Assert::false(CustomerAccess::isEnabledForCustomer($db, 7));
	},

	'customer api: a key can only narrow the customer effective scopes' => static function (): void {
		$access = ['scopes' => ['orders:read', 'returns:read', 'returns:write']];

		// Empty/NULL key scopes inherit the full effective set.
		Assert::same($access['scopes'], CustomerAccess::effectiveKeyScopes(['scopes' => null], $access));
		Assert::same($access['scopes'], CustomerAccess::effectiveKeyScopes(['scopes' => ''], $access));

		// A subset narrows.
		Assert::same(['orders:read'], CustomerAccess::effectiveKeyScopes(['scopes' => '["orders:read"]'], $access));

		// A scope the customer no longer has is dropped (stale key).
		Assert::same(
			['returns:read'],
			CustomerAccess::effectiveKeyScopes(['scopes' => '["returns:read","returns:write"]'], ['scopes' => ['returns:read']])
		);
	},

	'customer api: a revoked scope is reflected in the key' => static function (): void {
		$scopes = CustomerAccess::effectiveKeyScopes(
			['scopes' => '["orders:read","returns:read"]'],
			['scopes' => []]
		);

		Assert::same([], $scopes, 'a key must never keep a grant the customer lost');
	},
];
