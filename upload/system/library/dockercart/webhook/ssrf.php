<?php
/**
 * DockerCart webhooks — SSRF guard.
 *
 * Webhook URLs are attacker-controlled input (anyone allowed to create a
 * subscription can point it anywhere). The guard therefore:
 *
 *   1. validates the URL syntax at save time (scheme, userinfo, port, host);
 *   2. resolves the hostname again at send time (A + AAAA) and rejects the
 *      delivery when *any* address is loopback/private/link-local/CGNAT/ULA/
 *      documentation/NAT64 — preventing DNS rebinding from reaching the
 *      internal network;
 *   3. returns the first validated IP so Sender can pin it with
 *      CURLOPT_RESOLVE (no second DNS lookup between check and request).
 *
 * `WEBHOOK_ALLOW_PRIVATE=1` (environment, development only) bypasses the
 * address checks so a local receiver can be used while testing. It is read from
 * the process env, never from the database, and only the worker sends.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

final class Ssrf {
	/** Ports a webhook may use. */
	private const PORTS = [80, 443];

	/** Maximum stored URL length (matches oc_dockercart_webhook_subscription.url). */
	private const MAX_URL_LENGTH = 500;

	private const HOSTNAME_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i';

	/** [network, mask, label] */
	private const BLOCKED_V4 = [
		['0.0.0.0', 8, 'this-network'],
		['10.0.0.0', 8, 'private'],
		['100.64.0.0', 10, 'cgnat'],
		['127.0.0.0', 8, 'loopback'],
		['169.254.0.0', 16, 'link-local'],
		['172.16.0.0', 12, 'private'],
		['192.0.0.0', 24, 'ietf-protocol'],
		['192.0.2.0', 24, 'documentation'],
		['192.168.0.0', 16, 'private'],
		['198.18.0.0', 15, 'benchmark'],
		['198.51.100.0', 24, 'documentation'],
		['203.0.113.0', 24, 'documentation'],
		['224.0.0.0', 4, 'multicast'],
		['240.0.0.0', 4, 'reserved'],
	];

	/** [network, prefix bits, label] */
	private const BLOCKED_V6 = [
		['::', 128, 'unspecified'],
		['::1', 128, 'loopback'],
		['fc00::', 7, 'ula'],
		['fe80::', 10, 'link-local'],
		['fec0::', 10, 'site-local'],
		['2001:db8::', 32, 'documentation'],
		['2001:10::', 28, 'orchid'],
		['64:ff9b::', 96, 'nat64'],
		['2002::', 16, '6to4'],
	];

	public static function allowPrivate(): bool {
		return getenv('WEBHOOK_ALLOW_PRIVATE') === '1';
	}

	/**
	 * Syntax-only validation (used by the panel/API at save time).
	 *
	 * @return array{ok: bool, error: string, host?: string, port?: int, scheme?: string, normalized?: string}
	 */
	public static function validateUrl(string $url): array {
		$url = trim($url);

		if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
			return ['ok' => false, 'error' => 'invalid_url'];
		}

		$parts = @parse_url($url);

		if (!is_array($parts)) {
			return ['ok' => false, 'error' => 'invalid_url'];
		}

		// The scheme is checked before the host so `file:///etc/passwd` is
		// reported as a scheme problem instead of a generic parse failure.
		$scheme = strtolower((string)($parts['scheme'] ?? ''));

		if ($scheme === '') {
			return ['ok' => false, 'error' => 'invalid_url'];
		}

		if (!in_array($scheme, ['http', 'https'], true)) {
			return ['ok' => false, 'error' => 'invalid_scheme'];
		}

		if (empty($parts['host'])) {
			return ['ok' => false, 'error' => 'invalid_url'];
		}

		if (isset($parts['user']) || isset($parts['pass'])) {
			return ['ok' => false, 'error' => 'credentials_not_allowed'];
		}

		$port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);

		// Ports are restricted in production; the development override exists so a
		// local receiver (e.g. `php -S 127.0.0.1:9998`) can be reached.
		if (!in_array($port, self::PORTS, true) && !self::allowPrivate()) {
			return ['ok' => false, 'error' => 'invalid_port'];
		}

		$host = (string)$parts['host'];

		// IPv6 literals come in brackets from parse_url().
		if (strlen($host) > 2 && $host[0] === '[' && substr($host, -1) === ']') {
			$host = substr($host, 1, -1);
		}

		$host = rtrim($host, '.');

		if ($host === '') {
			return ['ok' => false, 'error' => 'invalid_host'];
		}

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			if (!self::isPublicIp($host) && !self::allowPrivate()) {
				return ['ok' => false, 'error' => 'ssrf_blocked'];
			}
		} else {
			if (!preg_match(self::HOSTNAME_PATTERN, $host)) {
				return ['ok' => false, 'error' => 'invalid_host'];
			}

			// Decimal/hex IPv4 spellings curl might still interpret as an IP.
			if (preg_match('/^\d+$/', $host) || preg_match('/^0x[0-9a-f]+$/i', $host)) {
				return ['ok' => false, 'error' => 'invalid_host'];
			}

			if (strtolower($host) === 'localhost') {
				if (!self::allowPrivate()) {
					return ['ok' => false, 'error' => 'ssrf_blocked'];
				}
			}
		}

		return [
			'ok'         => true,
			'error'      => '',
			'host'       => $host,
			'port'       => $port,
			'scheme'     => $scheme,
			'normalized' => $scheme . '://' . (strpos($host, ':') !== false ? '[' . $host . ']' : $host) . (isset($parts['port']) ? ':' . $port : '') . (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''),
		];
	}

	/**
	 * Full check: syntax + DNS/IP validation at send time.
	 *
	 * @return array{ok: bool, error: string, host: string, port: int, ip: string, url: string}
	 */
	public static function check(string $url): array {
		$validation = self::validateUrl($url);

		if (!$validation['ok']) {
			return [
				'ok'    => false,
				'error' => (string)$validation['error'],
				'host'  => (string)($validation['host'] ?? ''),
				'port'  => (int)($validation['port'] ?? 0),
				'ip'    => '',
				'url'   => $url,
			];
		}

		$host = (string)$validation['host'];
		$port = (int)$validation['port'];

		if (self::allowPrivate()) {
			return ['ok' => true, 'error' => '', 'host' => $host, 'port' => $port, 'ip' => '', 'url' => (string)$validation['normalized']];
		}

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return ['ok' => true, 'error' => '', 'host' => $host, 'port' => $port, 'ip' => $host, 'url' => (string)$validation['normalized']];
		}

		$ips = self::resolve($host);

		if (!$ips) {
			return ['ok' => false, 'error' => 'dns_failed', 'host' => $host, 'port' => $port, 'ip' => '', 'url' => $url];
		}

		foreach ($ips as $ip) {
			if (!self::isPublicIp($ip)) {
				return ['ok' => false, 'error' => 'ssrf_blocked', 'host' => $host, 'port' => $port, 'ip' => '', 'url' => $url];
			}
		}

		return ['ok' => true, 'error' => '', 'host' => $host, 'port' => $port, 'ip' => $ips[0], 'url' => (string)$validation['normalized']];
	}

	/**
	 * @return array<int, string> resolved A/AAAA addresses (empty on failure)
	 */
	public static function resolve(string $host): array {
		$ips = [];
		$records = @dns_get_record($host, DNS_A | DNS_AAAA);

		if (is_array($records)) {
			foreach ($records as $record) {
				if (!empty($record['ip'])) {
					$ips[] = (string)$record['ip'];
				}

				if (!empty($record['ipv6'])) {
					$ips[] = (string)$record['ipv6'];
				}
			}
		}

		if (!$ips) {
			$ipv4 = @gethostbynamel($host);

			if (is_array($ipv4)) {
				$ips = array_map('strval', $ipv4);
			}
		}

		return array_values(array_unique($ips));
	}

	public static function isPublicIp(string $ip): bool {
		if (self::allowPrivate()) {
			return true;
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			foreach (self::BLOCKED_V4 as [$network, $bits, $label]) {
				if (self::ipv4InCidr($ip, $network, $bits)) {
					return false;
				}
			}

			return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}

		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			return false;
		}

		// IPv4-mapped IPv6 (::ffff:127.0.0.1 and ::ffff:7f00:1) -> validate the v4 part.
		$binary = inet_pton($ip);

		if (is_string($binary) && strlen($binary) === 16 && substr($binary, 0, 10) === str_repeat("\0", 10) && substr($binary, 10, 2) === "\xff\xff") {
			return self::isPublicIp((string)inet_ntop(substr($binary, 12, 4)));
		}

		foreach (self::BLOCKED_V6 as [$network, $bits, $label]) {
			if (self::ipv6InCidr($ip, (string)$network, (int)$bits)) {
				return false;
			}
		}

		return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
	}

	private static function ipv4InCidr(string $ip, string $network, int $bits): bool {
		$ip_long = ip2long($ip);
		$net_long = ip2long($network);

		if ($ip_long === false || $net_long === false) {
			return false;
		}

		$mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;

		return ($ip_long & $mask) === ($net_long & $mask);
	}

	private static function ipv6InCidr(string $ip, string $network, int $bits): bool {
		$ip_binary = inet_pton($ip);
		$net_binary = inet_pton($network);

		if (!is_string($ip_binary) || !is_string($net_binary) || strlen($ip_binary) !== 16 || strlen($net_binary) !== 16) {
			return false;
		}

		$bytes = intdiv($bits, 8);
		$remainder = $bits % 8;

		if ($bytes > 0 && substr($ip_binary, 0, $bytes) !== substr($net_binary, 0, $bytes)) {
			return false;
		}

		if ($remainder === 0) {
			return true;
		}

		$mask = (0xFF << (8 - $remainder)) & 0xFF;

		return (ord($ip_binary[$bytes]) & $mask) === (ord($net_binary[$bytes]) & $mask);
	}
}
