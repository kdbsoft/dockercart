<?php
/**
 * DockerCart admin REST API — responder.
 *
 * The regular Response class already collects headers, so the content type is
 * appended there (framework.php seeds "text/html" at boot; the last
 * Content-Type wins when output() sends them all with replace=true). Nothing is
 * echo'ed directly, so a controller can still be wrapped by the framework.
 *
 * Success envelope:      {"data": …}   (collections add "meta")
 * Error envelope:        RFC 9457 problem details, Content-Type
 *                        application/problem+json (see Dockercart\Api\Error)
 *
 * Also owns the two HTTP concurrency features:
 *   - ETag: setEtag() / notModified() — strong validator derived from the exact
 *     representation a client would GET, so If-Match/If-None-Match work;
 *   - Idempotency-Key replay/storage is delegated to Dockercart\Api\Idempotency
 *     (called from send(), i.e. exactly once per response).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Response {
	private \Registry $registry;
	private Context $context;

	public function __construct(\Registry $registry, Context $context) {
		$this->registry = $registry;
		$this->context = $context;
	}

	/**
	 * Sends a successful single-resource response.
	 */
	public function ok($data, int $status = 200): void {
		$this->send(['data' => $data], $status);
	}

	/**
	 * Sends a collection with pagination metadata.
	 */
	public function collection(array $rows, array $meta, int $status = 200): void {
		$this->send(['data' => array_values($rows), 'meta' => $meta], $status);
	}

	/**
	 * 201 Created (+ optional Location header).
	 */
	public function created($data, string $location = ''): void {
		$headers = [];

		if ($location !== '') {
			$headers[] = 'Location: ' . $location;
		}

		$this->send(['data' => $data], 201, $headers);
	}

	/**
	 * 204 No Content.
	 */
	public function noContent(): void {
		$this->context->status = 204;

		http_response_code(204);

		$this->registry->get('response')->addHeader('X-Request-Id: ' . $this->context->request_id);
		$this->registry->get('response')->addHeader('Cache-Control: no-store');
		$this->registry->get('response')->setOutput('');
	}

	/**
	 * 304 Not Modified for a conditional GET (If-None-Match matched).
	 */
	public function notModified(string $etag): void {
		$this->context->status = 304;

		http_response_code(304);

		$response = $this->registry->get('response');

		$response->addHeader('ETag: ' . $etag);
		$response->addHeader('X-Request-Id: ' . $this->context->request_id);
		$response->addHeader('Cache-Control: no-store');
		$response->setOutput('');
	}

	/**
	 * Replays a stored answer of an earlier identical request (Idempotency-Key).
	 * Nothing is re-executed and nothing is stored again.
	 */
	public function replay(array $stored): void {
		$status = (int)($stored['status'] ?? 200);

		$this->context->status = $status;

		http_response_code($status);

		$response = $this->registry->get('response');

		$response->addHeader('Content-Type: application/json; charset=utf-8');
		$response->addHeader('X-Request-Id: ' . $this->context->request_id);
		$response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
		$response->addHeader('Idempotent-Replay: true');

		foreach ((array)($stored['headers'] ?? []) as $header) {
			$response->addHeader($header);
		}

		$response->setOutput((string)($stored['body'] ?? ''));
	}

	/**
	 * Sends an RFC 9457 problem document.
	 */
	public function error(string $code, int $status = 0, array $fields = [], string $detail = ''): void {
		$status = $status ?: Error::status($code);

		$this->context->status = $status;
		$this->context->error_code = $code;

		if ($fields) {
			$this->context->fields = $fields;
		}

		$this->send($this->problem($code, $status, $fields, $detail), $status, [], 'application/problem+json; charset=utf-8');
	}

	/**
	 * Builds the problem document (shared with emitEmergency()).
	 */
	public function problem(string $code, int $status, array $fields = [], string $detail = ''): array {
		$problem = [
			'type'       => Error::type($code),
			'title'      => Error::title($code),
			'status'     => $status,
			'detail'     => $detail !== '' ? $detail : Error::message($code),
			'instance'   => $this->context->path !== '' ? $this->context->path : '/api',
			'code'       => $code,
			'request_id' => $this->context->request_id,
		];

		if ($fields) {
			$problem['fields'] = $fields;
		}

		return $problem;
	}

	/**
	 * Writes a JSON payload with the shared headers.
	 */
	public function send($payload, int $status = 200, array $headers = [], string $content_type = 'application/json; charset=utf-8'): void {
		$this->context->status = $status;

		http_response_code($status);

		$response = $this->registry->get('response');

		$response->addHeader('Content-Type: ' . $content_type);
		$response->addHeader('X-Request-Id: ' . $this->context->request_id);
		$response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
		$response->addHeader('X-Content-Type-Options: nosniff');

		if ($this->context->etag !== null) {
			$response->addHeader('ETag: ' . $this->context->etag);
		}

		foreach ($headers as $header) {
			$response->addHeader($header);
		}

		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

		if ($json === false) {
			$json = json_encode($this->problem(Error::SERVER_ERROR, 500, [], 'Response encoding failed.'));
		}

		// Persist the answer for a retried POST (Idempotency-Key) before it is
		// flushed, so a crash between send() and output() cannot create a
		// duplicate on the next retry while nothing is stored.
		/** @var Idempotency $idempotency */
		$idempotency = $this->registry->get('api_idempotency');

		$idempotency->store($this->context, (string)$json, $status, $headers);

		$response->setOutput((string)$json);
	}

	/**
	 * Terminal problem output used by the exception/fatal handlers.
	 *
	 * Those run after the request stack has been unwound, so Response::output()
	 * will never be called for them — the document is written directly.
	 */
	public function emitEmergency(string $code, int $status = 0, array $fields = [], string $detail = ''): void {
		$status = $status ?: Error::status($code);

		$this->context->status = $status;
		$this->context->error_code = $code;

		$problem = $this->problem($code, $status, $fields, $detail);

		if (!headers_sent()) {
			http_response_code($status);
			header('Content-Type: application/problem+json; charset=utf-8');
			header('X-Request-Id: ' . $this->context->request_id);
			header('Cache-Control: no-store, no-cache, must-revalidate');
		}

		echo json_encode($problem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	}
}
