<?php
/**
 * DockerCart admin REST API — endpoint table.
 *
 * This is the single source of truth for the public API surface:
 *   public path + HTTP method  ->  admin controller route + action
 *                                + permissions required to reach it.
 *
 * It doubles as a security whitelist: nothing is reachable unless it is listed
 * here, so adding an admin controller never accidentally publishes an endpoint.
 *
 * The optional 7th column marks an endpoint as public (reachable without a key);
 * only the OpenAPI document uses it, so integrators can discover the API without
 * credentials.
 *
 * Permission semantics mirror the panel:
 *   "read"  routes are checked against oc_user_group.permission.access
 *   "write" routes are checked against oc_user_group.permission.modify
 * An entry with an empty list needs nothing beyond a valid, unexpired key.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Routes {
	/**
	 * Supported version prefix (first path segment).
	 */
	public const VERSION = 'v1';

	/**
	 * [method, path pattern, controller route, action, read routes, write routes]
	 */
	private const TABLE = [
		// ---------------------------------------------------------------- products
		['GET',    '/v1/products',                        'api/catalog/products', 'index',    ['catalog/product'], []],
		['POST',   '/v1/products',                        'api/catalog/products', 'add',      [], ['catalog/product']],
		['GET',    '/v1/products/{product_id}',           'api/catalog/products', 'get',      ['catalog/product'], []],
		['PUT',    '/v1/products/{product_id}',           'api/catalog/products', 'edit',     [], ['catalog/product']],
		['PATCH',  '/v1/products/{product_id}',           'api/catalog/products', 'patch',    [], ['catalog/product']],
		['DELETE', '/v1/products/{product_id}',           'api/catalog/products', 'delete',   [], ['catalog/product']],
		['GET',    '/v1/products/{product_id}/variants',  'api/catalog/products', 'variants', ['catalog/product'], []],
		['PATCH',  '/v1/products/{product_id}/stock',     'api/catalog/products', 'stock',    [], ['catalog/product']],
		['PATCH',  '/v1/products/{product_id}/price',     'api/catalog/products', 'price',    [], ['catalog/product']],

		// -------------------------------------------------------------- categories
		['GET',    '/v1/categories',                      'api/catalog/categories', 'index',  ['catalog/category'], []],
		['POST',   '/v1/categories',                      'api/catalog/categories', 'add',    [], ['catalog/category']],
		['GET',    '/v1/categories/tree',                 'api/catalog/categories', 'tree',   ['catalog/category'], []],
		['GET',    '/v1/categories/{category_id}',        'api/catalog/categories', 'get',    ['catalog/category'], []],
		['PUT',    '/v1/categories/{category_id}',        'api/catalog/categories', 'edit',   [], ['catalog/category']],
		['PATCH',  '/v1/categories/{category_id}',        'api/catalog/categories', 'patch',  [], ['catalog/category']],
		['DELETE', '/v1/categories/{category_id}',        'api/catalog/categories', 'delete', [], ['catalog/category']],

		// ----------------------------------------------------------- manufacturers
		['GET',    '/v1/manufacturers',                   'api/catalog/manufacturers', 'index',  ['catalog/manufacturer'], []],
		['POST',   '/v1/manufacturers',                   'api/catalog/manufacturers', 'add',    [], ['catalog/manufacturer']],
		['GET',    '/v1/manufacturers/{manufacturer_id}', 'api/catalog/manufacturers', 'get',    ['catalog/manufacturer'], []],
		['PUT',    '/v1/manufacturers/{manufacturer_id}', 'api/catalog/manufacturers', 'edit',   [], ['catalog/manufacturer']],
		['PATCH',  '/v1/manufacturers/{manufacturer_id}', 'api/catalog/manufacturers', 'patch',  [], ['catalog/manufacturer']],
		['DELETE', '/v1/manufacturers/{manufacturer_id}', 'api/catalog/manufacturers', 'delete', [], ['catalog/manufacturer']],

		// ------------------------------------------------------------------ orders
		['GET',    '/v1/orders',                          'api/sale/orders', 'index',      ['sale/order'], []],
		['GET',    '/v1/orders/{order_id}',               'api/sale/orders', 'get',        ['sale/order'], []],
		['PATCH',  '/v1/orders/{order_id}',               'api/sale/orders', 'patch',      [], ['sale/order', 'sale/order/shipment']],
		['GET',    '/v1/orders/{order_id}/history',       'api/sale/orders', 'history',    ['sale/order'], []],
		['POST',   '/v1/orders/{order_id}/history',       'api/sale/orders', 'addHistory', [], ['sale/order', 'sale/order/status']],
		['GET',    '/v1/order-statuses',                  'api/sale/order_statuses', 'index', ['sale/order'], []],

		// --------------------------------------------------------------- customers
		['GET',    '/v1/customers',                       'api/customer/customers', 'index', ['customer/customer'], []],
		['GET',    '/v1/customers/{customer_id}',         'api/customer/customers', 'get',   ['customer/customer'], []],

		// ----------------------------------------------------------------- returns
		['GET',    '/v1/returns',                         'api/sale/returns', 'index',      ['sale/return'], []],
		['POST',   '/v1/returns',                         'api/sale/returns', 'add',        [], ['sale/return']],
		['GET',    '/v1/returns/{return_id}',             'api/sale/returns', 'get',        ['sale/return'], []],
		['POST',   '/v1/returns/{return_id}/history',     'api/sale/returns', 'addHistory', [], ['sale/return']],

		// -------------------------------------------------------------- warehouses
		['GET',    '/v1/warehouses',                      'api/warehouse/warehouses', 'index', ['warehouse/warehouse', 'warehouse/stock'], []],
		['GET',    '/v1/warehouses/{warehouse_id}/stock', 'api/warehouse/warehouses', 'stock', ['warehouse/stock', 'warehouse/warehouse'], []],
		['POST',   '/v1/stock/adjustments',               'api/warehouse/stock',      'adjustments', [], ['warehouse/stock']],

		// -------------------------------------------------------------------- blog
		['GET',    '/v1/blog/posts',                      'api/blog/posts', 'index',  ['extension/module/dockercart_blog_post'], []],
		['POST',   '/v1/blog/posts',                      'api/blog/posts', 'add',    [], ['extension/module/dockercart_blog_post']],
		['GET',    '/v1/blog/posts/{post_id}',            'api/blog/posts', 'get',    ['extension/module/dockercart_blog_post'], []],
		['PUT',    '/v1/blog/posts/{post_id}',            'api/blog/posts', 'edit',   [], ['extension/module/dockercart_blog_post']],
		['PATCH',  '/v1/blog/posts/{post_id}',            'api/blog/posts', 'patch',  [], ['extension/module/dockercart_blog_post']],
		['DELETE', '/v1/blog/posts/{post_id}',            'api/blog/posts', 'delete', [], ['extension/module/dockercart_blog_post']],

		['GET',    '/v1/blog/categories',                 'api/blog/categories', 'index',  ['extension/module/dockercart_blog_category'], []],
		['POST',   '/v1/blog/categories',                 'api/blog/categories', 'add',    [], ['extension/module/dockercart_blog_category']],
		['GET',    '/v1/blog/categories/{category_id}',   'api/blog/categories', 'get',    ['extension/module/dockercart_blog_category'], []],
		['PUT',    '/v1/blog/categories/{category_id}',   'api/blog/categories', 'edit',   [], ['extension/module/dockercart_blog_category']],
		['PATCH',  '/v1/blog/categories/{category_id}',   'api/blog/categories', 'patch',  [], ['extension/module/dockercart_blog_category']],
		['DELETE', '/v1/blog/categories/{category_id}',   'api/blog/categories', 'delete', [], ['extension/module/dockercart_blog_category']],

		['GET',    '/v1/blog/comments',                   'api/blog/comments', 'index',   ['extension/module/dockercart_blog_comment'], []],
		['GET',    '/v1/blog/comments/{comment_id}',      'api/blog/comments', 'get',     ['extension/module/dockercart_blog_comment'], []],
		['PATCH',  '/v1/blog/comments/{comment_id}',      'api/blog/comments', 'patch',   [], ['extension/module/dockercart_blog_comment']],
		['POST',   '/v1/blog/comments/{comment_id}/approve', 'api/blog/comments', 'approve', [], ['extension/module/dockercart_blog_comment']],
		['DELETE', '/v1/blog/comments/{comment_id}',      'api/blog/comments', 'delete',  [], ['extension/module/dockercart_blog_comment']],

		['GET',    '/v1/blog/authors',                    'api/blog/authors', 'index',  ['extension/module/dockercart_blog_author'], []],
		['POST',   '/v1/blog/authors',                    'api/blog/authors', 'add',    [], ['extension/module/dockercart_blog_author']],
		['GET',    '/v1/blog/authors/{author_id}',        'api/blog/authors', 'get',    ['extension/module/dockercart_blog_author'], []],
		['PUT',    '/v1/blog/authors/{author_id}',        'api/blog/authors', 'edit',   [], ['extension/module/dockercart_blog_author']],
		['PATCH',  '/v1/blog/authors/{author_id}',        'api/blog/authors', 'patch',  [], ['extension/module/dockercart_blog_author']],
		['DELETE', '/v1/blog/authors/{author_id}',        'api/blog/authors', 'delete', [], ['extension/module/dockercart_blog_author']],

		// ---------------------------------------------------------------- discovery
		['GET',    '/v1/openapi.json',                    'api/openapi', 'index', [], [], true],

		// -------------------------------------------------------------------- meta
		['GET',    '/v1/meta/languages',                  'api/meta', 'languages',        [], []],
		['GET',    '/v1/meta/stores',                     'api/meta', 'stores',           [], []],
		['GET',    '/v1/meta/currencies',                 'api/meta', 'currencies',       [], []],
		['GET',    '/v1/meta/tax-classes',                'api/meta', 'taxClasses',       [], []],
		['GET',    '/v1/meta/customer-groups',            'api/meta', 'customerGroups',   [], []],
		['GET',    '/v1/meta/order-statuses',             'api/meta', 'orderStatuses',    [], []],
		['GET',    '/v1/meta/return-statuses',            'api/meta', 'returnStatuses',   [], []],
		['GET',    '/v1/meta/return-reasons',             'api/meta', 'returnReasons',    [], []],
		['GET',    '/v1/meta/return-actions',             'api/meta', 'returnActions',    [], []],
		['GET',    '/v1/meta/sections',                   'api/meta', 'sections',         [], []],
	];

	/**
	 * Matches a public path + HTTP method against the endpoint table.
	 *
	 * @return array{status: string, route?: string, action?: string, params?: array,
	 *               read?: array, write?: array, allowed?: array}
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
			[$entry_method, $pattern, $route, $action, $read, $write] = $entry;

			$public = !empty($entry[6]);

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
				'read'   => $read,
				'write'  => $write,
				'public' => $public,
			];
		}

		if ($allowed) {
			return ['status' => 'method_not_allowed', 'allowed' => array_values(array_unique($allowed))];
		}

		return ['status' => 'not_found', 'allowed' => []];
	}

	/**
	 * Returns "METHOD /v1/path" => description rows (used by the docs page and
	 * by scripts that cross-check the OpenAPI document).
	 */
	public static function table(): array {
		$rows = [];

		foreach (self::TABLE as $entry) {
			$rows[] = [
				'method'   => $entry[0],
				'path'     => $entry[1],
				'route'    => $entry[2],
				'action'   => $entry[3],
				'read'     => $entry[4],
				'write'    => $entry[5],
				'public'   => !empty($entry[6]),
			];
		}

		return $rows;
	}

	/**
	 * Normalized path segments of a request path ("/v1/products/12/" => [v1, products, 12]).
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
