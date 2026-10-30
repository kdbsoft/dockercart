<?php
/**
 * DockerCart admin REST API — error catalogue (RFC 9457 problem details).
 *
 * Every failure is answered with `application/problem+json`:
 *
 *   {
 *     "type": "urn:dockercart:api:error:validation_failed",
 *     "title": "Unprocessable Content",
 *     "status": 422,
 *     "detail": "The given data was invalid.",
 *     "instance": "/api/v1/products",
 *     "code": "validation_failed",
 *     "request_id": "0f5c9b1e2d3a4b5c",
 *     "fields": {"model": ["required"]}
 *   }
 *
 * `type` is a stable URN per error code (RFC 9457 §3.1 explicitly allows any URI
 * reference), `code` duplicates the last URN segment as a plain string so
 * clients do not have to parse it, `fields` maps validation problems per field
 * and `request_id` matches the API journal.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Error {
	// 400
	public const BAD_REQUEST = 'bad_request';
	public const INVALID_JSON = 'invalid_json';

	// 401
	public const UNAUTHORIZED = 'unauthorized';
	public const TOKEN_EXPIRED = 'token_expired';
	public const TOKEN_REVOKED = 'token_revoked';

	// 403
	public const FORBIDDEN = 'forbidden';
	public const IP_NOT_ALLOWED = 'ip_not_allowed';
	public const API_ACCESS_DISABLED = 'api_access_disabled';

	// 404 / 405
	public const NOT_FOUND = 'not_found';
	public const UNSUPPORTED_VERSION = 'unsupported_version';
	public const METHOD_NOT_ALLOWED = 'method_not_allowed';

	// 409
	public const CONFLICT = 'conflict';
	public const INVALID_TRANSITION = 'invalid_transition';
	public const TRACKING_REQUIRED = 'tracking_required';
	public const INSUFFICIENT_STOCK = 'insufficient_stock';
	public const CART_EMPTY = 'cart_empty';
	public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';
	public const IDEMPOTENCY_IN_PROGRESS = 'idempotency_in_progress';
	public const CHECKOUT_COMPLETED = 'checkout_completed';
	public const CHECKOUT_NOT_CONFIRMED = 'checkout_not_confirmed';
	public const PAYMENT_UNAVAILABLE = 'payment_unavailable';
	public const PAYMENT_ALREADY_STARTED = 'payment_already_started';

	// 502
	public const PAYMENT_GATEWAY_ERROR = 'payment_gateway_error';

	// 412 / 428
	public const PRECONDITION_FAILED = 'precondition_failed';
	public const PRECONDITION_REQUIRED = 'precondition_required';

	// 422
	public const VALIDATION_FAILED = 'validation_failed';

	// 429
	public const RATE_LIMITED = 'rate_limited';

	// 5xx
	public const SERVER_ERROR = 'server_error';
	public const API_DISABLED = 'api_disabled';
	public const NOT_CONFIGURED = 'not_configured';

	/**
	 * HTTP status code per error code.
	 */
	private const STATUS = [
		self::BAD_REQUEST              => 400,
		self::INVALID_JSON             => 400,
		self::UNAUTHORIZED             => 401,
		self::TOKEN_EXPIRED            => 401,
		self::TOKEN_REVOKED            => 401,
		self::FORBIDDEN                => 403,
		self::IP_NOT_ALLOWED           => 403,
		self::API_ACCESS_DISABLED      => 403,
		self::NOT_FOUND                => 404,
		self::UNSUPPORTED_VERSION      => 404,
		self::METHOD_NOT_ALLOWED       => 405,
		self::CONFLICT                 => 409,
		self::INVALID_TRANSITION       => 409,
		self::TRACKING_REQUIRED        => 409,
		self::INSUFFICIENT_STOCK       => 409,
		self::CART_EMPTY               => 409,
		self::IDEMPOTENCY_CONFLICT     => 409,
		self::IDEMPOTENCY_IN_PROGRESS  => 409,
		self::CHECKOUT_COMPLETED       => 409,
		self::CHECKOUT_NOT_CONFIRMED   => 409,
		self::PAYMENT_UNAVAILABLE      => 409,
		self::PAYMENT_ALREADY_STARTED  => 409,
		self::PRECONDITION_FAILED      => 412,
		self::PRECONDITION_REQUIRED    => 428,
		self::VALIDATION_FAILED        => 422,
		self::RATE_LIMITED             => 429,
		self::SERVER_ERROR             => 500,
		self::PAYMENT_GATEWAY_ERROR    => 502,
		self::API_DISABLED             => 503,
		self::NOT_CONFIGURED          => 503,
	];

	/**
	 * Detail message per error code.
	 */
	private const MESSAGE = [
		self::BAD_REQUEST              => 'The request could not be understood.',
		self::INVALID_JSON             => 'The request body is not valid JSON.',
		self::INVALID_TRANSITION       => 'The requested status transition is not allowed.',
		self::TRACKING_REQUIRED        => 'The order cannot enter the shipping status without a tracking number.',
		self::INSUFFICIENT_STOCK       => 'There is not enough stock to fulfil the request.',
		self::CART_EMPTY               => 'The cart is empty.',
		self::UNAUTHORIZED             => 'A valid API key is required. Send it as "Authorization: Bearer <token>".',
		self::TOKEN_EXPIRED            => 'The API key has expired.',
		self::TOKEN_REVOKED            => 'The API key has been revoked.',
		self::FORBIDDEN                => 'The API key is not allowed to perform this action.',
		self::IP_NOT_ALLOWED           => 'The client IP address is not allowed for this API key.',
		self::API_ACCESS_DISABLED      => 'API access is not enabled for this account.',
		self::NOT_FOUND                => 'The requested resource was not found.',
		self::UNSUPPORTED_VERSION      => 'The requested API version is not supported.',
		self::METHOD_NOT_ALLOWED       => 'The HTTP method is not allowed for this resource.',
		self::CONFLICT                 => 'The resource is in a state that does not allow this action.',
		self::IDEMPOTENCY_CONFLICT     => 'This Idempotency-Key was already used with a different request body.',
		self::IDEMPOTENCY_IN_PROGRESS  => 'A request with this Idempotency-Key is still being processed.',
		self::CHECKOUT_COMPLETED       => 'The checkout has already been completed; the order exists and cannot be placed again.',
		self::CHECKOUT_NOT_CONFIRMED   => 'The checkout has not been confirmed yet; place the order before starting the payment.',
		self::PAYMENT_UNAVAILABLE      => 'The payment method of this order is no longer available in this store.',
		self::PAYMENT_ALREADY_STARTED  => 'The payment was already started and the order has moved on since; start a new order instead.',
		self::PAYMENT_GATEWAY_ERROR    => 'The payment method failed to start the payment.',
		self::PRECONDITION_FAILED      => 'The resource was modified since the given If-Match value.',
		self::PRECONDITION_REQUIRED    => 'This endpoint requires an If-Match header (optimistic locking is enforced).',
		self::VALIDATION_FAILED        => 'The given data was invalid.',
		self::RATE_LIMITED             => 'Too many requests. Slow down and retry later.',
		self::SERVER_ERROR             => 'An unexpected server error occurred.',
		self::API_DISABLED             => 'The API is disabled.',
		self::NOT_CONFIGURED           => 'The API is not configured.',
	];

	public static function status(string $code): int {
		return self::STATUS[$code] ?? 500;
	}

	public static function statuses(): array {
		return self::STATUS;
	}

	public static function message(string $code): string {
		return self::MESSAGE[$code] ?? self::MESSAGE[self::SERVER_ERROR];
	}

	/**
	 * RFC 7807 `type` URI for a code.
	 */
	public static function type(string $code): string {
		return 'urn:dockercart:api:error:' . $code;
	}

	/**
	 * RFC 7807 `title` for a code (the HTTP reason phrase, as recommended).
	 */
	public static function title(string $code): string {
		return self::reasonPhrase(self::status($code));
	}

	private static function reasonPhrase(int $status): string {
		$phrases = [
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			409 => 'Conflict',
			412 => 'Precondition Failed',
			422 => 'Unprocessable Content',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			503 => 'Service Unavailable',
		];

		return $phrases[$status] ?? 'Error';
	}
}
