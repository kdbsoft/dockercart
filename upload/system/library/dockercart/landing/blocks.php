<?php
/**
 * Landing page block registry.
 *
 * Single source of truth for what a landing page may contain:
 *
 *   code    => name/label key, icon, palette group, field schema
 *
 * The admin builder renders its palette and properties panel from this list,
 * the admin model normalises every saved block through it (unknown codes and
 * fields are dropped, values are cast/whitelisted), and the storefront renders
 * landing/blocks/<code>.twig with the same normalised data. Nothing else may
 * decide what a block is.
 *
 * Field schema:
 *   ['key' => 'heading', 'type' => 'text', 'localized' => true,
 *    'label' => 'field_heading', 'default' => '', 'max' => 255]
 *
 *   localized = true  -> stored per language in oc_landing_block_description
 *   localized = false -> stored in oc_landing_block.settings (shared)
 *
 * Every block additionally carries the shared wrapper fields (background,
 * padding, container, alignment, visibility, anchor, custom class).
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Landing;

final class Blocks {
	public const DEFAULT_LANGUAGE_FALLBACK = true;

	private const MAX_PRODUCTS = 24;

	private const MAX_REPEATER_ROWS = 6;

	/**
	 * Palette groups: key => language key.
	 */
	public const GROUPS = [
		'content' => 'group_content',
		'commerce' => 'group_commerce',
		'media' => 'group_media',
		'layout' => 'group_layout',
	];

	/**
	 * Block definitions (order = palette order inside a group).
	 */
	private const BLOCKS = [
		// ------------------------------------------------------------- content
		'hero' => [
			'name'  => 'block_hero',
			'icon'  => 'image-plus',
			'group' => 'content',
			'fields' => [
				['key' => 'badge', 'type' => 'text', 'localized' => true, 'label' => 'field_badge', 'default' => '', 'max' => 64],
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'subheading', 'type' => 'textarea', 'localized' => true, 'label' => 'field_subheading', 'default' => '', 'max' => 500],
				['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_background_image', 'default' => ''],
				['key' => 'image_mobile', 'type' => 'image', 'localized' => false, 'label' => 'field_background_image_mobile', 'default' => ''],
				['key' => 'image_overlay', 'type' => 'number', 'localized' => false, 'label' => 'field_overlay', 'default' => 40, 'min' => 0, 'max' => 90],
				['key' => 'height', 'type' => 'select', 'localized' => false, 'label' => 'field_height', 'default' => 'lg', 'options' => [
					'sm' => 'option_height_sm',
					'md' => 'option_height_md',
					'lg' => 'option_height_lg',
					'full' => 'option_height_full',
				]],
				['key' => 'content_position', 'type' => 'select', 'localized' => false, 'label' => 'field_content_position', 'default' => 'left', 'options' => [
					'left' => 'option_left',
					'center' => 'option_center',
					'right' => 'option_right',
				]],
				['key' => 'buttons', 'type' => 'repeater', 'localized' => true, 'label' => 'field_buttons', 'default' => [], 'max_rows' => 2, 'fields' => [
					['key' => 'label', 'type' => 'text', 'label' => 'field_button_label', 'default' => '', 'max' => 64],
					['key' => 'href', 'type' => 'link', 'label' => 'field_button_link', 'default' => ''],
					['key' => 'style', 'type' => 'select', 'label' => 'field_button_style', 'default' => 'primary', 'options' => [
						'primary' => 'option_style_primary',
						'secondary' => 'option_style_secondary',
						'ghost' => 'option_style_ghost',
					]],
				]],
				['key' => 'text_color', 'type' => 'select', 'localized' => false, 'label' => 'field_text_color', 'default' => 'light', 'options' => [
					'dark' => 'option_color_dark',
					'light' => 'option_color_light',
				]],
			],
		],

		'text' => [
			'name'  => 'block_text',
			'icon'  => 'align-left',
			'group' => 'content',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'body', 'type' => 'richtext', 'localized' => true, 'label' => 'field_text', 'default' => ''],
				['key' => 'max_width', 'type' => 'select', 'localized' => false, 'label' => 'field_max_width', 'default' => 'normal', 'options' => [
					'narrow' => 'option_width_narrow',
					'normal' => 'option_width_normal',
					'wide' => 'option_width_wide',
				]],
			],
		],

		'cta' => [
			'name'  => 'block_cta',
			'icon'  => 'megaphone',
			'group' => 'content',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'body', 'type' => 'textarea', 'localized' => true, 'label' => 'field_text', 'default' => '', 'max' => 500],
				['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_image', 'default' => ''],
				['key' => 'layout', 'type' => 'select', 'localized' => false, 'label' => 'field_layout', 'default' => 'center', 'options' => [
					'center' => 'option_layout_center',
					'split' => 'option_layout_split',
				]],
				['key' => 'button_label', 'type' => 'text', 'localized' => true, 'label' => 'field_button_label', 'default' => '', 'max' => 64],
				['key' => 'button_href', 'type' => 'link', 'localized' => false, 'label' => 'field_button_link', 'default' => ''],
				['key' => 'button_style', 'type' => 'select', 'localized' => false, 'label' => 'field_button_style', 'default' => 'primary', 'options' => [
					'primary' => 'option_style_primary',
					'secondary' => 'option_style_secondary',
					'light' => 'option_style_light',
				]],
			],
		],

		// ------------------------------------------------------------ commerce
		'products' => [
			'name'  => 'block_products',
			'icon'  => 'layout-grid',
			'group' => 'commerce',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'subheading', 'type' => 'text', 'localized' => true, 'label' => 'field_subheading', 'default' => '', 'max' => 200],
				['key' => 'source', 'type' => 'select', 'localized' => false, 'label' => 'field_source', 'default' => 'manual', 'options' => [
					'manual' => 'option_source_manual',
					'category' => 'option_source_category',
					'special' => 'option_source_special',
					'latest' => 'option_source_latest',
					'bestseller' => 'option_source_bestseller',
				]],
				['key' => 'product_ids', 'type' => 'products', 'localized' => false, 'label' => 'field_products', 'default' => []],
				['key' => 'category_id', 'type' => 'category', 'localized' => false, 'label' => 'field_category', 'default' => 0],
				['key' => 'limit', 'type' => 'number', 'localized' => false, 'label' => 'field_limit', 'default' => 8, 'min' => 1, 'max' => self::MAX_PRODUCTS],
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '4', 'options' => [
					'2' => 'option_columns_2',
					'3' => 'option_columns_3',
					'4' => 'option_columns_4',
					'5' => 'option_columns_5',
				]],
				['key' => 'link_label', 'type' => 'text', 'localized' => true, 'label' => 'field_link_label', 'default' => '', 'max' => 64],
				['key' => 'link_href', 'type' => 'link', 'localized' => false, 'label' => 'field_link_href', 'default' => ''],
			],
		],

		'products_carousel' => [
			'name'  => 'block_products_carousel',
			'icon'  => 'gallery-horizontal-end',
			'group' => 'commerce',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'subheading', 'type' => 'text', 'localized' => true, 'label' => 'field_subheading', 'default' => '', 'max' => 200],
				['key' => 'source', 'type' => 'select', 'localized' => false, 'label' => 'field_source', 'default' => 'latest', 'options' => [
					'manual' => 'option_source_manual',
					'category' => 'option_source_category',
					'special' => 'option_source_special',
					'latest' => 'option_source_latest',
					'bestseller' => 'option_source_bestseller',
				]],
				['key' => 'product_ids', 'type' => 'products', 'localized' => false, 'label' => 'field_products', 'default' => []],
				['key' => 'category_id', 'type' => 'category', 'localized' => false, 'label' => 'field_category', 'default' => 0],
				['key' => 'limit', 'type' => 'number', 'localized' => false, 'label' => 'field_limit', 'default' => 12, 'min' => 1, 'max' => self::MAX_PRODUCTS],
				['key' => 'per_view', 'type' => 'number', 'localized' => false, 'label' => 'field_per_view', 'default' => 4, 'min' => 1, 'max' => 6],
				['key' => 'link_label', 'type' => 'text', 'localized' => true, 'label' => 'field_link_label', 'default' => '', 'max' => 64],
				['key' => 'link_href', 'type' => 'link', 'localized' => false, 'label' => 'field_link_href', 'default' => ''],
			],
		],

		'categories' => [
			'name'  => 'block_categories',
			'icon'  => 'folder-tree',
			'group' => 'commerce',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'subheading', 'type' => 'text', 'localized' => true, 'label' => 'field_subheading', 'default' => '', 'max' => 200],
				['key' => 'source', 'type' => 'select', 'localized' => false, 'label' => 'field_source', 'default' => 'manual', 'options' => [
					'manual' => 'option_source_manual',
					'parent' => 'option_source_parent',
				]],
				['key' => 'category_ids', 'type' => 'categories', 'localized' => false, 'label' => 'field_categories', 'default' => []],
				['key' => 'parent_category_id', 'type' => 'category', 'localized' => false, 'label' => 'field_parent_category', 'default' => 0],
				['key' => 'limit', 'type' => 'number', 'localized' => false, 'label' => 'field_limit', 'default' => 6, 'min' => 1, 'max' => 12],
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '3', 'options' => [
					'2' => 'option_columns_2',
					'3' => 'option_columns_3',
					'4' => 'option_columns_4',
					'6' => 'option_columns_6',
				]],
				['key' => 'image_height', 'type' => 'select', 'localized' => false, 'label' => 'field_image_height', 'default' => 'md', 'options' => [
					'sm' => 'option_height_sm',
					'md' => 'option_height_md',
					'lg' => 'option_height_lg',
				]],
			],
		],

		// --------------------------------------------------------------- media
		'image' => [
			'name'  => 'block_image',
			'icon'  => 'image',
			'group' => 'media',
			'fields' => [
				['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_image', 'default' => ''],
				['key' => 'image_mobile', 'type' => 'image', 'localized' => false, 'label' => 'field_image_mobile', 'default' => ''],
				['key' => 'alt', 'type' => 'text', 'localized' => true, 'label' => 'field_alt', 'default' => '', 'max' => 255],
				['key' => 'caption', 'type' => 'text', 'localized' => true, 'label' => 'field_caption', 'default' => '', 'max' => 255],
				['key' => 'href', 'type' => 'link', 'localized' => false, 'label' => 'field_link', 'default' => ''],
				['key' => 'ratio', 'type' => 'select', 'localized' => false, 'label' => 'field_ratio', 'default' => 'auto', 'options' => [
					'auto' => 'option_ratio_auto',
					'square' => 'option_ratio_square',
					'video' => 'option_ratio_video',
					'wide' => 'option_ratio_wide',
				]],
				['key' => 'rounded', 'type' => 'toggle', 'localized' => false, 'label' => 'field_rounded', 'default' => 1],
			],
		],

		'image_text' => [
			'name'  => 'block_image_text',
			'icon'  => 'columns-2',
			'group' => 'media',
			'fields' => [
				['key' => 'image', 'type' => 'image', 'localized' => false, 'label' => 'field_image', 'default' => ''],
				['key' => 'alt', 'type' => 'text', 'localized' => true, 'label' => 'field_alt', 'default' => '', 'max' => 255],
				['key' => 'image_position', 'type' => 'select', 'localized' => false, 'label' => 'field_image_position', 'default' => 'left', 'options' => [
					'left' => 'option_left',
					'right' => 'option_right',
				]],
				['key' => 'ratio', 'type' => 'select', 'localized' => false, 'label' => 'field_ratio', 'default' => 'square', 'options' => [
					'auto' => 'option_ratio_auto',
					'square' => 'option_ratio_square',
					'video' => 'option_ratio_video',
				]],
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'body', 'type' => 'richtext', 'localized' => true, 'label' => 'field_text', 'default' => ''],
				['key' => 'bullets', 'type' => 'textarea', 'localized' => true, 'label' => 'field_bullets', 'default' => '', 'max' => 1000],
				['key' => 'button_label', 'type' => 'text', 'localized' => true, 'label' => 'field_button_label', 'default' => '', 'max' => 64],
				['key' => 'button_href', 'type' => 'link', 'localized' => false, 'label' => 'field_button_link', 'default' => ''],
				['key' => 'button_style', 'type' => 'select', 'localized' => false, 'label' => 'field_button_style', 'default' => 'primary', 'options' => [
					'primary' => 'option_style_primary',
					'secondary' => 'option_style_secondary',
					'link' => 'option_style_link',
				]],
			],
		],

		'banner' => [
			'name'  => 'block_banner',
			'icon'  => 'panel-top',
			'group' => 'media',
			'fields' => [
				['key' => 'banner_id', 'type' => 'banner', 'localized' => false, 'label' => 'field_banner', 'default' => 0],
				['key' => 'height', 'type' => 'select', 'localized' => false, 'label' => 'field_height', 'default' => 'md', 'options' => [
					'sm' => 'option_height_sm',
					'md' => 'option_height_md',
					'lg' => 'option_height_lg',
				]],
				['key' => 'autoplay', 'type' => 'number', 'localized' => false, 'label' => 'field_autoplay', 'default' => 6, 'min' => 0, 'max' => 30],
				['key' => 'show_arrows', 'type' => 'toggle', 'localized' => false, 'label' => 'field_show_arrows', 'default' => 1],
			],
		],

		// -------------------------------------------------------------- layout
		'spacer' => [
			'name'  => 'block_spacer',
			'icon'  => 'separator-horizontal',
			'group' => 'layout',
			'fields' => [
				['key' => 'height', 'type' => 'select', 'localized' => false, 'label' => 'field_height', 'default' => 'md', 'options' => [
					'xs' => 'option_height_xs',
					'sm' => 'option_height_sm',
					'md' => 'option_height_md',
					'lg' => 'option_height_lg',
					'xl' => 'option_height_xl',
				]],
				['key' => 'divider', 'type' => 'toggle', 'localized' => false, 'label' => 'field_divider', 'default' => 0],
			],
		],

		'html' => [
			'name'  => 'block_html',
			'icon'  => 'code',
			'group' => 'layout',
			'fields' => [
				['key' => 'html', 'type' => 'code', 'localized' => true, 'label' => 'field_html', 'default' => ''],
			],
		],
	];

	/**
	 * Wrapper fields present on every block (shared, non localised).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function wrapperFields(): array {
		return [
			['key' => 'bg_type', 'type' => 'select', 'localized' => false, 'label' => 'field_bg_type', 'default' => 'none', 'options' => [
				'none' => 'option_bg_none',
				'color' => 'option_bg_color',
				'image' => 'option_bg_image',
			]],
			['key' => 'bg_color', 'type' => 'color', 'localized' => false, 'label' => 'field_bg_color', 'default' => ''],
			['key' => 'bg_image', 'type' => 'image', 'localized' => false, 'label' => 'field_bg_image', 'default' => ''],
			['key' => 'overlay', 'type' => 'number', 'localized' => false, 'label' => 'field_overlay', 'default' => 30, 'min' => 0, 'max' => 90],
			['key' => 'padding', 'type' => 'select', 'localized' => false, 'label' => 'field_padding', 'default' => 'lg', 'options' => [
				'none' => 'option_padding_none',
				'xs' => 'option_padding_xs',
				'sm' => 'option_padding_sm',
				'md' => 'option_padding_md',
				'lg' => 'option_padding_lg',
				'xl' => 'option_padding_xl',
			]],
			['key' => 'container', 'type' => 'select', 'localized' => false, 'label' => 'field_container', 'default' => 'boxed', 'options' => [
				'boxed' => 'option_container_boxed',
				'full' => 'option_container_full',
			]],
			['key' => 'align', 'type' => 'select', 'localized' => false, 'label' => 'field_align', 'default' => 'left', 'options' => [
				'left' => 'option_left',
				'center' => 'option_center',
				'right' => 'option_right',
			]],
			['key' => 'hide_desktop', 'type' => 'toggle', 'localized' => false, 'label' => 'field_hide_desktop', 'default' => 0],
			['key' => 'hide_tablet', 'type' => 'toggle', 'localized' => false, 'label' => 'field_hide_tablet', 'default' => 0],
			['key' => 'hide_mobile', 'type' => 'toggle', 'localized' => false, 'label' => 'field_hide_mobile', 'default' => 0],
			['key' => 'anchor', 'type' => 'text', 'localized' => false, 'label' => 'field_anchor', 'default' => '', 'max' => 64],
			['key' => 'custom_class', 'type' => 'text', 'localized' => false, 'label' => 'field_custom_class', 'default' => '', 'max' => 128],
		];
	}

	/**
	 * All block codes in palette order.
	 *
	 * @return array<int, string>
	 */
	public static function codes(): array {
		return array_keys(self::BLOCKS);
	}

	/**
	 * The whole registry (declarations only, without wrapper fields).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::BLOCKS;
	}

	public static function exists(string $code): bool {
		return isset(self::BLOCKS[$code]);
	}

	/**
	 * Fields of a block including the shared wrapper fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(string $code): array {
		if (!self::exists($code)) {
			return [];
		}

		return array_merge(self::BLOCKS[$code]['fields'], self::wrapperFields());
	}

	/**
	 * Localisable field definitions of a block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function localizedFields(string $code): array {
		$fields = [];

		foreach (self::fields($code) as $field) {
			if (!empty($field['localized'])) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Default settings/content for a new block instance.
	 *
	 * @return array{settings: array<string, mixed>, content: array<string, mixed>}
	 */
	public static function defaults(string $code): array {
		$settings = [];
		$content = [];

		foreach (self::fields($code) as $field) {
			if (!empty($field['localized'])) {
				$content[$field['key']] = $field['default'];
			} else {
				$settings[$field['key']] = $field['default'];
			}
		}

		return ['settings' => $settings, 'content' => $content];
	}

	/**
	 * The schema sent to the builder (palette + properties panel).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		$schema = [];

		foreach (self::BLOCKS as $code => $block) {
			$schema[$code] = [
				'code'   => $code,
				'name'   => $block['name'],
				'icon'   => $block['icon'],
				'group'  => $block['group'],
				'fields' => self::fields($code),
			];
		}

		return $schema;
	}

	/**
	 * Normalises the shared (per-block, non-localised) settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalizeSettings(string $code, array $settings): array {
		$normalized = [];

		foreach (self::fields($code) as $field) {
			if (!empty($field['localized'])) {
				continue;
			}

			$key = $field['key'];
			$value = array_key_exists($key, $settings) ? $settings[$key] : $field['default'];

			$normalized[$key] = self::cast($field, $value);
		}

		return $normalized;
	}

	/**
	 * Normalises the translated content of one language.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalizeContent(string $code, array $content): array {
		$normalized = [];

		foreach (self::fields($code) as $field) {
			if (empty($field['localized'])) {
				continue;
			}

			$key = $field['key'];
			$value = array_key_exists($key, $content) ? $content[$key] : $field['default'];

			$normalized[$key] = self::cast($field, $value);
		}

		return $normalized;
	}

	/**
	 * Casts one field value according to its type.
	 *
	 * @param array<string, mixed> $field
	 * @return mixed
	 */
	private static function cast(array $field, $value) {
		switch ($field['type']) {
			case 'number':
				$value = (int)$value;
				$min = isset($field['min']) ? (int)$field['min'] : 0;
				$max = isset($field['max']) ? (int)$field['max'] : PHP_INT_MAX;

				return max($min, min($max, $value));

			case 'toggle':
				return empty($value) ? 0 : 1;

			case 'select':
				$options = isset($field['options']) ? $field['options'] : [];

				return isset($options[(string)$value]) ? (string)$value : (string)$field['default'];

			case 'color':
				return self::sanitizeColor((string)$value);

			case 'image':
			case 'link':
				return self::sanitizePath((string)$value);

			case 'products':
				return self::sanitizeIds($value, self::MAX_PRODUCTS);

			case 'categories':
				return self::sanitizeIds($value, 24);

			case 'category':
			case 'banner':
				return max(0, (int)$value);

			case 'repeater':
				return self::sanitizeRepeater($field, $value);

			case 'richtext':
				return self::sanitizeHtml((string)$value);

			case 'code':
				return trim((string)$value);

			case 'textarea':
				return self::truncate(trim((string)$value), isset($field['max']) ? (int)$field['max'] : 5000);

			case 'text':
			default:
				return self::truncate(trim(strip_tags((string)$value)), isset($field['max']) ? (int)$field['max'] : 255);
		}
	}

	/**
	 * @param array<string, mixed> $field
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitizeRepeater(array $field, $value): array {
		if (!is_array($value)) {
			return [];
		}

		$max_rows = isset($field['max_rows']) ? (int)$field['max_rows'] : self::MAX_REPEATER_ROWS;
		$sub_fields = isset($field['fields']) && is_array($field['fields']) ? $field['fields'] : [];
		$rows = [];

		foreach ($value as $row) {
			if (!is_array($row)) {
				continue;
			}

			$normalized = [];

			foreach ($sub_fields as $sub) {
				$sub_value = array_key_exists($sub['key'], $row) ? $row[$sub['key']] : $sub['default'];
				$normalized[$sub['key']] = self::cast($sub, $sub_value);
			}

			// Drop empty rows (a fresh repeater row in the panel).
			$empty = true;

			foreach ($normalized as $key => $sub_value) {
				if ($key === 'style') {
					continue;
				}

				if ($sub_value !== '' && $sub_value !== 0) {
					$empty = false;
					break;
				}
			}

			if ($empty) {
				continue;
			}

			$rows[] = $normalized;

			if (count($rows) >= $max_rows) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * @param mixed $value
	 * @return array<int, int>
	 */
	private static function sanitizeIds($value, int $max): array {
		if (!is_array($value)) {
			return [];
		}

		$ids = [];

		foreach ($value as $id) {
			$id = (int)$id;

			if ($id > 0 && !in_array($id, $ids, true)) {
				$ids[] = $id;
			}

			if (count($ids) >= $max) {
				break;
			}
		}

		return $ids;
	}

	private static function sanitizeColor(string $value): string {
		$value = trim($value);

		if ($value === '') {
			return '';
		}

		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
			return strtolower($value);
		}

		return '';
	}

	private static function sanitizePath(string $value): string {
		$value = trim($value);

		// A stored link is either an internal storefront path ("index.php?route=...",
		// "/black-friday") or an absolute http(s)/mailto/tel URL. Anything with a
		// script-capable scheme is dropped.
		if ($value === '') {
			return '';
		}

		if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
			return '';
		}

		if (preg_match('#^(https?:)?//#i', $value) || preg_match('#^(mailto|tel):#i', $value)) {
			return self::truncate($value, 255);
		}

		// Internal path: keep it relative but never allow protocol-relative tricks.
		return self::truncate(ltrim($value, '/'), 255);
	}

	private static function sanitizeHtml(string $value): string {
		$value = trim($value);

		if ($value === '') {
			return '';
		}

		$cleaned = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $value);

		if ($cleaned !== null) {
			$value = $cleaned;
		}

		$cleaned = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $value);

		if ($cleaned !== null) {
			$value = $cleaned;
		}

		return self::truncate($value, 65535);
	}

	private static function truncate(string $value, int $length): string {
		if ($length <= 0) {
			return $value;
		}

		if (function_exists('utf8_strlen')) {
			if (utf8_strlen($value) > $length) {
				return utf8_substr($value, 0, $length);
			}

			return $value;
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}

	/**
	 * Section wrapper classes.
	 *
	 * Visibility classes are literal strings so Tailwind's content scan
	 * (upload/**\/*.php) keeps them in the compiled stylesheet.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function wrapperClasses(string $code, array $settings, bool $editing = false): string {
		$classes = ['lp-block', 'lp-block--' . $code];

		if (!$editing) {
			$classes[] = self::visibilityClass($settings);
		}

		if (!empty($settings['custom_class'])) {
			$classes[] = (string)$settings['custom_class'];
		}

		return trim(implode(' ', array_filter($classes)));
	}

	/**
	 * Section wrapper inline styles.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, string>
	 */
	public static function wrapperStyles(string $code, array $settings): array {
		$styles = [];

		$padding = [
			'none' => '0',
			'xs' => '0.75rem',
			'sm' => '1.5rem',
			'md' => '2.5rem',
			'lg' => '4rem',
			'xl' => '6rem',
		];

		$key = isset($settings['padding']) ? (string)$settings['padding'] : 'lg';

		if (isset($padding[$key]) && $key !== 'none') {
			$styles['padding-top'] = $padding[$key];
			$styles['padding-bottom'] = $padding[$key];
		}

		$bg_type = isset($settings['bg_type']) ? (string)$settings['bg_type'] : 'none';

		if ($bg_type === 'color' && !empty($settings['bg_color'])) {
			$styles['background-color'] = (string)$settings['bg_color'];
		}

		if ($bg_type === 'image' && !empty($settings['bg_image'])) {
			$styles['background-image'] = "url('image/" . str_replace("'", '%27', (string)$settings['bg_image']) . "')";
			$styles['background-size'] = 'cover';
			$styles['background-position'] = 'center';
		}

		return $styles;
	}

	/**
	 * Inline style string for the wrapper (empty when nothing to style).
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function wrapperStyleAttribute(string $code, array $settings): string {
		$parts = [];

		foreach (self::wrapperStyles($code, $settings) as $property => $value) {
			$parts[] = $property . ':' . $value;
		}

		return implode(';', $parts);
	}

	/**
	 * Overlay opacity for a background image block (0 when not applicable).
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function overlay(array $settings): int {
		if ((isset($settings['bg_type']) ? (string)$settings['bg_type'] : 'none') !== 'image') {
			return 0;
		}

		return isset($settings['overlay']) ? max(0, min(90, (int)$settings['overlay'])) : 0;
	}

	/**
	 * Responsive visibility class for the wrapper.
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function visibilityClass(array $settings): string {
		$mobile = empty($settings['hide_mobile']);
		$tablet = empty($settings['hide_tablet']);
		$desktop = empty($settings['hide_desktop']);

		if ($mobile && $tablet && $desktop) {
			return '';
		}

		if (!$mobile && !$tablet && !$desktop) {
			return 'hidden';
		}

		if ($mobile && $tablet && !$desktop) {
			return 'lg:hidden';
		}

		if ($mobile && !$tablet && $desktop) {
			return 'md:hidden lg:block';
		}

		if (!$mobile && $tablet && $desktop) {
			return 'hidden md:block';
		}

		if ($mobile && !$tablet && !$desktop) {
			return 'md:hidden';
		}

		// tablet only
		return 'hidden md:block lg:hidden';
	}
}
