<?php
/**
 * DockerCart customer (storefront) REST API — storefront loader for bridged
 * controllers.
 *
 * While Dockercart\Api\CatalogControllers runs a storefront controller inside
 * the admin MVC tree, the registry's `load` key is this object instead of the
 * framework Loader. Every `$this->load->…()` of the storefront controller then
 * reads the *catalog* tree (the framework Loader is bound to DIR_APPLICATION,
 * which is upload/admin/ in this application), while everything else keeps its
 * admin behaviour.
 *
 * Only the routes the checkout pipeline actually needs are supported:
 *
 *   - model()    -> Dockercart\Api\CatalogModels (allowlist, no class collisions)
 *   - language() -> the catalog language pack, merged into the panel Language
 *                   object (see CatalogLanguage::all())
 *   - view()     -> the storefront theme template (DIR_TEMPLATE points at the
 *                   panel, so the source is read explicitly and handed to Twig)
 *   - controller(), library(), helper(), config() -> their usual meaning
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CatalogLoader {
	private \Registry $registry;

	/**
	 * Storefront theme directory, e.g. "catalog/view/theme/dockercart".
	 */
	private string $theme_directory;

	public function __construct(\Registry $registry, string $theme_directory = '') {
		$this->registry = $registry;
		$this->theme_directory = $theme_directory;
	}

	/**
	 * @return mixed the catalog model (registered as model_<route>)
	 */
	public function model($route) {
		return CatalogModels::load($this->registry, (string)$route);
	}

	/**
	 * Loads a catalog language file into the shared Language object.
	 *
	 * @param string $route e.g. "extension/payment/cod"
	 * @param string $key   optional sub-key (kept for Loader compatibility)
	 * @return array
	 */
	public function language($route, $key = '') {
		$route = self::sanitize((string)$route);

		$strings = CatalogLanguage::all($this->registry, $route);

		$language = $this->registry->get('language');

		if ((string)$key !== '') {
			$sub = new \Language(CatalogLanguage::code($this->registry));
			$sub->data = $strings;

			$language->data[(string)$key] = $sub;

			return $language->data;
		}

		$language->data = array_merge((array)$language->data, $strings);

		return $language->data;
	}

	/**
	 * Renders a storefront theme template.
	 *
	 * @param string $route e.g. "extension/payment/cod"
	 * @return string
	 */
	public function view($route, $data = array()) {
		$route = self::sanitize((string)$route);
		$trigger = $route;

		$code = '';

		$event = $this->registry->has('event') ? $this->registry->get('event') : null;

		$result = $event ? $event->trigger('view/' . $trigger . '/before', array(&$route, &$data, &$code)) : null;

		if ($result && !$result instanceof \Exception) {
			$output = $result;
		} else {
			$template = new \Template((string)$this->registry->get('config')->get('template_engine'));

			foreach ($data as $name => $value) {
				$template->set($name, $value);
			}

			// Template::render() passes its second argument through as the template
			// source, so the storefront file is used even though DIR_TEMPLATE points
			// at the panel theme.
			$output = $template->render($route, $this->source($route));
		}

		$result = $event ? $event->trigger('view/' . $trigger . '/after', array(&$route, &$data, &$output)) : null;

		if ($result && !$result instanceof \Exception) {
			$output = $result;
		}

		return $output;
	}

	/**
	 * Renders another storefront controller and returns its output.
	 *
	 * @return mixed
	 */
	public function controller($route, $data = array()) {
		$result = CatalogControllers::run($this->registry, (string)$route, 'index', array('data' => $data));

		return $result['output'];
	}

	public function library($route) {
		$route = self::sanitize((string)$route);

		$file = DIR_SYSTEM . 'library/' . $route . '.php';
		$class = str_replace('/', '\\', $route);

		if (!is_file($file)) {
			throw new \Exception('Error: Could not load library ' . $route . '!');
		}

		include_once(modification($file));

		$this->registry->set(basename($route), new $class($this->registry));
	}

	public function helper($route) {
		$route = self::sanitize((string)$route);

		$file = DIR_SYSTEM . 'helper/' . $route . '.php';

		if (!is_file($file)) {
			throw new \Exception('Error: Could not load helper ' . $route . '!');
		}

		include_once(modification($file));
	}

	public function config($route) {
		$this->registry->get('config')->load(self::sanitize((string)$route));
	}

	/**
	 * Template source, preferring an OCMOD modification of the storefront file.
	 */
	private function source(string $route): string {
		$relative = 'catalog/view/theme/' . $this->theme_directory . '/template/' . $route . '.twig';

		$candidates = [];

		if (defined('DIR_MODIFICATION')) {
			$candidates[] = DIR_MODIFICATION . $relative;
		}

		$candidates[] = DIR_CATALOG . 'view/theme/' . $this->theme_directory . '/template/' . $route . '.twig';

		foreach ($candidates as $file) {
			if (is_file($file)) {
				return (string)file_get_contents($file);
			}
		}

		throw new \Exception('Error: Could not load storefront template ' . $relative . '!');
	}

	private static function sanitize(string $route): string {
		return (string)preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
	}
}
