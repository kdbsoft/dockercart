<?php
/**
 * DockerCart admin REST API — Idempotency-Key replay.
 *
 * Reached only when a POST carries an Idempotency-Key that was already answered:
 * startup/api.php stores the stored answer in the request context and routes
 * here, so the original controller action is not executed again.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

class ControllerApiReplay extends \Dockercart\Api\Controller {
	public function index() {
		$stored = $this->context()->replay_payload;

		if (!is_array($stored)) {
			$this->responder()->error(\Dockercart\Api\Error::NOT_FOUND);

			return;
		}

		$this->responder()->replay($stored);
	}
}
