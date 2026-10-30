<?php
/**
 * DockerCart admin REST API — API key repository.
 *
 * Token format: "dcapi_" + 48 hex chars. Only the SHA-256 of the token is
 * stored (oc_api_key.token_hash, unique) so a database leak does not hand out
 * working credentials; the plain token is displayed to the admin exactly once
 * (create / rotate).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class KeyRepository {
	public const TOKEN_PREFIX = 'dcapi_';
	public const PREFIX_LENGTH = 14;

	/**
	 * Pre-authentication request budget per client IP (per minute) when
	 * config_api_anon_rate_limit is not set.
	 */
	public const DEFAULT_ANON_RATE_LIMIT = 60;

	private \DB $db;
	private \Cache $cache;
	private \Config $config;

	public function __construct(\Registry $registry) {
		$this->db = $registry->get('db');
		$this->cache = $registry->get('cache');
		$this->config = $registry->get('config');
	}

	/**
	 * Builds a fresh token and its storable representation.
	 *
	 * @return array{token: string, token_hash: string, prefix: string}
	 */
	public static function generateToken(): array {
		$token = self::TOKEN_PREFIX . bin2hex(random_bytes(24));

		return [
			'token'      => $token,
			'token_hash' => hash('sha256', $token),
			'prefix'     => substr($token, 0, self::PREFIX_LENGTH),
		];
	}

	public static function hash(string $token): string {
		return hash('sha256', $token);
	}

	/**
	 * Looks a key up by its plain token.
	 */
	public function findByToken(string $token): ?array {
		if ($token === '' || strlen($token) > 128) {
			return null;
		}

		$hash = self::hash($token);

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_key` WHERE `token_hash` = '" . $this->db->escape($hash) . "'");

		if (!$query->num_rows) {
			return null;
		}

		// Constant-time confirmation (the lookup itself is an indexed equality).
		if (!hash_equals((string)$query->row['token_hash'], $hash)) {
			return null;
		}

		return $query->row;
	}

	public function getKey(int $api_key_id): ?array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_key` WHERE `api_key_id` = '" . (int)$api_key_id . "'");

		return $query->num_rows ? $query->row : null;
	}

	public function getGroup(int $user_group_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "user_group` WHERE `user_group_id` = '" . (int)$user_group_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * The owner of a key (an admin user). An empty result means the user does
	 * not exist any more — the key must then stop working.
	 */
	public function getOwner(int $user_id): array {
		$query = $this->db->query("SELECT `user_id`, `username`, `status`, `user_group_id` FROM `" . DB_PREFIX . "user` WHERE `user_id` = '" . (int)$user_id . "'");

		return $query->num_rows ? $query->row : [];
	}

	/**
	 * Records usage without bumping date_modified more than once a minute.
	 */
	public function touch(array $key, string $ip): void {
		$api_key_id = (int)($key['api_key_id'] ?? 0);

		if (!$api_key_id) {
			return;
		}

		$last_used_at = (string)($key['last_used_at'] ?? '');
		$last_used_ip = (string)($key['last_used_ip'] ?? '');

		if ($ip === $last_used_ip && $last_used_at !== '' && (time() - strtotime($last_used_at)) < 60) {
			return;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "api_key` SET `last_used_at` = NOW(), `last_used_ip` = '" . $this->db->escape($ip) . "' WHERE `api_key_id` = '" . $api_key_id . "'");
	}

	/**
	 * Whether the client IP is allowed for the key. Empty allowlist = any IP.
	 */
	public function ipAllowed(array $key, string $ip): bool {
		$allowlist = trim((string)($key['ip_allowlist'] ?? ''));

		if ($allowlist === '') {
			return true;
		}

		if ($ip === '') {
			return false;
		}

		foreach (preg_split('/[\s,]+/', $allowlist) ?: [] as $entry) {
			$entry = trim($entry);

			if ($entry === '') {
				continue;
			}

			if ($this->ipMatches($ip, $entry)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fixed-window per-key rate limiting (1 minute window, cache backed).
	 *
	 * @return array{limit: int, remaining: int, reset: int, exceeded: bool}
	 */
	public function rateLimit(array $key): array {
		$limit = (int)($key['rate_limit'] ?? 0);

		if ($limit <= 0) {
			$limit = (int)$this->config->get('config_api_rate_limit');
		}

		if ($limit <= 0) {
			$limit = 120;
		}

		$window = (int)floor(time() / 60);
		$reset = ($window + 1) * 60;
		$cache_key = 'api_rate_' . (int)($key['api_key_id'] ?? 0) . '_' . $window;

		$count = (int)$this->cache->get($cache_key) + 1;

		$this->cache->set($cache_key, $count);

		return [
			'limit'     => $limit,
			'remaining' => max(0, $limit - $count),
			'reset'     => $reset,
			'exceeded'  => ($count > $limit),
		];
	}

	/**
	 * Pre-authentication throttle, per client IP.
	 *
	 * Runs *before* the key lookup, so a client without a valid token cannot turn
	 * the API into a database amplifier (one oc_api_key query per request) or write
	 * one journal row per attempt. Keys get their own, separate budget in
	 * rateLimit().
	 *
	 * @return array{limit: int, remaining: int, reset: int, exceeded: bool}
	 */
	public function rateLimitAnonymous(string $ip): array {
		$limit = (int)$this->config->get('config_api_anon_rate_limit');

		if ($limit <= 0) {
			$limit = self::DEFAULT_ANON_RATE_LIMIT;
		}

		$window = (int)floor(time() / 60);
		$reset = ($window + 1) * 60;

		// Hashed so the key is safe for every cache backend (an IPv6 literal
		// contains ':' which the file adaptor strips) and does not leak the IP.
		$cache_key = 'api_anon_rate_' . sha1($ip === '' ? 'unknown' : $ip) . '_' . $window;

		$count = (int)$this->cache->get($cache_key) + 1;

		$this->cache->set($cache_key, $count);

		return [
			'limit'     => $limit,
			'remaining' => max(0, $limit - $count),
			'reset'     => $reset,
			'exceeded'  => ($count > $limit),
		];
	}

	/**
	 * Per-key usage counter for the API keys list page.
	 */
	public function getRequestCounts(int $days = 1): array {
		$days = max(1, $days);

		$query = $this->db->query("SELECT `api_key_id`, COUNT(*) AS `total` FROM `" . DB_PREFIX . "api_log` WHERE `date_added` >= DATE_SUB(NOW(), INTERVAL " . $days . " DAY) GROUP BY `api_key_id`");

		$counts = [];

		foreach ($query->rows as $row) {
			$counts[(int)$row['api_key_id']] = (int)$row['total'];
		}

		return $counts;
	}

	/**
	 * Matches a single IP against an exact address or a CIDR block (v4 + v6).
	 */
	private function ipMatches(string $ip, string $rule): bool {
		if (strpos($rule, '/') === false) {
			$rule_ip = @inet_pton($rule);
			$client_ip = @inet_pton($ip);

			return $rule_ip !== false && $client_ip !== false && $rule_ip === $client_ip;
		}

		[$subnet, $bits] = explode('/', $rule, 2);

		if (!is_numeric($bits)) {
			return false;
		}

		$subnet_bin = @inet_pton($subnet);
		$client_bin = @inet_pton($ip);

		if ($subnet_bin === false || $client_bin === false || strlen($subnet_bin) !== strlen($client_bin)) {
			return false;
		}

		$bits = (int)$bits;
		$max_bits = strlen($subnet_bin) * 8;

		if ($bits < 0 || $bits > $max_bits) {
			return false;
		}

		$full_bytes = intdiv($bits, 8);
		$rest_bits = $bits % 8;

		if ($full_bytes > 0 && substr($subnet_bin, 0, $full_bytes) !== substr($client_bin, 0, $full_bytes)) {
			return false;
		}

		if ($rest_bits === 0) {
			return true;
		}

		$mask = (0xFF << (8 - $rest_bits)) & 0xFF;

		return (ord($subnet_bin[$full_bytes]) & $mask) === (ord($client_bin[$full_bytes]) & $mask);
	}
}
