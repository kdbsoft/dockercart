<?php
/**
 * DockerCart customer (storefront) REST API — catalog language strings.
 *
 * The store API runs on the admin MVC tree, so $this->language->load() reads the
 * panel language packs. Some labels the checkout produces come from the
 * *storefront* packs (a gift line is named "Gift with Purchase: …" on the
 * website), and the panel packs may not carry them at all.
 *
 * This helper reads a single key out of the catalog language file for the
 * request language (falling back to en-gb) without touching the shared Language
 * object, so a label can never leak into unrelated admin strings.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CatalogLanguage {
	/**
	 * Catalogue strings per file and language, resolved once per request.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $cache = [];

	/**
	 * One string of a catalog language file, or '' when it does not exist.
	 */
	public static function get(\Registry $registry, string $filename, string $key): string {
		foreach ([self::code($registry), 'en-gb'] as $code) {
			$strings = self::file($filename, $code);

			if (isset($strings[$key])) {
				return (string)$strings[$key];
			}
		}

		return '';
	}

	/**
	 * Every string of a catalog language file for the request language, with the
	 * en-gb pack underneath as the fallback.
	 *
	 * Used by Dockercart\Api\CatalogLoader to give a bridged storefront
	 * controller the language pack it expects (the API runs with the *panel*
	 * language object, which cannot read the catalog tree).
	 *
	 * @return array<string, string>
	 */
	public static function all(\Registry $registry, string $filename): array {
		return array_merge(self::file($filename, 'en-gb'), self::file($filename, self::code($registry)));
	}

	/**
	 * Storefront language code of the request.
	 */
	public static function code(\Registry $registry): string {
		$config = $registry->get('config');

		$language = trim((string)$config->get('config_language'));

		return $language !== '' ? $language : 'en-gb';
	}

	/**
	 * @return array<string, string>
	 */
	private static function file(string $filename, string $code): array {
		$cache_key = $code . '|' . $filename;

		if (isset(self::$cache[$cache_key])) {
			return self::$cache[$cache_key];
		}

		self::$cache[$cache_key] = [];

		$file = DIR_CATALOG . 'language/' . $code . '/' . $filename . '.php';

		if (!is_file($file)) {
			return [];
		}

		$_ = [];

		include $file;

		self::$cache[$cache_key] = is_array($_) ? $_ : [];

		return self::$cache[$cache_key];
	}
}
