<?php
/**
 * DockerCart webhooks — subscription secrets.
 *
 * Each subscription signs with its own secret (`whsec_` + 64 hex chars). The
 * secret is encrypted at rest with the store's `config_encryption` key
 * (AES-256-GCM, system/library/encryption.php) and is only ever returned once:
 * on create or on rotate. GET responses expose `secret_hint` (the first 10
 * chars) so an operator can identify the right secret without revealing it.
 *
 * The delivery worker decrypts the secret right before signing; if the
 * encryption key changed or the row is corrupted, decryption fails and the
 * delivery is marked `secret_unavailable` instead of being sent unsigned.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

final class Secret {
	public const PREFIX = 'whsec_';

	/** Visible prefix length for the support hint. */
	private const HINT_LENGTH = 10;

	/**
	 * @return array{secret: string, hint: string}
	 */
	public static function generate(): array {
		$secret = self::PREFIX . bin2hex(random_bytes(32));

		return [
			'secret' => $secret,
			'hint'   => self::hint($secret),
		];
	}

	public static function hint(string $secret): string {
		return substr($secret, 0, self::HINT_LENGTH);
	}

	public static function encrypt(string $secret, string $encryption_key): string {
		$encryption = new \Encryption();

		return (string)$encryption->encrypt($encryption_key, $secret);
	}

	/**
	 * Returns null when the stored value cannot be decrypted (wrong/rotated
	 * config_encryption, truncated value, malformed base64).
	 */
	public static function decrypt(string $encrypted, string $encryption_key): ?string {
		if ($encrypted === '') {
			return null;
		}

		try {
			$secret = (new \Encryption())->decrypt($encryption_key, $encrypted);
		} catch (\Throwable $e) {
			return null;
		}

		return is_string($secret) && $secret !== '' ? $secret : null;
	}
}
