<?php
/**
 * REST surface: routes, permissions, controllers and the OpenAPI document.
 *
 * `Routes::TABLE` is the security whitelist and the OpenAPI file is the client
 * contract; they must never drift apart.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\Routes;

/**
 * @return array<int, array{method: string, path: string, route: string, action: string, read: array, write: array, public: bool}>
 */
function webhook_api_routes(): array {
	if (!class_exists(Routes::class, false)) {
		require_once UPLOAD_DIR . '/system/library/dockercart/api/routes.php';
	}

	return Routes::table();
}

/**
 * @return array<string, mixed>
 */
function webhook_openapi(): array {
	static $document = null;

	if ($document === null) {
		$document = json_decode(repo_file('upload/api/openapi.json'), true);

		if (!is_array($document)) {
			throw new TestFailure('upload/api/openapi.json is not valid JSON');
		}
	}

	return $document;
}

return [
	'api: the routes table and the OpenAPI document describe the same surface' => static function (): void {
		$document = webhook_openapi();

		$routes = [];

		foreach (webhook_api_routes() as $entry) {
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

		Assert::same($routes, $documented, 'Routes::TABLE and upload/api/openapi.json disagree');
	},

	'api: every route maps to an existing controller action' => static function (): void {
		foreach (webhook_api_routes() as $entry) {
			$file = UPLOAD_DIR . '/admin/controller/' . $entry['route'] . '.php';

			Assert::true(is_file($file), 'missing controller ' . $entry['route'] . '.php for ' . $entry['path']);

			Assert::contains(
				'function ' . $entry['action'] . '(',
				(string)file_get_contents($file),
				$entry['route'] . ' has no action ' . $entry['action'] . '()'
			);
		}
	},

	'api: method+path pairs are unique' => static function (): void {
		$seen = [];

		foreach (webhook_api_routes() as $entry) {
			$key = $entry['method'] . ' ' . $entry['path'];

			Assert::false(isset($seen[$key]), 'duplicate route ' . $key);
			$seen[$key] = true;
		}
	},

	'api: every documented error response exists' => static function (): void {
		$document = webhook_openapi();

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

	'api: every $ref in the OpenAPI document resolves' => static function (): void {
		$document = webhook_openapi();

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

	'api: webhook routes use the panel permission model' => static function (): void {
		$expected = [
			'GET /v1/webhooks'                                  => ['read' => true, 'write' => false],
			'POST /v1/webhooks'                                 => ['read' => false, 'write' => true],
			'GET /v1/webhooks/events'                           => ['read' => true, 'write' => false],
			'GET /v1/webhooks/{webhook_id}'                     => ['read' => true, 'write' => false],
			'PUT /v1/webhooks/{webhook_id}'                     => ['read' => false, 'write' => true],
			'PATCH /v1/webhooks/{webhook_id}'                   => ['read' => false, 'write' => true],
			'DELETE /v1/webhooks/{webhook_id}'                  => ['read' => false, 'write' => true],
			'POST /v1/webhooks/{webhook_id}/rotate'             => ['read' => false, 'write' => true],
			'POST /v1/webhooks/{webhook_id}/test'               => ['read' => false, 'write' => true],
			'GET /v1/webhooks/{webhook_id}/deliveries'          => ['read' => true, 'write' => false],
			'GET /v1/webhook-deliveries/{delivery_id}'          => ['read' => true, 'write' => false],
			'POST /v1/webhook-deliveries/{delivery_id}/retry'   => ['read' => false, 'write' => true],
		];

		$found = [];

		foreach (webhook_api_routes() as $entry) {
			$key = $entry['method'] . ' ' . $entry['path'];

			if (strpos($entry['path'], 'webhook') === false) {
				continue;
			}

			$found[$key] = $entry;
		}

		Assert::same(array_keys($expected), array_keys($found), 'the webhook route list changed');

		foreach ($expected as $key => $shape) {
			$entry = $found[$key];

			Assert::false($entry['public'], $key . ' must never be public');

			if ($shape['read']) {
				Assert::same(['setting/webhook'], $entry['read'], $key . ' read permission');
				Assert::same([], $entry['write'], $key . ' must not require write');
			}

			if ($shape['write']) {
				Assert::same(['setting/webhook'], $entry['write'], $key . ' write permission');
				Assert::same([], $entry['read'], $key . ' must not require read');
			}
		}
	},

	'api: /v1/webhooks/events is not shadowed by the {webhook_id} pattern' => static function (): void {
		$match = Routes::match('GET', '/v1/webhooks/events');

		Assert::same('ok', $match['status']);
		Assert::same('events', $match['action']);

		$match = Routes::match('GET', '/v1/webhooks/42');

		Assert::same('ok', $match['status']);
		Assert::same('get', $match['action']);
		Assert::same(['webhook_id' => 42], $match['params']);
	},

	'api: webhook paths are reachable through the version prefix rules' => static function (): void {
		foreach (['/v1/webhooks', '/v1/webhooks/7', '/v1/webhook-deliveries/9'] as $path) {
			$match = Routes::match('GET', $path);

			Assert::same('ok', $match['status'], $path . ' must match');
			Assert::same('api/webhooks', $match['route']);
		}

		Assert::same('method_not_allowed', Routes::match('GET', '/v1/webhooks/7/rotate')['status']);
		Assert::same('not_found', Routes::match('GET', '/v1/webhooks/7/unknown')['status']);
		Assert::same('not_found', Routes::match('GET', '/v1/webhooks/abc')['status']);
	},

	'api: the OpenAPI document documents the webhook tag and schemas' => static function (): void {
		$document = webhook_openapi();

		$tags = array_column($document['tags'], 'name');

		Assert::true(in_array('Webhooks', $tags, true), 'the Webhooks tag is missing');

		foreach (['Webhook', 'WebhookInput', 'WebhookPatch', 'WebhookEvent', 'WebhookDelivery', 'WebhookDeliveryDetail', 'WebhookTestResult'] as $schema) {
			Assert::true(isset($document['components']['schemas'][$schema]), 'missing schema ' . $schema);
		}

		Assert::true(isset($document['components']['responses']['Conflict']), 'missing the Conflict response');
	},

	'api: the OpenAPI create response documents the one-time secret' => static function (): void {
		$document = webhook_openapi();

		$secret = $document['components']['schemas']['Webhook']['properties']['secret'] ?? null;

		Assert::true(is_array($secret), 'the Webhook schema must describe the one-time secret field');
		Assert::contains('only', (string)($secret['description'] ?? ''), 'the secret field must be documented as create/rotate only');
	},

	'api: the event catalogue keeps English descriptions and hides language keys' => static function (): void {
		$source = repo_file('upload/admin/controller/api/webhooks.php');

		Assert::contains("=> \$event['description']", $source, 'the API must serve the canonical English description');
		Assert::notContains('description_key', $source, 'language keys are a panel detail, not part of the API contract');
	},

	'api: the API controller never returns the stored ciphertext' => static function (): void {
		$source = repo_file('upload/admin/controller/api/webhooks.php');

		Assert::notContains('secret_encrypted', $source, 'the API must never read the ciphertext column');
		Assert::contains("['secret']", $source, 'the plain secret must be attached explicitly on create/rotate');
	},
];
