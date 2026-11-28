<?php
/**
 * robots.txt helpers: the `Sitemap:` line belongs to the store URL.
 *
 * The store URL is derived, never configured twice (docker/store-url.sh), so the
 * sitemap advertised in robots.txt must follow it. A first boot that still ran
 * with the `.env.example` placeholder seeded
 * `Sitemap: http://dockercart.local:8080/sitemap.xml`; the file is then owned by
 * the admin (System -> Indexing) and never rewritten by the entrypoint, so the
 * placeholder survived every later fix — search engines were pointed at a host
 * no visitor can resolve, while the storefront itself was fine.
 *
 * Rule: a Sitemap URL pointing at the placeholder host is rewritten to the
 * current store URL. A real host chosen by the operator (a CDN, an alternate
 * domain) is never touched, and nothing outside the Sitemap lines is changed.
 *
 * `docker/store-url.sh` (`store_robots_fix_sitemap`) performs the same rewrite
 * on the file at container boot — both implementations are kept in parity by
 * tools/tests/cases/robots_sitemap.php.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Seo;

final class Robots {
	/** Host of the .env.example placeholder — only a local store uses it. */
	public const PLACEHOLDER_HOST = 'dockercart.local';

	/** Where the store publishes its sitemap, appended to the store URL. */
	public const SITEMAP_PATH = 'sitemap.xml';

	/**
	 * Is this URL pointing at the placeholder host?
	 *
	 * Only the host is compared (port and path ignored): the seeded placeholder
	 * carried `:8080`, and a local store may legitimately keep the default port.
	 *
	 * @param	string	$url
	 * @return	bool
	 */
	public static function isPlaceholder(string $url): bool {
		return self::host($url) === self::PLACEHOLDER_HOST;
	}

	/**
	 * Absolute sitemap URL for a store URL.
	 *
	 * @param	string	$store_url
	 * @return	string
	 */
	public static function sitemapUrl(string $store_url): string {
		$store = rtrim(trim($store_url), '/');

		if ($store === '') {
			return '';
		}

		return $store . '/' . self::SITEMAP_PATH;
	}

	/**
	 * Rewrite our own Sitemap lines to the current store URL.
	 *
	 * A line is "ours" when its host is the placeholder host (the domain was not
	 * known yet when the file was written) or the store's own host (the line was
	 * written over http, before TLS was switched on, or with a stale port). Such
	 * a line is rewritten to `<store URL>/sitemap.xml`, so an HTTPS store never
	 * advertises its sitemap over http. A line pointing at any *other* host (a
	 * CDN, an alternate domain) is chosen by the operator and left alone.
	 *
	 * The body is returned unchanged when there is nothing to do: no usable store
	 * URL, or every Sitemap line is either canonical or foreign.
	 *
	 * @param	string	$body		robots.txt contents
	 * @param	string	$store_url	effective store URL (https when the store has one)
	 * @return	string
	 */
	public static function normalise(string $body, string $store_url): string {
		$target = self::sitemapUrl($store_url);
		$store_host = self::host($store_url);

		if ($target === '' || $store_host === '') {
			return $body;
		}

		$result = preg_replace_callback(
			'/^([ \t]*Sitemap:[ \t]*)(\S+)[ \t]*$/mi',
			static function (array $match) use ($target, $store_host): string {
				$host = self::host($match[2]);

				if ($host === self::PLACEHOLDER_HOST || $host === $store_host) {
					return $match[1] . $target;
				}

				return $match[0];
			},
			$body
		);

		return $result === null ? $body : $result;
	}

	/**
	 * Host part of a URL, lowercased ('' when unparsable).
	 *
	 * @param	string	$url	absolute URL, scheme optional
	 * @return	string
	 */
	private static function host(string $url): string {
		$url = trim($url);

		if ($url === '') {
			return '';
		}

		// parse_url needs a scheme (or a protocol-relative '//') to report the host.
		if (!preg_match('~^[A-Za-z][A-Za-z0-9+.-]*://~', $url)) {
			$url = '//' . ltrim($url, '/');
		}

		$host = parse_url($url, PHP_URL_HOST);

		return is_string($host) ? strtolower($host) : '';
	}
}
