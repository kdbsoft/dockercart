<?php
/**
 * DockerCart admin REST API — base resource controller.
 *
 * Every controller in upload/admin/controller/api/ extends this class. It only
 * provides transport concerns (envelopes, pagination, validation, language
 * mapping); all business logic stays in the regular admin models, exactly like
 * the panel controllers use them.
 *
 * Authentication, route resolution and the permission check happen before the
 * controller runs (admin/controller/startup/api.php), so an action body can
 * assume an authenticated principal.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

abstract class Controller extends \Controller {
	/**
	 * Per request state created by the startup pre-action.
	 */
	protected function context(): Context {
		return $this->registry->get('api_context');
	}

	protected function responder(): Response {
		return $this->registry->get('api_response');
	}

	/**
	 * Decoded JSON body (startup/api.php put it into $request->post).
	 */
	protected function body(): array {
		return is_array($this->request->post) ? $this->request->post : [];
	}

	/**
	 * Reads a value from the JSON body, falling back to the query string.
	 */
	protected function input(string $key, $default = null) {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}

		if (isset($this->request->get[$key])) {
			return $this->request->get[$key];
		}

		return $default;
	}

	protected function query(string $key, $default = null) {
		return $this->request->get[$key] ?? $default;
	}

	protected function queryString(string $key, string $default = ''): string {
		return trim((string)($this->request->get[$key] ?? $default));
	}

	protected function queryInt(string $key, int $default = 0): int {
		return isset($this->request->get[$key]) ? (int)$this->request->get[$key] : $default;
	}

	/**
	 * Path parameter captured by Dockercart\Api\Routes (e.g. product_id).
	 */
	protected function param(string $key, int $default = 0): int {
		return (int)($this->context()->params[$key] ?? $default);
	}

	/**
	 * Pagination state: page is 1-based, limit is clamped to 20..100.
	 *
	 * @return array{page: int, limit: int, start: int}
	 */
	protected function pagination(int $default_limit = 20, int $max_limit = 100): array {
		$page = max(1, (int)($this->request->get['page'] ?? 1));
		$limit = (int)($this->request->get['limit'] ?? $default_limit);

		if ($limit < 1) {
			$limit = $default_limit;
		}

		$limit = min($limit, $max_limit);

		return [
			'page'  => $page,
			'limit' => $limit,
			'start' => ($page - 1) * $limit,
		];
	}

	protected function meta(int $page, int $limit, int $total): array {
		return [
			'page'  => $page,
			'limit' => $limit,
			'total' => $total,
			'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0,
		];
	}

	protected function respond($data, int $status = 200): void {
		$this->responder()->ok($data, $status);
	}

	protected function respondCollection(array $rows, array $meta): void {
		$this->responder()->collection($rows, $meta);
	}

	protected function respondCreated($data, string $location = ''): void {
		$this->responder()->created($data, $location);
	}

	protected function respondNoContent(): void {
		$this->responder()->noContent();
	}

	/**
	 * @param array $data  raw input
	 * @param array $rules Dockercart\Api\Validator rules
	 * @return array normalized values
	 */
	protected function validateRequest(array $data, array $rules): array {
		$validator = new Validator();

		return $validator->validate($data, $rules);
	}

	/**
	 * Ids (language_id => value) => codes (language code => value).
	 */
	protected function descriptionsToCodes(array $descriptions): array {
		$map = $this->languageMap();
		$output = [];

		foreach ($descriptions as $language_id => $value) {
			$code = $map->code((int)$language_id);

			if ($code === '') {
				continue;
			}

			$output[$code] = $value;
		}

		return $output;
	}

	/**
	 * Codes (language code => value) => ids (language_id => value).
	 *
	 * @param array $defaults field => default value applied to every language
	 */
	protected function descriptionsToIds($descriptions, array $defaults = []): array {
		$map = $this->languageMap();

		if (!is_array($descriptions) || !$descriptions) {
			return [];
		}

		$output = [];

		foreach ($descriptions as $code => $value) {
			$language_id = $map->requireId((string)$code);

			if (!is_array($value)) {
				throw new HttpException(Error::VALIDATION_FAILED, [
					'descriptions.' . $code => ['must be an object'],
				]);
			}

			$output[$language_id] = array_merge($defaults, $value);
		}

		return $output;
	}

	protected function languageMap(): LanguageMap {
		if (!$this->registry->has('api_language_map')) {
			$this->registry->set('api_language_map', new LanguageMap($this->registry));
		}

		return $this->registry->get('api_language_map');
	}

	protected function languageId(string $code = ''): int {
		$map = $this->languageMap();

		return $code === '' ? $map->defaultId() : $map->requireId($code);
	}

	/**
	 * "sort"/"order" query parameters mapped onto a model's whitelist.
	 *
	 * @param array $allowed whitelisted values accepted from the client; a map
	 *                       (public name => model column) is mapped, a list is
	 *                       validated as-is
	 * @return array{sort: string, order: string}
	 */
	protected function sorting(array $allowed, string $default_sort, string $default_order = 'ASC'): array {
		$sort = (string)($this->request->get['sort'] ?? '');
		$order = strtoupper((string)($this->request->get['order'] ?? $default_order));

		$is_map = $allowed !== [] && array_keys($allowed) !== range(0, count($allowed) - 1);

		if ($is_map) {
			$sort = isset($allowed[$sort]) ? (string)$allowed[$sort] : $default_sort;
		} elseif (!in_array($sort, $allowed, true)) {
			$sort = $default_sort;
		}

		return [
			'sort'  => $sort,
			'order' => $order === 'DESC' ? 'DESC' : 'ASC',
		];
	}

	/**
	 * Sends a single resource with a strong ETag derived from the exact body a
	 * GET would return, and answers 304 for a matching If-None-Match.
	 */
	protected function respondResource(array $payload, int $status = 200): void {
		$etag = $this->etagFor($payload);

		$this->context()->etag = $etag;

		if ($status === 200 && $this->ifNoneMatch() === $etag) {
			$this->responder()->notModified($etag);

			return;
		}

		$this->respond($payload, $status);
	}

	/**
	 * Strong validator: sha1 of the canonical representation. Any write changes
	 * the response, so a stale If-Match can never be mistaken for a fresh one.
	 */
	protected function etagFor(array $payload): string {
		$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

		return '"' . sha1((string)$json) . '"';
	}

	/**
	 * Reads a request header *without* Request::clean().
	 *
	 * ETag validators are quoted strings and Request::clean() runs
	 * htmlspecialchars() over $_SERVER, so `"abc"` would arrive as `&quot;abc&quot;`
	 * and never match. Everything compared byte-for-byte (validators, tokens)
	 * must come from the raw server array.
	 */
	protected function rawHeader(string $name): string {
		return trim((string)($_SERVER[$name] ?? ''));
	}

	/**
	 * Optimistic concurrency check for PUT / PATCH / DELETE.
	 *
	 * - If-Match present  -> it must match the current representation (412 otherwise);
	 * - If-Match absent   -> rejected with 428 when config_api_require_if_match is on,
	 *                        allowed otherwise (simple integrations keep working);
	 * - If-Match: *       -> only requires the resource to exist (already checked).
	 *
	 * @param array $current current representation (same shape a GET returns)
	 */
	protected function assertPreconditions(array $current): void {
		$if_match = $this->rawHeader('HTTP_IF_MATCH');

		if ($if_match === '') {
			if ($this->config->has('config_api_require_if_match') && (int)$this->config->get('config_api_require_if_match') === 1) {
				throw new HttpException(Error::PRECONDITION_REQUIRED);
			}

			return;
		}

		if ($if_match === '*') {
			return;
		}

		$current_etag = $this->etagFor($current);

		foreach (explode(',', $if_match) as $candidate) {
			if ($this->normalizeEtag($candidate) === $current_etag) {
				return;
			}
		}

		throw new HttpException(Error::PRECONDITION_FAILED);
	}

	/**
	 * If-None-Match value (weak comparison form) for conditional GET.
	 */
	protected function ifNoneMatch(): string {
		$header = $this->rawHeader('HTTP_IF_NONE_MATCH');

		if ($header === '') {
			return '';
		}

		return $this->normalizeEtag(explode(',', $header)[0]);
	}

	/**
	 * Normalizes a validator received from a client.
	 *
	 * nginx rewrites the ETag of a gzipped response by appending the coding to
	 * the tag (`"abc"` -> `"abc-gzip"`), so a client that echoes the header would
	 * otherwise never match. The weak prefix is dropped as well; If-Match is
	 * compared against our own strong tag, so a weak candidate can only match
	 * when the opaque value is identical.
	 */
	protected function normalizeEtag(string $etag): string {
		$etag = trim($etag);

		if (strpos($etag, 'W/') === 0) {
			$etag = substr($etag, 2);
		}

		return (string)preg_replace('/-(gzip|br|zstd)(?="$)/i', '', $etag);
	}

	protected function abortNotFound(array $fields = []): void {
		throw new HttpException(Error::NOT_FOUND, $fields);
	}

	protected function abortConflict(string $code = Error::CONFLICT, array $fields = []): void {
		throw new HttpException($code, $fields);
	}

	protected function abortForbidden(): void {
		throw new HttpException(Error::FORBIDDEN);
	}
}
