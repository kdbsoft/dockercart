<?php
/**
 * Blog posts as landing pages: the owner-link guarantees.
 *
 * A landing bound to a blog post (oc_landing.post_id) is that article's
 * composed page, not a standalone one. These are static checks on the files
 * that carry the integration (same pattern as information_landing.php),
 * plus two functional parts that run without MariaDB:
 *
 *   1. the migration is idempotent and adds both owner columns;
 *   2. post-owned landings never leak into the standalone surfaces (list,
 *      slug resolution, taken slugs) and their own status/schedule/slugs are
 *      ignored — visibility and URL belong to oc_blog_post / oc_blog_seo_url;
 *   3. the storefront renders post-owned landings only through the blog
 *      route, gated by the post status, with the article meta and canonical;
 *   4. the builder knows the post owner mode (back link, preview bases from
 *      oc_blog_seo_url, the owner panel writes through the blog model);
 *   5. enableBuilder() creates the owned landing seeded with the classic
 *      WYSIWYG copy, and never rebuilds an existing one;
 *   6. the search index reads the composed blocks when use_builder is on;
 *   7. the admin strings exist in all three panel locales.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Landing\Blocks;

const BLOG_POST_LANDING_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const BLOG_POST_LANDING_KEYS = [
	'text_mode_builder',
	'text_mode_classic',
	'text_switch_to_builder',
	'text_switch_to_classic',
	'text_success_builder_mode',
	'button_open_builder',
	'text_builder_cta_hint',
	'text_builder_pending',
	'text_builder_badge',
	'text_builder_badge_hint',
];

const BLOG_POST_BUILDER_BADGE_HINTS = [
	'en-gb' => 'The article is composed from sections in the Builder.',
	'ru-ua' => 'Статья собрана из секций в Builder.',
	'uk-ua' => 'Стаття складена з секцій у Builder.',
];

const BLOG_POST_BUILDER_OPEN_LABELS = [
	'en-gb' => 'Open in Builder',
	'ru-ua' => 'Открыть в Builder',
	'uk-ua' => 'Відкрити в Builder',
];

/**
 * Loads the admin blog post model with a fake DB and a minimal registry
 * (same pattern as information_landing.php), so enableBuilder() can be
 * exercised without MariaDB.
 */
function blog_post_landing_model(FakeDb $db): ModelExtensionModuleDockercartBlogPost {
	if (!class_exists('Loader', false)) {
		require_once DIR_SYSTEM . 'engine/loader.php';
		require_once DIR_SYSTEM . 'engine/proxy.php';
		require_once DIR_SYSTEM . 'engine/action.php';
		require_once DIR_SYSTEM . 'engine/event.php';
	}

	require_once DIR_APPLICATION . 'model/extension/module/dockercart_blog_post.php';

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

		public function delete($key) {
		}
	});

	return new ModelExtensionModuleDockercartBlogPost($registry);
}

/**
 * @return array<int, array<string, mixed>>
 */
function blog_post_landing_languages(): array {
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
function blog_post_landing_method_source(string $file, string $method): string {
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
		$files = glob(REPO_ROOT . '/docker/mysql/migrations/*blog_post_landing.sql');

		Assert::true((bool)$files, 'expected a *blog_post_landing.sql migration');

		$sql = (string)file_get_contents($files[0]);

		Assert::contains('ADD COLUMN IF NOT EXISTS `use_builder`', $sql);
		Assert::contains('ADD COLUMN IF NOT EXISTS `post_id`', $sql, 'oc_landing.post_id');
		Assert::contains('ADD INDEX IF NOT EXISTS `idx_landing_post`', $sql);

		// Fresh module installs get the flag from the module installer too.
		$install = repo_file('upload/admin/controller/extension/module/dockercart_blog_install.sql');

		Assert::contains('`use_builder` tinyint(1) NOT NULL DEFAULT \'0\'', $install, 'module install SQL carries the flag');
	},

	'landing model: post-owned pages are hidden from standalone surfaces' => static function (): void {
		$model = repo_file('upload/admin/model/design/landing.php');

		Assert::contains('l.post_id IS NULL', $model, 'owner filter in list/total/slug queries');

		$list_where = blog_post_landing_method_source('upload/admin/model/design/landing.php', 'landingListWhere');

		Assert::contains('l.information_id IS NULL', $list_where, 'the information exclusion stays');
		Assert::contains('l.post_id IS NULL', $list_where, 'the post exclusion is added');

		Assert::contains(
			'l.post_id IS NULL',
			blog_post_landing_method_source('upload/admin/model/design/landing.php', 'getTakenSlugs'),
			'taken slugs exclude post-owned landings'
		);

		$catalog_model = repo_file('upload/catalog/model/design/landing.php');

		Assert::contains('AND l.post_id IS NULL', blog_post_landing_method_source('upload/catalog/model/design/landing.php', 'getLandingBySlug'), 'slug resolution excludes post-owned pages');
		Assert::notSame('', blog_post_landing_method_source('upload/catalog/model/design/landing.php', 'getLandingByPost'), 'getLandingByPost exists');
	},

	'landing model: an owned save ignores status, schedule, home and slugs' => static function (): void {
		$source = blog_post_landing_method_source('upload/admin/model/design/landing.php', 'saveLanding');

		Assert::notSame('', $source, 'saveLanding is missing');

		Assert::contains('post_id', $source, 'the save must know the post owner');
		Assert::contains("(int)\$query->row['post_id'] > 0", $source, 'owned covers post owners');
	},

	'landing model: adding and duplicating carry the post link' => static function (): void {
		$model = repo_file('upload/admin/model/design/landing.php');

		Assert::contains("\$post_id = (int)(\$data['post_id'] ?? 0)", $model, 'addLanding reads the post link');

		$duplicate = blog_post_landing_method_source('upload/admin/model/design/landing.php', 'duplicateLanding');

		Assert::contains('int $new_post_id = 0', $model, 'duplicateLanding takes the new post owner');
		Assert::contains("'post_id'         => \$new_post_id", $duplicate, 'duplicates keep the post link');
	},

	'blog model: builder glue exists' => static function (): void {
		$model = repo_file('upload/admin/model/extension/module/dockercart_blog_post.php');

		Assert::contains('public function getBuilderLandingId', $model);
		Assert::contains('public function setBuilderMode', $model);
		Assert::contains('public function enableBuilder', $model);
		Assert::contains("'code'     => 'text',", $model, 'conversion seeds a text block');

		foreach (['addPost', 'editPost'] as $method) {
			$source = blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', $method);

			Assert::notSame('', $source, $method . ' is missing');
			Assert::contains('use_builder', $source, $method . ' must persist use_builder');
		}

		Assert::contains('deleteLanding', blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', 'deletePost'), 'delete cascades the owned landing');
		Assert::contains('duplicateLanding', blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', 'copyPost'), 'copy carries the sections over');
		Assert::contains('use_builder', blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', 'copyPost'), 'copy keeps the builder mode');
	},

	'blog model: owner settings write through the blog tables' => static function (): void {
		$settings = blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', 'updateBuilderSettings');
		$seo_urls = blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_blog_post.php', 'updateBuilderSeoUrls');

		Assert::notSame('', $settings, 'updateBuilderSettings is missing');
		Assert::notSame('', $seo_urls, 'updateBuilderSeoUrls is missing');

		Assert::contains('blog_post` SET status', $settings, 'visibility lives on the post');
		Assert::contains('blog_post_description', $settings, 'meta tags live on the post description');
		Assert::contains('blog_seo_url', $seo_urls, 'keywords live in oc_blog_seo_url');
		Assert::contains("'blog_post_id=", $seo_urls, 'the query param matches the blog pipeline');
		Assert::contains('invalidateSeoUrlCache', $seo_urls, 'the blog SEO cache is invalidated');
	},

	'storefront: the blog route delegates to landing/page' => static function (): void {
		$source = blog_post_landing_method_source('upload/catalog/controller/blog/post.php', 'index');

		Assert::notSame('', $source);
		Assert::contains('getLandingByPost', $source);
		Assert::contains('use_builder', $source, 'the toggle gates the delegation');
		Assert::contains('preview_token', $source, 'draft articles still open in the builder canvas');
		Assert::contains("load->controller('landing/page')", $source, 'delegation anchor');
	},

	'storefront: post-owned landings are gated and carry the article meta' => static function (): void {
		$source = blog_post_landing_method_source('upload/catalog/controller/landing/page.php', 'index');

		Assert::notSame('', $source);

		// 1. direct hits stay 404 (only the blog route plants blog_post_id)
		Assert::contains("\$owner_post_id", $source);
		Assert::contains("(int)\$this->request->get['blog_post_id'] !== \$owner_post_id", $source, 'owner guard');

		// 2. visibility comes from the blog post, not from the landing
		Assert::contains('getPost($owner_post_id)', $source, 'post gate');
		Assert::contains('isLive', $source, 'standalone gate stays');

		// 3. meta/canonical/breadcrumbs point at the article
		Assert::contains("'blog/post', 'blog_post_id=' . \$owner_post_id", $source);
		Assert::contains("'blog/category'", $source, 'the breadcrumb keeps the Blog trail');
		Assert::contains("\$chrome = 'full';", $source, 'owned pages keep the theme chrome');

		// 4. the view counter: landing views for standalone, post views for
		//    composed articles
		Assert::contains('!$owner_information_id && !$owner_post_id', $source, 'landing views skip owned pages');
		Assert::contains('incrementViews($owner_post_id)', $source, 'composed articles count on the post');
	},

	'admin builder: the builder knows its post owner' => static function (): void {
		$source = blog_post_landing_method_source('upload/admin/controller/design/landing.php', 'builder');

		Assert::notSame('', $source);
		Assert::contains("\$owner_post_id", $source);
		Assert::contains("'extension/module/dockercart_blog_post'", $source, 'back link for owned articles');
		Assert::contains('getPostSeoUrls', $source, 'preview bases use the blog keyword');
		Assert::contains("route=blog/post&blog_post_id=", $source, 'preview fallback targets the article route');
		Assert::contains('dockercart_blog_post/updateNames', $source, 'the title writes through the blog module');

		$save = blog_post_landing_method_source('upload/admin/controller/design/landing.php', 'save');

		Assert::notSame('', $save, 'save is missing');
		Assert::contains('validateBlogOwner', $save, 'post owner fields are validated');
		Assert::contains('updateBuilderSettings', $save, 'post owner fields are written');
		Assert::contains('updateBuilderSeoUrls', $save, 'post keywords are written');
	},

	'blog admin controller: the form wires the toggle' => static function (): void {
		$controller = repo_file('upload/admin/controller/extension/module/dockercart_blog_post.php');

		Assert::contains('public function setBuilderMode', $controller, 'the instant switch action exists');

		$source = blog_post_landing_method_source('upload/admin/controller/extension/module/dockercart_blog_post.php', 'getForm');

		Assert::contains("'can_builder'", $source);
		Assert::contains("'builder_url'", $source);

		foreach (['add', 'edit'] as $action) {
			$source = blog_post_landing_method_source('upload/admin/controller/extension/module/dockercart_blog_post.php', $action);

			Assert::notSame('', $source, $action . ' is missing');
			Assert::contains('enableBuilder', $source, $action . ' must create the owned landing');
		}

		$list = blog_post_landing_method_source('upload/admin/controller/extension/module/dockercart_blog_post.php', 'getList');

		Assert::contains('use_builder', $list, 'the list exposes the badge flag');
	},

	'templates: the form exposes the toggle, the list the badge' => static function (): void {
		$form = repo_file('upload/admin/view/template/extension/module/dockercart_blog_post_form.twig');

		Assert::contains('name="use_builder"', $form);
		Assert::contains('{{ builder_url }}', $form);
		Assert::contains('dc-builder-lang-cta', $form, 'per-language open-in-builder CTA');
		Assert::contains('dc-description-field', $form, 'the classic WYSIWYG field toggles off');
		Assert::contains('setBuilderMode', $form, 'the toggle switches instantly');

		$list = repo_file('upload/admin/view/template/extension/module/dockercart_blog_post_list.twig');

		Assert::contains('post.use_builder', $list, 'builder badge in the list');
		Assert::contains('</i> {{ text_builder_badge }}</span>', $list, 'the badge must show the Builder label');
	},

	'builder shell: the owner identity is generic' => static function (): void {
		$twig = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::contains('owner_post_id', $twig, 'the post owner is flagged');
		Assert::contains('text_owner_post', $twig);
		Assert::contains('ownerIdField', $twig, 'the POST field name is configurable');

		$js = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains('CFG.ownerId', $js, 'owner mode flag');
		Assert::contains('body.set(CFG.ownerIdField', $js, 'the title flush posts the right owner id field');
	},

	'localization: all three locales carry the same keys' => static function (): void {
		$english = read_language_file(REPO_ROOT . '/upload/admin/language/en-gb/extension/module/dockercart_blog_post.php');

		foreach (BLOG_POST_LANDING_KEYS as $key) {
			Assert::true(array_key_exists($key, $english), 'en-gb misses ' . $key);
		}

		Assert::same('Builder', $english['text_builder_badge']);
		Assert::same(BLOG_POST_BUILDER_BADGE_HINTS['en-gb'], $english['text_builder_badge_hint']);

		foreach (BLOG_POST_LANDING_LOCALES as $locale) {
			$localized = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/extension/module/dockercart_blog_post.php');

			$missing = array_values(array_diff(BLOG_POST_LANDING_KEYS, array_keys($localized)));

			Assert::same([], $missing, $locale . ' misses blog builder keys');
			Assert::same($english['text_builder_badge'], $localized['text_builder_badge'], $locale . ' badge label');
			Assert::same(BLOG_POST_BUILDER_BADGE_HINTS[$locale], $localized['text_builder_badge_hint'], $locale . ' badge tooltip');
			Assert::same(BLOG_POST_BUILDER_OPEN_LABELS[$locale], $localized['button_open_builder'], $locale . ' open button');

			$landing = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/design/landing.php');

			Assert::true(array_key_exists('text_owner_post', $landing), $locale . ' misses text_owner_post');
			Assert::true(array_key_exists('text_owner_information', $landing), $locale . ' keeps text_owner_information');

			$catalog_landing = read_language_file(REPO_ROOT . '/upload/catalog/language/' . $locale . '/landing/landing.php');

			Assert::true(array_key_exists('text_blog_section', $catalog_landing), $locale . ' misses text_blog_section');
		}
	},

	'roles: no new routes are required (content routes already cover both)' => static function (): void {
		$roles = repo_file('upload/system/library/dockercart/roles.php');

		Assert::contains("'extension/module/dockercart_blog_post'", $roles);
		Assert::contains("'design/landing'", $roles);
	},

	'model: enableBuilder creates the owned landing and converts the copy' => static function (): void {
		$db = new FakeDb();

		// 1. no landing bound yet; 2. the article's classic descriptions;
		// 3. its tags; 4./5. the language list (saveDescriptions + saveBlocks).
		$db->pushResult([]);
		$db->pushResult([
			[
				'post_id'          => 5,
				'language_id'      => 1,
				'name'             => 'How to Choose a Smartphone',
				'description'      => 'A comprehensive guide.',
				'content'          => '&lt;h2&gt;Budget&lt;/h2&gt;&lt;p&gt;Pick one.&lt;/p&gt;',
				'meta_title'       => 'Smartphone Guide',
				'meta_description' => 'x',
				'meta_keyword'      => '',
			],
			[
				'post_id'          => 5,
				'language_id'      => 2,
				'name'             => 'Как выбрать смартфон',
				'description'      => 'Подробное руководство.',
				'content'          => '&lt;h2&gt;Бюджет&lt;/h2&gt;&lt;p&gt;Выберите.&lt;/p&gt;',
				'meta_title'       => '',
				'meta_description' => '',
				'meta_keyword'      => '',
			],
		]);
		$db->pushResult([]);
		$db->pushResult(blog_post_landing_languages());
		$db->pushResult(blog_post_landing_languages());

		$model = blog_post_landing_model($db);

		$landing_id = $model->enableBuilder(5);

		Assert::true($landing_id > 0, 'a landing id comes back');

		$landing_insert = array_values(array_filter($db->queries, static function (string $sql): bool {
			return strpos($sql, 'INSERT INTO `oc_landing`') === 0;
		}));

		Assert::count(1, $landing_insert, 'exactly one landing row');
		Assert::contains("post_id = '5'", $landing_insert[0], 'bound to the blog post');
		Assert::contains("status = '0'", $landing_insert[0], 'visibility is governed by the article, not the landing');
		Assert::contains("chrome = 'full'", $landing_insert[0], 'articles keep the theme chrome');

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
			if (strpos($sql, 'How to Choose a Smartphone') !== false || strpos($sql, 'Как выбрать смартфон') !== false) {
				$with_heading++;
			}

			// The content column is JSON, so closing tags are stored as <\/>
			// (json_encode escapes slashes) — match the opening tag text only.
			if (strpos($sql, 'Budget') !== false || strpos($sql, 'Бюджет') !== false) {
				$with_body++;
			}
		}

		Assert::same(2, $with_heading, 'heading = post title for every seeded language');
		Assert::same(2, $with_body, 'body = classic content for every seeded language');
	},

	'model: enableBuilder is idempotent — an existing landing is never rebuilt' => static function (): void {
		$db = new FakeDb();

		$db->pushResult([['landing_id' => 42]]);

		$model = blog_post_landing_model($db);

		Assert::same(42, $model->enableBuilder(5));
		Assert::count(1, $db->queries, 'only the owner lookup ran, no re-conversion');
		Assert::contains('FROM `oc_landing` WHERE post_id', $db->queries[0]);
	},

	'search: builder-composed articles index their composed text' => static function (): void {
		$model = repo_file('upload/admin/model/extension/module/dockercart_search.php');

		Assert::contains('use_builder', blog_post_landing_method_source('upload/admin/model/extension/module/dockercart_search.php', 'indexBlogPost'), 'the indexer knows the builder flag');
		Assert::contains('builderPostContent', $model, 'the composed text has an extractor');
		Assert::contains("code <> 'html'", $model, 'raw html blocks are not indexed as copy');
		Assert::contains('Blocks::plainTextFromContent', $model, 'the extractor reuses the landing library');

		// The extractor is a pure function: entities decode, markup strips,
		// repeater rows flatten, whitespace collapses, invalid JSON is empty.
		$json = '{"heading":"&lt;b&gt;Title&lt;/b&gt;","items":[{"title":"First","text":"line one\\nline two"},{"title":"Second","text":"<p>para</p>"}],"count":7}';

		Assert::same(
			'Title First line one line two Second para',
			Blocks::plainTextFromContent($json)
		);
		Assert::same('', Blocks::plainTextFromContent('{invalid'));
		Assert::same('', Blocks::plainTextFromContent(''));
	},
];
