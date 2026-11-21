<?php
/**
 * Landing sections & columns: storage hierarchy guarantees.
 *
 * The builder tree (sections -> columns -> widgets) lives in a flat table keyed
 * by (parent_id, slot, sort_order). ModelDesignLanding::saveBlocks() is the
 * single writer, so its normalisation rules are exercised directly against a
 * FakeDb here:
 *
 *   1. temp ids (negative) are remapped to the inserted rows and children
 *      follow their section;
 *   2. containers are forced to the top level (no nested sections);
 *   3. a child whose parent is not part of the payload is PROMOTED to the top
 *      level instead of dropped (content never disappears);
 *   4. slot is clamped to the parent structure's column count;
 *   5. blocks missing from the payload (and their descriptions) are deleted;
 *   6. templates keep the template-local hierarchy refs intact.
 *
 * Plus static guards for the migration and the renderer surface.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LANDING_SECTIONS_MIGRATION = REPO_ROOT . '/docker/mysql/migrations/20261121_landing_sections.sql';
const LANDING_LANGUAGE_REPAIR_MIGRATION = REPO_ROOT . '/docker/mysql/migrations/20261122_landing_language_repair.sql';

/**
 * Loads the admin landing model with a fake DB and a minimal registry (same
 * pattern as information_landing.php), so saveBlocks() can run without
 * MariaDB.
 */
function landing_sections_model(FakeDb $db): ModelDesignLanding {
	if (!class_exists('Loader', false)) {
		require_once DIR_SYSTEM . 'engine/loader.php';
		require_once DIR_SYSTEM . 'engine/proxy.php';
		require_once DIR_SYSTEM . 'engine/action.php';
		require_once DIR_SYSTEM . 'engine/event.php';
	}

	require_once DIR_APPLICATION . 'model/design/landing.php';

	$config = new Config();
	$config->set('config_language_id', 1);

	$registry = new Registry();
	$registry->set('db', $db);
	$registry->set('config', $config);
	$registry->set('load', new Loader($registry));
	$registry->set('event', new Event($registry));
	$registry->set('cache', new class {
		public function get($key) {
			return [
				'en-gb' => ['language_id' => 1, 'name' => 'English', 'code' => 'en-gb', 'locale' => 'en-gb', 'image' => '', 'directory' => 'en-gb', 'sort_order' => 1, 'status' => 1],
				'ru-ua' => ['language_id' => 3, 'name' => 'Русский', 'code' => 'ru-ua', 'locale' => 'ru-ua', 'image' => '', 'directory' => 'ru-ua', 'sort_order' => 2, 'status' => 1],
			];
		}

		public function set($key, $value) {
		}
	});

	return new ModelDesignLanding($registry);
}

/**
 * @return array<int, string> INSERT INTO oc_landing_block statements, in order
 */
function landing_sections_block_inserts(FakeDb $db): array {
	$inserts = [];

	foreach ($db->queries as $sql) {
		if (strpos($sql, 'INSERT INTO `' . DB_PREFIX . 'landing_block` SET') !== false) {
			$inserts[] = $sql;
		}
	}

	return $inserts;
}

return [
	'migration: hierarchy columns are added idempotently' => static function (): void {
		Assert::true(is_file(LANDING_SECTIONS_MIGRATION));

		$sql = (string)file_get_contents(LANDING_SECTIONS_MIGRATION);

		Assert::contains('ADD COLUMN IF NOT EXISTS `parent_id`', $sql);
		Assert::contains('ADD COLUMN IF NOT EXISTS `slot`', $sql);
		Assert::contains('ADD INDEX IF NOT EXISTS `idx_landing_block_parent`', $sql);
	},

	'migration: language repair is guarded and one-shot' => static function (): void {
		Assert::true(is_file(LANDING_LANGUAGE_REPAIR_MIGRATION));

		$sql = (string)file_get_contents(LANDING_LANGUAGE_REPAIR_MIGRATION);

		Assert::contains('JSON_VALID', $sql, 'invalid JSON rows are never destroyed');
		Assert::contains('JSON_SET', $sql);
		Assert::contains("code = 'dockercart_migrations'", $sql, 'runs once, guarded by a marker row');
		Assert::contains("b.parent_id = 0", $sql, 'repairs only top-level text blocks');
		Assert::contains("= i.title", $sql, 'repairs only the seeded block (heading == page title)');
	},

	'saveBlocks: remaps temp ids and parents, forces containers top level' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([['block_id' => 10], ['block_id' => 11]]);

		$model = landing_sections_model($db);

		$saved = $model->saveBlocks(5, [
			['block_id' => 10, 'code' => 'section', 'status' => 1, 'settings' => ['structure' => '2'], 'content' => []],
			['block_id' => -1, 'code' => 'text', 'parent_id' => 10, 'slot' => 9, 'sort' => 2, 'settings' => [], 'content' => []],
			['block_id' => -3, 'code' => 'section', 'parent_id' => 10, 'status' => 1, 'settings' => ['structure' => '1'], 'content' => []],
			['block_id' => -2, 'code' => 'text', 'parent_id' => -3, 'slot' => 7, 'sort' => 1, 'settings' => [], 'content' => []],
			['block_id' => -4, 'code' => 'hero', 'parent_id' => 999, 'slot' => 0, 'sort' => 3, 'settings' => [], 'content' => []],
		]);

		// Response: payload order, real ids, hierarchy resolved.
		Assert::count(5, $saved);
		Assert::same(10, $saved[0]['block_id']);
		Assert::same(0, $saved[0]['parent_id'], 'existing section stays top level');
		Assert::same(10, $saved[1]['parent_id'], 'existing child keeps its section');
		Assert::same(1, $saved[1]['slot'], 'slot clamped to structure columns');

		$section_id = $saved[2]['block_id'];

		Assert::true($section_id > 0, 'new section got a real id');
		Assert::same(0, $saved[2]['parent_id'], 'a section submitted with a parent is forced top level');
		Assert::same($section_id, $saved[3]['parent_id'], 'temp parent ref (-3) resolved to the inserted section');
		Assert::same(0, $saved[3]['slot'], 'slot clamped to the 1-column structure');
		Assert::same(0, $saved[4]['parent_id'], 'unknown parent promotes the child to top level');
		Assert::same(3, $saved[4]['sort']);
		Assert::same(-4, $saved[4]['client_id']);

		// Statements: the container INSERT carries parent_id 0, the child INSERT
		// carries the resolved section id.
		$inserts = landing_sections_block_inserts($db);

		Assert::count(4, $inserts);
		Assert::contains("parent_id = '" . $section_id . "'", $inserts[2]);
		Assert::contains("parent_id = '0'", $inserts[1], 'the nested section is saved top level');
		Assert::contains("parent_id = '0'", $inserts[3], 'the orphan is promoted');

		// Block 11 was not in the payload -> both rows are deleted.
		Assert::contains("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '11'", implode("\n", $db->queries));
		Assert::notContains("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '10'", implode("\n", $db->queries));
	},

	'saveBlocks: clamps slot and sort to sane ranges' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([]); // no existing blocks

		$model = landing_sections_model($db);

		$saved = $model->saveBlocks(9, [
			['block_id' => -1, 'code' => 'section', 'status' => 1, 'settings' => ['structure' => '4'], 'content' => []],
			['block_id' => -2, 'code' => 'text', 'parent_id' => -1, 'slot' => 99, 'sort' => 5000, 'settings' => [], 'content' => []],
		]);

		Assert::same(3, $saved[1]['slot'], 'slot 99 clamped to the 4-column structure (max index 3)');
		Assert::same(999, $saved[1]['sort'], 'sort clamped to 999');
	},

	'saveBlocks: children never precede their section' => static function (): void {
		$db = new FakeDb();
		$db->pushResult([]);

		$model = landing_sections_model($db);

		// Malformed order: the child is submitted before the section it points
		// at. The child must survive (promoted), not vanish.
		$saved = $model->saveBlocks(9, [
			['block_id' => -1, 'code' => 'text', 'parent_id' => -2, 'slot' => 0, 'sort' => 0, 'settings' => [], 'content' => []],
			['block_id' => -2, 'code' => 'section', 'status' => 1, 'settings' => ['structure' => '2'], 'content' => []],
		]);

		Assert::count(2, $saved);
		Assert::same(0, $saved[0]['parent_id'], 'unresolvable parent promotes instead of dropping');
		Assert::same(0, $saved[1]['parent_id'], 'the section itself stays top level');
	},

	'addTemplate: keeps the template-local hierarchy references' => static function (): void {
		$db = new FakeDb();

		$model = landing_sections_model($db);

		$template_id = $model->addTemplate('Two column set', [
			['block_id' => -5, 'code' => 'section', 'status' => 1, 'settings' => ['structure' => '2-1'], 'content' => []],
			['block_id' => -6, 'code' => 'text', 'parent_id' => -5, 'slot' => 1, 'sort' => 0, 'settings' => [], 'content' => []],
		]);

		Assert::true($template_id > 0);

		$sql = implode("\n", $db->queries);

		Assert::contains('landing_template', $sql);
		Assert::contains('block_id\":-5', $sql, 'block_id stays as the template-local ref');
		Assert::contains('parent_id\":-5', $sql, 'the child points at the template-local ref');
		Assert::contains('structure', $sql);
	},

	'renderer: section template and tree anchors exist' => static function (): void {
		Assert::true(
			is_file(REPO_ROOT . '/upload/catalog/view/theme/dockercart/template/landing/blocks/section.twig'),
			'missing template landing/blocks/section.twig'
		);

		$controller = (string)file_get_contents(REPO_ROOT . '/upload/catalog/controller/landing/page.php');

		Assert::contains('data-lp-container', $controller, 'sections are marked as drop containers');
		Assert::contains('renderBlockInner', $controller, 'widgets inside columns render without the section wrapper');
		Assert::contains('only_block', $controller);

		$iframe = (string)file_get_contents(REPO_ROOT . '/upload/catalog/view/javascript/dockercart_landing_builder.js');

		Assert::contains('lp:moveTree', $iframe, 'the iframe reports the full tree on drag');
		Assert::contains('data-lp-slot', $iframe, 'columns are drop targets');

		$admin = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains('lp-navigator', $admin, 'the structure tree panel exists');
		Assert::contains('applyTree', $admin, 'tree moves from the navigator reorder the model');
	},
];
