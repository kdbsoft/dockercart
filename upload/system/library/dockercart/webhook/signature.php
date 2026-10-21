<?php
/**
 * DockerCart webhooks — HMAC request signatures.
 *
 * Header contract:
 *   X-DockerCart-Timestamp: <unix seconds>
 *   X-DockerCart-Signature: sha256=<hex>
 *
 * The signed string is `timestamp . "." . raw_body` (not JSON-normalized), so a
 * receiver must verify against the exact bytes it received. Retries get a fresh
 * timestamp/signature but the same body and event id.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

final class Signature {
	public const HEADER = 'X-DockerCart-Signature';
	public const TIMESTAMP_HEADER = 'X-DockerCart-Timestamp';

	public static function now(): int {
		return time();
	}

	public static function compute(string $timestamp, string $body, string $secret): string {
		return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
	}
}
