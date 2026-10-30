<?php
/**
 * DockerCart admin REST API — per request state.
 *
 * One instance is created by admin/controller/startup/api.php, stored in the
 * registry as "api_context", and read by the resource controllers, the JSON
 * error controller and the shutdown journal writer.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Context {
	public string $method = 'GET';
	public string $path = '';
	public string $route = '';
	public string $action = '';
	public array $params = [];

	/** HTTP methods accepted for the matched path when the method itself failed (405). */
	public array $allowed = [];

	/** Routes accepted for this endpoint: ['read' => [...], 'write' => [...]] */
	public array $read = [];
	public array $write = [];

	/** Raw oc_api_key row of the authenticated request (null when unauthenticated). */
	public ?array $key = null;

	public ?Principal $principal = null;

	/** Customer principal of a store-api request (null on the admin api app). */
	public ?CustomerPrincipal $customer_principal = null;

	/** Required scope of the matched store-api endpoint (null for public endpoints). */
	public ?string $scope = null;

	public string $request_id = '';
	public float $started_at = 0.0;

	/** Client IP as resolved by the startup pre-action (proxy aware). */
	public string $ip = '';

	/** Response bookkeeping (filled by Dockercart\Api\Response). */
	public int $status = 0;
	public string $error_code = '';
	public array $fields = [];

	/** Ask the journal to skip this request (e.g. the log purge of itself). */
	public bool $log = true;

	/** Strong entity tag of the representation this request is about (see Response::setEtag()). */
	public ?string $etag = null;

	/** sha1 of the canonical request body — used to bind an Idempotency-Key to its payload. */
	public string $request_hash = '';

	/** Raw Idempotency-Key header of the request ('' when absent). */
	public string $idempotency_header = '';

	/**
	 * Pending idempotency row (oc_api_idempotency.api_idempotency_id), set when a
	 * POST with Idempotency-Key starts executing; Response::send() stores the
	 * answer under it.
	 */
	public int $idempotency_id = 0;

	/** Stored answer of an earlier identical request, replayed without executing the action. */
	public ?array $replay_payload = null;

	public function isWrite(): bool {
		return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
	}

	public function elapsedMs(): int {
		if ($this->started_at <= 0.0) {
			return 0;
		}

		return (int)round((microtime(true) - $this->started_at) * 1000);
	}
}
