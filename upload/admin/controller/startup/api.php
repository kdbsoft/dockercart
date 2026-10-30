<?php
/**
 * DockerCart admin REST API — startup pre-action.
 *
 * Runs for the `api` application only (upload/system/config/api.php) and
 * replaces the panel's startup/login + startup/permission pair:
 *
 *   1. request id + public path resolution (PATH_INFO, ?_route_, ?route=),
 *   2. JSON body decoding into $request->post,
 *   3. bearer token authentication (oc_api_key) => Dockercart\Api\Principal in
 *      the registry as "user", so permission checks and reusable panel JSON
 *      actions keep working,
 *   4. per-key IP allowlist + rate limiting,
 *   5. endpoint/permission resolution from Dockercart\Api\Routes (whitelist),
 *   6. translation of the REST path into an admin controller route + action,
 *   7. JSON-safe error handling (exceptions and fatals never render HTML),
 *   8. request journal written on shutdown.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

class ControllerStartupApi extends Controller {
	public function index() {
		$context = new \Dockercart\Api\Context();

		$context->started_at = microtime(true);
		$context->method = strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET'));
		$context->request_id = $this->resolveRequestId();
		$context->path = $this->resolvePath();
		$context->ip = $this->clientIp();

		$this->registry->set('api_context', $context);
		$this->registry->set('api_response', new \Dockercart\Api\Response($this->registry, $context));
		$this->registry->set('api_keys', new \Dockercart\Api\KeyRepository($this->registry));
		$this->registry->set('api_journal', new \Dockercart\Api\Journal($this->registry));
		$this->registry->set('api_idempotency', new \Dockercart\Api\Idempotency($this->registry));

		$context->idempotency_header = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));

		// The panel's error handler echoes HTML when config_error_display is on.
		// An API client must always receive JSON, so the flag is forced off for
		// this request (the log file keeps receiving the messages).
		$this->config->set('config_error_display', false);
		ini_set('display_errors', '0');

		// startup/startup instantiates Cart\Cart, which deletes a stale
		// "cart_session" cookie when the session id does not match. The API is
		// stateless and must never hand out cookies, so drop them all.
		header_remove('Set-Cookie');

		$this->registerHandlers($context);

		if (!$this->parseBody($context)) {
			return;
		}

		// Resolve the endpoint first: public entries (GET /v1/openapi.json) must
		// answer without a key, everything else requires one.
		$match = \Dockercart\Api\Routes::match($context->method, $context->path);

		if ($match['status'] === 'unsupported_version') {
			$this->fail($context, \Dockercart\Api\Error::UNSUPPORTED_VERSION);

			return;
		}

		if ($match['status'] === 'not_found') {
			$this->fail($context, \Dockercart\Api\Error::NOT_FOUND);

			return;
		}

		if ($match['status'] === 'method_not_allowed') {
			$context->allowed = $match['allowed'];

			$this->fail($context, \Dockercart\Api\Error::METHOD_NOT_ALLOWED);

			return;
		}

		$context->route = $match['route'];
		$context->action = $match['action'];
		$context->params = $match['params'];
		$context->read = $match['read'];
		$context->write = $match['write'];

		if (empty($match['public']) && !$this->authenticate($context)) {
			return;
		}

		if (!$this->authorize($context)) {
			return;
		}

		if (!$this->handleIdempotency($context)) {
			return;
		}

		$this->applyLanguageOverride($context);

		// Hand the request over to the regular router: Action resolves
		// "route/action" to admin/controller/<route>.php::<action>().
		foreach ($context->params as $name => $value) {
			$this->request->get[$name] = $value;
		}

		$this->request->get['route'] = $context->route . '/' . $context->action;
	}

	/**
	 * Resolves the public path from whichever rewrite the deployment applies:
	 * nginx/apache (_route_), PATH_INFO (/api/index.php/v1/products) or the
	 * query-string fallback (?route=api/... or ?route=/v1/...).
	 */
	private function resolvePath(): string {
		$path = '';

		if (!empty($this->request->get['_route_']) && is_string($this->request->get['_route_'])) {
			$path = $this->request->get['_route_'];
		} elseif (!empty($this->request->server['PATH_INFO']) && is_string($this->request->server['PATH_INFO'])) {
			$path = $this->request->server['PATH_INFO'];
		} elseif (!empty($this->request->get['route']) && is_string($this->request->get['route']) && strpos($this->request->get['route'], 'api') === 0) {
			// ?route=api/... => /api/... (kept for CLI/tests/curl convenience)
			$path = substr($this->request->get['route'], 3);
		} else {
			$path = (string)parse_url((string)($this->request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH);

			// Direct front-controller access: /api/index.php/v1/products
			$script = (string)($this->request->server['SCRIPT_NAME'] ?? '');

			if ($script !== '' && strpos($path, $script) === 0) {
				$path = substr($path, strlen($script));
			}

			if ($path === '/api' || strpos($path, '/api/') === 0) {
				$path = substr($path, strlen('/api'));
			}
		}

		// A rewrite can hand over the whole request target; keep the path only.
		$path = (string)(parse_url((string)$path, PHP_URL_PATH) ?: '');

		$path = preg_replace('#/+#', '/', '/' . ltrim((string)$path, '/'));

		return rtrim((string)$path, '/') ?: '/';
	}

	private function resolveRequestId(): string {
		$incoming = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));

		if ($incoming !== '' && preg_match('/^[A-Za-z0-9._\-]{8,40}$/', $incoming)) {
			return $incoming;
		}

		return bin2hex(random_bytes(8));
	}

	/**
	 * Decodes a JSON request body into $request->post.
	 *
	 * Values deliberately bypass Request::clean() (htmlspecialchars) — otherwise
	 * every "&", "<" and quote in product descriptions would be mangled. The
	 * admin models escape on write and Twig escapes on render.
	 */
	private function parseBody(\Dockercart\Api\Context $context): bool {
		if (in_array($context->method, ['GET', 'HEAD', 'DELETE', 'OPTIONS'], true)) {
			return true;
		}

		$raw = file_get_contents('php://input');

		if ($raw === false || trim($raw) === '') {
			return true;
		}

		$content_type = strtolower((string)($this->request->server['CONTENT_TYPE'] ?? ''));

		if (strpos($content_type, 'application/json') === false) {
			// form-urlencoded / multipart already parsed by PHP + Request::clean()
			return true;
		}

		$data = json_decode($raw, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->fail($context, \Dockercart\Api\Error::INVALID_JSON);

			return false;
		}

		if (!is_array($data)) {
			$this->fail($context, \Dockercart\Api\Error::INVALID_JSON);

			return false;
		}

		$this->request->post = $data;

		$context->request_hash = $this->canonicalHash($data);

		return true;
	}

	/**
	 * Stable hash of the request payload: key order must not matter, so the same
	 * logical body always yields the same Idempotency-Key fingerprint.
	 */
	private function canonicalHash(array $data): string {
		$normalize = static function ($value) use (&$normalize) {
			if (!is_array($value)) {
				return $value;
			}

			if (array_keys($value) !== range(0, count($value) - 1)) {
				ksort($value);
			}

			return array_map($normalize, $value);
		};

		return sha1((string)json_encode($normalize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * Idempotent POST: replays a stored answer or takes the lock for this attempt.
	 */
	private function handleIdempotency(\Dockercart\Api\Context $context): bool {
		/** @var \Dockercart\Api\Idempotency $idempotency */
		$idempotency = $this->registry->get('api_idempotency');

		try {
			$replay = $idempotency->begin($context);
		} catch (\Dockercart\Api\HttpException $exception) {
			$this->fail($context, $exception->getErrorCode(), $exception->getFields());

			return false;
		}

		if ($replay === null) {
			return true;
		}

		$context->replay_payload = $replay;

		$this->request->get['route'] = 'api/replay/index';

		return false;
	}

	private function authenticate(\Dockercart\Api\Context $context): bool {
		/** @var \Dockercart\Api\KeyRepository $keys */
		$keys = $this->registry->get('api_keys');

		// Fail closed: the API is only served when the store setting enables it.
		if (!$this->config->has('config_api_status') || (int)$this->config->get('config_api_status') !== 1) {
			$this->fail($context, \Dockercart\Api\Error::API_DISABLED);

			return false;
		}

		$token = $this->bearerToken();

		if ($token === '') {
			if ($this->throttleAnonymous($context)) {
				$this->fail($context, \Dockercart\Api\Error::UNAUTHORIZED);
			}

			return false;
		}

		$key = $keys->findByToken($token);

		if (!$key) {
			// A guessed token costs one indexed lookup; charge it to the per-IP
			// budget so the endpoint cannot be used as a lookup amplifier.
			if ($this->throttleAnonymous($context)) {
				$this->fail($context, \Dockercart\Api\Error::UNAUTHORIZED);
			}

			return false;
		}

		$context->key = $key;

		// Customer keys belong to the store API (/store-api/v1/...): they must
		// never authenticate against the admin surface.
		if ((int)($key['customer_id'] ?? 0) > 0) {
			$this->fail($context, \Dockercart\Api\Error::UNAUTHORIZED);

			return false;
		}

		if (!(int)$key['status']) {
			$this->fail($context, \Dockercart\Api\Error::TOKEN_REVOKED);

			return false;
		}

		if (!empty($key['expires_at']) && strtotime((string)$key['expires_at']) <= time()) {
			$this->fail($context, \Dockercart\Api\Error::TOKEN_EXPIRED);

			return false;
		}

		$ip = $this->clientIp();

		if (!$keys->ipAllowed($key, $ip)) {
			$this->fail($context, \Dockercart\Api\Error::IP_NOT_ALLOWED);

			return false;
		}

		$group_id = $this->resolveGroupId($key);

		if ($group_id === null) {
			// The owner of the key is gone or disabled — the key must not work.
			$this->fail($context, \Dockercart\Api\Error::TOKEN_REVOKED);

			return false;
		}

		$principal = new \Dockercart\Api\Principal($key, $keys->getGroup($group_id), $group_id);

		$this->registry->set('user', $principal);

		$context->principal = $principal;

		$rate = $keys->rateLimit($key);

		$response = $this->registry->get('response');

		$response->addHeader('X-RateLimit-Limit: ' . $rate['limit']);
		$response->addHeader('X-RateLimit-Remaining: ' . $rate['remaining']);
		$response->addHeader('X-RateLimit-Reset: ' . $rate['reset']);

		if ($rate['exceeded']) {
			$this->fail($context, \Dockercart\Api\Error::RATE_LIMITED);

			return false;
		}

		$keys->touch($key, $ip);

		return true;
	}

	/**
	 * Charges the per-IP budget of requests that carry no usable key.
	 *
	 * Only unauthenticated attempts are counted: a valid key has its own, wider
	 * budget (KeyRepository::rateLimit()) and must never be throttled by requests
	 * somebody else sent from the same address.
	 *
	 * @return bool false when the request must be answered with 429
	 */
	private function throttleAnonymous(\Dockercart\Api\Context $context): bool {
		/** @var \Dockercart\Api\KeyRepository $keys */
		$keys = $this->registry->get('api_keys');

		if (!$keys->rateLimitAnonymous($this->clientIp())['exceeded']) {
			return true;
		}

		$this->fail($context, \Dockercart\Api\Error::RATE_LIMITED);

		return false;
	}

	/**
	 * The group a key acts with.
	 *
	 * A key owned by an admin user follows the *current* group of that user, so
	 * moving a user to another role immediately changes what the key may do.
	 * A shared key keeps the group stored in the key row.
	 *
	 * @return int|null the group id, or null when the owner is gone/disabled
	 */
	private function resolveGroupId(array $key): ?int {
		$user_id = (int)($key['user_id'] ?? 0);

		if (!$user_id) {
			return (int)($key['user_group_id'] ?? 0);
		}

		/** @var \Dockercart\Api\KeyRepository $keys */
		$keys = $this->registry->get('api_keys');

		$owner = $keys->getOwner($user_id);

		if (!$owner || !(int)$owner['status']) {
			return null;
		}

		return (int)$owner['user_group_id'];
	}

	private function authorize(\Dockercart\Api\Context $context): bool {
		$principal = $context->principal;

		if (!$principal instanceof \Dockercart\Api\Principal) {
			// Public endpoint (no key): nothing to check.
			if ($context->key === null) {
				return true;
			}

			$this->fail($context, \Dockercart\Api\Error::UNAUTHORIZED);

			return false;
		}

		if ($context->isWrite()) {
			if ($context->write && !$principal->canAny('modify', $context->write)) {
				$this->fail($context, \Dockercart\Api\Error::FORBIDDEN);

				return false;
			}
		} elseif ($context->read && !$principal->canAny('access', $context->read)) {
			$this->fail($context, \Dockercart\Api\Error::FORBIDDEN);

			return false;
		}

		return true;
	}

	/**
	 * ?language=ru-ua selects which localization list queries join (the panel
	 * does the same through the session).
	 */
	private function applyLanguageOverride(\Dockercart\Api\Context $context): void {
		$code = trim((string)($this->request->get['language'] ?? ''));

		if ($code === '') {
			return;
		}

		$map = new \Dockercart\Api\LanguageMap($this->registry);

		if (!$map->exists($code)) {
			$this->fail($context, \Dockercart\Api\Error::VALIDATION_FAILED, ['language' => ['unknown language code "' . $code . '"']]);

			return;
		}

		$this->config->set('config_language_id', $map->id($code));
	}

	/**
	 * Routes the request to the JSON error controller.
	 */
	private function fail(\Dockercart\Api\Context $context, string $code, array $fields = []): void {
		$context->error_code = $code;

		if ($fields) {
			$context->fields = $fields;
		}

		$this->request->get['route'] = 'api/error/index';
	}

	/**
	 * Authorization: Bearer <token> (preferred) or X-Api-Key: <token>.
	 * Tokens in the query string are intentionally not supported — they leak
	 * into proxy access logs.
	 */
	private function bearerToken(): string {
		$header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

		if ($header === '' && function_exists('getallheaders')) {
			$headers = getallheaders();

			foreach ((array)$headers as $name => $value) {
				if (strcasecmp((string)$name, 'Authorization') === 0) {
					$header = (string)$value;

					break;
				}
			}
		}

		if ($header !== '' && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
			return trim($matches[1]);
		}

		return trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
	}

	/**
	 * Client IP as seen by the front proxy.
	 *
	 * nginx overwrites X-Real-IP with $remote_addr, so it cannot be spoofed by
	 * the client; X-Forwarded-For is only a fallback and its *last* entry is used
	 * (the address appended by our own proxy).
	 */
	private function clientIp(): string {
		$real_ip = trim((string)($this->request->server['HTTP_X_REAL_IP'] ?? ''));

		if ($real_ip !== '' && filter_var($real_ip, FILTER_VALIDATE_IP)) {
			return $real_ip;
		}

		$forwarded = (string)($this->request->server['HTTP_X_FORWARDED_FOR'] ?? '');

		if ($forwarded !== '') {
			$parts = array_map('trim', explode(',', $forwarded));

			for ($index = count($parts) - 1; $index >= 0; $index--) {
				if (filter_var($parts[$index], FILTER_VALIDATE_IP)) {
					return $parts[$index];
				}
			}
		}

		return (string)($this->request->server['REMOTE_ADDR'] ?? '');
	}

	/**
	 * Central exception handler + fatal error handler + journal writer.
	 */
	private function registerHandlers(\Dockercart\Api\Context $context): void {
		$registry = $this->registry;

		set_exception_handler(function (\Throwable $exception) use ($registry, $context) {
			/** @var \Dockercart\Api\Response $responder */
			$responder = $registry->get('api_response');

			if ($exception instanceof \Dockercart\Api\HttpException) {
				$code = $exception->getErrorCode();
				$status = $exception->getStatus();
				$fields = $exception->getFields();
			} else {
				$code = \Dockercart\Api\Error::SERVER_ERROR;
				$status = 500;
				$fields = [];

				$log = $registry->get('log');

				if ($log) {
					$log->write('API ' . get_class($exception) . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
				}
			}

			$context->status = $status;
			$context->error_code = $code;

			if (!headers_sent()) {
				http_response_code($status);
			}

			$responder->emitEmergency($code, $status, $fields);
		});

		register_shutdown_function(function () use ($registry, $context) {
			$fatal = false;

			$error = error_get_last();

			if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
				$fatal = true;

				$log = $registry->get('log');

				if ($log) {
					$log->write('API fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
				}

				if (!headers_sent()) {
					$context->status = 500;
					$context->error_code = \Dockercart\Api\Error::SERVER_ERROR;

					http_response_code(500);

					/** @var \Dockercart\Api\Response $responder */
					$responder = $registry->get('api_response');
					$responder->emitEmergency(\Dockercart\Api\Error::SERVER_ERROR, 500, []);
				}
			}

			if (!$context->log || $context->key === null) {
				// Only authenticated attempts are journalled: oc_api_log is keyed by
				// api_key_id (the panel usage counters read it) and an unauthenticated
				// client must not be able to write one row per request — nginx already
				// logs those 401s.
				return;
			}

			try {
				/** @var \Dockercart\Api\Journal $journal */
				$journal = $registry->get('api_journal');

				$journal->record([
					'api_key_id'  => (int)($context->key['api_key_id'] ?? 0),
					'key_name'    => (string)($context->key['name'] ?? ''),
					'method'      => $context->method,
					'path'        => $context->path,
					'route'       => $context->route . ($context->action !== '' ? '/' . $context->action : ''),
					'status_code' => $context->status ?: (int)http_response_code(),
					'duration_ms' => $context->elapsedMs(),
					'ip'          => $context->ip,
					'user_agent'  => (string)($registry->get('request')->server['HTTP_USER_AGENT'] ?? ''),
					'request_id'  => $context->request_id,
					'error_code'  => $context->error_code,
				]);
			} catch (\Throwable $exception) {
				// Journalling must never break the response.
				unset($exception);
			}
		});
	}
}
