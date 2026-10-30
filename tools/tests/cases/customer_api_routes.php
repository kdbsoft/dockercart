<?php
/**
 * Customer (storefront) REST surface: routes, scopes and the OpenAPI document.
 *
 * `StoreRoutes::TABLE` is the security whitelist and upload/api/openapi-store.json
 * is the client contract; they must never drift apart. The scope of every entry
 * must come from the CustomerScopes catalogue.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\CustomerScopes;
use Dockercart\Api\StoreRoutes;

/**
 * @return array<int, array{method: string, path: string, route: string, action: string, scope: ?string, public: bool}>
 */
function customer_api_store_routes(): array {
	if (!class_exists(StoreRoutes::class, false)) {
		require_once UPLOAD_DIR . '/system/library/dockercart/api/store_routes.php';
	}

	return StoreRoutes::table();
}

/**
 * @return array<string, mixed>
 */
function customer_api_store_openapi(): array {
	static $document = null;

	if ($document === null) {
		$document = json_decode(repo_file('upload/api/openapi-store.json'), true);

		if (!is_array($document)) {
			throw new TestFailure('upload/api/openapi-store.json is not valid JSON');
		}
	}

	return $document;
}

return [
	'customer api: the store routes table and the OpenAPI document describe the same surface' => static function (): void {
		$document = customer_api_store_openapi();

		$routes = [];

		foreach (customer_api_store_routes() as $entry) {
			$routes[] = $entry['method'] . ' ' . $entry['path'];
		}

		$documented = [];

		foreach ($document['paths'] as $path => $operations) {
			foreach (array_keys($operations) as $method) {
				$documented[] = strtoupper((string)$method) . ' ' . $path;
			}
		}

		sort($routes);
		sort($documented);

		Assert::same($routes, $documented, 'StoreRoutes::TABLE and upload/api/openapi-store.json disagree');
	},

	'customer api: every store route maps to an existing controller action' => static function (): void {
		foreach (customer_api_store_routes() as $entry) {
			$file = UPLOAD_DIR . '/admin/controller/' . $entry['route'] . '.php';

			Assert::true(is_file($file), 'missing controller ' . $entry['route'] . '.php for ' . $entry['path']);

			Assert::contains(
				'function ' . $entry['action'] . '(',
				(string)file_get_contents($file),
				$entry['route'] . ' has no action ' . $entry['action'] . '()'
			);
		}
	},

	'customer api: method+path pairs are unique' => static function (): void {
		$seen = [];

		foreach (customer_api_store_routes() as $entry) {
			$key = $entry['method'] . ' ' . $entry['path'];

			Assert::false(isset($seen[$key]), 'duplicate route ' . $key);
			$seen[$key] = true;
		}
	},

	'customer api: every store route requires a valid scope (except public discovery)' => static function (): void {
		foreach (customer_api_store_routes() as $entry) {
			if ($entry['public']) {
				Assert::same(null, $entry['scope'], $entry['path'] . ' is public and must not require a scope');

				continue;
			}

			Assert::true(is_string($entry['scope']) && $entry['scope'] !== '', $entry['path'] . ' must require a scope');
			Assert::true(CustomerScopes::isValid((string)$entry['scope']), $entry['path'] . ' uses unknown scope "' . $entry['scope'] . '"');
		}
	},

	'customer api: only the discovery endpoint is public' => static function (): void {
		$public = [];

		foreach (customer_api_store_routes() as $entry) {
			if ($entry['public']) {
				$public[] = $entry['method'] . ' ' . $entry['path'];
			}
		}

		Assert::same(['GET /v1/openapi.json'], $public, 'only GET /v1/openapi.json may be public');
	},

	'customer api: every $ref in the store OpenAPI document resolves' => static function (): void {
		$document = customer_api_store_openapi();

		$resolve = static function (string $ref) use ($document) {
			$node = $document;

			foreach (explode('/', ltrim($ref, '#/')) as $segment) {
				if (!is_array($node) || !array_key_exists($segment, $node)) {
					return null;
				}

				$node = $node[$segment];
			}

			return $node;
		};

		$walk = static function ($node) use (&$walk, $resolve): void {
			if (is_array($node)) {
				if (isset($node['$ref']) && is_string($node['$ref'])) {
					Assert::true($resolve($node['$ref']) !== null, 'unresolved $ref ' . $node['$ref']);
				}

				foreach ($node as $child) {
					$walk($child);
				}
			}
		};

		$walk($document);
	},

	'customer api: every documented error response exists' => static function (): void {
		$document = customer_api_store_openapi();

		foreach ($document['paths'] as $path => $operations) {
			foreach ($operations as $method => $operation) {
				foreach (($operation['responses'] ?? []) as $status => $response) {
					if (!isset($response['$ref'])) {
						continue;
					}

					$name = substr($response['$ref'], strlen('#/components/responses/'));

					Assert::true(isset($document['components']['responses'][$name]), $method . ' ' . $path . ' references unknown response "' . $name . '"');
				}
			}
		}
	},

	'customer api: the OpenAPI document declares the scope catalogue' => static function (): void {
		$document = customer_api_store_openapi();

		$description = (string)($document['info']['description'] ?? '');

		foreach (CustomerScopes::all() as $scope) {
			Assert::contains($scope, $description, 'the scope catalogue must be documented: ' . $scope);
		}

		$tags = array_column($document['tags'], 'name');

		Assert::true(in_array('Orders', $tags, true), 'the Orders tag is missing');
		Assert::true(in_array('Returns', $tags, true), 'the Returns tag is missing');
		Assert::true(in_array('Products', $tags, true), 'the Products tag is missing');
		Assert::true(in_array('Categories', $tags, true), 'the Categories tag is missing');
	},

	'customer api: the store path matcher strips the version and captures ids' => static function (): void {
		if (!class_exists(StoreRoutes::class, false)) {
			require_once UPLOAD_DIR . '/system/library/dockercart/api/store_routes.php';
		}

		$match = StoreRoutes::match('GET', '/v1/orders/42');

		Assert::same('ok', $match['status']);
		Assert::same('store_api/orders', $match['route']);
		Assert::same('get', $match['action']);
		Assert::same(['order_id' => 42], $match['params']);
		Assert::same(CustomerScopes::ORDERS_READ, $match['scope']);

		Assert::same('ok', StoreRoutes::match('GET', '/v1/orders')['status']);
		Assert::same('method_not_allowed', StoreRoutes::match('DELETE', '/v1/orders')['status']);
		Assert::same('not_found', StoreRoutes::match('GET', '/v1/orders/abc')['status']);
		Assert::same('not_found', StoreRoutes::match('GET', '/v1/customers')['status']);
		Assert::same('unsupported_version', StoreRoutes::match('GET', '/v2/orders')['status']);
	},

	'customer api: the store path matcher resolves the catalogue routes' => static function (): void {
		if (!class_exists(StoreRoutes::class, false)) {
			require_once UPLOAD_DIR . '/system/library/dockercart/api/store_routes.php';
		}

		$products = StoreRoutes::match('GET', '/v1/products');

		Assert::same('ok', $products['status']);
		Assert::same('store_api/products', $products['route']);
		Assert::same('index', $products['action']);
		Assert::same([], $products['params']);
		Assert::same(CustomerScopes::PRODUCTS_READ, $products['scope']);

		$variants = StoreRoutes::match('GET', '/v1/products/42/variants');

		Assert::same('ok', $variants['status']);
		Assert::same('store_api/products', $variants['route']);
		Assert::same('variants', $variants['action']);
		Assert::same(['product_id' => 42], $variants['params']);
		Assert::same(CustomerScopes::PRODUCTS_READ, $variants['scope']);

		$tree = StoreRoutes::match('GET', '/v1/categories/tree');

		Assert::same('ok', $tree['status']);
		Assert::same('store_api/categories', $tree['route']);
		Assert::same('tree', $tree['action']);
		Assert::same([], $tree['params']);

		$category = StoreRoutes::match('GET', '/v1/categories/3');

		Assert::same('ok', $category['status']);
		Assert::same('get', $category['action']);
		Assert::same(['category_id' => 3], $category['params']);

		Assert::same('not_found', StoreRoutes::match('GET', '/v1/categories/abc')['status']);
		Assert::same('method_not_allowed', StoreRoutes::match('DELETE', '/v1/products')['status']);
	},
];
