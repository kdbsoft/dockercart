<?php
/**
 * SSRF guard: URL syntax, address classification and DNS resolution.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Ssrf;

return [
	'ssrf: accepts a plain public https endpoint' => static function (): void {
		$result = Ssrf::validateUrl('https://erp.example.com/hooks/dockercart?a=1');

		Assert::true($result['ok'], 'expected a valid URL, got ' . $result['error']);
		Assert::same('erp.example.com', $result['host']);
		Assert::same(443, $result['port']);
		Assert::same('https://erp.example.com/hooks/dockercart?a=1', $result['normalized']);
	},

	'ssrf: http defaults to port 80' => static function (): void {
		$result = Ssrf::validateUrl('http://erp.example.com/hook');

		Assert::true($result['ok'], 'expected a valid URL, got ' . $result['error']);
		Assert::same(80, $result['port']);
	},

	'ssrf: rejects non-http schemes' => static function (): void {
		Assert::same('invalid_scheme', Ssrf::validateUrl('ftp://erp.example.com/hook')['error']);
		Assert::same('invalid_scheme', Ssrf::validateUrl('file:///etc/passwd')['error']);
		Assert::same('invalid_scheme', Ssrf::validateUrl('gopher://erp.example.com/')['error']);
	},

	'ssrf: rejects credentials in the URL' => static function (): void {
		Assert::same('credentials_not_allowed', Ssrf::validateUrl('https://user:pass@erp.example.com/hook')['error']);
	},

	'ssrf: rejects non-standard ports' => static function (): void {
		Assert::same('invalid_port', Ssrf::validateUrl('https://erp.example.com:8443/hook')['error']);
		Assert::same('invalid_port', Ssrf::validateUrl('http://erp.example.com:6379/hook')['error']);
	},

	'ssrf: rejects loopback, private and link-local literals' => static function (): void {
		foreach ([
			'http://127.0.0.1/hook',
			'http://127.1.2.3/hook',
			'http://10.0.0.5/hook',
			'http://172.16.4.2/hook',
			'http://192.168.1.1/hook',
			'http://169.254.169.254/latest/meta-data',
			'http://100.64.0.1/hook',
			'http://0.0.0.0/hook',
			'http://[::1]/hook',
			'http://[fe80::1]/hook',
			'http://[fc00::1]/hook',
			'http://[::ffff:127.0.0.1]/hook',
		] as $url) {
			$result = Ssrf::validateUrl($url);

			Assert::false($result['ok'], $url . ' must be refused');
			Assert::same('ssrf_blocked', $result['error'], $url);
		}
	},

	'ssrf: rejects localhost by name' => static function (): void {
		Assert::same('ssrf_blocked', Ssrf::validateUrl('http://localhost/hook')['error']);
	},

	'ssrf: rejects decimal/hex spellings curl would resolve as an IP' => static function (): void {
		Assert::same('invalid_host', Ssrf::validateUrl('http://2130706433/hook')['error']);
		Assert::same('invalid_host', Ssrf::validateUrl('http://0x7f000001/hook')['error']);
	},

	'ssrf: rejects malformed URLs and hosts' => static function (): void {
		Assert::same('invalid_url', Ssrf::validateUrl('')['error']);
		Assert::same('invalid_url', Ssrf::validateUrl('erp.example.com/hook')['error']);
		Assert::same('invalid_host', Ssrf::validateUrl('https://erp_example!.com/hook')['error']);
		Assert::same('invalid_url', Ssrf::validateUrl('https://' . str_repeat('a', 600) . '.com/')['error']);
	},

	'ssrf: isPublicIp classifies IPv4 ranges' => static function (): void {
		foreach (['0.0.0.0', '10.1.2.3', '100.64.0.1', '127.0.0.1', '169.254.169.254', '172.16.0.1', '192.168.0.1', '198.18.0.1', '203.0.113.5', '224.0.0.1', '240.0.0.1'] as $ip) {
			Assert::false(Ssrf::isPublicIp($ip), $ip . ' must be blocked');
		}

		foreach (['8.8.8.8', '1.1.1.1', '93.184.216.34'] as $ip) {
			Assert::true(Ssrf::isPublicIp($ip), $ip . ' must be allowed');
		}
	},

	'ssrf: isPublicIp classifies IPv6 ranges' => static function (): void {
		foreach (['::', '::1', 'fc00::1', 'fd12:3456::1', 'fe80::1', '2001:db8::1', '::ffff:192.168.0.1', '64:ff9b::1.2.3.4'] as $ip) {
			Assert::false(Ssrf::isPublicIp($ip), $ip . ' must be blocked');
		}

		foreach (['2001:4860:4860::8888', '2606:4700:4700::1111'] as $ip) {
			Assert::true(Ssrf::isPublicIp($ip), $ip . ' must be allowed');
		}
	},

	'ssrf: check() pins an IP for a public hostname' => static function (): void {
		if (!function_exists('dns_get_record') || @dns_get_record('example.com', DNS_A) === []) {
			throw new SkippedTest('no DNS available in this environment');
		}

		$result = Ssrf::check('https://example.com/hook');

		Assert::true($result['ok'], 'expected example.com to pass, got ' . $result['error']);
		Assert::true($result['ip'] !== '', 'the validated address must be pinned');
		Assert::true(Ssrf::isPublicIp($result['ip']), 'pinned address must be public');
	},

	'ssrf: check() refuses a hostname that resolves to a private address' => static function (): void {
		// localhost is refused by name before any DNS lookup.
		$result = Ssrf::check('http://localhost/hook');

		Assert::false($result['ok']);
		Assert::same('ssrf_blocked', $result['error']);
	},

	'ssrf: the development override relaxes addresses and ports' => static function (): void {
		$previous = getenv('WEBHOOK_ALLOW_PRIVATE');

		putenv('WEBHOOK_ALLOW_PRIVATE=1');

		try {
			Assert::true(Ssrf::allowPrivate());
			Assert::true(Ssrf::validateUrl('http://127.0.0.1:9998/hook')['ok'], 'dev override must allow a local receiver');
			Assert::true(Ssrf::isPublicIp('127.0.0.1'));
			Assert::true(Ssrf::check('http://127.0.0.1:9998/hook')['ok']);
		} finally {
			if ($previous === false) {
				putenv('WEBHOOK_ALLOW_PRIVATE');
			} else {
				putenv('WEBHOOK_ALLOW_PRIVATE=' . $previous);
			}
		}

		Assert::false(Ssrf::allowPrivate());
		Assert::false(Ssrf::validateUrl('http://127.0.0.1:9998/hook')['ok']);
		Assert::false(Ssrf::isPublicIp('127.0.0.1'));
	},
];
