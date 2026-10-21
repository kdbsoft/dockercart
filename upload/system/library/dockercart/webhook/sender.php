<?php
/**
 * DockerCart webhooks — HTTP sender.
 *
 * Only the delivery worker uses this class. It pins the validated IP with
 * CURLOPT_RESOLVE (so the DNS answer checked by Ssrf is the one curl connects
 * to), never follows redirects, ignores proxy environment variables, and caps
 * the stored response body. TLS verification stays on.
 *
 * @package    DockerCart
 * @subpackage Webhook
 */

declare(strict_types=1);

namespace Dockercart\Webhook;

/** @property \Config $config */
final class Sender {
	/** How much of the receiver response is kept in the delivery journal. */
	public const MAX_RESPONSE_BYTES = 65536;

	/** How much of the response is stored in the DB (after truncation). */
	public const STORED_RESPONSE_BYTES = 2048;

	public const USER_AGENT = 'DockerCart-Webhook/1.0';

	/** @var \Registry */
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function __get($key) {
		return $this->registry->get($key);
	}

	/**
	 * @param array<int, string> $headers full header lines ("Name: value")
	 * @return array{ok: bool, status: int, body: string, error: string, duration_ms: int, truncated: bool}
	 */
	public function send(string $url, array $headers, string $body, int $timeout = 10): array {
		$target = Ssrf::check($url);

		if (!$target['ok']) {
			return [
				'ok'          => false,
				'status'      => 0,
				'body'        => '',
				'error'       => (string)$target['error'],
				'duration_ms' => 0,
				'truncated'   => false,
			];
		}

		$timeout = max(1, min(60, $timeout));
		$response = '';
		$truncated = false;

		$curl = curl_init();

		$options = [
			CURLOPT_URL            => (string)$target['url'],
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HEADER         => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS      => 0,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_USERAGENT      => self::USER_AGENT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_PROXY          => '',
			CURLOPT_NOPROXY        => '*',
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_WRITEFUNCTION  => static function ($handle, string $chunk) use (&$response, &$truncated): int {
				if (strlen($response) >= Sender::MAX_RESPONSE_BYTES) {
					$truncated = true;

					return strlen($chunk);
				}

				$remaining = Sender::MAX_RESPONSE_BYTES - strlen($response);
				$response .= substr($chunk, 0, $remaining);

				if (strlen($chunk) > $remaining) {
					$truncated = true;
				}

				return strlen($chunk);
			},
		];

		// Pin the validated address. IPv6 literals must be bracketed in the
		// CURLOPT_RESOLVE entry; IP-literal hosts are already pinned by the URL.
		if (!empty($target['ip']) && !filter_var($target['host'], FILTER_VALIDATE_IP)) {
			$address = strpos($target['ip'], ':') !== false ? '[' . $target['ip'] . ']' : $target['ip'];

			$options[CURLOPT_RESOLVE] = [$target['host'] . ':' . (int)$target['port'] . ':' . $address];
		}

		if (defined('CURLOPT_PROTOCOLS_STR')) {
			$options[CURLOPT_PROTOCOLS_STR] = 'http,https';
			$options[CURLOPT_REDIR_PROTOCOLS_STR] = '';
		} else {
			$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
			$options[CURLOPT_REDIR_PROTOCOLS] = 0;
		}

		curl_setopt_array($curl, $options);

		$started = microtime(true);
		$result = curl_exec($curl);
		$duration_ms = (int)round((microtime(true) - $started) * 1000);

		$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = (string)curl_error($curl);
		$errno = (int)curl_errno($curl);

		curl_close($curl);

		$ok = $errno === 0 && $status >= 200 && $status < 300;

		return [
			'ok'          => $ok,
			'status'      => $status,
			'body'        => self::scrub($response, self::STORED_RESPONSE_BYTES),
			'error'       => $ok ? '' : self::errorCode($errno, $status, $error),
			'duration_ms' => $duration_ms,
			'truncated'   => $truncated || $result === false,
		];
	}

	/**
	 * Networks/timeouts are retried; a real HTTP answer is classified by the
	 * caller. Returns a short machine code for the journal.
	 */
	private static function errorCode(int $errno, int $status, string $error): string {
		if ($errno === CURLE_OPERATION_TIMEDOUT) {
			return 'timeout';
		}

		if ($errno === CURLE_COULDNT_CONNECT || $errno === CURLE_COULDNT_RESOLVE_HOST) {
			return 'connection_failed';
		}

		if ($errno !== 0) {
			return 'curl_' . $errno;
		}

		if ($status >= 400) {
			return 'http_' . $status;
		}

		return $error !== '' ? 'curl_error' : 'unsuccessful_status';
	}

	/**
	 * UTF-8 safe truncation for the DB column (a receiver may answer with a
	 * binary payload or a non-UTF-8 charset).
	 */
	private static function scrub(string $body, int $limit): string {
		$body = substr($body, 0, $limit);

		if (function_exists('mb_scrub')) {
			$body = mb_scrub($body, 'UTF-8');
		} elseif (function_exists('iconv')) {
			$scrubbed = @iconv('UTF-8', 'UTF-8//IGNORE', $body);

			if ($scrubbed !== false) {
				$body = $scrubbed;
			}
		}

		return $body;
	}
}
