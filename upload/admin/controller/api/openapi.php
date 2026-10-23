<?php
/**
 * DockerCart admin REST API — public API description.
 *
 *   GET /v1/openapi.json
 *
 * The only unauthenticated endpoint: an integrator must be able to fetch the
 * contract without a key first (and Swagger UI / code generators need a stable
 * URL). The same document is served to the panel docs page.
 *
 * The response is cacheable (ETag + short max-age) — it only changes when the
 * code is deployed.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

class ControllerApiOpenapi extends \Dockercart\Api\Controller {
	public function index() {
		// A disabled API exposes nothing — not even the discovery document.
		// The panel docs page renders the same file session-gated, so an
		// integrator who needs the contract while the API is off should ask
		// the shop to enable it first.
		if ($this->config->has('config_api_status') && (int)$this->config->get('config_api_status') === 0) {
			$this->responder()->error(\Dockercart\Api\Error::API_DISABLED);

			return;
		}

		$file = dirname(rtrim(DIR_APPLICATION, '/\\')) . '/api/openapi.json';

		$json = is_file($file) ? file_get_contents($file) : false;

		if ($json === false) {
			$this->responder()->error(\Dockercart\Api\Error::NOT_FOUND, 404, [], 'The OpenAPI document is not deployed.');

			return;
		}

		$etag = '"' . sha1($json) . '"';

		// nginx appends "-gzip" to the tag of a compressed response, so the echoed
		// validator is normalized exactly like the resource endpoints do.
		$if_none_match = $this->rawHeader('HTTP_IF_NONE_MATCH');

		if ($if_none_match !== '' && ($if_none_match === '*' || $this->normalizeEtag($if_none_match) === $etag)) {
			$this->responder()->notModified($etag);

			return;
		}

		// The document is cacheable, unlike regular API responses, so the shared
		// "no-store" header from Response::send() is bypassed on purpose.
		$this->context()->etag = $etag;

		http_response_code(200);

		$response = $this->registry->get('response');

		$response->addHeader('Content-Type: application/json; charset=utf-8');
		$response->addHeader('ETag: ' . $etag);
		$response->addHeader('Cache-Control: public, max-age=300');
		$response->addHeader('X-Request-Id: ' . $this->context()->request_id);
		$response->addHeader('X-Content-Type-Options: nosniff');
		$response->setOutput($json);
	}
}
