<?php
/**
 * Information pages as landing pages: the owner-link guarantees.
 *
 * A landing bound to an information page (oc_landing.information_id) is that
 * page's composed body, not a standalone page. These are static checks on the
 * files that carry the integration, because they cannot be executed without a
 * full stack. They pin the properties that are easy to break by accident:
 *
 *   1. the migration is idempotent and adds both owner columns;
 *   2. owned landings never leak into the standalone surfaces (list, slug
 *      resolution, taken slugs, seo_url mirror, home page, own status);
 *   3. the storefront renders owned pages only through the information route,
 *      gated by the information page status, with its meta and canonical;
 *   4. the builder knows the owner mode (back link, preview bases, the
 *      slide-over drops slug/status/schedule/home/chrome);
 *   5. the admin strings exist in all three panel locales.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const INFORMATION_LANDING_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const INFORMATION_LANDING_KEYS = [
	'text_builder_card',
	'entry_use_builder',
	'help_use_builder',
	'button_open_builder',
	'text_builder_cta_hint',
	'text_builder_pending',
	'text_builder_badge',
	'text_builder_badge_hint',
];

const INFORMATION_BUILDER_BADGE_HINTS = [
	'en-gb' => 'The page content is composed from sections in the Builder.',
	'ru-ua' => 'Содержимое страницы собрано из секций в Builder.',
	'uk-ua' => 'Вміст сторінки складено з секцій у Builder.',
];

const INFORMATION_BUILDER_OPEN_LABELS = [
	'en-gb' => 'Open in Builder',
	'ru-ua' => 'Открыть в Builder',
	'uk-ua' => 'Відкрити в Builder',
];

/**
 * Loads the admin information model with a fake DB and a minimal registry
 * (same pattern as product_file_model.php), so enableBuilder() can be
 * exercised without MariaDB.
 */
function information_landing_model(FakeDb $db): ModelCatalogInformation {
	if (!class_exists('Loader', false)) {
		require_once DIR_SYSTEM . 'engine/loader.php';
		require_once DIR_SYSTEM . 'engine/proxy.php';
		require_once DIR_SYSTEM . 'engine/action.php';
		require_once DIR_SYSTEM . 'engine/event.php';
	}

	require_once DIR_APPLICATION . 'model/catalog/information.php';

	$config = new Config();
	$config->set('config_language_id', 1);

	$registry = new Registry();
	$registry->set('db', $db);
	$registry->set('config', $config);
	$registry->set('load', new Loader($registry));
	$registry->set('event', new Event($registry));
	$registry->set('cache', new class {
		public function get($key) {
			return null;
		}

		public function set($key, $value) {
		}
	});

	return new ModelCatalogInformation($registry);
}

/**
 * @return array<int, array<string, mixed>>
 */
function information_landing_languages(): array {
	$languages = [];

	foreach ([1 => 'en-gb', 2 => 'ru-ua', 3 => 'uk-ua'] as $language_id => $code) {
		$languages[] = [
			'language_id' => $language_id,
			'name'        => $code,
			'code'        => $code,
			'locale'      => $code,
			'image'       => '',
			'directory'   => $code,
			'sort_order'  => $language_id,
			'status'      => 1,
		];
	}

	return $languages;
}

/**
 * Returns the source of a method body, or '' when the method is missing.
 */
function information_landing_method_source(string $file, string $method): string {
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
	$length = strlen($source);

	for ($i = $start; $i < $length; $i++) {
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

return [
	'migration: exists and is idempotent' => static function (): void {
		$files = glob(REPO_ROOT . '/docker/mysql/migrations/*information_landing.sql');

		Assert::true((bool)$files, 'expected a *information_landing.sql migration');

		$sql = (string)file_get_contents($files[0]);

		Assert::contains('ADD COLUMN IF NOT EXISTS `use_builder`', $sql);
		Assert::contains('ADD COLUMN IF NOT EXISTS `information_id`', $sql, 'oc_landing.information_id');
		Assert::contains('ADD INDEX IF NOT EXISTS `idx_landing_information`', $sql);
	},

	'landing model: owned pages are hidden from standalone surfaces' => static function (): void {
		$model = repo_file('upload/admin/model/design/landing.php');

		Assert::contains('l.information_id IS NULL', $model, 'owner filter in list/total/slug queries');

		foreach (['getLandings', 'getTotalLandings'] as $method) {
			$source = information_landing_method_source('upload/admin/model/design/landing.php', $method);

			Assert::notSame('', $source, $method . ' is missing');

			// Both list queries build their WHERE through the shared helper; the
			// helper itself is pinned to exclude owned pages.
			Assert::contains('landingListWhere($data)', $source, $method . ' must route through the list filter');
		}

		Assert::contains(
			'information_id IS NULL',
			information_landing_method_source('upload/admin/model/design/landing.php', 'landingListWhere'),
			'the shared list filter excludes owned pages'
		);

		$catalog_model = repo_file('upload/catalog/model/design/landing.php');

		Assert::contains('AND l.information_id IS NULL', information_landing_method_source('upload/catalog/model/design/landing.php', 'getLandingBySlug'), 'slug resolution excludes owned pages');

		Assert::contains('information_id', information_landing_method_source('upload/catalog/model/design/landing.php', 'getLandingByInformation'), 'owner lookup exists');
	},

	'landing model: an owned save ignores status, schedule, home and slugs' => static function (): void {
		$source = information_landing_method_source('upload/admin/model/design/landing.php', 'saveLanding');

		Assert::notSame('', $source, 'saveLanding is missing');

		// The owner flag is read inside the transaction, the standalone-only
		// fields are skipped for it and descriptions stay slug-free.
		Assert::contains('information_id', $source, 'the save must know the owner');
		Assert::contains("if (!\$owned)", $source);
		Assert::contains("\$owned ? 'full'", $source, 'owned chrome is forced to full');
		Assert::contains(', $owned);', $source, 'descriptions must be saved in owned mode');

		$model = repo_file('upload/admin/model/design/landing.php');

		Assert::contains('saveDescriptions(int $landing_id, array $descriptions, bool $owned = false', $model, 'descriptions save knows the owner');
		Assert::contains('if (!$owned) {', information_landing_method_source('upload/admin/model/design/landing.php', 'saveDescriptions'), 'owned pages never sync oc_seo_url');

		Assert::contains('validateLanding(array $data, int $landing_id = 0, bool $owned = false', $model, 'validation knows the owner');
		Assert::contains('if ($owned) {', information_landing_method_source('upload/admin/model/design/landing.php', 'validateLanding'), 'owned pages skip slug/schedule/home rules');
	},

	'landing model: adding and duplicating carry the owner link' => static function (): void {
		$model = repo_file('upload/admin/model/design/landing.php');

		Assert::contains("\$information_id = (int)(\$data['information_id'] ?? 0)", $model, 'addLanding reads the owner link');

		$duplicate = information_landing_method_source('upload/admin/model/design/landing.php', 'duplicateLanding');

		Assert::contains('int $new_information_id = 0', $model, 'duplicateLanding takes the new owner');
		Assert::contains("\$new_information_id", $duplicate, 'duplicates keep the owner link');
	},

	'information model: builder glue exists' => static function (): void {
		$model = repo_file('upload/admin/model/catalog/information.php');

		Assert::contains('public function getBuilderLandingId', $model);
		Assert::contains('public function enableBuilder', $model);
		Assert::contains("'code'     => 'text',", $model, 'conversion seeds a text block');

		foreach (['addInformation', 'editInformation'] as $method) {
			$source = information_landing_method_source('upload/admin/model/catalog/information.php', $method);

			Assert::notSame('', $source, $method . ' is missing');
			Assert::contains('use_builder', $source, $method . ' must persist use_builder');
		}

		Assert::contains('deleteLanding', information_landing_method_source('upload/admin/model/catalog/information.php', 'deleteInformation'), 'delete cascades the owned landing');
		Assert::contains('duplicateLanding', information_landing_method_source('upload/admin/model/catalog/information.php', 'copyInformation'), 'copy carries the sections over');
	},

	'storefront: the information route delegates to landing/page' => static function (): void {
		$source = information_landing_method_source('upload/catalog/controller/information/information.php', 'index');

		Assert::notSame('', $source);
		Assert::contains("getLandingByInformation", $source);
		Assert::contains("use_builder", $source, 'the toggle gates the delegation');
		Assert::contains("load->controller('landing/page')", $source, 'delegation anchor');
	},

	'storefront: owned landings are gated and carry the page meta' => static function (): void {
		$source = information_landing_method_source('upload/catalog/controller/landing/page.php', 'index');

		Assert::notSame('', $source);

		// 1. direct hits stay 404 (only the information route plants information_id)
		Assert::contains("\$owner_information_id", $source);
		Assert::contains('(int)$this->request->get[\'information_id\'] !== $owner_information_id', $source, 'owner guard');

		// 2. visibility comes from the information page, not from the landing
		Assert::contains('getInformation($owner_information_id)', $source, 'info gate');
		Assert::contains('isLive', $source, 'standalone gate stays');

		// 3. meta/canonical/breadcrumbs point at the information page
		Assert::contains("'information/information', 'information_id=' . \$owner_information_id", $source);
		Assert::contains("\$chrome = 'full';", $source, 'owned pages keep the theme chrome');

		// 4. the view counter is not inflated by owned pages
		Assert::contains('!$preview && !$owner_information_id', $source);
	},

	'admin controller: the builder knows its owner' => static function (): void {
		$source = information_landing_method_source('upload/admin/controller/design/landing.php', 'builder');

		Assert::notSame('', $source);
		Assert::contains("\$owner_information_id", $source);
		Assert::contains("'catalog/information'", $source, 'back link for owned pages');
		Assert::contains('getInformationSeoUrls', $source, 'preview bases use the information keyword');

		$save = information_landing_method_source('upload/admin/controller/design/landing.php', 'save');

		Assert::contains('validateLanding($payload, $landing_id, $owned)', $save, 'owned saves skip slug rules');

		foreach (['delete', 'copy', 'status'] as $action) {
			$source = information_landing_method_source('upload/admin/controller/design/landing.php', $action);

			Assert::notSame('', $source, $action . ' is missing');
			// ownerType() is generic across owner kinds (information, blog post).
			Assert::contains('ownerType', $source, $action . ' must refuse owned landings');
		}
	},

	'admin controller: information form wires the builder toggle' => static function (): void {
		$source = information_landing_method_source('upload/admin/controller/catalog/information.php', 'getForm');

		Assert::contains("'can_builder'", $source);
		Assert::contains("'builder_url'", $source);

		foreach (['add', 'edit'] as $action) {
			$source = information_landing_method_source('upload/admin/controller/catalog/information.php', $action);

			Assert::notSame('', $source, $action . ' is missing');
			Assert::contains('enableBuilder', $source, $action . ' must create the owned landing');
		}
	},

	'templates: the form exposes the toggle, the list the badge' => static function (): void {
		$form = repo_file('upload/admin/view/template/catalog/information_form.twig');

		Assert::contains('name="use_builder"', $form);
		Assert::contains('{{ builder_url }}', $form);
		Assert::contains('data-toggle="tooltip"', $form, 'hints must use bootstrap tooltips');

		$list = repo_file('upload/admin/view/template/catalog/information_list.twig');

		Assert::contains('information.use_builder', $list, 'builder badge in the list');
		Assert::contains('</i> {{ text_builder_badge }}</span>', $list, 'the badge must show the Builder label');
	},

	'builder shell: the settings slide-over drops owner-managed fields' => static function (): void {
		$twig = repo_file('upload/admin/view/template/design/landing_builder.twig');

		// Owner identity is generic since blog posts became a second owner
		// type: ownerId/ownerIdField carry which entity owns the landing.
		Assert::contains('ownerId', $twig);
		Assert::contains('text_owner_information', $twig);

		$js = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains('CFG.ownerId', $js, 'owner mode flag');
		Assert::contains('data-page="status"', $js, 'standalone settings keep the status field');
	},

	'localization: all three panel locales carry the same keys' => static function (): void {
		$english = read_language_file(REPO_ROOT . '/upload/admin/language/en-gb/catalog/information.php');

		foreach (INFORMATION_LANDING_KEYS as $key) {
			Assert::true(array_key_exists($key, $english), 'en-gb misses ' . $key);
		}

		Assert::same('Builder', $english['text_builder_badge']);
		Assert::same(INFORMATION_BUILDER_BADGE_HINTS['en-gb'], $english['text_builder_badge_hint']);

		foreach (INFORMATION_LANDING_LOCALES as $locale) {
			$localized = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/catalog/information.php');

			$missing = array_values(array_diff(INFORMATION_LANDING_KEYS, array_keys($localized)));

			Assert::same([], $missing, $locale . ' misses information builder keys');
			Assert::same($english['text_builder_badge'], $localized['text_builder_badge'], $locale . ' badge label');
			Assert::same(INFORMATION_BUILDER_BADGE_HINTS[$locale], $localized['text_builder_badge_hint'], $locale . ' badge tooltip');
			Assert::same(INFORMATION_BUILDER_OPEN_LABELS[$locale], $localized['button_open_builder'], $locale . ' open button');

			$landing = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/design/landing.php');

			Assert::true(array_key_exists('text_owner_information', $landing), $locale . ' misses text_owner_information');
			Assert::true(array_key_exists('error_owner', $landing), $locale . ' misses error_owner');
		}
	},

	'roles: no new routes are required (content routes already cover both)' => static function (): void {
		$roles = repo_file('upload/system/library/dockercart/roles.php');

		Assert::contains("'catalog/information'", $roles);
		Assert::contains("'design/landing'", $roles);
	},

	'model: enableBuilder creates the owned landing and converts the copy' => static function (): void {
		$db = new FakeDb();

		// 1. no landing bound yet; 2. the page's classic descriptions;
		// 3./4. the language list (saveDescriptions + saveBlocks).
		$db->pushResult([]);
		$db->pushResult([
			[
				'information_id'   => 5,
				'language_id'      => 1,
				'title'            => 'About Us',
				'description'      => '<p>Hello <b>world</b></p>',
				'meta_title'       => 'About Us | Shop',
				'meta_description' => 'x',
				'meta_keyword'      => '',
			],
			[
				'information_id'   => 5,
				'language_id'      => 2,
				'title'            => 'About Us RU',
				'description'      => '<p>Privet <b>mir</b></p>',
				'meta_title'       => '',
				'meta_description' => '',
				'meta_keyword'      => '',
			],
		]);
		$db->pushResult(information_landing_languages());
		$db->pushResult(information_landing_languages());

		$model = information_landing_model($db);

		$landing_id = $model->enableBuilder(5);

		Assert::true($landing_id > 0, 'a landing id comes back');

		$landing_insert = array_values(array_filter($db->queries, static function (string $sql): bool {
			return strpos($sql, 'INSERT INTO `oc_landing`') === 0;
		}));

		Assert::count(1, $landing_insert, 'exactly one landing row');
		Assert::contains("information_id = '5'", $landing_insert[0], 'bound to the information page');
		Assert::contains("status = '0'", $landing_insert[0], 'visibility is governed by the page, not the landing');
		Assert::contains("chrome = 'full'", $landing_insert[0], 'information pages keep the theme chrome');

		$block_insert = array_values(array_filter($db->queries, static function (string $sql): bool {
			return strpos($sql, 'INSERT INTO `oc_landing_block`') === 0;
		}));

		Assert::count(1, $block_insert, 'one section');
		Assert::contains("code = 'text'", $block_insert[0], 'the classic copy becomes a text block');

		$content_inserts = array_values(array_filter($db->queries, static function (string $sql): bool {
			return strpos($sql, 'INSERT INTO `oc_landing_block_description`') === 0;
		}));

		Assert::count(3, $content_inserts, 'one content row per panel language');

		$with_heading = 0;
		$with_body = 0;

		foreach ($content_inserts as $sql) {
			if (strpos($sql, 'About Us') !== false) {
				$with_heading++;
			}

			// The content column is JSON, so closing tags are stored as <\/>
			// (json_encode escapes slashes) — match the opening tag text only.
			if (strpos($sql, 'Hello <b>world') !== false || strpos($sql, 'Privet <b>mir') !== false) {
				$with_body++;
			}
		}

		Assert::same(2, $with_heading, 'heading = page title for every seeded language');
		Assert::same(2, $with_body, 'body = classic description for every seeded language');
	},

	'model: enableBuilder is idempotent — an existing landing is never rebuilt' => static function (): void {
		$db = new FakeDb();

		$db->pushResult([['landing_id' => 42]]);

		$model = information_landing_model($db);

		Assert::same(42, $model->enableBuilder(5));
		Assert::count(1, $db->queries, 'only the owner lookup ran, no re-conversion');
		Assert::contains('FROM `oc_landing` WHERE information_id', $db->queries[0]);
	},
];
