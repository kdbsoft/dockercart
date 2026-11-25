<?php
/**
 * Advanced Description: the section registry.
 *
 * Single source of truth for what an entity's Advanced Description may
 * contain (entity = product, category, manufacturer):
 *
 *   type => name/label key, badge icon, field schema
 *
 * The admin section editor (the form JS module) renders its cards from this
 * list, the admin model normalises every saved document through
 * normalizeDocument() (unknown types and fields are dropped, values are
 * cast/whitelisted), and the storefront renders
 * description/sections/<type>.twig from the same normalised data. Nothing
 * else may decide what a section is.
 *
 * Storage model (two JSON columns per entity):
 *   <entity>.advanced_description         structure shared by every language:
 *                                        {"sections":[{"sid","type","settings"}]}
 *                                        (images, icons, column counts, order)
 *   <entity>_description.advanced_content per-language section texts keyed by
 *                                        sid: {"s1":{"heading":..,"rows":[..]}}
 *
 * The section count is deliberately tiny (six purely descriptive types, no
 * FAQ/testimonials/CTA/commerce blocks) - this is a description composer, not
 * a second page builder. The landing library is only used for its public
 * sanitizers (Blocks::sanitizeHtml()/sanitizePath()) and Text helpers.
 *
 * Document shapes:
 *   editor document (what the form JS loads/serialises):
 *     {"sections":[{"sid","type","settings":{…},"rows":[{shared…,
 *       "content":{<language_id>:{localized…}}}],
 *       "content":{<language_id>:{localized…}}}]}
 *   normalizeDocument() splits that into
 *     structure = {"sections":[{"sid","type","settings":{…,"rows":[{shared…}]}}]}
 *     content   = {<language_id>:{<sid>:{localized…,"rows":[{localized…}]}}}
 *   Repeater rows are structural: the row list and every shared sub-field
 *   (icon, image) live once in the structure; each language keeps its own
 *   texts per row index. forView() re-merges them with the default-language
 *   fallback, so adding a row once makes it available everywhere.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Description;

use Dockercart\Landing\Blocks;
use Dockercart\Landing\Text;

final class Sections {
	public const MAX_SECTIONS = 10;

	public const MAX_ROWS = 12;

	/** Lucide icon names offered by the `features` rows. Values are language-key suffixes. */
	public const ICONS = [
		'sparkles'         => 'icon_sparkles',
		'shield-check'     => 'icon_shield_check',
		'cpu'              => 'icon_cpu',
		'zap'              => 'icon_zap',
		'battery-charging' => 'icon_battery_charging',
		'feather'          => 'icon_feather',
		'ruler'            => 'icon_ruler',
		'truck'            => 'icon_truck',
		'package'          => 'icon_package',
		'package-check'    => 'icon_package_check',
		'credit-card'      => 'icon_credit_card',
		'wallet'           => 'icon_wallet',
		'headset'          => 'icon_headset',
		'gift'             => 'icon_gift',
		'heart'            => 'icon_heart',
		'leaf'             => 'icon_leaf',
		'sun'              => 'icon_sun',
		'droplets'         => 'icon_droplets',
		'thermometer'      => 'icon_thermometer',
		'wrench'           => 'icon_wrench',
		'award'            => 'icon_award',
		'globe'            => 'icon_globe',
		'smartphone'       => 'icon_smartphone',
		'headphones'       => 'icon_headphones',
	];

	private const ALIGN_OPTIONS = [
		'left'   => 'option_align_left',
		'center' => 'option_align_center',
	];

	private const POSITION_OPTIONS = [
		'left'  => 'option_position_left',
		'right' => 'option_position_right',
	];

	private const COLUMNS_OPTIONS = [
		'2' => 'option_columns_2',
		'3' => 'option_columns_3',
		'4' => 'option_columns_4',
	];

	/**
	 * Section type definitions (registry order).
	 *
	 * Field schema (same conventions as Dockercart\Landing\Blocks):
	 *   ['key' => 'heading', 'type' => 'text', 'localized' => true,
	 *    'label' => 'field_heading', 'default' => '', 'max' => 200]
	 *
	 *   localized = true  -> stored per language in <entity>_description.advanced_content
	 *   localized = false -> shared in <entity>.advanced_description
	 *
	 * One field of type 'repeater' is allowed per section; its sub-fields carry
	 * their own localized flag - shared sub-fields (icon, image) stay in the
	 * structure rows, localized ones (title, text, caption...) go per language.
	 */
	private const TYPES = [
		'text' => [
			'name'  => 'section_text',
			'icon'  => 'align-left',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'body', 'type' => 'richtext', 'localized' => true, 'label' => 'field_body', 'default' => ''],
				['key' => 'align', 'type' => 'select', 'localized' => false, 'label' => 'field_align', 'default' => 'left', 'options' => self::ALIGN_OPTIONS],
			],
		],
		'text_image' => [
			'name'  => 'section_text_image',
			'icon'  => 'image',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'body', 'type' => 'richtext', 'localized' => true, 'label' => 'field_body', 'default' => ''],
				['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_image', 'default' => ''],
				['key' => 'position', 'type' => 'select', 'localized' => false, 'label' => 'field_position', 'default' => 'right', 'options' => self::POSITION_OPTIONS],
			],
		],
		'features' => [
			'name'  => 'section_features',
			'icon'  => 'layout-grid',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '4', 'options' => self::COLUMNS_OPTIONS],
				['key' => 'rows', 'type' => 'repeater', 'localized' => false, 'label' => 'field_rows', 'default' => [], 'max_rows' => self::MAX_ROWS, 'fields' => [
					['key' => 'icon', 'type' => 'select', 'localized' => false, 'label' => 'field_icon', 'default' => 'sparkles', 'options' => self::ICONS],
					['key' => 'title', 'type' => 'text', 'localized' => true, 'label' => 'field_title', 'default' => '', 'max' => 120],
					['key' => 'text', 'type' => 'textarea', 'localized' => true, 'label' => 'field_text', 'default' => '', 'max' => 500],
				]],
			],
		],
		'gallery' => [
			'name'  => 'section_gallery',
			'icon'  => 'images',
			'fields' => [
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '3', 'options' => self::COLUMNS_OPTIONS],
				['key' => 'rows', 'type' => 'repeater', 'localized' => false, 'label' => 'field_rows', 'default' => [], 'max_rows' => self::MAX_ROWS, 'fields' => [
					['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_image', 'default' => ''],
					['key' => 'caption', 'type' => 'text', 'localized' => true, 'label' => 'field_caption', 'default' => '', 'max' => 200],
				]],
			],
		],
		'stats' => [
			'name'  => 'section_stats',
			'icon'  => 'trending-up',
			'fields' => [
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '3', 'options' => self::COLUMNS_OPTIONS],
				['key' => 'rows', 'type' => 'repeater', 'localized' => false, 'label' => 'field_rows', 'default' => [], 'max_rows' => self::MAX_ROWS, 'fields' => [
					['key' => 'value', 'type' => 'text', 'localized' => true, 'label' => 'field_value', 'default' => '', 'max' => 40],
					['key' => 'label', 'type' => 'text', 'localized' => true, 'label' => 'field_label', 'default' => '', 'max' => 120],
				]],
			],
		],
		'statement' => [
			'name'  => 'section_statement',
			'icon'  => 'quote',
			'fields' => [
				['key' => 'text', 'type' => 'textarea', 'localized' => true, 'label' => 'field_text', 'default' => '', 'max' => 500],
				['key' => 'footnote', 'type' => 'text', 'localized' => true, 'label' => 'field_footnote', 'default' => '', 'max' => 200],
			],
		],
	];

	/**
	 * Preset templates: six ready-made section compositions shown directly in
	 * the editor. Sections carry only shared structure - every localized field
	 * starts empty for each language. Only whitelisted types/settings are used,
	 * so a preset document passes normalizeDocument() untouched.
	 */
	private const PRESETS = [
		'specs_highlights' => [
			'name' => 'preset_specs_highlights',
			'sections' => [
				['type' => 'features', 'settings' => ['columns' => '4'], 'rows' => [
					['icon' => 'cpu'],
					['icon' => 'zap'],
					['icon' => 'battery-charging'],
					['icon' => 'shield-check'],
				]],
				['type' => 'text_image', 'settings' => ['position' => 'right']],
				['type' => 'stats', 'settings' => ['columns' => '3'], 'rows' => [[], [], []]],
			],
		],
		'product_launch' => [
			'name' => 'preset_product_launch',
			'sections' => [
				['type' => 'statement', 'settings' => []],
				['type' => 'features', 'settings' => ['columns' => '4'], 'rows' => [
					['icon' => 'sparkles'],
					['icon' => 'zap'],
					['icon' => 'shield-check'],
					['icon' => 'gift'],
				]],
				['type' => 'stats', 'settings' => ['columns' => '3'], 'rows' => [[], [], []]],
				['type' => 'text', 'settings' => ['align' => 'center']],
			],
		],
		'visual_story' => [
			'name' => 'preset_visual_story',
			'sections' => [
				['type' => 'text_image', 'settings' => ['position' => 'right']],
				['type' => 'text_image', 'settings' => ['position' => 'left']],
				['type' => 'statement', 'settings' => []],
			],
		],
		'lookbook' => [
			'name' => 'preset_lookbook',
			'sections' => [
				['type' => 'gallery', 'settings' => ['columns' => '3'], 'rows' => [[], [], []]],
				['type' => 'text', 'settings' => ['align' => 'center']],
				['type' => 'gallery', 'settings' => ['columns' => '2'], 'rows' => [[], []]],
			],
		],
		'gift_guide' => [
			'name' => 'preset_gift_guide',
			'sections' => [
				['type' => 'text', 'settings' => ['align' => 'center']],
				['type' => 'gallery', 'settings' => ['columns' => '4'], 'rows' => [[], [], [], []]],
				['type' => 'features', 'settings' => ['columns' => '3'], 'rows' => [
					['icon' => 'gift'],
					['icon' => 'heart'],
					['icon' => 'package-check'],
				]],
				['type' => 'statement', 'settings' => []],
			],
		],
		'campaign' => [
			'name' => 'preset_campaign',
			'sections' => [
				['type' => 'gallery', 'settings' => ['columns' => '4'], 'rows' => [[], [], [], []]],
				['type' => 'statement', 'settings' => []],
				['type' => 'text_image', 'settings' => ['position' => 'left']],
				['type' => 'stats', 'settings' => ['columns' => '3'], 'rows' => [[], [], []]],
			],
		],
	];

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function types(): array {
		return self::TYPES;
	}

	public static function typeExists(string $type): bool {
		return isset(self::TYPES[$type]);
	}

	/**
	 * Fields of one section type (schema order).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(string $type): array {
		return isset(self::TYPES[$type]['fields']) ? self::TYPES[$type]['fields'] : [];
	}

	/**
	 * The repeater field of a section type (or null when the type has none).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function repeater(string $type): ?array {
		foreach (self::fields($type) as $field) {
			if (($field['type'] ?? '') === 'repeater') {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Preset meta (code => label key) in registry order.
	 *
	 * @return array<string, string>
	 */
	public static function presets(): array {
		$presets = [];

		foreach (self::PRESETS as $code => $preset) {
			$presets[$code] = $preset['name'];
		}

		return $presets;
	}

	/**
	 * Schema for the admin editor: types, fields and preset meta with their
	 * language label keys still unresolved (see localizedSchema()).
	 *
	 * @return array<string, mixed>
	 */
	public static function schema(): array {
		return [
			'types'   => self::TYPES,
			'presets' => self::presets(),
		];
	}

	/**
	 * Resolves every label key of a schema() document through $translate
	 * (callable: language key -> translated string, key back when missing).
	 * Pure walk over the known schema shape, safe to embed as JSON.
	 *
	 * @param array<string, mixed> $schema
	 * @param callable(string): string $translate
	 * @return array<string, mixed>
	 */
	public static function localizedSchema(array $schema, callable $translate): array {
		$resolve_field = null;

		$resolve_field = static function (array $field) use ($translate, &$resolve_field): array {
			if (isset($field['label']) && is_string($field['label'])) {
				$field['label'] = $translate($field['label']);
			}

			if (isset($field['options']) && is_array($field['options'])) {
				foreach ($field['options'] as $key => $label) {
					if (is_string($label)) {
						$field['options'][$key] = $translate($label);
					}
				}
			}

			if (isset($field['fields']) && is_array($field['fields'])) {
				foreach ($field['fields'] as $index => $sub) {
					if (is_array($sub)) {
						$field['fields'][$index] = $resolve_field($sub);
					}
				}
			}

			return $field;
		};

		$types = [];

		foreach ((array)($schema['types'] ?? []) as $type => $definition) {
			if (!is_array($definition)) {
				continue;
			}

			$fields = [];

			foreach ((array)($definition['fields'] ?? []) as $field) {
				if (is_array($field)) {
					$fields[] = $resolve_field($field);
				}
			}

			$types[(string)$type] = [
				'name'   => $translate((string)($definition['name'] ?? '')),
				'icon'   => (string)($definition['icon'] ?? ''),
				'fields' => $fields,
			];
		}

		$presets = [];

		foreach ((array)($schema['presets'] ?? []) as $code => $label) {
			$presets[(string)$code] = is_string($label) ? $translate($label) : (string)$code;
		}

		return ['types' => $types, 'presets' => $presets];
	}

	/**
	 * The editor document of a preset: its section composition with empty
	 * localized content for every given language (the client applies it by
	 * replacing its sections; normalizeDocument() accepts the result).
	 *
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>|null null when the preset code is unknown
	 */
	public static function presetDocument(string $code, array $language_ids): ?array {
		if (!isset(self::PRESETS[$code])) {
			return null;
		}

		$sections = [];
		$index = 0;

		foreach ((array)self::PRESETS[$code]['sections'] as $section) {
			$index++;

			$sections[] = self::sectionForEditor((string)$section['type'], 's' . $index, (array)($section['settings'] ?? []), (array)($section['rows'] ?? []), $language_ids);
		}

		return ['sections' => $sections];
	}

	/**
	 * The conversion document: one text section per language seeded from the
	 * classic WYSIWYG copy (heading empty - the entity name is already on
	 * the page). Called when the mode switches on with no stored sections.
	 *
	 * @param array<int, string> $bodies language_id => classic description copy
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	public static function fromClassic(array $bodies, array $language_ids): array {
		$content = [];

		foreach ($language_ids as $language_id) {
			$content[(int)$language_id] = [
				'heading' => '',
				'body'    => (string)($bodies[(int)$language_id] ?? ''),
			];
		}

		$section = self::sectionForEditor('text', 's1', ['align' => 'left'], [], $language_ids);
		$section['content'] = $content;

		return ['sections' => [$section]];
	}

	/**
	 * Normalises an editor document into its storage halves.
	 *
	 * Everything hostile is dropped or clamped, never an error: unknown types,
	 * unknown fields, unknown icons, scripts in rich-text, overlong text, too
	 * many sections/rows. Rows keep their shared sub-fields once; every
	 * language receives its own copy of the localized sub-fields aligned by
	 * row index (missing rows/texts become defaults). Empty rows are kept:
	 * a row exists because the operator added it, and the storefront skips
	 * anything it cannot paint.
	 *
	 * @param array<string, mixed> $document decoded editor JSON
	 * @param array<int, int> $language_ids known languages
	 * @return array{structure: array<string, mixed>, content: array<int, array<string, array<string, mixed>>>}
	 */
	public static function normalizeDocument(array $document, array $language_ids): array {
		$language_ids = array_values(array_unique(array_map('intval', $language_ids)));

		$raw_sections = isset($document['sections']) && is_array($document['sections']) ? $document['sections'] : [];

		$structure = [];
		$content = [];
		$used_sids = [];
		$index = 0;

		foreach (array_slice($raw_sections, 0, self::MAX_SECTIONS) as $raw) {
			if (!is_array($raw)) {
				continue;
			}

			$type = (string)($raw['type'] ?? '');

			if (!self::typeExists($type)) {
				continue;
			}

			$index++;

			$sid = self::normalizeSid((string)($raw['sid'] ?? ''), $index, $used_sids);
			$raw_settings = isset($raw['settings']) && is_array($raw['settings']) ? $raw['settings'] : [];

			$settings = [];
			$localized = [];

			foreach (self::fields($type) as $field) {
				$key = (string)$field['key'];

				if ($field['type'] === 'repeater') {
					$settings[$key] = self::normalizeRows($field, $raw, $language_ids, $localized);
				} elseif (!empty($field['localized'])) {
					$localized['fields'][$key] = self::localizedValue($field, $raw, $language_ids);
				} else {
					$settings[$key] = self::cast($field, $raw_settings[$key] ?? $field['default']);
				}
			}

			$structure[] = ['sid' => $sid, 'type' => $type, 'settings' => $settings];

			foreach ($language_ids as $language_id) {
				$section_content = [];

				foreach (self::fields($type) as $field) {
					if (!empty($field['localized'])) {
						$section_content[(string)$field['key']] = $localized['fields'][(string)$field['key']][(int)$language_id];
					}
				}

				if (isset($localized['rows'])) {
					$section_content['rows'] = $localized['rows'][(int)$language_id] ?? [];
				}

				$content[(int)$language_id][$sid] = $section_content;
			}
		}

		return [
			'structure' => ['sections' => $structure],
			'content'   => $content,
		];
	}

	/**
	 * Rebuilds the editor document from the stored halves (form loading).
	 *
	 * @param array<string, mixed> $structure decoded advanced_description JSON
	 * @param array<int, array<string, array<string, mixed>>> $content language_id => sid => fields
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	public static function editorDocument(array $structure, array $content, array $language_ids): array {
		$language_ids = array_values(array_unique(array_map('intval', $language_ids)));

		$sections = [];

		foreach ((array)($structure['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$type = (string)($section['type'] ?? '');

			if (!self::typeExists($type)) {
				continue;
			}

			$sid = (string)($section['sid'] ?? '');
			$settings = isset($section['settings']) && is_array($section['settings']) ? $section['settings'] : [];

			$editor = [
				'sid'      => $sid,
				'type'     => $type,
				'settings' => [],
				'content'  => [],
			];

			$repeater = self::repeater($type);
			$row_index = 0;

			foreach (self::fields($type) as $field) {
				$key = (string)$field['key'];

				if ($field['type'] === 'repeater') {
					$editor['rows'] = self::editorRows($repeater, (array)($settings[$key] ?? []), $content, $sid, $language_ids);
				} elseif (!empty($field['localized'])) {
					foreach ($language_ids as $language_id) {
						$editor['content'][(int)$language_id][$key] = self::storedValue($content, (int)$language_id, $sid, $key, (string)$field['default']);
					}
				} else {
					$editor['settings'][$key] = isset($settings[$key]) ? $settings[$key] : $field['default'];
				}
			}

			$sections[] = $editor;
		}

		return ['sections' => $sections];
	}

	/**
	 * Merges the stored halves for the storefront: one view-ready section
	 * list for the requested language, empty localized fields falling back
	 * to the default language (the landing blockContent() rule). Sections
	 * with nothing to show are skipped, so an untouched template never
	 * paints empty frames on a public page.
	 *
	 * @param array<string, mixed> $structure
	 * @param array<int, array<string, array<string, mixed>>> $content
	 * @return array<int, array<string, mixed>>
	 */
	public static function forView(array $structure, array $content, int $language_id, int $default_language_id): array {
		$sections = [];

		foreach ((array)($structure['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			$type = (string)($section['type'] ?? '');

			if (!self::typeExists($type)) {
				continue;
			}

			$sid = (string)($section['sid'] ?? '');
			$settings = isset($section['settings']) && is_array($section['settings']) ? $section['settings'] : [];

			$view = [
				'type'     => $type,
				'settings' => [],
				'content'  => [],
			];

			$repeater = self::repeater($type);
			$view_rows = [];

			foreach (self::fields($type) as $field) {
				$key = (string)$field['key'];

				if ($field['type'] === 'repeater') {
					$structure_rows = (array)($settings[$key] ?? []);

					foreach ($structure_rows as $row) {
						if (!is_array($row)) {
							continue;
						}

						$view_row = [];
						$row_index = count($view_rows);

						foreach ((array)($repeater['fields'] ?? []) as $sub) {
							$sub_key = (string)$sub['key'];

							if (!empty($sub['localized'])) {
								$view_row[$sub_key] = self::mergedValue($content, $language_id, $default_language_id, $sid, true, $row_index, $sub_key, (string)$sub['default']);
							} else {
								$view_row[$sub_key] = self::cast($sub, $row[$sub_key] ?? $sub['default']);
							}
						}

						$view_rows[] = $view_row;
					}
				} elseif (!empty($field['localized'])) {
					$view['content'][$key] = self::mergedValue($content, $language_id, $default_language_id, $sid, false, 0, $key, (string)$field['default']);
				} else {
					$view['settings'][$key] = self::cast($field, $settings[$key] ?? $field['default']);
				}
			}

			if ($repeater !== null) {
				$view['rows'] = $view_rows;
			}

			if (self::viewIsEmpty($type, $view)) {
				continue;
			}

			$sections[] = $view;
		}

		return $sections;
	}

	/**
	 * Resolves the Advanced Description payload of an entity save.
	 *
	 * The admin form posts the mode flag plus the normalised editor document
	 * (arrays); copies and hand-crafted payloads may carry the stored halves
	 * as raw JSON strings. Whatever the request does not carry falls back to
	 * the stored halves, so a classic-mode save keeps the composed sections
	 * intact (they act as the fallback when the mode goes off), and a first
	 * switch-on with nothing composed converts the classic copy of the same
	 * save into one text section per language.
	 *
	 * An absent `use_advanced_description` key keeps the stored mode, so
	 * callers that rebuild the form data themselves (the REST product
	 * documents, programmatic saves) never flip an entity back to the
	 * classic mode by accident - only an explicit flag does.
	 *
	 * @param array<string, mixed> $data the posted form data
	 * @param string $descriptions_key the per-language array key inside $data
	 * @param string $stored_structure_json the stored advanced_description column
	 * @param array<int, array<string, mixed>> $stored_descriptions language_id => stored description row
	 * @param int $stored_use the stored use_advanced_description flag
	 * @return array{0: int, 1: string, 2: array<int, string>} flag, structure JSON, per-language content JSON
	 */
	public static function resolveSave(array $data, string $descriptions_key, string $stored_structure_json = '', array $stored_descriptions = [], int $stored_use = 0): array {
		$use = array_key_exists('use_advanced_description', $data)
			? !empty($data['use_advanced_description'])
			: $stored_use === 1;

		$structure = null;
		$posted_content = null;

		if (isset($data['advanced_description']) && is_array($data['advanced_description'])) {
			$decoded_document = $data['advanced_description'];
		} elseif (isset($data['advanced_description']) && is_string($data['advanced_description']) && $data['advanced_description'] !== '') {
			// Request::clean() HTML-escapes the hidden JSON field; decode the
			// boundary once before json_decode (raw copy JSON is unchanged).
			$decoded_document = json_decode(html_entity_decode($data['advanced_description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
		} else {
			$decoded_document = null;
		}

		if (is_array($decoded_document)) {
			if (self::isEditorDocument($decoded_document)) {
				// The ordinary entity-form POST carries the full editor document
				// as one Request::clean()-escaped JSON string. Normalise it here,
				// at the model boundary, so the three controllers can keep passing
				// their original request array to add/edit unchanged.
				$language_ids = array_keys((array)($data[$descriptions_key] ?? []));
				$normalized = self::normalizeDocument($decoded_document, $language_ids);
				$structure = $normalized['structure'];
				$posted_content = $normalized['content'];
			} else {
				// A copy payload carries the already-split storage structure.
				$structure = $decoded_document;
			}
		} elseif ($stored_structure_json !== '') {
			$decoded = json_decode($stored_structure_json, true);

			if (is_array($decoded)) {
				$structure = $decoded;
			}
		}

		$content = [];

		foreach ((array)($data[$descriptions_key] ?? []) as $language_id => $value) {
			$language_id = (int)$language_id;

			if (!is_array($value)) {
				continue;
			}

			if (isset($value['advanced_content']) && is_array($value['advanced_content'])) {
				$content[$language_id] = $value['advanced_content'];
			} elseif (isset($value['advanced_content']) && is_string($value['advanced_content']) && $value['advanced_content'] !== '') {
				$decoded = json_decode($value['advanced_content'], true);

				if (is_array($decoded)) {
					$content[$language_id] = $decoded;
				}
			} elseif (isset($stored_descriptions[$language_id]['advanced_content'])) {
				$decoded = json_decode((string)$stored_descriptions[$language_id]['advanced_content'], true);

				if (is_array($decoded)) {
					$content[$language_id] = $decoded;
				}
			}
		}

		if ($posted_content !== null) {
			$content = $posted_content;
		}

		if ($use && (!is_array($structure) || empty($structure['sections']))) {
			$bodies = [];
			$language_ids = [];

			foreach ((array)($data[$descriptions_key] ?? []) as $language_id => $value) {
				$bodies[(int)$language_id] = (string)(is_array($value) ? ($value['description'] ?? '') : '');
				$language_ids[] = (int)$language_id;
			}

			$language_ids = array_values(array_unique($language_ids));

			if ($bodies) {
				$normalized = self::normalizeDocument(self::fromClassic($bodies, $language_ids), $language_ids);

				$structure = $normalized['structure'];

				foreach ($normalized['content'] as $language_id => $section_content) {
					$content[(int)$language_id] = $section_content;
				}
			}
		}

		$structure_json = '';

		if (is_array($structure) && !empty($structure['sections'])) {
			$structure_json = (string)json_encode($structure, JSON_UNESCAPED_UNICODE);
		}

		$content_json = [];

		foreach ($content as $language_id => $section_content) {
			$content_json[(int)$language_id] = (string)json_encode($section_content, JSON_UNESCAPED_UNICODE);
		}

		return [$use ? 1 : 0, $structure_json, $content_json];
	}

	/**
	 * A section skeleton with defaults everywhere - the base both preset
	 * documents and the classic conversion build on.
	 *
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function sectionForEditor(string $type, string $sid, array $settings, array $rows, array $language_ids): array {
		$section = [
			'sid'      => $sid,
			'type'     => $type,
			'settings' => [],
			'content'  => [],
		];

		$repeater = self::repeater($type);

		foreach (self::fields($type) as $field) {
			$key = (string)$field['key'];

			if ($field['type'] === 'repeater') {
				$section['rows'] = [];

				foreach ($rows as $row) {
					if (!is_array($row)) {
						$row = [];
					}

					$editor_row = [];
					$row_content = [];

					foreach ((array)($repeater['fields'] ?? []) as $sub) {
						$sub_key = (string)$sub['key'];

						if (!empty($sub['localized'])) {
							foreach ($language_ids as $language_id) {
								$row_content[(int)$language_id][$sub_key] = (string)($row[$sub_key] ?? $sub['default']);
							}
						} else {
							$editor_row[$sub_key] = (string)($row[$sub_key] ?? $sub['default']);
						}
					}

					$editor_row['content'] = $row_content;

					$section['rows'][] = $editor_row;
				}
			} elseif (!empty($field['localized'])) {
				foreach ($language_ids as $language_id) {
					$section['content'][(int)$language_id][$key] = (string)($settings[$key] ?? $field['default']);
				}
			} else {
				$section['settings'][$key] = isset($settings[$key]) ? $settings[$key] : $field['default'];
			}
		}

		return $section;
	}

	/**
	 * Whether a decoded advanced_description payload is the editor document
	 * (with per-language content) rather than the already-split storage
	 * structure used by entity copies.
	 *
	 * @param array<string, mixed> $document
	 */
	private static function isEditorDocument(array $document): bool {
		foreach ((array)($document['sections'] ?? []) as $section) {
			if (!is_array($section)) {
				continue;
			}

			if (isset($section['content'])) {
				return true;
			}

			foreach ((array)($section['rows'] ?? []) as $row) {
				if (is_array($row) && isset($row['content'])) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Normalises one section's repeater rows.
	 *
	 * Shared sub-fields stay in the structure row; localized sub-fields are
	 * collected per language (row count identical across languages, indexes
	 * aligned, count clamped to the field's max_rows).
	 *
	 * @param array<string, mixed> $field the repeater field
	 * @param array<string, mixed> $raw_section
	 * @param array<int, int> $language_ids
	 * @param array<string, mixed> $localized written: ['rows'][<language_id>] = per-language rows
	 * @return array<int, array<string, mixed>> structure rows (shared sub-fields only)
	 */
	private static function normalizeRows(array $field, array $raw_section, array $language_ids, array &$localized): array {
		$rows = [];
		$per_language = [];

		$raw_rows = isset($raw_section['rows']) && is_array($raw_section['rows']) ? $raw_section['rows'] : [];
		$max_rows = isset($field['max_rows']) ? (int)$field['max_rows'] : self::MAX_ROWS;

		foreach (array_slice($raw_rows, 0, $max_rows) as $raw_row) {
			if (!is_array($raw_row)) {
				continue;
			}

			$structure_row = [];

			foreach ((array)($field['fields'] ?? []) as $sub) {
				if (!empty($sub['localized'])) {
					continue;
				}

				$structure_row[(string)$sub['key']] = self::cast($sub, $raw_row[$sub['key']] ?? $sub['default']);
			}

			foreach ($language_ids as $language_id) {
				$language_row = [];

				$raw_content = isset($raw_row['content']) && is_array($raw_row['content']) ? $raw_row['content'] : [];
				$language_content = is_array($raw_content[(int)$language_id] ?? null) ? $raw_content[(int)$language_id] : [];

				foreach ((array)($field['fields'] ?? []) as $sub) {
					if (empty($sub['localized'])) {
						continue;
					}

					$language_row[(string)$sub['key']] = self::cast($sub, $language_content[$sub['key']] ?? $sub['default']);
				}

				$per_language[(int)$language_id][] = $language_row;
			}

			$rows[] = $structure_row;
		}

		$localized['rows'] = $per_language;

		return $rows;
	}

	/**
	 * A localized field of one section, resolved per language.
	 *
	 * @param array<string, mixed> $field
	 * @param array<string, mixed> $raw_section
	 * @param array<int, int> $language_ids
	 * @return array<int, string>
	 */
	private static function localizedValue(array $field, array $raw_section, array $language_ids): array {
		$values = [];
		$raw_content = isset($raw_section['content']) && is_array($raw_section['content']) ? $raw_section['content'] : [];

		foreach ($language_ids as $language_id) {
			$language_content = is_array($raw_content[(int)$language_id] ?? null) ? $raw_content[(int)$language_id] : [];

			$values[(int)$language_id] = self::cast($field, $language_content[$field['key']] ?? $field['default']);
		}

		return $values;
	}

	/**
	 * Value casting, mirroring Dockcart\Landing\Blocks::cast() for the
	 * field types this registry uses.
	 *
	 * @param array<string, mixed> $field
	 * @param mixed $value
	 * @return string
	 */
	private static function cast(array $field, $value): string {
		switch ((string)($field['type'] ?? 'text')) {
			case 'select':
				$options = isset($field['options']) ? $field['options'] : [];

				return isset($options[(string)$value]) ? (string)$value : (string)$field['default'];

			case 'image':
				return Blocks::sanitizePath((string)$value);

			case 'richtext':
				return Blocks::sanitizeHtml((string)$value);

			case 'textarea':
				return Text::truncate(trim(strip_tags((string)$value)), isset($field['max']) ? (int)$field['max'] : 5000);

			case 'text':
			default:
				return Text::truncate(trim(strip_tags((string)$value)), isset($field['max']) ? (int)$field['max'] : 255);
		}
	}

	/**
	 * Sanitises a client section id; empty/invalid ids become s<index>.
	 *
	 * @param array<string, string> $used_sids
	 */
	private static function normalizeSid(string $sid, int $index, array &$used_sids): string {
		$sid = substr((string)preg_replace('/[^a-zA-Z0-9_-]/', '', $sid), 0, 32);

		if ($sid === '') {
			$sid = 's' . $index;
		}

		$base = $sid;
		$attempt = 0;

		while (isset($used_sids[$sid])) {
			$attempt++;
			$sid = substr($base, 0, 29) . '-' . $attempt;
		}

		$used_sids[$sid] = $sid;

		return $sid;
	}

	/**
	 * Rebuilds editor rows from stored halves: shared sub-fields from the
	 * structure row, localized sub-fields from each language's content.
	 *
	 * @param array<string, mixed>|null $repeater
	 * @param array<int, array<string, mixed>> $structure_rows
	 * @param array<int, array<string, array<string, mixed>>> $content
	 * @param array<int, int> $language_ids
	 * @return array<int, array<string, mixed>>
	 */
	private static function editorRows(?array $repeater, array $structure_rows, array $content, string $sid, array $language_ids): array {
		$rows = [];
		$row_index = 0;

		foreach ($structure_rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$editor_row = [];

			foreach ((array)($repeater['fields'] ?? []) as $sub) {
				$sub_key = (string)$sub['key'];

				if (!empty($sub['localized'])) {
					foreach ($language_ids as $language_id) {
						$editor_row['content'][(int)$language_id][$sub_key] = self::storedRowValue($content, (int)$language_id, $sid, $row_index, $sub_key, (string)$sub['default']);
					}
				} else {
					$editor_row[$sub_key] = isset($row[$sub_key]) ? $row[$sub_key] : $sub['default'];
				}
			}

			$rows[] = $editor_row;
			$row_index++;
		}

		return $rows;
	}

	/**
	 * A stored localized section field (default when the row never carried it).
	 *
	 * @param array<int, array<string, array<string, mixed>>> $content
	 */
	private static function storedValue(array $content, int $language_id, string $sid, string $key, string $default): string {
		$value = $content[$language_id][$sid][$key] ?? $default;

		return is_string($value) ? $value : $default;
	}

	/**
	 * A stored localized row field by section id + row index.
	 *
	 * @param array<int, array<string, array<string, mixed>>> $content
	 */
	private static function storedRowValue(array $content, int $language_id, string $sid, int $row_index, string $key, string $default): string {
		$value = $content[$language_id][$sid]['rows'][$row_index][$key] ?? $default;

		return is_string($value) ? $value : $default;
	}

	/**
	 * A localized value for the storefront: the language's own copy, or the
	 * default language's copy when the field is empty there.
	 *
	 * @param array<int, array<string, array<string, mixed>>> $content
	 */
	private static function mergedValue(array $content, int $language_id, int $default_language_id, string $sid, bool $from_rows, int $row_index, string $key, string $default): string {
		$value = $from_rows
			? self::storedRowValue($content, $language_id, $sid, $row_index, $key, $default)
			: self::storedValue($content, $language_id, $sid, $key, $default);

		if ($value !== '') {
			return $value;
		}

		if ($language_id !== $default_language_id) {
			return $from_rows
				? self::storedRowValue($content, $default_language_id, $sid, $row_index, $key, $default)
				: self::storedValue($content, $default_language_id, $sid, $key, $default);
		}

		return $default;
	}

	/**
	 * Whether a merged section has nothing worth rendering.
	 *
	 * @param array<string, mixed> $view
	 */
	private static function viewIsEmpty(string $type, array $view): bool {
		$content = (array)($view['content'] ?? []);
		$rows = (array)($view['rows'] ?? []);
		$settings = (array)($view['settings'] ?? []);

		switch ($type) {
			case 'text':
				return trim((string)($content['heading'] ?? '')) === ''
					&& trim(strip_tags((string)($content['body'] ?? ''))) === '';

			case 'statement':
				return trim((string)($content['text'] ?? '')) === ''
					&& trim((string)($content['footnote'] ?? '')) === '';

			case 'text_image':
				return trim((string)($content['heading'] ?? '')) === ''
					&& trim(strip_tags((string)($content['body'] ?? ''))) === ''
					&& (string)($settings['image'] ?? '') === '';

			case 'features':
				if (trim((string)($content['heading'] ?? '')) !== '') {
					return false;
				}

				foreach ($rows as $row) {
					if (trim((string)($row['title'] ?? '')) !== '' || trim((string)($row['text'] ?? '')) !== '') {
						return false;
					}
				}

				return true;

			case 'gallery':
				foreach ($rows as $row) {
					if ((string)($row['image'] ?? '') !== '') {
						return false;
					}
				}

				return true;

			case 'stats':
				foreach ($rows as $row) {
					if (trim((string)($row['value'] ?? '')) !== '' || trim((string)($row['label'] ?? '')) !== '') {
						return false;
					}
				}

				return true;
		}

		return false;
	}
}
