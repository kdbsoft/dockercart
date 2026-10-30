<?php
/**
 * API free text: escaped on write, decoded on read.
 *
 * The panel theme runs with Twig autoescape off and relies on "escape on input"
 * (`Request::clean()` runs htmlspecialchars() over every posted value). The REST
 * APIs bypass `Request::clean()` on purpose (JSON bodies), so every free-text
 * write path has to escape the value itself — otherwise a customer API key could
 * store markup that executes in an operator's session (stored XSS), because the
 * panel renders order comments, return comments and addresses unescaped.
 *
 * The behavioural tests pin the escaping helpers; the anchors keep the write
 * paths honest (a refactor that drops the call fails the build), and the
 * localization test keeps the new settings labels in all three locales.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;
use Dockercart\Api\StoreAddress;

/**
 * Source of a method body, or '' when the method is missing.
 *
 * Walks braces from the opening one so a nested `if` cannot end the body early.
 */
function api_text_method_source(string $file, string $method): string {
	$source = repo_file($file);

	$position = strpos($source, 'function ' . $method . '(');

	if ($position === false) {
		return '';
	}

	$start = strpos($source, '{', $position);

	if ($start === false) {
		return '';
	}

	$depth = 0;

	for ($i = $start, $length = strlen($source); $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

/**
 * Registry around a FakeDb (plus the config the address helper reads).
 */
function api_text_registry(FakeDb $db): \Registry {
	$registry = new \Registry();
	$registry->set('db', $db);
	$registry->set('config', new \Config());

	return $registry;
}

return [
	'api text: encodeText escapes markup like Request::clean() does' => static function (): void {
		Assert::same(
			'&lt;img src=x onerror=alert(1)&gt;',
			Serializer::encodeText('<img src=x onerror=alert(1)>'),
			'< and > must be escaped'
		);
		Assert::same('&quot;quoted&quot;', Serializer::encodeText('"quoted"'), '" must be escaped');
		Assert::same('AT&amp;T', Serializer::encodeText('AT&T'), '& must be escaped');

		// ENT_COMPAT (the flag Request::clean() uses) leaves apostrophes alone.
		Assert::same("O'Brien", Serializer::encodeText("O'Brien"), 'apostrophes stay readable');
	},

	'api text: encodeText/decodeText round-trips user content' => static function (): void {
		foreach (['AT&T', '5 < 6 > 4', '"quotes" & <tags>', 'plain text', 'Ünïcode — тире'] as $value) {
			Assert::same($value, Serializer::decodeText(Serializer::encodeText($value)), 'round trip of ' . $value);
		}

		// Legacy rows escaped more than once (older imports) still decode.
		Assert::same('<b>', Serializer::decodeText('&amp;lt;b&amp;gt;'), 'double-escaped content is normalised');
	},

	'api text: the escaped value is bounded by the column budget' => static function (): void {
		// Six quotes escape to 36 characters: over a varchar(32) column.
		try {
			Serializer::encodeTextWithin('firstname', str_repeat('"', 6), 32);

			throw new TestFailure('a value that overflows the column must be rejected');
		} catch (HttpException $exception) {
			Assert::same(Error::VALIDATION_FAILED, $exception->getErrorCode());
			Assert::same(['firstname' => ['max:32']], $exception->getFields());
		}

		// The same input fits when the column is wide enough.
		Assert::same(str_repeat('&quot;', 6), Serializer::encodeTextWithin('firstname', str_repeat('"', 6), 36), 'six quotes fit in 36 characters');

		try {
			Serializer::encodeTextWithinBytes('comment', str_repeat('<', 6), 20);

			throw new TestFailure('a value that overflows the TEXT budget must be rejected');
		} catch (HttpException $exception) {
			Assert::same(Error::VALIDATION_FAILED, $exception->getErrorCode());
		}
	},

	'api text: an address is stored escaped and read back verbatim' => static function (): void {
		$db = new FakeDb();

		// normalize() reads the customer (name fallback), the country and whether
		// the country has zones.
		$db->pushResult([['customer_id' => 7, 'firstname' => 'Ann', 'lastname' => 'Lee', 'address_id' => 0]]);
		$db->pushResult([[
			'country_id'      => 220,
			'name'            => 'UA',
			'iso_code_2'      => 'UA',
			'iso_code_3'      => 'UKR',
			'address_format'  => '{address_1}',
			'postcode_required' => 0,
		]]);
		$db->pushResult([]);

		$addresses = new StoreAddress(api_text_registry($db), 7);

		$raw = [
			'firstname'  => '<b>Ann</b>',
			'lastname'   => 'Lee',
			'address_1'  => '"><script>alert(2)</script>',
			'city'       => 'Kyiv',
			'country_id' => 220,
		];

		$stored = $addresses->normalize($raw);

		Assert::notContains('<', $stored['firstname'], 'a stored name must not contain live markup');
		Assert::notContains('<', $stored['address_1'], 'a stored address must not contain live markup');
		Assert::contains('&lt;b&gt;', $stored['firstname'], 'the name is escaped, not stripped');
		Assert::contains('&quot;&gt;&lt;script&gt;', $stored['address_1'], 'the address is escaped, not stripped');

		// What the API returns is the plain text the client sent.
		$response = $addresses->represent($stored, false);

		Assert::same($raw['firstname'], $response['firstname'], 'the name is decoded for the response');
		Assert::same($raw['address_1'], $response['address_1'], 'the address is decoded for the response');
		Assert::same('Lee', $stored['lastname'], 'content without markup is stored unchanged');
		Assert::same('Kyiv', $stored['city'], 'content without markup is stored unchanged');
	},

	'api text: a partial address update does not double-escape what it keeps' => static function (): void {
		// PUT merges the payload over the current address. The stored row is
		// already escaped for the panel, so the merge base must be the decoded
		// (reader's) form — otherwise every untouched field gains another layer of
		// escaping on each save.
		$stored = [
			'address_id'   => 49,
			'customer_id'  => 7,
			'firstname'    => '&lt;b&gt;Ann&lt;/b&gt;',
			'lastname'     => 'Lee',
			'company'      => '',
			'address_1'    => '1 Main St',
			'address_2'    => '',
			'city'         => 'Kyiv',
			'postcode'     => '',
			'country_id'   => 220,
			'zone_id'      => 0,
			'custom_field' => '[]',
			'country'      => 'UA',
			'iso_code_2'   => 'UA',
			'iso_code_3'   => 'UKR',
			'address_format' => '{address_1}',
			'postcode_required' => 0,
		];

		$db = new FakeDb();
		$db->pushResult([$stored]);                 // requireRow()
		$db->pushResult([['customer_id' => 7, 'firstname' => 'Ann', 'lastname' => 'Lee', 'address_id' => 49]]);
		$db->pushResult([['country_id' => 220, 'name' => 'UA', 'iso_code_2' => 'UA', 'iso_code_3' => 'UKR', 'address_format' => '{address_1}', 'postcode_required' => 0]]);
		$db->pushResult([]);                        // countryHasZones()
		$db->pushResult([$stored]);                 // get() after the write

		$addresses = new StoreAddress(api_text_registry($db), 7);

		$updated = $addresses->update(49, ['address_1' => '2 Main St']);

		$update = '';

		foreach ($db->queries as $sql) {
			if (stripos($sql, 'UPDATE') === 0 && strpos($sql, 'oc_address') !== false) {
				$update = $sql;
			}
		}

		Assert::true($update !== '', 'the address update statement is missing');
		Assert::contains("'&lt;b&gt;Ann&lt;/b&gt;'", $update, 'the untouched name keeps exactly one layer of escaping');
		Assert::notContains('&amp;amp;', $update, 'no double escaping');
		Assert::notContains('&amp;lt;', $update, 'no double escaping of the name');
		Assert::contains("'2 Main St'", $update, 'the sent field is written');
		Assert::same('<b>Ann</b>', $updated['firstname'], 'the response is plain text again');
	},

	'api text: every free-text write path keeps escaping' => static function (): void {
		$anchors = [
			// file => method => expected fragment
			'upload/admin/controller/store_api/returns.php'          => ['add' => 'encodeTextWithinBytes('],
			'upload/admin/controller/api/sale/returns.php'           => ['add' => 'encodeTextWithinBytes(', 'addHistory' => 'encodeTextWithinBytes('],
			'upload/admin/controller/api/sale/orders.php'            => ['patch' => 'encodeTextWithinBytes(', 'addHistory' => 'encodeTextWithinBytes('],
			'upload/admin/controller/api/blog/comments.php'          => ['patch' => 'encodeTextWithin'],
			'upload/admin/controller/api/blog/authors.php'           => ['validateAuthor' => 'encodeTextWithin'],
			'upload/admin/controller/api/webhooks.php'               => ['subscriptionInput' => 'encodeTextWithin'],
			'upload/system/library/dockercart/api/store_address.php' => ['normalize' => 'encodeTextWithin'],
			'upload/system/library/dockercart/api/store_checkout.php' => ['text' => 'encodeTextWithin', 'comment' => 'encodeTextWithinBytes'],
		];

		foreach ($anchors as $file => $methods) {
			foreach ($methods as $method => $fragment) {
				$source = api_text_method_source($file, $method);

				Assert::true($source !== '', $file . '::' . $method . '() is missing');
				Assert::contains($fragment, $source, $file . '::' . $method . '() must escape free text (' . $fragment . ')');
			}
		}

		// Reads of the same fields must decode, or a GET -> PATCH round trip would
		// double-escape the content.
		$decoders = [
			'upload/admin/controller/api/sale/orders.php'            => 'orderResponse',
			'upload/admin/controller/api/sale/returns.php'           => 'returnResponse',
			'upload/admin/controller/store_api/returns.php'          => 'returnResponse',
			'upload/system/library/dockercart/api/store_checkout.php' => 'state',
			'upload/system/library/dockercart/api/store_address.php' => 'represent',
			'upload/system/library/dockercart/api/store_order.php'   => 'full',
		];

		foreach ($decoders as $file => $method) {
			Assert::contains('decodeText(', api_text_method_source($file, $method), $file . '::' . $method . '() must decode escaped text');
		}
	},

	'api text: a failed request still finalises its Idempotency-Key row' => static function (): void {
		// The terminal problem output runs after the request stack was unwound, so
		// it must store the answer itself — otherwise the 5 minute lock stays behind
		// and every retry answers 409 idempotency_in_progress.
		Assert::contains(
			'storeIdempotentAnswer(',
			api_text_method_source('upload/system/library/dockercart/api/response.php', 'emitEmergency'),
			'emitEmergency() must finalise the idempotency row'
		);

		Assert::contains(
			'storeIdempotentAnswer(',
			api_text_method_source('upload/system/library/dockercart/api/response.php', 'send'),
			'send() must finalise the idempotency row'
		);

		$store = api_text_method_source('upload/system/library/dockercart/api/response.php', 'storeIdempotentAnswer');

		Assert::contains('idempotency_id', $store, 'the helper is a no-op without a key');
		Assert::contains('->store(', $store, 'the helper delegates to Idempotency::store()');
		Assert::contains('catch (\\Throwable', $store, 'bookkeeping must never break the answer');
	},

	'api text: the new API settings labels exist in every locale' => static function (): void {
		$keys = ['entry_api_anon_rate_limit', 'help_api_anon_rate_limit'];

		$english = read_language_file(REPO_ROOT . '/upload/admin/language/en-gb/setting/api_key.php');

		foreach ($keys as $key) {
			Assert::true(isset($english[$key]), 'en-gb is missing ' . $key);
		}

		foreach (['ru-ua', 'uk-ua'] as $locale) {
			$file = REPO_ROOT . '/upload/admin/language/' . $locale . '/setting/api_key.php';
			$strings = read_language_file($file);

			foreach ($keys as $key) {
				Assert::true(isset($strings[$key]) && $strings[$key] !== '', $locale . ' is missing ' . $key);
			}
		}
	},
];
