<?php
/**
 * HMAC request signatures (the receiver-side contract).
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Signature;

return [
	'signature: format is sha256=<64 hex>' => static function (): void {
		$signature = Signature::compute('1792588800', '{"a":1}', 'whsec_test');

		Assert::same("sha256=", substr($signature, 0, 7));
		Assert::same(71, strlen($signature));
		Assert::true((bool)preg_match('/^sha256=[0-9a-f]{64}$/', $signature));
	},

	'signature: matches an independent hash_hmac computation' => static function (): void {
		$timestamp = '1792588800';
		$body = '{"id":"evt_1","type":"order.created"}';
		$secret = 'whsec_0123456789abcdef';

		$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

		Assert::same($expected, Signature::compute($timestamp, $body, $secret));
	},

	'signature: deterministic for the same input' => static function (): void {
		Assert::same(
			Signature::compute('1', 'body', 'secret'),
			Signature::compute('1', 'body', 'secret')
		);
	},

	'signature: changes when the body, timestamp or secret changes' => static function (): void {
		$base = Signature::compute('1', '{"a":1}', 'secret');

		Assert::notSame($base, Signature::compute('2', '{"a":1}', 'secret'));
		Assert::notSame($base, Signature::compute('1', '{"a":2}', 'secret'));
		Assert::notSame($base, Signature::compute('1', '{"a":1}', 'secret2'));
	},

	'signature: a receiver can reject a replayed body with a fresh timestamp' => static function (): void {
		$secret = 'whsec_receiver';

		$sent_timestamp = '1792588800';
		$body = '{"order_id":1}';
		$sent_signature = Signature::compute($sent_timestamp, $body, $secret);

		// Honest request: matches.
		Assert::true(hash_equals(Signature::compute($sent_timestamp, $body, $secret), $sent_signature));

		// Attacker replays the signature with a modified body.
		Assert::false(hash_equals(Signature::compute($sent_timestamp, '{"order_id":2}', $secret), $sent_signature));

		// Attacker replays with a new timestamp.
		Assert::false(hash_equals(Signature::compute('1792589999', $body, $secret), $sent_signature));
	},

	'signature: header names are stable' => static function (): void {
		Assert::same('X-DockerCart-Signature', Signature::HEADER);
		Assert::same('X-DockerCart-Timestamp', Signature::TIMESTAMP_HEADER);
	},

	'signature: now() is a unix timestamp' => static function (): void {
		Assert::true(abs(Signature::now() - time()) <= 1);
	},
];
