<?php
/**
 * Landing page URL rules: SEO keywords and the draft preview token.
 *
 * Slugs are written into oc_seo_url, so they follow the same constraints the
 * SEO pipeline expects (lowercase, no slashes). The preview token is the only
 * way a draft is reachable from the admin builder, so its expiry/scope checks
 * are security relevant.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Landing\Url;

return [
	'slug: spaces, case and punctuation are normalised' => static function (): void {
		Assert::same('black-friday-2026', Url::normalizeSlug('  Black Friday 2026!  '));
		Assert::same('offer-deal', Url::normalizeSlug('offer_deal'));
		Assert::same('a-b', Url::normalizeSlug('a---b'));
		Assert::same('shop.demo', Url::normalizeSlug('Shop.Demo'));
		Assert::same('x', Url::normalizeSlug('///x///'));
		Assert::same('', Url::normalizeSlug('   '));
	},

	'slug: cyrillic is transliterated (ru-ua / uk-ua panels)' => static function (): void {
		Assert::same('chernaya-pyatnitsa', Url::normalizeSlug('Чёрная пятница'));
		Assert::same('vesnyana-aktsiya', Url::normalizeSlug('Весняна акція'));
		Assert::same('shchodenni-znizhki', Url::normalizeSlug('Щоденні знижки'));
	},

	'slug: length is capped' => static function (): void {
		$slug = Url::normalizeSlug(str_repeat('a', 400));

		Assert::same(Url::SLUG_MAX_LENGTH, strlen($slug));
	},

	'slug: validity requires an already normalised, unique-looking keyword' => static function (): void {
		Assert::true(Url::isValidSlug('black-friday'));
		Assert::true(Url::isValidSlug('shop.demo-2026'));
		Assert::false(Url::isValidSlug('Black-Friday'), 'uppercase is not normalised');
		Assert::false(Url::isValidSlug('with space'));
		Assert::false(Url::isValidSlug('ab'), 'shorter than 3 characters');
		Assert::false(Url::isValidSlug('-leading'), 'must start alphanumeric');
		Assert::false(Url::isValidSlug('a/b'), 'slashes would collide with category paths');
	},

	'slug: reserved segments are rejected' => static function (): void {
		foreach (['admin', 'api', 'image', 'catalog', 'extension', 'system', 'checkout', 'account', 'blog', 'product', 'information'] as $reserved) {
			Assert::false(Url::isValidSlug($reserved), $reserved . ' must be reserved');
		}
	},

	'slug: duplicates get a copy suffix' => static function (): void {
		Assert::same('offer', Url::uniqueSlug('offer', []));
		Assert::same('offer-copy', Url::uniqueSlug('offer', ['offer']));
		Assert::same('offer-copy-2', Url::uniqueSlug('offer', ['offer', 'offer-copy']));
		Assert::same('offer-copy-3', Url::uniqueSlug('Offer', ['offer', 'offer-copy', 'offer-copy-2']));
	},

	'slug: empty input still produces a usable keyword' => static function (): void {
		$slug = Url::uniqueSlug('', []);

		Assert::true(Url::isValidSlug($slug), 'got ' . $slug);
	},

	'token: sign + verify round trip' => static function (): void {
		$token = Url::createPreviewToken(7, 3600, 'secret', 1000);

		Assert::true(Url::verifyPreviewToken(7, $token, 'secret', 1000));
		Assert::true(Url::verifyPreviewToken(7, $token, 'secret', 1000 + 3500));
	},

	'token: wrong landing id, secret or format is rejected' => static function (): void {
		$token = Url::createPreviewToken(7, 3600, 'secret', 1000);

		Assert::false(Url::verifyPreviewToken(8, $token, 'secret', 1000));
		Assert::false(Url::verifyPreviewToken(7, $token, 'other-secret', 1000));
		Assert::false(Url::verifyPreviewToken(7, 'garbage', 'secret', 1000));
		Assert::false(Url::verifyPreviewToken(0, $token, 'secret', 1000));
		Assert::false(Url::verifyPreviewToken(7, $token, '', 1000));
	},

	'token: expiry is enforced' => static function (): void {
		$token = Url::createPreviewToken(7, 3600, 'secret', 1000);

		Assert::false(Url::verifyPreviewToken(7, $token, 'secret', 1000 + 3700));
	},

	'token: a token reaching far into the future is rejected' => static function (): void {
		$far = (1000 + Url::PREVIEW_TOKEN_LIFETIME + 1000) . '.' . Url::signPreviewToken(7, 1000 + Url::PREVIEW_TOKEN_LIFETIME + 1000, 'secret');

		Assert::false(Url::verifyPreviewToken(7, $far, 'secret', 1000));
	},

	'token: default lifetime is one hour' => static function (): void {
		$token = Url::createPreviewToken(3, 0, 'secret', 500);
		$parts = explode('.', $token);

		Assert::same((string)(500 + Url::PREVIEW_TOKEN_LIFETIME), $parts[0]);
	},
];
