<?php
/**
 * ReviewSpam
 *
 * Lightweight anti-abuse helpers for the review submission flow:
 * honeypot field, per-IP / per-customer rate limiting and cheap content
 * heuristics. The DB lookups are performed by the caller and the counts are
 * passed in, so this class stays testable without a registry.
 */
class ReviewSpam {
	/**
	 * A honeypot field must be left empty by humans; bots auto-fill it.
	 *
	 * @param mixed $value
	 */
	public static function honeypotFilled($value): bool {
		return (string)$value !== '';
	}

	/**
	 * Whether the submission exceeds the allowed rate limit.
	 */
	public static function isRateLimited(int $count, int $max): bool {
		return $count >= max(1, $max);
	}

	/**
	 * Cheap content heuristics: flag reviews that carry a suspicious amount
	 * of links or embedded e-mail addresses.
	 */
	public static function containsSpamPatterns(string $text): bool {
		$text = (string)$text;

		if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/i', $text)) {
			return true;
		}

		preg_match_all('/(?:https?:\/\/|www\.)[^\s<>]+/i', $text, $matches);

		if (count($matches[0]) > 2) {
			return true;
		}

		return false;
	}

	/**
	 * Deterministic opaque hash of a client IP for rate-limit lookups.
	 */
	public static function ipHash(string $ip): string {
		return md5(trim($ip));
	}
}
