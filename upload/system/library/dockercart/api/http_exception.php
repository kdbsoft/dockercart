<?php
/**
 * DockerCart admin REST API — transport level exception.
 *
 * Thrown by controllers/libraries when a request must be answered with a
 * specific error code (404 for a missing product, 422 for a payload the model
 * rejects, 400 for an order flow transition the admin would also refuse, …).
 * The central exception handler in admin/controller/startup/api.php turns it
 * into the regular JSON error envelope.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

class HttpException extends \Exception {
	private string $error_code;
	private array $fields;
	private int $status;

	/**
	 * @param string $error_code one of the Error::* codes
	 * @param array  $fields     per-field validation messages
	 * @param int    $status     HTTP status override (defaults to the code's own)
	 */
	public function __construct(string $error_code, array $fields = [], int $status = 0) {
		$this->error_code = $error_code;
		$this->fields = $fields;
		$this->status = $status ?: Error::status($error_code);

		parent::__construct(Error::message($error_code));
	}

	public function getErrorCode(): string {
		return $this->error_code;
	}

	public function getFields(): array {
		return $this->fields;
	}

	public function getStatus(): int {
		return $this->status;
	}
}
