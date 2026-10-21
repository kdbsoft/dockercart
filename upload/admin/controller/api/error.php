<?php
/**
 * DockerCart admin REST API — JSON error controller.
 *
 * Every failure detected before a resource controller runs (unknown route,
 * unsupported version, missing/invalid token, rate limit, forbidden) is
 * delegated here by admin/controller/startup/api.php. It is also the
 * action_default of the api application, so a request without a usable route
 * still answers in the regular JSON error envelope.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

class ControllerApiError extends \Dockercart\Api\Controller {
	public function index() {
		$context = $this->context();

		$code = $context->error_code !== '' ? $context->error_code : \Dockercart\Api\Error::NOT_FOUND;

		if ($code === \Dockercart\Api\Error::METHOD_NOT_ALLOWED && $context->allowed) {
			$this->response->addHeader('Allow: ' . implode(', ', $context->allowed));
		}

		$this->responder()->error($code, $context->status, $context->fields);
	}

	public function notFound() {
		$this->responder()->error(\Dockercart\Api\Error::NOT_FOUND);
	}
}
