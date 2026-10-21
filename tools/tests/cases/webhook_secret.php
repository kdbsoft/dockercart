<?php
/**
 * Subscription secrets: generation, hint, AES-GCM storage.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Secret;

return [
	'secret: prefixed with whsec_ and 64 hex chars' => static function (): void {
		$generated = Secret::generate();

		Assert::same('whsec_', substr($generated['secret'], 0, 6));
		Assert::same(70, strlen($generated['secret']));
		Assert::true((bool)preg_match('/^whsec_[0-9a-f]{64}$/', $generated['secret']), 'unexpected secret format');
	},

	'secret: unique per subscription' => static function (): void {
		Assert::notSame(Secret::generate()['secret'], Secret::generate()['secret']);
	},

	'secret: hint exposes the first 10 characters only' => static function (): void {
		$generated = Secret::generate();

		Assert::same(10, strlen($generated['hint']));
		Assert::same(substr($generated['secret'], 0, 10), $generated['hint']);
		Assert::notContains($generated['secret'], $generated['hint'] . 'x');
	},

	'secret: encrypt/decrypt round trip with config_encryption' => static function (): void {
		$key = str_repeat('a', 64);
		$secret = Secret::generate()['secret'];

		$stored = Secret::encrypt($secret, $key);

		Assert::notSame($secret, $stored, 'the plain secret must not be stored');
		Assert::same($secret, Secret::decrypt($stored, $key));
	},

	'secret: ciphertext differs between encryptions (random IV)' => static function (): void {
		$key = str_repeat('b', 64);
		$secret = Secret::generate()['secret'];

		Assert::notSame(Secret::encrypt($secret, $key), Secret::encrypt($secret, $key));
	},

	'secret: a rotated encryption key cannot decrypt old rows' => static function (): void {
		$secret = Secret::generate()['secret'];
		$stored = Secret::encrypt($secret, str_repeat('c', 64));

		Assert::same(null, Secret::decrypt($stored, str_repeat('d', 64)));
	},

	'secret: corrupt ciphertext decrypts to null (never to a bogus secret)' => static function (): void {
		Assert::same(null, Secret::decrypt('not-a-valid-ciphertext', str_repeat('e', 64)));
		Assert::same(null, Secret::decrypt('', str_repeat('e', 64)));
	},
];
