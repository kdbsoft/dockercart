<?php
/**
 * DockerCart customer (storefront) REST API — endpoint table.
 *
 * The customer counterpart of Dockercart\Api\Routes: the single source of truth
 * for the /store-api/v1/... surface. Each entry maps a public path + method to a
 * store_api controller action and the scope it needs (Dockercart\Api\CustomerScopes).
 * Nothing is reachable unless it is listed here, so a new controller action can
 * never accidentally publish itself.
 *
 * The optional 6th column marks an endpoint as public (reachable without a key);
 * only the OpenAPI document uses it.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreRoutes {
	/**
	 * Supported version prefix (first path segment).
	 */
	public const VERSION = 'v1';

	/**
	 * [method, path pattern, controller route, action, required scope, public?]
	 */
	private const TABLE = [
		// --------------------------------------------------------------- products
		['GET', '/v1/products',                       'store_api/products',   'index',    CustomerScopes::PRODUCTS_READ],
		['GET', '/v1/products/{product_id}',          'store_api/products',   'get',      CustomerScopes::PRODUCTS_READ],
		['GET', '/v1/products/{product_id}/variants', 'store_api/products',   'variants', CustomerScopes::PRODUCTS_READ],

		// ------------------------------------------------------------- categories
		['GET', '/v1/categories',                     'store_api/categories', 'index', CustomerScopes::PRODUCTS_READ],
		['GET', '/v1/categories/tree',                'store_api/categories', 'tree',  CustomerScopes::PRODUCTS_READ],
		['GET', '/v1/categories/{category_id}',       'store_api/categories', 'get',   CustomerScopes::PRODUCTS_READ],

		// ----------------------------------------------------------------- orders
		['GET', '/v1/orders',                     'store_api/orders', 'index',   CustomerScopes::ORDERS_READ],
		['GET', '/v1/orders/{order_id}',          'store_api/orders', 'get',     CustomerScopes::ORDERS_READ],
		['GET', '/v1/orders/{order_id}/history',  'store_api/orders', 'history', CustomerScopes::ORDERS_READ],

		// ------------------------------------------------------------------- cart
		['GET',    '/v1/cart',                     'store_api/cart', 'index',        CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/cart',                     'store_api/cart', 'clear',        CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/cart/items',               'store_api/cart', 'addItem',      CustomerScopes::CHECKOUT_WRITE],
		['PATCH',  '/v1/cart/items/{cart_id}',     'store_api/cart', 'updateItem',   CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/cart/items/{cart_id}',     'store_api/cart', 'removeItem',   CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/cart/coupon',              'store_api/cart', 'coupon',       CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/cart/coupon',              'store_api/cart', 'removeCoupon', CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/cart/voucher',             'store_api/cart', 'voucher',      CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/cart/voucher',             'store_api/cart', 'removeVoucher', CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/cart/reward',              'store_api/cart', 'reward',       CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/cart/reward',              'store_api/cart', 'removeReward', CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/cart/shipping-methods',    'store_api/cart', 'shippingMethods', CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/cart/payment-methods',     'store_api/cart', 'paymentMethods',  CustomerScopes::CHECKOUT_WRITE],

		// --------------------------------------------------------------- addresses
		['GET',    '/v1/addresses',                'store_api/addresses', 'index',  CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/addresses',                'store_api/addresses', 'add',    CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/addresses/{address_id}',   'store_api/addresses', 'get',    CustomerScopes::CHECKOUT_WRITE],
		['PUT',    '/v1/addresses/{address_id}',   'store_api/addresses', 'edit',   CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/addresses/{address_id}',   'store_api/addresses', 'delete', CustomerScopes::CHECKOUT_WRITE],

		// ---------------------------------------------------------------- checkout
		['GET',    '/v1/checkout',                              'store_api/checkout', 'index',   CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/checkout',                              'store_api/checkout', 'create',  CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/checkout/{checkout_id}',                'store_api/checkout', 'get',     CustomerScopes::CHECKOUT_WRITE],
		['PATCH',  '/v1/checkout/{checkout_id}',                'store_api/checkout', 'update',  CustomerScopes::CHECKOUT_WRITE],
		['DELETE', '/v1/checkout/{checkout_id}',                'store_api/checkout', 'delete',  CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/checkout/{checkout_id}/shipping-methods', 'store_api/checkout', 'shippingMethods', CustomerScopes::CHECKOUT_WRITE],
		['GET',    '/v1/checkout/{checkout_id}/payment-methods',  'store_api/checkout', 'paymentMethods',  CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/checkout/{checkout_id}/confirm',        'store_api/checkout', 'confirm', CustomerScopes::CHECKOUT_WRITE],
		['POST',   '/v1/checkout/{checkout_id}/payment',        'store_api/checkout', 'payment', CustomerScopes::CHECKOUT_WRITE],

		// ---------------------------------------------------------------- returns
		['GET',  '/v1/returns',                   'store_api/returns', 'index', CustomerScopes::RETURNS_READ],
		['GET',  '/v1/returns/{return_id}',       'store_api/returns', 'get',   CustomerScopes::RETURNS_READ],
		['POST', '/v1/returns',                   'store_api/returns', 'add',   CustomerScopes::RETURNS_WRITE],

		// ------------------------------------------------------------------- meta
		['GET', '/v1/meta/return-reasons',        'store_api/meta', 'returnReasons', CustomerScopes::RETURNS_READ],
		['GET', '/v1/meta/return-actions',        'store_api/meta', 'returnActions', CustomerScopes::RETURNS_READ],
		['GET', '/v1/meta/countries',             'store_api/meta', 'countries',     CustomerScopes::CHECKOUT_WRITE],
		['GET', '/v1/meta/countries/{country_id}/zones', 'store_api/meta', 'zones',   CustomerScopes::CHECKOUT_WRITE],

		// -------------------------------------------------------------- discovery
		['GET', '/v1/openapi.json',               'store_api/openapi', 'index', null, true],
	];

	/**
	 * Matches a public path + HTTP method against the endpoint table.
	 *
	 * @return array{status: string, route?: string, action?: string, params?: array,
	 *               scope?: string|null, allowed?: array}
	 */
	public static function match(string $method, string $path): array {
		$method = strtoupper($method);

		// HEAD is served by the GET implementation (PHP strips the body itself).
		if ($method === 'HEAD') {
			$method = 'GET';
		}

		$segments = self::segments($path);

		if (!$segments) {
			return ['status' => 'not_found', 'allowed' => []];
		}

		// Version handling: /v1/... is explicit, a bare path is treated as v1.
		if (preg_match('/^v\d+$/', (string)$segments[0])) {
			if ($segments[0] !== self::VERSION) {
				return ['status' => 'unsupported_version', 'allowed' => []];
			}

			array_shift($segments);
		}

		$allowed = [];

		foreach (self::TABLE as $entry) {
			[$entry_method, $pattern, $route, $action, $scope] = $entry;

			$public = !empty($entry[5]);

			$params = self::matchPattern($pattern, $segments);

			if ($params === null) {
				continue;
			}

			if ($entry_method !== $method) {
				$allowed[] = $entry_method;

				continue;
			}

			return [
				'status' => 'ok',
				'route'  => $route,
				'action' => $action,
				'params' => $params,
				'scope'  => $scope,
				'public' => $public,
			];
		}

		if ($allowed) {
			return ['status' => 'method_not_allowed', 'allowed' => array_values(array_unique($allowed))];
		}

		return ['status' => 'not_found', 'allowed' => []];
	}

	/**
	 * Returns the endpoint rows (used by the docs page and by tests that
	 * cross-check the OpenAPI document).
	 */
	public static function table(): array {
		$rows = [];

		foreach (self::TABLE as $entry) {
			$rows[] = [
				'method' => $entry[0],
				'path'   => $entry[1],
				'route'  => $entry[2],
				'action' => $entry[3],
				'scope'  => $entry[4],
				'public' => !empty($entry[5]),
			];
		}

		return $rows;
	}

	/**
	 * Normalized path segments of a request path ("/v1/orders/12/" => [v1, orders, 12]).
	 */
	private static function segments(string $path): array {
		$path = (string)(parse_url($path, PHP_URL_PATH) ?: '');
		$path = preg_replace('#/+#', '/', '/' . ltrim($path, '/'));

		return array_values(array_filter(explode('/', (string)$path), static function ($segment) {
			return $segment !== '';
		}));
	}

	/**
	 * @return array|null captured params, or null when the pattern does not match
	 */
	private static function matchPattern(string $pattern, array $segments): ?array {
		$pattern_segments = self::segments($pattern);

		// The version prefix is already stripped from the request segments.
		if ($pattern_segments && preg_match('/^v\d+$/', (string)$pattern_segments[0])) {
			array_shift($pattern_segments);
		}

		if (count($pattern_segments) !== count($segments)) {
			return null;
		}

		$params = [];

		foreach ($pattern_segments as $index => $expected) {
			$actual = (string)$segments[$index];

			if (preg_match('/^\{(.+)\}$/', $expected, $matches)) {
				$name = $matches[1];

				if (!preg_match('/^\d+$/', $actual)) {
					return null;
				}

				$params[$name] = (int)$actual;

				continue;
			}

			if ($expected !== $actual) {
				return null;
			}
		}

		return $params;
	}
}
