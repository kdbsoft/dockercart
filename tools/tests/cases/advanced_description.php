<?php
/**
 * Advanced Description: the lightweight description-section subsystem.
 *
 * Products, categories and manufacturers may switch their description slot
 * from the classic WYSIWYG copy to a flat list of descriptive sections. This
 * is intentionally independent of the landing page builder. The tests guard:
 *
 *   1. the idempotent nine-column migration;
 *   2. the six-type registry, its templates, field schema and icon whitelist;
 *   3. the six preset compositions;
 *   4. storage normalisation (unknown types/icons, scripts, limits, ids);
 *   5. editor/store round-trips and the default-language fallback;
 *   6. save resolution (conversion, classic fallback, stored-mode preservation,
 *      raw JSON copy payloads and Request::clean entity decoding);
 *   7. the three admin models/controllers/forms and storefront integrations;
 *   8. locale parity for the shared registry and the new editor labels.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Description\Admin;
use Dockercart\Description\Sections;

const ADVANCED_DESCRIPTION_MIGRATION = 'docker/mysql/migrations/20261124_advanced_description.sql';

const ADVANCED_DESCRIPTION_TEMPLATE_DIR = '/upload/catalog/view/theme/dockercart/template/description/sections/';

const ADVANCED_DESCRIPTION_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const ADVANCED_DESCRIPTION_ENTITIES = ['product', 'category', 'manufacturer'];

const ADVANCED_DESCRIPTION_LABELS = [
	'text_advanced_description',
	'text_mode_classic',
	'text_mode_advanced',
	'text_switch_to_advanced',
	'text_switch_to_classic',
	'text_classic_description_hint',
	'text_advanced_description_hint',
	'text_add_section',
	'text_apply_template',
	'text_empty_sections',
	'text_move_up',
	'text_move_down',
	'text_remove_section',
	'text_add_row',
	'text_remove_row',
	'text_select_image',
	'text_remove_image',
	'text_confirm_template',
	'text_section_limit',
	'text_row_limit',
];

/**
 * Loads an OpenCart language file into an isolated key => value map.
 *
 * @return array<string, string>
 */
function advanced_description_language(string $locale, string $file): array {
	$_ = [];
	include REPO_ROOT . '/upload/admin/language/' . $locale . '/' . $file . '.php';

	return $_;
}

return [
	'migration: exists and carries exactly nine idempotent columns' => static function (): void {
		$sql = (string)file_get_contents(REPO_ROOT . '/' . ADVANCED_DESCRIPTION_MIGRATION);

		Assert::true($sql !== '', 'migration file exists');
		Assert::same(9, substr_count($sql, 'ADD COLUMN IF NOT EXISTS'), 'all nine columns are idempotent');
		Assert::same(3, substr_count($sql, 'ALTER TABLE `oc_product`') + substr_count($sql, 'ALTER TABLE `oc_category`') + substr_count($sql, 'ALTER TABLE `oc_manufacturer`'), 'three entity tables');
		Assert::same(3, substr_count($sql, 'ALTER TABLE `oc_product_description`') + substr_count($sql, 'ALTER TABLE `oc_category_description`') + substr_count($sql, 'ALTER TABLE `oc_manufacturer_description`'), 'three description tables');
		Assert::contains('use_advanced_description', $sql);
		Assert::contains('advanced_description', $sql);
		Assert::contains('advanced_content', $sql);
		Assert::notContains('oc_landing', $sql, 'the description editor is independent of Page Builder');
	},

	'registry: the six descriptive types are stable' => static function (): void {
		Assert::same(
			['text', 'text_image', 'features', 'gallery', 'stats', 'statement'],
			array_keys(Sections::types())
		);
	},

	'registry: every type has a storefront template and a known schema' => static function (): void {
		$allowed = ['text', 'textarea', 'richtext', 'select', 'image', 'repeater'];

		foreach (Sections::types() as $type => $definition) {
			Assert::true(is_file(REPO_ROOT . '/' . ADVANCED_DESCRIPTION_TEMPLATE_DIR . $type . '.twig'), 'missing template ' . $type);
			Assert::true($definition['name'] !== '' && $definition['icon'] !== '', $type . ' has editor metadata');

			foreach ($definition['fields'] as $field) {
				Assert::true(in_array($field['type'], $allowed, true), $type . ' uses a supported field type');
				Assert::true(array_key_exists('localized', $field), $type . ' declares localization');
				Assert::true(array_key_exists('default', $field), $type . ' declares a default');

				if ($field['type'] === 'repeater') {
					foreach ($field['fields'] as $sub) {
						Assert::true(in_array($sub['type'], $allowed, true), $type . ' repeater field type');
						Assert::true(array_key_exists('localized', $sub), $type . ' repeater localization');
					}
				}
			}
		}
	},

	'registry: feature icons are a closed Lucide whitelist' => static function (): void {
		Assert::true(count(Sections::ICONS) >= 20, 'useful icon palette');
		Assert::true(isset(Sections::ICONS['cpu'], Sections::ICONS['zap'], Sections::ICONS['heart']));

		foreach (Sections::ICONS as $icon => $label) {
			Assert::true((bool)preg_match('/^[a-z0-9-]+$/', $icon), 'safe Lucide name ' . $icon);
			Assert::true(str_starts_with($label, 'icon_'), 'translatable label ' . $label);
		}
	},

	'presets: all six compositions use allowed types and sane settings' => static function (): void {
		$expected_counts = [
			'specs_highlights' => 3,
			'product_launch'   => 4,
			'visual_story'     => 3,
			'lookbook'         => 3,
			'gift_guide'       => 4,
			'campaign'         => 4,
		];

		Assert::same(array_keys($expected_counts), array_keys(Sections::presets()));

		foreach ($expected_counts as $code => $count) {
			$document = Sections::presetDocument($code, [1, 3]);

			Assert::true(is_array($document), $code);
			Assert::count($count, $document['sections'], $code . ' section count');

			foreach ($document['sections'] as $section) {
				Assert::true(Sections::typeExists($section['type']), $code . ' allowed type');

				foreach ($section['settings'] as $key => $value) {
					if ($key === 'columns') {
						Assert::true(in_array($value, ['2', '3', '4'], true), $code . ' columns');
					}
				}
			}
		}

		$gift_guide = Sections::presetDocument('gift_guide', [1, 3]);
		Assert::same(['text', 'gallery', 'features', 'statement'], array_column($gift_guide['sections'], 'type'), 'gift guide keeps its intended composition');
		Assert::count(4, $gift_guide['sections'][1]['rows'], 'gift guide gallery has four slots');
		Assert::count(3, $gift_guide['sections'][2]['rows'], 'gift guide highlights have three rows');
	},

	'normalisation: unknown types, fields and icons never reach storage' => static function (): void {
		$normalized = Sections::normalizeDocument(['sections' => [
			['sid' => 'bad id!', 'type' => 'script', 'settings' => ['x' => 'y']],
			['sid' => 'a', 'type' => 'features', 'settings' => ['columns' => '99', 'unknown' => 'drop'], 'rows' => [
				['icon' => 'javascript:alert(1)', 'content' => [1 => ['title' => '<b>CPU</b>', 'text' => '<script>x</script>Fast']]],
			]],
			['sid' => 'a', 'type' => 'text', 'content' => [1 => ['heading' => '<i>Title</i><script>bad()</script>', 'body' => '<p onclick="x()">Body</p><script>bad()</script>']]],
		]], [1, 3]);

		Assert::count(2, $normalized['structure']['sections'], 'unknown section dropped, duplicate id kept');
		Assert::same('a', $normalized['structure']['sections'][0]['sid']);
		Assert::notSame('a', $normalized['structure']['sections'][1]['sid'], 'duplicate ids are made unique');
		Assert::same('4', $normalized['structure']['sections'][0]['settings']['columns'], 'invalid columns use the default');
		Assert::false(isset($normalized['structure']['sections'][0]['settings']['unknown']), 'unknown setting dropped');
		Assert::same('sparkles', $normalized['structure']['sections'][0]['settings']['rows'][0]['icon'], 'invalid icon uses the default');
		Assert::same('CPU', $normalized['content'][1]['a']['rows'][0]['title'], 'plain text is stripped');
		Assert::same('xFast', $normalized['content'][1]['a']['rows'][0]['text'], 'plain textarea tags are stripped and the remainder stays escaped at render time');
		Assert::notContains('<script', $normalized['content'][1][$normalized['structure']['sections'][1]['sid']]['body']);
		Assert::notContains('onclick', $normalized['content'][1][$normalized['structure']['sections'][1]['sid']]['body']);
	},

	'normalisation: sections and repeater rows are capped' => static function (): void {
		$sections = [];

		for ($i = 0; $i < 15; $i++) {
			$sections[] = ['type' => 'text'];
		}

		$normalized = Sections::normalizeDocument(['sections' => $sections], [1]);
		Assert::count(Sections::MAX_SECTIONS, $normalized['structure']['sections']);

		$normalized = Sections::normalizeDocument(['sections' => [[
			'type'    => 'stats',
			'settings' => ['columns' => '3'],
			'rows'    => array_fill(0, 20, ['content' => [1 => ['value' => '1']]]),
		]]], [1]);
		Assert::count(Sections::MAX_ROWS, $normalized['structure']['sections'][0]['settings']['rows']);
		Assert::count(Sections::MAX_ROWS, $normalized['content'][1]['s1']['rows']);
	},

	'editor round-trip: shared rows and per-language text survive a save' => static function (): void {
		$document = ['sections' => [[
			'sid'      => 'features',
			'type'     => 'features',
			'settings' => ['columns' => '3'],
			'rows'     => [[
				'icon'    => 'cpu',
				'content' => [1 => ['title' => 'Processor', 'text' => 'Fast'], 3 => ['title' => 'Процессор']],
			]],
			'content'  => [1 => ['heading' => 'Highlights'], 3 => []],
		]]];

		$normalized = Sections::normalizeDocument($document, [1, 3]);
		$editor = Sections::editorDocument($normalized['structure'], $normalized['content'], [1, 3]);
		$section = $editor['sections'][0];

		Assert::same('cpu', $section['rows'][0]['icon']);
		Assert::same('Processor', $section['rows'][0]['content'][1]['title']);
		Assert::same('Процессор', $section['rows'][0]['content'][3]['title']);
		Assert::same('Highlights', $section['content'][1]['heading']);
	},

	'storefront view: empty localized fields fall back to the default language' => static function (): void {
		$normalized = Sections::normalizeDocument(['sections' => [[
			'type'     => 'features',
			'settings' => ['columns' => '3'],
			'rows'     => [[
				'icon'    => 'zap',
				'content' => [1 => ['title' => 'Battery', 'text' => 'All day'], 3 => ['title' => 'Батарея']],
			]],
			'content'  => [1 => ['heading' => 'Highlights'], 3 => []],
		]]], [1, 3]);

		$view = Sections::forView($normalized['structure'], $normalized['content'], 3, 1);

		Assert::count(1, $view);
		Assert::same('Highlights', $view[0]['content']['heading']);
		Assert::same('Батарея', $view[0]['rows'][0]['title']);
		Assert::same('All day', $view[0]['rows'][0]['text']);
	},

	'storefront view: untouched template sections are not painted' => static function (): void {
		$normalized = Sections::normalizeDocument(Sections::presetDocument('lookbook', [1]), [1]);

		Assert::same([], Sections::forView($normalized['structure'], $normalized['content'], 1, 1));
	},

	'save resolution: first enable converts the classic copy once' => static function (): void {
		[$flag, $structure, $content] = Sections::resolveSave([
			'use_advanced_description' => 1,
			'product_description'      => [1 => ['description' => '<p>Classic EN</p>'], 3 => ['description' => '<p>Классика</p>']],
		], 'product_description');

		Assert::same(1, $flag);
		Assert::contains('"type":"text"', $structure);
		Assert::contains('Classic EN', $content[1]);
		Assert::contains('Классика', $content[3]);
	},

	'save resolution: classic mode keeps the stored halves for a later re-enable' => static function (): void {
		$structure = '{"sections":[{"sid":"s1","type":"text","settings":{"align":"left"}}]}';
		$content = '{"s1":{"heading":"Saved"}}';

		[$flag, $saved_structure, $saved_content] = Sections::resolveSave([
			'product_description' => [1 => ['description' => 'New classic copy']],
		], 'product_description', $structure, [1 => ['advanced_content' => $content]]);

		Assert::same(0, $flag);
		Assert::same($structure, $saved_structure);
		Assert::same($content, $saved_content[1]);
	},

	'save resolution: an absent mode key preserves the stored mode (REST safety)' => static function (): void {
		[$flag] = Sections::resolveSave(['product_description' => [1 => ['description' => 'x']]], 'product_description', '{"sections":[]}', [], 1);
		Assert::same(1, $flag, 'a REST/programmatic save must not disable Advanced Description');

		[$flag] = Sections::resolveSave(['use_advanced_description' => 0], 'product_description', '{"sections":[]}', [], 1);
		Assert::same(0, $flag, 'an explicit form toggle does disable it');
	},

	'save resolution: copy payloads may carry the stored JSON as strings' => static function (): void {
		[$flag, $structure, $content] = Sections::resolveSave([
			'use_advanced_description' => '1',
			'advanced_description'    => '{"sections":[{"sid":"a","type":"stats","settings":{"columns":"3"}}]}',
			'category_description'    => [1 => ['description' => 'copy', 'advanced_content' => '{"a":{"rows":[{"value":"10"}]}}']],
		], 'category_description');

		Assert::same(1, $flag);
		Assert::contains('"type":"stats"', $structure);
		Assert::same('{"a":{"rows":[{"value":"10"}]}}', $content[1]);
	},

	'admin config: keyed getLanguages() rows become a JSON array for the editor' => static function (): void {
		$language = new class {
			public function get(string $key): string {
				return $key;
			}
		};
		$config = Admin::config($language, [
			1 => ['language_id' => 1, 'code' => 'en-gb', 'name' => 'English'],
			3 => ['language_id' => 3, 'code' => 'ru-ua', 'name' => 'Russian'],
		]);

		Assert::same([0, 1], array_keys($config['languages']), 'the browser expects a list, not an object');
		Assert::same([1, 3], array_column($config['languages'], 'id'));
	},

	'admin boundary: Request::clean entity escaping is decoded before JSON' => static function (): void {
		$json = (string)json_encode([
			'sections' => [
				['type' => 'text', 'content' => [1 => ['heading' => 'A & B "quoted"']]],
			],
		]);
		$escaped = htmlspecialchars($json, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$posted = Admin::posted($escaped, [['language_id' => 1, 'code' => 'en-gb', 'name' => 'English']]);

		Assert::same('A & B "quoted"', $posted['content'][1]['s1']['heading']);
	},

	'save resolution: the model accepts the Request::clean-escaped editor JSON' => static function (): void {
		$document = ['sections' => [[
			'sid'     => 'form',
			'type'    => 'text',
			'settings' => ['align' => 'center'],
			'content' => [1 => ['heading' => 'From the form', 'body' => '<p>Body</p>']],
		]]];
		$escaped = htmlspecialchars((string)json_encode($document), ENT_COMPAT, 'UTF-8');

		[$flag, $structure, $content] = Sections::resolveSave([
			'use_advanced_description' => 1,
			'advanced_description'    => $escaped,
			'product_description'      => [1 => ['description' => 'classic']],
		], 'product_description');

		Assert::same(1, $flag);
		Assert::contains('"sid":"form"', $structure);
		Assert::same('From the form', json_decode($content[1], true)['form']['heading']);
	},

	'models: all three persist the flag, structure and per-language content' => static function (): void {
		foreach (ADVANCED_DESCRIPTION_ENTITIES as $entity) {
			$source = (string)file_get_contents(REPO_ROOT . '/upload/admin/model/catalog/' . $entity . '.php');

			Assert::contains('use_advanced_description', $source, $entity . ' persists the mode');
			Assert::contains('advanced_description', $source, $entity . ' persists the structure');
			Assert::contains('advanced_content', $source, $entity . ' persists the texts');
			Assert::contains('Sections::resolveSave', $source, $entity . ' uses the shared save resolver');
		}
	},

	'models: category and manufacturer copies carry both new columns' => static function (): void {
		foreach (['category', 'manufacturer'] as $entity) {
			$source = (string)file_get_contents(REPO_ROOT . '/upload/admin/model/catalog/' . $entity . '.php');

			Assert::contains('$data["use_advanced_description"] = $' . $entity, $source, $entity . ' copies the mode');
			Assert::contains('$data["advanced_description"] = (string) ($' . $entity, $source, $entity . ' copies the structure');
		}
	},

	'product query: the manufacturer join cannot overwrite product description columns' => static function (): void {
		$source = (string)file_get_contents(REPO_ROOT . '/upload/catalog/model/catalog/product.php');

		Assert::same(2, substr_count($source, 'p.use_advanced_description, p.advanced_description, pd.advanced_content'), 'single and bulk product queries re-alias the colliding columns');
	},

	'storefront: all three controllers build sections and templates render them' => static function (): void {
		foreach (ADVANCED_DESCRIPTION_ENTITIES as $entity) {
			$controller = (string)file_get_contents(REPO_ROOT . '/upload/catalog/controller/product/' . $entity . '.php');
			Assert::contains('Description\\View::sections', $controller, $entity . ' controller builds sections');
		}

		foreach (['product', 'category', 'manufacturer_info'] as $template) {
			$source = (string)file_get_contents(REPO_ROOT . '/upload/catalog/view/theme/dockercart/template/product/' . $template . '.twig');
			Assert::contains("include 'dockercart/template/description/sections.twig'", $source, $template . ' renders the region');
		}
	},

	'admin: one shared editor is wired into all three forms' => static function (): void {
		foreach (ADVANCED_DESCRIPTION_ENTITIES as $entity) {
			$controller = (string)file_get_contents(REPO_ROOT . '/upload/admin/controller/catalog/' . $entity . '.php');
			$form = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/template/catalog/' . $entity . '_form.twig');

			Assert::contains("load->language('common/description_sections')", $controller, $entity . ' loads shared labels');
			Assert::contains('Description\\Admin::state', $controller, $entity . ' prepares editor state');
			Assert::contains('dockercart_description_editor.js', $controller, $entity . ' loads the editor');
			Assert::contains("include 'common/description_editor.twig'", $form, $entity . ' embeds the editor');

			$tabs_position = strpos($form, '<ul class="nav nav-tabs" id="language">');
			$editor_position = strpos($form, "include 'common/description_editor.twig'");
			$content_position = strpos($form, '<div class="tab-content">');
			Assert::true($tabs_position !== false && $editor_position !== false && $content_position !== false
				&& $tabs_position < $editor_position && $editor_position < $content_position,
				$entity . ' places the shared language tabs above both advanced and classic description fields');
		}

		$editor = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/javascript/dockercart_description_editor.js');
		$partial = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/template/common/description_editor.twig');

		Assert::contains("jQuery(document).on('shown.bs.tab'", $editor, 'Bootstrap 3 custom tab events must be received through jQuery');
		Assert::contains('languages|first.language_id', $partial, 'the initial language works with keyed getLanguages() rows');
		Assert::contains('dc-opts-toolbar dc-ad-mode-selector', $partial, 'the mode switcher uses the information-form toolbar style');
		Assert::contains('data-description-mode-toggle', $partial, 'the mode switcher is a toggle button, not a select');
		Assert::contains('name="use_advanced_description"', $partial, 'the mode still posts through the 0/1 hidden input');
		Assert::same(0, substr_count($partial, '<ul class="dropdown-menu"'), 'template choices do not use dropdowns');
		Assert::notContains('dropdown-toggle', $partial, 'there is no dropdown trigger');
		Assert::notContains('data-description-picker', $partial, 'the picker buttons and panels are removed');
		Assert::contains('data-description-add-toggle', $partial, 'the compact add-section control is present');
		Assert::contains('data-description-add-panel', $partial, 'the add-section control has an inline panel');
		Assert::contains('data-description-add-cards', $partial, 'the add-section panel has a card mount point');
		Assert::contains('class="dc-ad-add-section__grid"', $partial, 'section choices use the two-column card grid');
		$sections_position = strpos($partial, 'class="dc-ad-sections"');
		$add_position = strpos($partial, 'class="dc-ad-add-section"');
		Assert::true($sections_position !== false && $add_position !== false && $sections_position < $add_position, 'the add-section control sits below the current section list');
		Assert::notContains('data-description-counter', $partial, 'the unused section counter is removed');
		Assert::notContains('text_apply_template', $partial, 'the Apply template heading is removed');
		Assert::notContains('layout-template', $partial, 'template cards do not render the layout-template icon');
		Assert::same(1, substr_count($partial, 'class="dc-ad-templates__grid"'), 'one always-visible template grid is rendered');
		Assert::contains('data-description-template-cards', $partial, 'template cards have a dedicated mount point');
		Assert::notContains('dc-ad-section__language', $editor, 'the shared language tabs replace repetitive per-section badges');
		Assert::contains('function renderSectionPreview', $editor, 'template cards render structural previews');
		Assert::contains('renderPresetPreview(preset)', $editor, 'template previews derive from the preset document');
		Assert::contains('function buildTemplates', $editor, 'the template grid is built directly');
		Assert::contains('function appendTemplateCard', $editor, 'each preset is rendered as an actionable card');
		Assert::contains('dc-ad-choice__number', $editor, 'template cards display their priority number');
		Assert::contains("position < 10 ? '0' + position", $editor, 'template numbers use a two-digit format');
		Assert::notContains('layout-template', $editor, 'the layout-template icon is not used by the editor');
		Assert::contains('JSON.parse(JSON.stringify(preset))', $editor, 'applying a template clones the preset before editing');
		Assert::contains('if ((state.sections || []).length && !window.confirm', $editor, 'only replacing existing sections asks for confirmation');
		Assert::contains('function buildSectionChoices', $editor, 'section choices are built from the registry');
		Assert::contains('function appendSectionCard', $editor, 'each section type is rendered as a card');
		Assert::contains('function makeSection', $editor, 'individual sections can be created from registry defaults');
		Assert::contains('data-description-add-type', $editor, 'section cards carry their registry type');
		Assert::contains('function updateSectionChoiceState', $editor, 'section choices reflect the section limit');
		Assert::contains("field.type === 'select' && field.key === 'columns'", $editor, 'column choices use a dedicated radio renderer');
		Assert::contains('<input type="radio"', $editor, 'column choices are native radio inputs');
		Assert::contains('control.matches(\'input[type="radio"]\')', $editor, 'language refresh preserves radio values and checked state');
		Assert::contains('dc-ad-row-add', $editor, 'the add-row button has a dedicated spacing class');
		Assert::notContains('btn-xs mt-3', $editor, 'the add-row button no longer depends on a utility class for spacing');
		Assert::contains('function refreshLanguage', $editor, 'language changes refresh controls in place');
		Assert::contains('editor.setContent(value || \'\');', $editor, 'rich text editors are reused when changing language');
		Assert::contains('refreshLanguage(previousLanguage);', $editor, 'the tab handler uses the in-place language refresh');

		foreach (array_keys(Sections::types()) as $type) {
			Assert::contains("case '" . $type . "':", $editor, $type . ' has a matching preview silhouette');
		}

		$css = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/stylesheet/dockercart_description_editor.css');
		Assert::contains('grid-template-columns: repeat(2, minmax(0, 1fr));', $css, 'preview cards stay two per row');
		Assert::contains('.dc-ad-templates__grid', $css, 'the template grid has dedicated styling');
		Assert::contains('.dc-ad-add-section__grid', $css, 'the section picker uses the same two-column layout');
		Assert::contains(".dc-ad-section__title {\n\tfont-size: 12.5px;", $css, 'section titles use the requested 12.5px size');
		Assert::contains('.dc-ad-choice__number', $css, 'priority numbers have dedicated styling');
		Assert::contains('.dc-ad-columns__input:checked + .dc-ad-columns__text', $css, 'radio choices have a visible checked state');
		Assert::contains(".dc-ad-columns {\n\tdisplay: flex;\n\tflex-wrap: wrap;\n\tgap: 4px;", $css, 'column radio choices use the compact 4px gap');
		Assert::contains('.dc-ad-add-section__toggle[aria-expanded="true"]', $css, 'the add-section button has a clear active state');
		Assert::contains('.dc-ad-row-add', $css, 'the add-row button has an explicit top margin');
		Assert::notContains('.dc-ad-templates__head', $css, 'the removed template heading has no leftover styling');
		Assert::contains('.dc-ad-choice:focus-visible', $css, 'preview cards have a visible keyboard focus state');
		Assert::notContains('.dc-ad-picker', $css, 'the unused picker styling is removed');
		Assert::notContains('.dc-ad-section__language', $css, 'the unused per-section language badge style is removed');
	},

	'locales: shared section labels have full parity' => static function (): void {
		$maps = [];

		foreach (ADVANCED_DESCRIPTION_LOCALES as $locale) {
			$maps[$locale] = advanced_description_language($locale, 'common/description_sections');
		}

		Assert::same(array_keys($maps['en-gb']), array_keys($maps['ru-ua']));
		Assert::same(array_keys($maps['en-gb']), array_keys($maps['uk-ua']));
		Assert::same('Преимущества', $maps['ru-ua']['preset_specs_highlights'], 'the Russian preset name stays short and clear');
	},

	'locales: every new editor label exists in every entity locale' => static function (): void {
		$empty_labels = [
			'en-gb' => 'No sections yet. Choose a template or add a section.',
			'ru-ua' => 'Секций пока нет. Выберите шаблон или добавьте секцию.',
			'uk-ua' => 'Секцій поки немає. Виберіть шаблон або додайте секцію.',
		];

		foreach (ADVANCED_DESCRIPTION_LOCALES as $locale) {
			foreach (ADVANCED_DESCRIPTION_ENTITIES as $entity) {
				$map = advanced_description_language($locale, 'catalog/' . $entity);

				foreach (ADVANCED_DESCRIPTION_LABELS as $key) {
					Assert::true(isset($map[$key]) && $map[$key] !== '', $locale . '/' . $entity . ': ' . $key);
				}

				Assert::same($empty_labels[$locale], $map['text_empty_sections'], $locale . '/' . $entity . ' empty state points to templates');
			}
		}
	},
];
