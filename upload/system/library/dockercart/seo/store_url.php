<?php
/**
 * Store base URL for crawlable output (sitemap, robots, feeds).
 *
 * OpenCart's `Url` concatenates its base with `index.php?route=…`
 * (`system/library/url.php`), so the base **must** end with a slash. Handing it a
 * base without one produced `https://store.dockercart.netindex.php/…` in
 * `sitemap.xml` — every <loc> and hreflang broken — which is why the trailing
 * slash is normalised here instead of at each call site.
 *
 * Which base: `config_url` is the http one and `config_ssl` the https one (the
 * entrypoint syncs both from `docker/store-url.sh`). A crawler only ever reaches
 * the https URL when the store has one (an http sitemap entry costs a redirect
 * hop and a canonical mismatch), so https wins whenever it is configured.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Seo;

final class StoreUrl {
	/**
	 * Effective store base URL, always with a trailing slash ('' when unknown).
	 *
	 * @param	string	$config_url	http base (config_url / HTTP_CATALOG)
	 * @param	string	$config_ssl	https base (config_ssl / HTTPS_CATALOG)
	 * @return	string
	 */
	public static function base(string $config_url, string $config_ssl): string {
		$url = trim($config_url);
		$ssl = trim($config_ssl);

		$base = stripos($ssl, 'https://') === 0 ? $ssl : $url;

		if ($base === '') {
			$base = $ssl;
		}

		if ($base === '') {
			return '';
		}

		return rtrim($base, '/') . '/';
	}
}
