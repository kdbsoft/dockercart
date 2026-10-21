<?php
/**
 * DockerCart webhooks — ULID generator.
 *
 * Event ids are `evt_` + a Crockford base32 ULID (26 chars): 48-bit millisecond
 * timestamp followed by 80 random bits. They are globally unique, sortable by
 * time and safe for logs/idempotency keys.
 *
 * Implemented in pure PHP (no bcmath/gmp): the 128 bits are walked in 5-bit
 * groups, with the leading two bits padded so the result is exactly 26 chars.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

final class Ulid {
	private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/**
	 * 26-character Crockford base32 ULID.
	 */
	public static function generate(): string {
		$milliseconds = (int)floor(microtime(true) * 1000);

		// 48-bit big-endian timestamp + 80 random bits = 16 bytes.
		$bytes = (string)hex2bin(str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT)) . random_bytes(10);

		return self::encode($bytes);
	}

	/**
	 * Crockford base32 of a 16-byte string (padded to 130 bits).
	 */
	public static function encode(string $bytes): string {
		if (strlen($bytes) !== 16) {
			throw new \InvalidArgumentException('A ULID needs exactly 16 bytes.');
		}

		$bits = '';

		for ($i = 0; $i < 16; $i++) {
			$bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
		}

		// 26 chars × 5 bits = 130; the two leading bits are always zero.
		$bits = str_pad($bits, 130, '0', STR_PAD_LEFT);

		$encoded = '';

		for ($offset = 0; $offset < 130; $offset += 5) {
			$encoded .= self::ALPHABET[bindec(substr($bits, $offset, 5))];
		}

		return $encoded;
	}
}
