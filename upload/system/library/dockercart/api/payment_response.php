<?php
/**
 * DockerCart customer (storefront) REST API — controller response capture.
 *
 * A storefront payment controller answers in one of three ways:
 *
 *   1. `$this->response->setOutput(json_encode(['redirect' => $url]))` (the
 *      common gateway case),
 *   2. `$this->response->setOutput($form_html)` — an auto-submitting form,
 *   3. `$this->response->redirect($url)` — and then the base implementation
 *      calls `header('Location: …')` followed by `exit()`.
 *
 * Pattern 3 would hand the API client a raw 302 (or an empty body) and would end
 * the request before the API could answer, so while a storefront controller runs
 * under Dockercart\Api\CatalogControllers the registry's `response` is replaced
 * by this class: it records the headers/output instead of emitting them, and it
 * turns a redirect into an exception the bridge catches — the controller stops
 * exactly where `exit()` would have stopped it, and nothing is written to the
 * client.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

/**
 * Thrown by PaymentResponse::redirect() to unwind the controller.
 */
final class PaymentRedirect extends \RuntimeException {
	private string $url;

	private int $status;

	public function __construct(string $url, int $status = 302) {
		parent::__construct('the payment module redirected to ' . $url);

		$this->url = $url;
		$this->status = $status;
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getStatusCode(): int {
		return $this->status;
	}
}

/**
 * Capturing replacement for the storefront `Response`.
 */
final class PaymentResponse extends \Response {
	/**
	 * @var string[]
	 */
	private array $captured_headers = [];

	private ?string $captured_output = null;

	/**
	 * @var array{url: string, status: int}|null
	 */
	private ?array $captured_redirect = null;

	public function addHeader($header) {
		$this->captured_headers[] = (string)$header;
	}

	/**
	 * Records the redirect instead of sending it, then unwinds the controller.
	 */
	public function redirect($url, $status = 302) {
		$url = str_replace(['&amp;', "\n", "\r"], ['&', '', ''], (string)$url);

		$this->captured_redirect = ['url' => $url, 'status' => (int)$status];

		throw new PaymentRedirect($url, (int)$status);
	}

	public function setOutput($output) {
		$this->captured_output = (string)$output;
	}

	public function getOutput() {
		return $this->captured_output;
	}

	/**
	 * @return string[]
	 */
	public function capturedHeaders(): array {
		return $this->captured_headers;
	}

	/**
	 * @return array{url: string, status: int}|null
	 */
	public function capturedRedirect(): ?array {
		return $this->captured_redirect;
	}

	/**
	 * First captured header of a kind ("Content-Type: …" => "application/json").
	 */
	public function capturedHeader(string $name): string {
		foreach ($this->captured_headers as $header) {
			$parts = explode(':', $header, 2);

			if (count($parts) === 2 && strcasecmp(trim($parts[0]), $name) === 0) {
				return trim($parts[1]);
			}
		}

		return '';
	}
}
