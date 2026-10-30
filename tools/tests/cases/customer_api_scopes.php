<?php
/**
 * Customer API scope catalogue.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\CustomerScopes;

return [
	'customer api: the scope catalogue is stable' => static function (): void {
		Assert::same(
			['products:read', 'orders:read', 'returns:read', 'returns:write', 'checkout:write'],
			CustomerScopes::all(),
			'the customer scope catalogue changed — update StoreRoutes and the OpenAPI document'
		);

		Assert::true(CustomerScopes::isValid('products:read'));
		Assert::true(CustomerScopes::isValid('orders:read'));
		Assert::true(CustomerScopes::isValid('checkout:write'));
		Assert::false(CustomerScopes::isValid('orders:write'));
		Assert::false(CustomerScopes::isValid(''));
	},

	'customer api: sanitize keeps only known scopes in canonical order' => static function (): void {
		Assert::same(
			['orders:read', 'returns:read'],
			CustomerScopes::sanitize(['returns:read', 'orders:read', 'returns:read', 'nope'])
		);

		Assert::same(
			['checkout:write'],
			CustomerScopes::sanitize(['checkout:write', 'checkout:read']),
			'checkout:write is the only checkout scope'
		);

		Assert::same([], CustomerScopes::sanitize('orders:read'));
		Assert::same([], CustomerScopes::sanitize(null));
	},

	'customer api: encode/decode round-trips and empty means inherit' => static function (): void {
		Assert::same(null, CustomerScopes::encode([]));
		Assert::same(null, CustomerScopes::encode(['unknown']));

		$encoded = CustomerScopes::encode(['returns:write', 'orders:read']);

		Assert::same('["orders:read","returns:write"]', $encoded);
		Assert::same(['orders:read', 'returns:write'], CustomerScopes::decode($encoded));

		Assert::same([], CustomerScopes::decode(null));
		Assert::same([], CustomerScopes::decode(''));
		Assert::same([], CustomerScopes::decode('{not json'));
		Assert::same(['orders:read'], CustomerScopes::decode('["orders:read","bogus"]'));
	},

	'customer api: every scope has a language label' => static function (): void {
		foreach (CustomerScopes::all() as $scope) {
			Assert::true(isset(CustomerScopes::labels()[$scope]), 'missing label for ' . $scope);
			Assert::true(CustomerScopes::labels()[$scope] !== '', 'empty label for ' . $scope);
		}
	},
];
