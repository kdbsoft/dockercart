<?php
/**
 * DockerCart customer (storefront) REST API — public API description.
 *
 *   GET /store-api/v1/openapi.json
 *
 * The only unauthenticated endpoint of the store API: an integrator must be able
 * to fetch the contract without a key first. The same document is served to the
 * account docs page. Cacheable (ETag + short max-age).
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

class ControllerStoreApiOpenapi extends \Dockercart\Api\Controller {
	public function index() {
		// A disabled customer API exposes nothing — not even the discovery
		// document.
		if (!$this->config->has('config_api_customer_status') || (int)$this->config->get('config_api_customer_status') !== 1) {
			$this->responder()->error(\Dockercart\Api\Error::API_DISABLED);

			return;
		}

		$file = dirname(rtrim(DIR_APPLICATION, '/\\')) . '/api/openapi-store.json';

		$json = is_file($file) ? file_get_contents($file) : false;

		if ($json === false) {
			$this->responder()->error(\Dockercart\Api\Error::NOT_FOUND, 404, [], 'The OpenAPI document is not deployed.');

			return;
		}

		$etag = '"' . sha1($json) . '"';

		$if_none_match = $this->rawHeader('HTTP_IF_NONE_MATCH');

		if ($if_none_match !== '' && ($if_none_match === '*' || $this->normalizeEtag($if_none_match) === $etag)) {
			$this->responder()->notModified($etag);

			return;
		}

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
