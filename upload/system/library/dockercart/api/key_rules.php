<?php
/**
 * DockerCart admin REST API — shared key rules.
 *
 * Validation of the fields of an API key (oc_api_key) that both admin
 * controllers need: the key page (Settings → API → Keys) and the API key
 * panel of the user form.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class KeyRules {
	/**
	 * Expiry date of a key (YYYY-MM-DD, the time part is added by the caller).
	 */
	public const EXPIRES_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

	/**
	 * Whether the expiry date is empty or a real calendar date.
	 */
	public static function isValidExpires(string $date): bool {
		if ($date === '') {
			return true;
		}

		if (!preg_match(self::EXPIRES_PATTERN, $date)) {
			return false;
		}

		[$year, $month, $day] = array_map('intval', explode('-', $date));

		return checkdate($month, $day, $year);
	}

	/**
	 * Whether the allowlist contains only IP addresses or CIDR blocks.
	 */
	public static function isValidIpAllowlist(string $allowlist): bool {
		foreach (preg_split('/[\s,]+/', trim($allowlist)) ?: [] as $entry) {
			$entry = trim($entry);

			if ($entry === '') {
				continue;
			}

			$address = $entry;
			$bits = null;

			if (strpos($entry, '/') !== false) {
				[$address, $bits] = explode('/', $entry, 2);
			}

			$is_valid = filter_var($address, FILTER_VALIDATE_IP) !== false
				&& ($bits === null || (ctype_digit($bits) && (int)$bits <= (strpos($address, ':') !== false ? 128 : 32)));

			if (!$is_valid) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the name of a key is usable.
	 */
	public static function isValidName(string $name): bool {
		$length = mb_strlen($name);

		return $length >= 1 && $length <= 128;
	}
}
