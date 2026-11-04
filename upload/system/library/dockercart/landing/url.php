<?php
/**
 * Landing page URL helpers: slug rules and the draft/preview token.
 *
 * Slugs are stored per language in oc_landing_description.slug and mirrored into
 * oc_seo_url (query = landing_id=<id>), so they follow the exact same rules the
 * SEO pipeline expects: lowercase, no slashes, no spaces.
 *
 * A draft/unpublished landing must still be reviewable from the builder, which
 * runs in the admin application and therefore has no storefront session. The
 * preview token is a short-lived HMAC over landing id + expiry, keyed with
 * config_encryption (shared by both applications).
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Landing;

final class Url {
	/** Preview links stay valid for an hour; the builder refreshes them. */
	public const PREVIEW_TOKEN_LIFETIME = 3600;

	public const SLUG_MIN_LENGTH = 3;

	public const SLUG_MAX_LENGTH = 255;

	/** Reserved first segments that would shadow real routes. */
	private const RESERVED = [
		'admin',
		'api',
		'image',
		'index.php',
		'catalog',
		'extension',
		'system',
		'checkout',
		'account',
		'blog',
		'product',
		'information',
	];

	/**
	 * Cyrillic -> Latin, so a Russian/Ukrainian keyword typed in the panel still
	 * produces a clean ASCII URL (the storefront serves en-gb, ru-ua and uk-ua).
	 */
	private const TRANSLITERATION = [
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
		'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
		'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
		'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
		'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
		'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
		// Uppercase forms: the panel may run without mbstring, in which case
		// utf8_strtolower()/strtolower() cannot lower Cyrillic for us.
		'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e',
		'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm',
		'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
		'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'shch',
		'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
		'І' => 'i', 'Ї' => 'yi', 'Є' => 'ye', 'Ґ' => 'g',
	];

	/**
	 * Normalises free-form input into a storable keyword.
	 */
	public static function normalizeSlug(string $slug): string {
		$slug = trim($slug);

		if (function_exists('utf8_strtolower')) {
			$slug = utf8_strtolower($slug);
		} else {
			$slug = strtolower($slug);
		}

		$slug = strtr($slug, self::TRANSLITERATION);

		$slug = str_replace([' ', '_', '\\', '/'], '-', $slug);
		$slug = (string)preg_replace('/[^a-z0-9.\-~]+/', '', $slug);
		$slug = (string)preg_replace('/-{2,}/', '-', $slug);
		$slug = trim($slug, '-');

		return self::truncate($slug, self::SLUG_MAX_LENGTH);
	}

	/**
	 * A slug is valid when it is already normalised, long enough and not reserved.
	 */
	public static function isValidSlug(string $slug): bool {
		if (self::normalizeSlug($slug) !== $slug) {
			return false;
		}

		$length = function_exists('utf8_strlen') ? utf8_strlen($slug) : strlen($slug);

		if ($length < self::SLUG_MIN_LENGTH || $length > self::SLUG_MAX_LENGTH) {
			return false;
		}

		if (in_array($slug, self::RESERVED, true)) {
			return false;
		}

		// A generated-route keyword would collide with the dash -> slash fallback.
		if (!preg_match('/^[a-z0-9][a-z0-9.\-~]*$/', $slug)) {
			return false;
		}

		return true;
	}

	/**
	 * Returns a slug that is not taken yet: "offer", "offer-copy", "offer-copy-2", …
	 *
	 * @param array<int, string> $taken keywords already used by other landings
	 */
	public static function uniqueSlug(string $slug, array $taken): string {
		$slug = self::normalizeSlug($slug);

		if ($slug === '') {
			$slug = 'landing';
		}

		$taken = array_map(static function ($value) {
			return self::normalizeSlug((string)$value);
		}, array_values($taken));

		if (!in_array($slug, $taken, true)) {
			return $slug;
		}

		$candidate = $slug . '-copy';

		if (!in_array($candidate, $taken, true)) {
			return $candidate;
		}

		for ($i = 2; $i < 1000; $i++) {
			$candidate = $slug . '-copy-' . $i;

			if (!in_array($candidate, $taken, true)) {
				return $candidate;
			}
		}

		return $slug . '-' . substr(sha1((string)microtime(true)), 0, 8);
	}

	/**
	 * Signs a preview token for one landing page.
	 */
	public static function signPreviewToken(int $landingId, int $expires, string $secret): string {
		return hash_hmac('sha256', self::tokenPayload($landingId, $expires), $secret);
	}

	/**
	 * Verifies a preview token and its expiry.
	 */
	public static function verifyPreviewToken(int $landingId, string $token, string $secret, ?int $now = null): bool {
		if ($landingId <= 0 || $token === '' || $secret === '') {
			return false;
		}

		$parts = explode('.', $token, 2);

		if (count($parts) !== 2) {
			return false;
		}

		$expires = (int)$parts[0];
		$signature = (string)$parts[1];
		$now = $now === null ? time() : $now;

		if ($expires <= 0 || $expires + 60 < $now) {
			return false;
		}

		// Bound how far in the future a token may reach (a copied link cannot live
		// longer than the configured lifetime, whatever the clock says).
		if ($expires > $now + self::PREVIEW_TOKEN_LIFETIME + 300) {
			return false;
		}

		return hash_equals(self::signPreviewToken($landingId, $expires, $secret), $signature);
	}

	/**
	 * Builds a full preview token ("<expires>.<signature>").
	 */
	public static function createPreviewToken(int $landingId, int $lifetime, string $secret, ?int $now = null): string {
		$now = $now === null ? time() : $now;
		$expires = $now + ($lifetime > 0 ? $lifetime : self::PREVIEW_TOKEN_LIFETIME);

		return $expires . '.' . self::signPreviewToken($landingId, $expires, $secret);
	}

	private static function tokenPayload(int $landingId, int $expires): string {
		return 'landing-preview:' . $landingId . ':' . $expires;
	}

	private static function truncate(string $value, int $length): string {
		if (function_exists('utf8_strlen')) {
			if (utf8_strlen($value) > $length) {
				return utf8_substr($value, 0, $length);
			}

			return $value;
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
