<?php
/**
 * Shared UTF-8 text helpers for the landing library.
 *
 * The landing pipeline runs in three different applications (admin, catalog,
 * tests) and used to carry four private copies of these two helpers with
 * slightly diverging fallbacks. One class, one behaviour:
 *
 *   Text::length()   - character count (utf8_strlen when available)
 *   Text::truncate() - character-bounded cut (utf8_substr when available)
 *
 * The OpenCart utf8 helpers exist in every application that loads
 * system/helper/utf8.php; strlen()/substr() is only a byte fallback for the
 * test harness, which boots without it.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Landing;

final class Text {
	/**
	 * Character length of a UTF-8 string.
	 */
	public static function length(string $value): int {
		return function_exists('utf8_strlen') ? utf8_strlen($value) : strlen($value);
	}

	/**
	 * Cut to at most $length characters; $length <= 0 keeps the value as is.
	 */
	public static function truncate(string $value, int $length): string {
		if ($length <= 0) {
			return $value;
		}

		if (function_exists('utf8_strlen')) {
			if (utf8_strlen($value) > $length) {
				return utf8_substr($value, 0, $length);
			}

			return $value;
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
