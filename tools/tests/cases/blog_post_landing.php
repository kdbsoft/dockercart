<?php
/**
 * Blog Advanced Description and removal of the Landing Page Builder bridge.
 *
 * The first half exercises the pure Builder -> Advanced Description converter
 * and the transactional data migration over FakeDb. The integration assertions
 * are extended with the admin/storefront wiring in the implementation steps.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Blog\AdvancedDescriptionMigration;
use Dockercart\Description\Sections;

const BLOG_ADVANCED_DESCRIPTION_SCHEMA_MIGRATION = 'docker/mysql/migrations/20261125_blog_advanced_description.sql';
const BLOG_ADVANCED_DESCRIPTION_DATA_MIGRATION = 'docker/mysql/migrations/20261125_blog_advanced_description.php';
const BLOG_ADVANCED_DESCRIPTION_DROP_MIGRATION = 'docker/mysql/migrations/20261126_remove_blog_builder_columns.sql';

/**
 * Minimal registry for the data migration (no models or network).
 */
function blog_advanced_description_registry(FakeDb $db): Registry {
	$config = new Config();
	$config->set('config_language_id', 1);

	$registry = new Registry();
	$registry->set('db', $db);
	$registry->set('config', $config);
	$registry->set('cache', new class {
		public function delete($key) {
		}
	});

	return $registry;
}

/**
 * @return array<int, array<string, mixed>>
 */
function blog_advanced_description_block(array $overrides = []): array {
	return $overrides + [
		'block_id'   => 1,
		'parent_id'  => 0,
		'slot'       => 0,
		'code'       => 'text',
		'sort_order' => 0,
		'status'     => 1,
		'settings'   => [],
		'content'    => [],
	];
}

function blog_advanced_description_query_index(FakeDb $db, string $fragment): ?int {
	foreach ($db->queries as $index => $sql) {
		if (str_contains($sql, $fragment)) {
			return $index;
		}
	}

	return null;
}

function blog_advanced_description_model(FakeDb $db): ModelExtensionModuleDockercartBlogPost {
	require_once DIR_APPLICATION . 'model/extension/module/dockercart_blog_post.php';

	$registry = blog_advanced_description_registry($db);
	$registry->set('load', new class($registry) {
		private Registry $registry;

		public function __construct(Registry $registry) {
			$this->registry = $registry;
		}

		public function model(string $route): void {
			if ($route === 'localisation/language') {
				$this->registry->set('model_localisation_language', new class {
					public function getLanguages(): array {
						return [['language_id' => 1]];
					}
				});
			} elseif ($route === 'setting/store') {
				$this->registry->set('model_setting_store', new class {
					public function getStores(): array {
						return [];
					}
				});
			} elseif ($route === 'design/seo_url') {
				$this->registry->set('model_design_seo_url', new class {
					public function invalidateSeoUrlCache(): void {
					}
				});
			}
		}
	});

	return new ModelExtensionModuleDockercartBlogPost($registry);
}

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function blog_advanced_description_post_data(array $overrides = []): array {
	return $overrides + [
		'author_id'          => 1,
		'image'              => '',
		'background_image'   => '',
		'status'             => 1,
		'featured'           => 0,
		'allow_comments'     => 1,
		'sort_order'         => 0,
		'date_published'     => '2026-01-01 00:00:00',
		'post_description'   => [1 => [
			'title'            => 'Article',
			'excerpt'          => 'Excerpt',
			'content'          => '&lt;p&gt;Classic&lt;/p&gt;',
			'meta_title'       => 'Article',
			'meta_description' => '',
			'meta_keyword'     => '',
			'tags'             => '',
		]],
		'post_store'         => [0],
		'post_category'      => [1],
		'post_product'       => [],
		'post_product_category' => [],
		'post_manufacturer'  => [],
	];
}

return [
	'migrations: schema, data and drop files are ordered and idempotent' => static function (): void {
		$schema = repo_file(BLOG_ADVANCED_DESCRIPTION_SCHEMA_MIGRATION);
		$drop = repo_file(BLOG_ADVANCED_DESCRIPTION_DROP_MIGRATION);

		Assert::same(3, substr_count($schema, 'ADD COLUMN IF NOT EXISTS'), 'three blog columns');
		Assert::contains('`use_advanced_description`', $schema);
		Assert::contains('`advanced_description`', $schema);
		Assert::contains('`advanced_content`', $schema);
		Assert::true(is_file(REPO_ROOT . '/' . BLOG_ADVANCED_DESCRIPTION_DATA_MIGRATION), 'data migration exists');
		$data = repo_file(BLOG_ADVANCED_DESCRIPTION_DATA_MIGRATION);
		Assert::contains('is_file($candidate . \'/upload/config.php\')', $data, 'host checkout layout is supported');
		Assert::contains('is_file($candidate . \'/config.php\')', $data, 'container webroot layout is supported');
		Assert::contains('$upload_root . \'/admin/model/extension/module/dockercart_search.php\'', $data, 'search model path follows the detected layout');
		Assert::contains('DROP COLUMN IF EXISTS `use_builder`', $drop);
		Assert::contains('DROP INDEX IF EXISTS `idx_landing_post`', $drop);
		Assert::contains('DROP COLUMN IF EXISTS `post_id`', $drop);
		Assert::contains("filename` = '20261125_blog_advanced_description.php'", $drop, 'destructive DDL fails closed without the data marker');
		Assert::true(
			version_compare('20261125_blog_advanced_description.sql', '20261126_remove_blog_builder_columns.sql', '<'),
			'data conversion runs before the legacy columns are dropped'
		);
	},

	'runners: same-name PHP data migrations are independently tracked' => static function (): void {
		foreach (['scripts/run-migrations.sh', 'update.sh'] as $file) {
			$source = repo_file($file);
			Assert::contains('data_migration="${migration%.sql}.php"', $source, $file . ' finds the companion');
			Assert::contains("filename='\$data_escaped", $source, $file . ' checks the data marker');
			Assert::contains("VALUES ('\$data_escaped", $source, $file . ' records the data marker');
		}

		$runner = repo_file('scripts/run-migrations.sh');
		$skip = strpos($runner, 'skipped=$((skipped + 1))');
		$companion = strpos($runner, 'data_migration="${migration%.sql}.php"');
		Assert::true($skip !== false && $companion !== false, 'runner has both paths');
		Assert::notContains("        continue\n    fi\n    echo \"run-migrations: applying \$filename\"", $runner, 'an applied SQL does not skip a pending data migration');
	},

	'converter: containers flatten in slot/sort order and known blocks map safely' => static function (): void {
		$blocks = [
			blog_advanced_description_block(['block_id' => 1, 'code' => 'section', 'sort_order' => 0]),
			blog_advanced_description_block([
				'block_id' => 2, 'parent_id' => 1, 'slot' => 1, 'code' => 'stats', 'sort_order' => 2,
				'settings' => ['columns' => '3'],
				'content'  => [1 => ['heading' => 'Numbers', 'items' => [['value' => '10', 'label' => 'Ten']]]],
			]),
			blog_advanced_description_block([
				'block_id' => 3, 'parent_id' => 1, 'slot' => 0, 'code' => 'image_text', 'sort_order' => 1,
				'settings' => ['image' => 'catalog/demo/image.jpg', 'image_position' => 'left', 'button_href' => '/guide'],
				'content'  => [1 => ['heading' => 'Picture', 'body' => '&lt;p&gt;Body&lt;/p&gt;', 'bullets' => "One\nTwo", 'button_label' => 'More', 'button_href' => '/guide']],
			]),
			blog_advanced_description_block([
				'block_id' => 4, 'code' => 'features', 'sort_order' => 3,
				'settings' => ['columns' => '4'],
				'content'  => [1 => ['heading' => 'Highlights', 'items' => [['icon' => 'landmark', 'title' => 'Fast', 'text' => 'Very']]]],
			]),
			blog_advanced_description_block([
				'block_id' => 5, 'code' => 'statement', 'sort_order' => 4,
				'content'  => [1 => ['text' => 'Quote', 'foot' => 'Author']],
			]),
			blog_advanced_description_block([
				'block_id' => 6, 'code' => 'image', 'sort_order' => 5,
				'settings' => ['image' => 'catalog/demo/gallery.jpg'],
				'content'  => [1 => ['caption' => 'Caption', 'alt' => 'Alt']],
			]),
			blog_advanced_description_block([
				'block_id' => 7, 'code' => 'html', 'sort_order' => 6,
				'content'  => [1 => ['html' => '&lt;script&gt;bad()&lt;/script&gt;&lt;p onclick="x()"&gt;Safe&lt;/p&gt;']],
			]),
			blog_advanced_description_block(['block_id' => 8, 'code' => 'spacer', 'sort_order' => 7]),
			blog_advanced_description_block(['block_id' => 9, 'code' => 'text', 'sort_order' => 8, 'status' => 0, 'content' => [1 => ['body' => 'Hidden']]]),
		];

		$document = AdvancedDescriptionMigration::document($blocks, [1, 3]);
		$types = array_column($document['sections'], 'type');

		Assert::same(['text_image', 'text', 'stats', 'features', 'statement', 'gallery', 'text'], $types, 'container children come first, stats heading gets a text section');
		Assert::same('left', $document['sections'][0]['settings']['position']);
		Assert::contains('<p>Body</p>', $document['sections'][0]['content'][1]['body'], 'display encoding is decoded once');
		Assert::contains('One', $document['sections'][0]['content'][1]['body']);
		Assert::contains('/guide', $document['sections'][0]['content'][1]['body']);
		Assert::same('Numbers', trim($document['sections'][1]['content'][1]['body']));
		Assert::same('10', $document['sections'][2]['rows'][0]['content'][1]['value']);
		Assert::same('sparkles', $document['sections'][3]['rows'][0]['icon'], 'unsupported Builder icon uses the closed AD whitelist');
		Assert::same('Author', $document['sections'][4]['content'][1]['footnote']);
		Assert::same('Caption', $document['sections'][5]['rows'][0]['content'][1]['caption']);
		Assert::notContains('<script', $document['sections'][6]['content'][1]['body']);
		Assert::notContains('onclick', $document['sections'][6]['content'][1]['body']);
		Assert::contains('Safe', $document['sections'][6]['content'][1]['body']);
		Assert::notContains('Hidden', (string)json_encode($document));
	},

	'converter: empty pages fall back to classic content and translations survive' => static function (): void {
		$document = AdvancedDescriptionMigration::document([], [1, 3], [1 => '&lt;p&gt;English&lt;/p&gt;', 3 => '&lt;p&gt;Русский&lt;/p&gt;']);

		Assert::count(1, $document['sections']);
		Assert::same('text', $document['sections'][0]['type']);
		Assert::same('<p>English</p>', $document['sections'][0]['content'][1]['body']);
		Assert::same('<p>Русский</p>', $document['sections'][0]['content'][3]['body']);
	},

	'converter: more than ten sections are merged without losing copy' => static function (): void {
		$blocks = [];

		for ($i = 1; $i <= 12; $i++) {
			$blocks[] = blog_advanced_description_block([
				'block_id' => $i, 'sort_order' => $i, 'content' => [1 => ['heading' => 'Block ' . $i, 'body' => 'Copy ' . $i]],
			]);
		}

		$document = AdvancedDescriptionMigration::document($blocks, [1]);

		Assert::count(Sections::MAX_SECTIONS, $document['sections']);
		Assert::same('Block 9', $document['sections'][8]['content'][1]['heading']);
		Assert::contains('Block 10', $document['sections'][9]['content'][1]['body']);
		Assert::contains('Block 12', $document['sections'][9]['content'][1]['body']);
	},

	'data migration: saves both halves before clearing Builder and deleting the landing' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['language_id' => 1], ['language_id' => 3]]);
		$db->pushResult([['post_id' => 5, 'use_builder' => 1, 'use_advanced_description' => 0, 'advanced_description' => '']]);
		$db->pushResult([
			['language_id' => 1, 'content' => '&lt;p&gt;Classic&lt;/p&gt;', 'advanced_content' => null],
			['language_id' => 3, 'content' => '&lt;p&gt;Классика&lt;/p&gt;', 'advanced_content' => null],
		]);
		$db->pushResult([['landing_id' => 42]]);
		$db->pushResult([[
			'block_id' => 7, 'parent_id' => 0, 'slot' => 0, 'code' => 'text', 'sort_order' => 0, 'status' => 1,
			'settings' => '{}',
		]]);
		$db->pushResult([[
			'block_id' => 7, 'language_id' => 1,
			'content' => '{"heading":"Builder","body":"&lt;p&gt;Built copy&lt;/p&gt;"}',
		]]);
		$db->pushResult([]);

		$result = AdvancedDescriptionMigration::migrate(blog_advanced_description_registry($db));

		Assert::same(1, $result['converted']);
		Assert::same(1, $result['deleted_landings']);
		Assert::same([5], $result['post_ids']);

		$save = blog_advanced_description_query_index($db, "UPDATE `oc_blog_post` SET use_advanced_description");
		$clear = blog_advanced_description_query_index($db, "UPDATE `oc_blog_post` SET use_builder = '0'");
		$delete = blog_advanced_description_query_index($db, "DELETE FROM `oc_landing` WHERE landing_id = '42'");
		$commit = blog_advanced_description_query_index($db, 'COMMIT');
		Assert::true(is_int($save) && is_int($clear) && is_int($delete) && is_int($commit), 'all migration phases ran');
		Assert::true($save < $clear && $clear < $delete && $delete < $commit, 'AD is saved before Builder data is removed');
		Assert::contains('\"type\":\"text\"', $db->queries[$save]);
		Assert::contains('Builder', (string)implode("\n", $db->queries));
		Assert::contains('Built copy', (string)implode("\n", $db->queries));
		Assert::contains('DELETE lbd FROM `oc_landing_block_description`', (string)implode("\n", $db->queries));
		Assert::contains("DELETE FROM `oc_landing_block` WHERE landing_id = '42'", (string)implode("\n", $db->queries));
		Assert::contains("DELETE FROM `oc_landing_description` WHERE landing_id = '42'", (string)implode("\n", $db->queries));
	},

	'data migration: an existing Advanced Description is preserved' => static function (): void {
		$db = new FakeDb();
		$structure = '{"sections":[{"sid":"mine","type":"text","settings":{"align":"left"}}]}';
		$db->pushResult([['language_id' => 1]]);
		$db->pushResult([['post_id' => 5, 'use_builder' => 1, 'use_advanced_description' => 1, 'advanced_description' => $structure]]);
		$db->pushResult([['language_id' => 1, 'content' => 'Classic', 'advanced_content' => '{"mine":{"body":"Mine"}}']]);
		$db->pushResult([['landing_id' => 42]]);
		$db->pushResult([]);

		$result = AdvancedDescriptionMigration::migrate(blog_advanced_description_registry($db));

		Assert::same(0, $result['converted']);
		Assert::same(1, $result['preserved']);
		Assert::notContains('advanced_description =', (string)implode("\n", $db->queries), 'operator content is never overwritten');
		Assert::notContains('advanced_content =', (string)implode("\n", $db->queries), 'translated sections are never overwritten');
		Assert::contains("UPDATE `oc_blog_post` SET use_builder = '0'", (string)implode("\n", $db->queries));
		Assert::contains("DELETE FROM `oc_landing` WHERE landing_id = '42'", (string)implode("\n", $db->queries));
	},

	'data migration: a failed save rolls back and keeps the Builder landing' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['language_id' => 1]]);
		$db->pushResult([['post_id' => 5, 'use_builder' => 1, 'use_advanced_description' => 0, 'advanced_description' => '']]);
		$db->pushResult([['language_id' => 1, 'content' => 'Classic', 'advanced_content' => null]]);
		$db->pushResult([['landing_id' => 42]]);
		$db->pushResult([]);
		$db->pushResult([]);
		$db->failOn('advanced_description =', 'simulated write failure');

		Assert::throws(\RuntimeException::class, static function () use ($db): void {
			AdvancedDescriptionMigration::migrate(blog_advanced_description_registry($db));
		});

		Assert::contains('ROLLBACK', (string)implode("\n", $db->queries));
		Assert::notContains('DELETE FROM `oc_landing`', (string)implode("\n", $db->queries), 'the source page survives a failed conversion');
		Assert::notContains('COMMIT', (string)implode("\n", $db->queries));
	},

	'data migration: orphan post-owned landings are deleted before dropping post_id' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['language_id' => 1]]);
		$db->pushResult([]);
		$db->pushResult([['landing_id' => 99]]);

		$result = AdvancedDescriptionMigration::migrate(blog_advanced_description_registry($db));

		Assert::same(1, $result['deleted_landings']);
		Assert::contains("DELETE FROM `oc_landing` WHERE landing_id = '99'", (string)implode("\n", $db->queries));
		Assert::contains('COMMIT', (string)implode("\n", $db->queries));
	},

	'admin model: addPost stores both Advanced Description halves' => static function (): void {
		$db = new FakeDb();
		$data = blog_advanced_description_post_data(['use_advanced_description' => 1]);
		$model = blog_advanced_description_model($db);

		$post_id = $model->addPost($data);
		$sql = (string)implode("\n", $db->queries);

		Assert::true($post_id > 0);
		Assert::contains("use_advanced_description = '1'", $sql);
		Assert::contains('advanced_description =', $sql);
		Assert::contains('advanced_content =', $sql);
		Assert::contains('Classic', $sql);
		Assert::notContains('use_builder', $sql);
	},

	'admin model: editPost preserves stored Advanced Description on a classic/API save' => static function (): void {
		$db = new FakeDb();
		$structure = '{"sections":[{"sid":"saved","type":"text","settings":{"align":"left"}}]}';
		$content = '{"saved":{"body":"Keep me"}}';
		$db->pushResult([['use_advanced_description' => 1, 'advanced_description' => $structure]]);
		$db->pushResult([[
			'language_id' => 1, 'name' => 'Article', 'description' => 'Excerpt',
			'content' => 'Old classic', 'meta_title' => 'Article', 'meta_description' => '',
			'meta_keyword' => '', 'advanced_content' => $content,
		]]);
		$db->pushResult([]);
		$data = blog_advanced_description_post_data();
		$data['post_description'][1]['content'] = 'New classic';

		$model = blog_advanced_description_model($db);
		$model->editPost(5, $data);
		$sql = (string)implode("\n", $db->queries);

		Assert::contains("use_advanced_description = '1'", $sql, 'an absent mode key keeps advanced mode');
		Assert::contains('Keep me', $sql, 'stored translated sections survive');
		Assert::contains('New classic', $sql);
		Assert::notContains('use_builder', $sql);
	},

	'admin form: the shared editor replaces Builder and keeps classic copy editable' => static function (): void {
		$controller = repo_file('upload/admin/controller/extension/module/dockercart_blog_post.php');
		$form = repo_file('upload/admin/view/template/extension/module/dockercart_blog_post_form.twig');

		Assert::contains("load->language('common/description_sections')", $controller);
		Assert::contains('Description\\Admin::state', $controller);
		Assert::contains('dockercart_description_editor.js', $controller);
		Assert::contains("'content'", $controller, 'blog resolver reads the article body');
		Assert::contains('Sections::forView', $controller, 'validation accepts non-empty advanced sections');
		Assert::contains("include 'common/description_mode.twig'", $form);
		Assert::contains("include 'common/description_editor.twig'", $form);
		Assert::contains('data-description-slot', $form);
		Assert::contains('data-description-classic-toggle', $form);
		Assert::contains('data-description-classic-body', $form);
		Assert::notContains('use_builder', $form);
		Assert::notContains('setBuilderMode', $form);
		Assert::notContains('can_builder', $controller);
		Assert::notContains('enableBuilder', $controller);
	},

	'storefront: blog renders Advanced Description inside the classic article shell' => static function (): void {
		$view = repo_file('upload/system/library/dockercart/description/view.php');
		$model = repo_file('upload/catalog/model/extension/module/dockercart_blog_post.php');
		$controller = repo_file('upload/catalog/controller/blog/post.php');
		$template = repo_file('upload/catalog/view/theme/dockercart/template/blog/post.twig');

		Assert::contains("'blog_post'    => ['table' => 'blog_post_description', 'id' => 'post_id']", $view);
		Assert::contains('bpd.advanced_content', $model, 'single-post query loads translated section texts');
		Assert::notContains('SELECT bp.*, bpd.name', $model, 'card/related queries do not load the heavy structure JSON');
		Assert::contains('Description\\View::sections', $controller);
		Assert::contains("'blog_post'", $controller);
		Assert::notContains('getLandingByPost', $controller);
		Assert::notContains("load->controller('landing/page')", $controller);
		Assert::contains("include 'dockercart/template/description/sections.twig'", $template);
		Assert::contains('{{ content }}', $template, 'classic content remains the fallback');
	},

	'search: classic and Advanced Description text are indexed without Builder SQL' => static function (): void {
		$model = repo_file('upload/admin/model/extension/module/dockercart_search.php');

		Assert::notContains('builderPostContent', $model);
		Assert::notContains('l.post_id', $model);
		Assert::notContains('use_builder', $model);
		Assert::contains('bpd.advanced_content', $model);
		Assert::contains('advancedBlogText', $model);
		Assert::contains('Sections::plainText', $model);
		Assert::contains('$classic_content . \' \' . $advanced_content', $model);

		$normalized = Sections::normalizeDocument(['sections' => [[
			'type' => 'features',
			'settings' => ['columns' => '3'],
			'rows' => [['icon' => 'cpu', 'content' => [1 => ['title' => 'Fast', 'text' => 'Very fast']]]],
			'content' => [1 => ['heading' => '<b>Highlights</b>']],
		]]], [1]);
		$sections = Sections::forView($normalized['structure'], $normalized['content'], 1, 1);

		Assert::same('Highlights Fast Very fast', Sections::plainText($sections));
	},

	'landing builder: the post-owner protocol is gone while information pages keep theirs' => static function (): void {
		$admin_model = repo_file('upload/admin/model/design/landing.php');
		$admin_controller = repo_file('upload/admin/controller/design/landing.php');
		$catalog_controller = repo_file('upload/catalog/controller/landing/page.php');
		$catalog_model = repo_file('upload/catalog/model/design/landing.php');
		$shell = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::notContains('post_id', $admin_model);
		Assert::notContains('validateBlogOwner', $admin_model);
		Assert::notContains('owner_post_id', $admin_controller);
		Assert::notContains('text_owner_post', $admin_controller);
		Assert::notContains('owner_post_id', $catalog_controller);
		Assert::notContains('getLandingByPost', $catalog_model);
		Assert::notContains('owner_post_id', $shell);
		Assert::contains('information_id', $admin_model, 'information-owned landings still work');
		Assert::contains("load->controller('landing/page')", repo_file('upload/catalog/controller/information/information.php'));

		$install = repo_file('upload/admin/controller/extension/module/dockercart_blog_install.sql');
		Assert::notContains('use_builder', $install);
		Assert::contains('use_advanced_description', $install);
		Assert::contains('advanced_content', $install);
	},

	'api: blog responses hide internal section JSON but keep the documented fields' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([[
			'language_id' => 1, 'code' => 'en-gb', 'name' => 'English',
			'locale' => 'en-gb', 'status' => 1, 'sort_order' => 1,
		]]);
		$config = new Config();
		$config->set('config_language_id', 1);
		$registry = new Registry();
		$registry->set('db', $db);
		$registry->set('config', $config);
		$payload = new \Dockercart\Api\Payload\BlogPost($registry);

		$response = $payload->descriptionsToResponse([1 => [
			'title' => '&lt;b&gt;Article&lt;/b&gt;',
			'excerpt' => 'Excerpt',
			'content' => '&lt;p&gt;Classic&lt;/p&gt;',
			'meta_title' => '',
			'meta_description' => '',
			'meta_keyword' => '',
			'tags' => 'a, b',
			'advanced_content' => '{"secret":true}',
		]]);

		Assert::same(['title', 'excerpt', 'content', 'meta_title', 'meta_description', 'meta_keyword', 'tags'], array_keys($response['en-gb']));
		Assert::same('<b>Article</b>', $response['en-gb']['title']);
		Assert::same('a, b', $response['en-gb']['tags']);
		Assert::notContains('advanced_content', (string)json_encode($response));
	},
];
