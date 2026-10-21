<?php
/**
 * DockerCart admin REST API — response value formatting.
 *
 * Keeps the API representation of database values consistent across resources:
 * dates are ISO-8601, money keeps the 4 decimals used by the schema, booleans
 * stay real JSON booleans.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Serializer {
	/**
	 * "2026-10-21 12:34:56" => "2026-10-21T12:34:56+03:00"
	 */
	public static function datetime($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
			return null;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('c', $timestamp);
	}

	/**
	 * Date-only value => "2026-10-21" (zero dates become null).
	 */
	public static function date($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00') {
			return null;
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? null : date('Y-m-d', $timestamp);
	}

	/**
	 * Price/amount as a fixed point string (the schema keeps 4 decimals).
	 */
	public static function money($value): string {
		return number_format((float)$value, 4, '.', '');
	}

	/**
	 * Quantity as a float with up to 2 decimals, trimmed of trailing zeros.
	 */
	public static function quantity($value): float {
		return round((float)$value, 2);
	}

	public static function bool($value): bool {
		return (bool)(int)$value;
	}

	public static function int($value): int {
		return (int)$value;
	}

	/**
	 * Converts a list of DB rows into a list of ids (e.g. category/store links).
	 */
	public static function ids(array $rows, string $column): array {
		$ids = [];

		foreach ($rows as $row) {
			$ids[] = (int)($row[$column] ?? 0);
		}

		return $ids;
	}

	/**
	 * Free text written to an HTML-rendered column the way the panel stores it.
	 *
	 * Request::clean() applies htmlspecialchars() to every posted value, and the
	 * storefront decodes descriptions on render (html_entity_decode). The API
	 * bypasses Request::clean() (raw JSON), so it must apply the same encoding on
	 * write and the matching decoding on read — otherwise a GET → PUT round trip
	 * would double-escape the content.
	 */
	public static function encodeText($value): string {
		return htmlspecialchars((string)$value, ENT_COMPAT, 'UTF-8');
	}

	/**
	 * Inverse of encodeText(): returns the HTML an admin/integrator expects to
	 * see, tolerating content that was escaped more than once by older imports.
	 */
	public static function decodeText($value): string {
		$decoded = (string)$value;

		for ($i = 0; $i < 5; $i++) {
			$next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($next === $decoded) {
				break;
			}

			$decoded = $next;
		}

		return $decoded;
	}
}
