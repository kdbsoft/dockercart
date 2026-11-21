<?php
/**
 * Owned landings: the builder edits the information page (status, meta tags,
 * default-store SEO keyword) straight from Page Settings.
 *
 * ModelCatalogInformation::updateBuilderSettings()/updateBuilderSeoUrls() are
 * the only writers, so their encoding and validation are exercised directly
 * against a FakeDb here:
 *
 *   1. values are stored exactly like the information form stores them
 *      (Request::clean: trim + htmlspecialchars) - JSON saves stay
 *      byte-identical to form saves and display with html_entity_decode;
 *   2. a keyword taken by another page on the default store is rejected,
 *      while the page's own keyword (and other stores) pass;
 *   3. meta fields longer than 255 characters are rejected, not truncated;
 *   4. SEO keywords replace the default store only; empty keywords delete.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Minimal registry for the information/landing models (same pattern as
 * landing_sections.php), extended with the cache writes the SEO helpers use.
 *
 * @return Registry
 */
function landing_owner_registry(FakeDb $db) {
	if (!class_exists('Loader', false)) {
		require_once DIR_SYSTEM . 'engine/loader.php';
		require_once DIR_SYSTEM . 'engine/proxy.php';
		require_once DIR_SYSTEM . 'engine/action.php';
		require_once DIR_SYSTEM . 'engine/event.php';
	}

	$config = new Config();
	$config->set('config_language_id', 1);

	$registry = new Registry();
	$registry->set('db', $db);
	$registry->set('config', $config);
	$registry->set('load', new Loader($registry));
	$registry->set('event', new Event($registry));
	$registry->set('cache', new class {
		public function get($key) {
			if ($key === 'admin.language') {
				return [
					'en-gb' => ['language_id' => 1, 'name' => 'English', 'code' => 'en-gb', 'locale' => 'en-gb', 'image' => '', 'directory' => 'en-gb', 'sort_order' => 1, 'status' => 1],
					'uk-ua' => ['language_id' => 2, 'name' => 'Українська', 'code' => 'uk-ua', 'locale' => 'uk-ua', 'image' => '', 'directory' => 'uk-ua', 'sort_order' => 2, 'status' => 1],
				];
			}

			// SEO URL cache version counter.
			return 1;
		}

		public function set($key, $value) {
		}

		public function delete($key) {
		}
	});

	return $registry;
}

function landing_owner_landing(FakeDb $db): ModelDesignLanding {
	require_once DIR_APPLICATION . 'model/design/landing.php';

	return new ModelDesignLanding(landing_owner_registry($db));
}

function landing_owner_information(FakeDb $db): ModelCatalogInformation {
	require_once DIR_APPLICATION . 'model/catalog/information.php';

	return new ModelCatalogInformation(landing_owner_registry($db));
}

return [
	'validateOwner: a clean page passes' => static function (): void {
		$db = new FakeDb();

		$errors = landing_owner_landing($db)->validateOwner([
			'status'       => 1,
			'descriptions' => [
				1 => ['slug' => 'about-us', 'meta_title' => 'About', 'meta_description' => '', 'meta_keyword' => ''],
				2 => ['slug' => '', 'meta_title' => 'Про нас', 'meta_description' => '', 'meta_keyword' => ''],
			],
		], 7);

		Assert::same([], $errors);
	},

	'validateOwner: a keyword taken by another page is rejected' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['store_id' => 0, 'query' => 'information_id=9', 'language_id' => 1]]);

		$errors = landing_owner_landing($db)->validateOwner([
			'status'       => 1,
			'descriptions' => [1 => ['slug' => 'about-us', 'meta_title' => 'About', 'meta_description' => '', 'meta_keyword' => '']],
		], 7);

		Assert::same(['owner.slug.1'], array_keys($errors));
		Assert::contains('already in use', reset($errors));
	},

	'validateOwner: the own keyword and other stores pass' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([
			['store_id' => 0, 'query' => 'information_id=7', 'language_id' => 1],
			['store_id' => 3, 'query' => 'information_id=9', 'language_id' => 1],
		]);

		$errors = landing_owner_landing($db)->validateOwner([
			'status'       => 0,
			'descriptions' => [1 => ['slug' => 'about-us', 'meta_title' => 'About', 'meta_description' => '', 'meta_keyword' => '']],
		], 7);

		Assert::same([], $errors);
	},

	'validateOwner: overlong meta fields are rejected, not truncated' => static function (): void {
		$db = new FakeDb();

		$errors = landing_owner_landing($db)->validateOwner([
			'status'       => 1,
			'descriptions' => [1 => ['slug' => '', 'meta_title' => str_repeat('x', 256), 'meta_description' => '', 'meta_keyword' => '']],
		], 7);

		Assert::same(['owner.meta_title.1'], array_keys($errors));
		Assert::contains('255', reset($errors));
	},

	'updateBuilderSettings: encodes exactly like the information form' => static function (): void {
		$db = new FakeDb();

		landing_owner_information($db)->updateBuilderSettings(7, 1, [
			1 => ['meta_title' => 'Fish & Chips', 'meta_description' => 'Tasty <b>fish</b>', 'meta_keyword' => 'fish'],
		]);

		$sql = implode("\n", $db->queries);

		Assert::contains("UPDATE `oc_information` SET status = '1' WHERE information_id = '7'", $sql);
		Assert::contains("`meta_title` = 'Fish &amp; Chips'", $sql, 'stored Request::clean-encoded, like the form');
		Assert::contains("`meta_description` = 'Tasty &lt;b&gt;fish&lt;/b&gt;'", $sql);
		Assert::notContains('&amp;amp;', $sql, 'never double-encoded');
		Assert::contains("language_id = '1'", $sql);
	},

	'updateBuilderSeoUrls: replaces the default store only' => static function (): void {
		$db = new FakeDb();

		landing_owner_information($db)->updateBuilderSeoUrls(7, 0, [1 => 'About & Us', 2 => '']);

		$sql = implode("\n", $db->queries);

		Assert::contains("DELETE FROM `oc_seo_url` WHERE query = 'information_id=7' AND store_id = '0'", $sql);
		Assert::contains("keyword = 'About &amp; Us'", $sql, 'keywords are form-encoded too');
		Assert::contains("language_id = '1'", $sql);
		Assert::notContains("language_id = '2'", $sql, 'empty keywords delete instead of inserting');
	},
];
