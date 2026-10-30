<?php
/**
 * DockerCart customer (storefront) REST API — storefront controller bridge.
 *
 * The store API reuses the admin MVC tree, so `$this->load->controller()` and the
 * framework `Action` only ever resolve upload/admin/controller/…. Storefront
 * controllers are not mirrors of admin models: a payment module implements its
 * whole gateway interaction (redirect URL, inline form, session token) in
 * upload/catalog/controller/extension/payment/<code>.php, and that file is
 * unreachable from the admin tree.
 *
 * This class runs one such controller action, with the tree temporarily swapped
 * for the duration of the call and restored in a `finally`:
 *
 *   load      -> CatalogLoader (catalog models / language packs / theme views)
 *   language  -> the panel object, with the catalog packs merged in (restored)
 *   url       -> the storefront Url (so $this->url->link() is a shop URL)
 *   response  -> PaymentResponse (captures output and redirects, see that file)
 *   session   -> the given keys set, the whole data array restored afterwards
 *                (the API's session row must not keep a gateway's scratch state)
 *   request   -> $request->post = the call's payload (a gateway that expects the
 *                submitted card/hosted-page fields finds them there)
 *   config    -> template_directory points at the storefront theme
 *
 * Only extension controllers may be bridged: a storefront checkout controller
 * would open a second, unreviewed order path.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CatalogControllers {
	/**
	 * Routes that may be bridged. Kept explicit so a call cannot reach arbitrary
	 * storefront behaviour.
	 */
	private const ALLOWED_PREFIX = [
		'extension/payment/',
		'extension/shipping/',
	];

	/**
	 * Runs a storefront controller action.
	 *
	 * @param array $options {
	 *     @var array      $session session keys published for the call
	 *     @var array|null $post    request payload exposed to the controller
	 *     @var array      $data    arguments passed to the action
	 * }
	 * @return array{output: string, redirect: string, json: array|null}
	 */
	public static function run(\Registry $registry, string $route, string $action = 'index', array $options = []): array {
		$route = self::sanitize($route);

		if (!self::allowed($route)) {
			throw new \RuntimeException('Storefront controller ' . $route . ' may not be bridged');
		}

		$file = DIR_CATALOG . 'controller/' . $route . '.php';

		if (!is_file($file)) {
			throw new \RuntimeException('Storefront controller ' . $route . ' is missing');
		}

		$class = 'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $route);

		$config = $registry->get('config');
		$request = $registry->get('request');

		$theme_directory = self::themeDirectory($config);

		$previous = [
			'load'               => $registry->has('load') ? $registry->get('load') : null,
			'url'                => $registry->has('url') ? $registry->get('url') : null,
			'response'           => $registry->has('response') ? $registry->get('response') : null,
			'template_directory' => (string)$config->get('template_directory'),
		];

		$language = $registry->has('language') ? $registry->get('language') : null;
		$language_data = $language ? $language->data : null;

		$session = $registry->has('session') ? $registry->get('session') : null;
		$session_data = $session ? $session->data : null;

		$post = $request->post;

		$response = new PaymentResponse();

		try {
			$config->set('template_directory', 'catalog/view/theme/' . $theme_directory . '/template/');

			$registry->set('load', new CatalogLoader($registry, $theme_directory));

			$url = new \Url((string)$config->get('config_url'), (string)$config->get('config_ssl'));
			$url->setRegistry($registry);

			$registry->set('url', $url);
			$registry->set('response', $response);

			if ($session) {
				$session->data = array_merge((array)$session->data, (array)($options['session'] ?? []));
			}

			if (isset($options['post']) && is_array($options['post'])) {
				$request->post = $options['post'];
			}

			include_once(modification($file));

			if (!class_exists($class)) {
				throw new \RuntimeException('Storefront controller ' . $route . ' declares no ' . $class);
			}

			$controller = new $class($registry);

			$reflection = new \ReflectionClass($controller);

			if (!$reflection->hasMethod($action) || $reflection->getMethod($action)->getNumberOfRequiredParameters() > count((array)($options['data'] ?? []))) {
				throw new \RuntimeException('Storefront controller ' . $route . ' has no callable ' . $action . '()');
			}

			ob_start();

			try {
				call_user_func_array([$controller, $action], array_values((array)($options['data'] ?? [])));

				$output = (string)ob_get_clean();
			} catch (PaymentRedirect $redirect) {
				$output = (string)ob_get_clean();
			} catch (\Throwable $exception) {
				ob_end_clean();

				throw $exception;
			}
		} finally {
			if ($session && is_array($session_data)) {
				$session->data = $session_data;
			}

			if ($language && is_array($language_data)) {
				$language->data = $language_data;
			}

			$request->post = $post;

			if ($previous['load'] !== null) {
				$registry->set('load', $previous['load']);
			}

			if ($previous['url'] !== null) {
				$registry->set('url', $previous['url']);
			}

			if ($previous['response'] !== null) {
				$registry->set('response', $previous['response']);
			}

			$config->set('template_directory', $previous['template_directory']);
		}

		$body = trim($output . (string)$response->getOutput());

		$json = self::decodeJson($body);

		$redirect = $response->capturedRedirect();

		if ($redirect !== null) {
			$url = (string)$redirect['url'];
		} else {
			$url = '';
		}

		if ($url === '' && is_array($json) && !empty($json['redirect']) && is_string($json['redirect'])) {
			$url = (string)$json['redirect'];
		}

		return [
			'output'   => $body,
			'redirect' => $url,
			'json'     => $json,
		];
	}

	/**
	 * @return array|null the decoded JSON answer of the module, if the body is one
	 */
	private static function decodeJson(string $body): ?array {
		$body = ltrim($body);

		if ($body === '' || ($body[0] !== '{' && $body[0] !== '[')) {
			return null;
		}

		$decoded = json_decode($body, true);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * Storefront theme directory of the request store (e.g. "dockercart").
	 */
	private static function themeDirectory($config): string {
		$theme = (string)$config->get('config_theme');

		$directory = $theme !== '' ? (string)$config->get('theme_' . $theme . '_directory') : '';

		if ($directory === '') {
			$directory = $theme !== '' ? $theme : 'default';
		}

		return $directory;
	}

	private static function allowed(string $route): bool {
		if (strpos($route, '..') !== false) {
			return false;
		}

		foreach (self::ALLOWED_PREFIX as $prefix) {
			if (strpos($route, $prefix) === 0 && strlen($route) > strlen($prefix)) {
				return true;
			}
		}

		return false;
	}

	private static function sanitize(string $route): string {
		return (string)preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
	}
}
