<?php
/**
 * DockerCart customer (storefront) REST API — startup pre-action.
 *
 * Runs for the `store_api` application only (upload/system/config/store_api.php)
 * and is the customer counterpart of admin/controller/startup/api.php:
 *
 *   1. request id + public path resolution (PATH_INFO, ?_route_, ?route=),
 *   2. JSON body decoding into $request->post,
 *   3. bearer token authentication (oc_api_key with customer_id > 0) => a
 *      Dockercart\Api\CustomerPrincipal registered as "customer",
 *   4. effective access resolution (customer override -> customer group) and
 *      per-key narrowing (Dockercart\Api\CustomerAccess),
 *   5. per-key IP allowlist + rate limiting,
 *   6. endpoint/scope resolution from Dockercart\Api\StoreRoutes (whitelist),
 *   7. translation of the REST path into a store_api controller route + action,
 *   8. JSON-safe error handling (exceptions and fatals never render HTML),
 *   9. request journal written on shutdown (shared oc_api_log).
 *
 * Admin/shared keys (customer_id = 0) are rejected here, and customer keys are
 * rejected by the admin api startup, so the two surfaces never cross.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

class ControllerStartupStoreApi extends Controller {
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
		// A store API client must always receive JSON, so the flag is forced off
		// for this request (the log file keeps receiving the messages).
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
		$match = \Dockercart\Api\StoreRoutes::match($context->method, $context->path);

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
		$context->scope = $match['scope'] ?? null;

		if (empty($match['public']) && !$this->authenticate($context)) {
			return;
		}

		if (!$this->authorize($context)) {
			return;
		}

		$this->applyPricingContext($context);

		$this->bootstrapCart($context);

		if (!$this->handleIdempotency($context)) {
			return;
		}

		if (!$this->applyLanguageOverride($context)) {
			return;
		}

		// Hand the request over to the regular router: Action resolves
		// "route/action" to admin/controller/<route>.php::<action>().
		foreach ($context->params as $name => $value) {
			$this->request->get[$name] = $value;
		}

		$this->request->get['route'] = $context->route . '/' . $context->action;
	}

	/**
	 * Resolves the public path from whichever rewrite the deployment applies:
	 * nginx/apache (_route_), PATH_INFO
	 * (/store-api/index.php/v1/orders) or the query-string fallback.
	 */
	private function resolvePath(): string {
		$path = '';

		if (!empty($this->request->get['_route_']) && is_string($this->request->get['_route_'])) {
			$path = $this->request->get['_route_'];
		} elseif (!empty($this->request->server['PATH_INFO']) && is_string($this->request->server['PATH_INFO'])) {
			$path = $this->request->server['PATH_INFO'];
		} else {
			$path = (string)parse_url((string)($this->request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH);

			// Direct front-controller access: /store-api/index.php/v1/orders
			$script = (string)($this->request->server['SCRIPT_NAME'] ?? '');

			if ($script !== '' && strpos($path, $script) === 0) {
				$path = substr($path, strlen($script));
			}

			if ($path === '/store-api' || strpos($path, '/store-api/') === 0) {
				$path = substr($path, strlen('/store-api'));
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
	 * every "&", "<" and quote in a comment would be mangled. The models escape
	 * on write and Twig escapes on render.
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

		if (!$this->config->has('config_api_customer_status') || (int)$this->config->get('config_api_customer_status') !== 1) {
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

		// Admin/shared keys have no customer: they must not reach the store API.
		$customer_id = (int)($key['customer_id'] ?? 0);

		if ($customer_id <= 0) {
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

		$customer = $this->loadCustomer($customer_id);

		if (!$customer || !(int)$customer['status']) {
			// The customer is gone or disabled — the key must not work.
			$this->fail($context, \Dockercart\Api\Error::TOKEN_REVOKED);

			return false;
		}

		$access = \Dockercart\Api\CustomerAccess::resolve($this->db, $customer_id);

		if (!$access['enabled']) {
			$this->fail($context, \Dockercart\Api\Error::API_ACCESS_DISABLED);

			return false;
		}

		$principal = new \Dockercart\Api\CustomerPrincipal(
			$customer,
			\Dockercart\Api\CustomerAccess::effectiveKeyScopes($key, $access),
			(int)$key['api_key_id']
		);

		$principal->setRegistry($this->registry);

		// The principal stands in for Cart\Customer, so shared models that read
		// $this->customer->getId() / isLogged() keep working.
		$this->registry->set('customer', $principal);

		$context->customer_principal = $principal;

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
	 * Charges the per-IP budget of requests that carry no usable key (see the
	 * admin counterpart in startup/api.php).
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
	 * The customer row behind a key.
	 */
	private function loadCustomer(int $customer_id): array {
		$query = $this->db->query("SELECT `customer_id`, `customer_group_id`, `firstname`, `lastname`, `email`, `telephone`, `address_id`, `status`, `api_status`, `api_scopes` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . $customer_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Resolves the catalogue context for the reused pricing models: the
	 * authenticated buyer's customer group (specials, group prices, % discount)
	 * and the storefront store of the request (product/category visibility).
	 *
	 * The admin startup loads settings for store 0 only, so the store-scoped
	 * settings are re-applied here (store 0 first, then the resolved store),
	 * exactly like catalog/controller/startup/startup.php does for the storefront.
	 */
	private function applyPricingContext(\Dockercart\Api\Context $context): void {
		$store_id = $this->resolveStoreId();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0' OR store_id = '" . (int)$store_id . "' ORDER BY store_id ASC");

		foreach ($query->rows as $setting) {
			if (!$setting['serialized']) {
				$this->config->set($setting['key'], $setting['value']);
			} else {
				$this->config->set($setting['key'], json_decode($setting['value'], true));
			}
		}

		// The store-scoped reload above restores config_customer_group_id from
		// oc_setting (the guest/default group), so the buyer's group must be
		// forced afterwards — setting it before the reload would be overwritten
		// and every price would silently be the default-group price.
		$this->config->set('config_store_id', $store_id);

		$principal = $context->customer_principal;

		if ($principal instanceof \Dockercart\Api\CustomerPrincipal) {
			$this->config->set('config_customer_group_id', $principal->getCustomerGroupId());
		}
	}

	/**
	 * Starts the customer's cart session for the endpoints that touch the cart.
	 *
	 * Cart\Cart keeps its lines in oc_cart keyed by (customer_id, session_id) and
	 * re-points every row of the customer onto the session id of the current
	 * request, so starting the session with the customer's own scope id
	 * (oc_customer.api_cart_scope) makes the API cart and the browser cart the
	 * same cart — whichever side touches it last moves the rows onto its own id.
	 *
	 * The same session id keys the oc_session row that holds the applied coupon /
	 * voucher / reward points, which is where the storefront total extensions read
	 * them from. Nothing else is stored there, and no cookie is ever handed out.
	 */
	private function bootstrapCart(\Dockercart\Api\Context $context): void {
		if (!in_array($context->route, ['store_api/cart', 'store_api/checkout'], true)) {
			return;
		}

		$principal = $context->customer_principal;

		if (!$principal instanceof \Dockercart\Api\CustomerPrincipal) {
			return;
		}

		try {
			$scope = \Dockercart\Api\CartScope::forCustomer($this->registry, $principal->getCustomerId());
		} catch (\Throwable $exception) {
			$log = $this->registry->get('log');

			if ($log) {
				$log->write('STORE API cart scope: ' . $exception->getMessage());
			}

			$this->fail($context, \Dockercart\Api\Error::SERVER_ERROR);

			return;
		}

		// A `cart_session` cookie belongs to the storefront's guest-cart
		// recovery. Cart\Cart would move the guest rows of that session into
		// this customer's cart, so a client could inject (or steal) a guest cart
		// by sending the cookie. The API never reads cookies.
		unset($this->request->cookie['cart_session'], $_COOKIE['cart_session']);

		$this->session->start($scope);

		// The storefront keeps the display currency in the session and the
		// shipping/payment extensions format their amounts with it. The API
		// reports everything in the store base currency.
		if (empty($this->session->data['currency'])) {
			$this->session->data['currency'] = (string)$this->config->get('config_currency');
		}

		$this->registry->set('cart', new \Cart\Cart($this->registry));

		// Cart\Cart re-creates the storefront cart cookie; never send it.
		header_remove('Set-Cookie');
	}

	/**
	 * Store of the request.
	 *
	 * The store the request was addressed to is the shop context of the call: it
	 * decides which store settings apply (currency, tax display, enabled payment
	 * and shipping modules, catalogue visibility), so it is derived from the host
	 * — never from the query string, which a client controls. An unknown host
	 * belongs to the main store (0).
	 *
	 * The /store-api front controller is stripped from the script path first, so a
	 * shop installed in a subdirectory (/shop/store-api/index.php) still matches
	 * its oc_store row — including direct front-controller access with PATH_INFO.
	 */
	private function resolveStoreId(): int {
		$host = str_replace('www.', '', trim((string)($this->request->server['HTTP_HOST'] ?? '')));

		if ($host === '') {
			return 0;
		}

		$script = (string)($this->request->server['SCRIPT_NAME'] ?? ($this->request->server['PHP_SELF'] ?? ''));

		$script = (string)preg_replace('#/store-api(?:/index\.php)?(?:/.*)?$#', '', $script);
		$script = (string)preg_replace('#/index\.php(?:/.*)?$#', '', $script);

		$base_path = rtrim($script, '/');

		$scheme = !empty($this->request->server['HTTPS']) ? 'https' : 'http';

		$url = $scheme . '://' . $host . $base_path . '/';

		$query = $this->db->query("SELECT `store_id` FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $this->db->escape($url) . "' OR REPLACE(`ssl`, 'www.', '') = '" . $this->db->escape($url) . "'");

		return $query->num_rows ? (int)$query->row['store_id'] : 0;
	}

	private function authorize(\Dockercart\Api\Context $context): bool {
		$principal = $context->customer_principal;

		if (!$principal instanceof \Dockercart\Api\CustomerPrincipal) {
			// Public endpoint (no key): nothing to check.
			if ($context->key === null) {
				return true;
			}

			$this->fail($context, \Dockercart\Api\Error::UNAUTHORIZED);

			return false;
		}

		if ($context->scope !== null && $context->scope !== '' && !$principal->can($context->scope)) {
			$this->fail($context, \Dockercart\Api\Error::FORBIDDEN);

			return false;
		}

		return true;
	}

	/**
	 * ?language=ru-ua selects which localization list queries join (the panel
	 * does the same through the session) and which language pack the total /
	 * shipping / payment extension labels are read from.
	 *
	 * @return bool false when the request has been routed to the error controller
	 */
	private function applyLanguageOverride(\Dockercart\Api\Context $context): bool {
		$code = trim((string)($this->request->get['language'] ?? ''));

		if ($code === '') {
			return true;
		}

		$map = new \Dockercart\Api\LanguageMap($this->registry);

		if (!$map->exists($code)) {
			$this->fail($context, \Dockercart\Api\Error::VALIDATION_FAILED, ['language' => ['unknown language code "' . $code . '"']]);

			return false;
		}

		$this->config->set('config_language_id', $map->id($code));

		$language = new \Language($code);
		$language->load($code);

		$this->registry->set('language', $language);

		return true;
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
					$log->write('STORE API ' . get_class($exception) . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
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
			$error = error_get_last();

			if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
				$log = $registry->get('log');

				if ($log) {
					$log->write('STORE API fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
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
				// Only authenticated attempts are journalled (oc_api_log is keyed by
				// api_key_id); an unauthenticated client must not be able to write one
				// row per request — nginx already logs those 401s.
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
