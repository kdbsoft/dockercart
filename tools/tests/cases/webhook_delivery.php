<?php
/**
 * Outbox rows: retry schedule, failure classification and limits.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Delivery;

return [
	'delivery: backoff table is 1m/5m/30m/2h/12h' => static function (): void {
		Assert::same([60, 300, 1800, 7200, 43200], Delivery::BACKOFF);

		Assert::same(60, Delivery::backoff(1, 6));
		Assert::same(300, Delivery::backoff(2, 6));
		Assert::same(1800, Delivery::backoff(3, 6));
		Assert::same(7200, Delivery::backoff(4, 6));
		Assert::same(43200, Delivery::backoff(5, 6));
	},

	'delivery: the last attempt has no follow-up delay' => static function (): void {
		Assert::same(0, Delivery::backoff(6, 6));
		Assert::same(0, Delivery::backoff(7, 6));
	},

	'delivery: nextAttemptSql is DB-relative (never a PHP timestamp)' => static function (): void {
		Assert::same('DATE_ADD(NOW(), INTERVAL 60 SECOND)', Delivery::nextAttemptSql(1, 6));
		Assert::same('DATE_ADD(NOW(), INTERVAL 43200 SECOND)', Delivery::nextAttemptSql(5, 6));
		Assert::same('NOW()', Delivery::nextAttemptSql(6, 6));
	},

	'delivery: nextAttemptSql honours a custom attempt budget' => static function (): void {
		Assert::same('NOW()', Delivery::nextAttemptSql(2, 2));
		Assert::same('DATE_ADD(NOW(), INTERVAL 60 SECOND)', Delivery::nextAttemptSql(1, 2));
	},

	'delivery: 2xx and 3xx-like misses are not classified as permanent' => static function (): void {
		Assert::false(Delivery::isPermanent('', 200));
		Assert::false(Delivery::isPermanent('', 0));
	},

	'delivery: network errors and 5xx are retried' => static function (): void {
		foreach (['timeout', 'connection_failed', 'curl_28', 'dns_failed', 'worker_error', 'http_500', 'http_503'] as $error) {
			Assert::false(Delivery::isPermanent($error, 0), $error . ' must be retried');
		}

		Assert::false(Delivery::isPermanent('http_500', 500));
		Assert::false(Delivery::isPermanent('http_429', 429));
		Assert::false(Delivery::isPermanent('http_408', 408));
		Assert::false(Delivery::isPermanent('http_425', 425));
	},

	'delivery: non-retryable 4xx is final' => static function (): void {
		foreach ([400, 401, 403, 404, 405, 410, 413, 422] as $status) {
			Assert::true(Delivery::isPermanent('http_' . $status, $status), $status . ' must be final');
		}
	},

	'delivery: URL and secret problems are final regardless of status' => static function (): void {
		foreach (['ssrf_blocked', 'secret_unavailable', 'invalid_url', 'invalid_port', 'invalid_host', 'invalid_scheme', 'credentials_not_allowed'] as $error) {
			Assert::true(Delivery::isPermanent($error, 0), $error . ' must be final');
		}
	},

	'delivery: limits fall back to the documented defaults' => static function (): void {
		Assert::same(
			['max_attempts' => 6, 'timeout' => 10, 'disable_after' => 4, 'enabled' => true],
			Delivery::limits(null, null, null, null)
		);
	},

	'delivery: limits are clamped to a sane range' => static function (): void {
		Assert::same(
			['max_attempts' => 1, 'timeout' => 1, 'disable_after' => 1, 'enabled' => false],
			Delivery::limits(0, 0, 0, 0)
		);

		Assert::same(
			['max_attempts' => 20, 'timeout' => 60, 'disable_after' => 100, 'enabled' => true],
			Delivery::limits(9999, 9999, 9999, 1)
		);
	},

	'delivery: limits accept the strings a Config object returns' => static function (): void {
		$limits = Delivery::limits('4', '15', '3', '0');

		Assert::same(4, $limits['max_attempts']);
		Assert::same(15, $limits['timeout']);
		Assert::same(3, $limits['disable_after']);
		Assert::false($limits['enabled']);
	},

	'delivery: status and event constants are stable' => static function (): void {
		Assert::same('pending', Delivery::STATUS_PENDING);
		Assert::same('sending', Delivery::STATUS_SENDING);
		Assert::same('delivered', Delivery::STATUS_DELIVERED);
		Assert::same('failed', Delivery::STATUS_FAILED);
		Assert::same('cancelled', Delivery::STATUS_CANCELLED);
		Assert::same('webhook.test', Delivery::TEST_EVENT);
	},
];
