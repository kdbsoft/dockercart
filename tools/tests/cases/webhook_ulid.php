<?php
/**
 * ULID / event id generation.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Webhook\Ulid;

return [
	'ulid: 26 Crockford base32 characters' => static function (): void {
		$ulid = Ulid::generate();

		Assert::same(26, strlen($ulid));
		Assert::true((bool)preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid), 'unexpected alphabet: ' . $ulid);
	},

	'ulid: event ids are prefixed with evt_' => static function (): void {
		// Dispatcher builds 'evt_' . Ulid::generate(); the id is 30 chars total.
		$event_id = 'evt_' . Ulid::generate();

		Assert::same(30, strlen($event_id));
		Assert::same('evt_', substr($event_id, 0, 4));
	},

	'ulid: unique across many generations' => static function (): void {
		$seen = [];

		for ($i = 0; $i < 2000; $i++) {
			$seen[Ulid::generate()] = true;
		}

		Assert::count(2000, $seen);
	},

	'ulid: time-ordered (later ids sort after earlier ones)' => static function (): void {
		$first = Ulid::generate();
		usleep(2000);
		$second = Ulid::generate();

		Assert::true(strcmp($first, $second) < 0, $first . ' should sort before ' . $second);
	},

	'ulid: encode is deterministic for fixed bytes' => static function (): void {
		$bytes = str_repeat("\0", 6) . str_repeat("\xff", 10);

		Assert::same(Ulid::encode($bytes), Ulid::encode($bytes));
		Assert::same(26, strlen(Ulid::encode($bytes)));
	},

	'ulid: encode rejects a payload of the wrong size' => static function (): void {
		Assert::throws(\InvalidArgumentException::class, static function (): void {
			Ulid::encode('too short');
		});
	},
];
