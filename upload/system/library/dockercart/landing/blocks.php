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
	 * Lucide icons offered by the `features` block. Values are data-lucide
	 * names; keys are language-key suffixes so the labels stay translatable.
	 */
	private const FEATURE_ICONS = [
		'sparkles' => 'option_icon_sparkles',
		'shield-check' => 'option_icon_shield_check',
		'credit-card' => 'option_icon_credit_card',
		'truck' => 'option_icon_truck',
		'package-check' => 'option_icon_package_check',
		'headset' => 'option_icon_headset',
		'wallet' => 'option_icon_wallet',
		'landmark' => 'option_icon_landmark',
		'refresh-cw' => 'option_icon_refresh_cw',
		'store' => 'option_icon_store',
	];

	/** Shared surface of the editorial blocks (works without any theme CSS). */
	private const BLOCK_VARIANTS = [
		'plain' => 'option_variant_plain',
		'soft'  => 'option_variant_soft',
		'dark'  => 'option_variant_dark',
	];

	/**
	 * Section structures: preset column layouts of the `section` container.
	 *
	 * Class strings are literals on purpose - the Tailwind content scan keeps
	 * them in the compiled stylesheet.
	 */
	private const STRUCTURES = [
		'1'   => ['columns' => 1, 'grid' => 'grid grid-cols-1', 'slots' => ['']],
		'2'   => ['columns' => 2, 'grid' => 'grid grid-cols-1 md:grid-cols-2', 'slots' => ['', '']],
		'2-1' => ['columns' => 2, 'grid' => 'grid grid-cols-1 md:grid-cols-3', 'slots' => ['md:col-span-2', '']],
		'1-2' => ['columns' => 2, 'grid' => 'grid grid-cols-1 md:grid-cols-3', 'slots' => ['', 'md:col-span-2']],
		'3'   => ['columns' => 3, 'grid' => 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3', 'slots' => ['', '', '']],
		'4'   => ['columns' => 4, 'grid' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4', 'slots' => ['', '', '', '']],
	];

	private const GAPS = [
		'none' => 'gap-0',
		'xs'   => 'gap-2',
		'sm'   => 'gap-4',
		'md'   => 'gap-6',
		'lg'   => 'gap-10',
	];

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

		'features' => [
			'name'  => 'block_features',
			'icon'  => 'sparkles',
			'group' => 'content',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'intro', 'type' => 'textarea', 'localized' => true, 'label' => 'field_intro', 'default' => '', 'max' => 500],
				['key' => 'items', 'type' => 'repeater', 'localized' => true, 'label' => 'field_items', 'default' => [], 'max_rows' => 6, 'fields' => [
					['key' => 'icon', 'type' => 'select', 'label' => 'field_icon', 'default' => 'sparkles', 'options' => self::FEATURE_ICONS],
					['key' => 'title', 'type' => 'text', 'label' => 'field_item_title', 'default' => '', 'max' => 100],
					['key' => 'text', 'type' => 'textarea', 'label' => 'field_item_text', 'default' => '', 'max' => 300],
				]],
				['key' => 'columns', 'type' => 'select', 'localized' => false, 'label' => 'field_columns', 'default' => '3', 'options' => [
					'2' => 'option_columns_2',
					'3' => 'option_columns_3',
					'4' => 'option_columns_4',
				]],
				['key' => 'variant', 'type' => 'select', 'localized' => false, 'label' => 'field_variant', 'default' => 'plain', 'options' => self::BLOCK_VARIANTS],
			],
		],

		'steps' => [
			'name'  => 'block_steps',
			'icon'  => 'route',
			'group' => 'content',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'intro', 'type' => 'textarea', 'localized' => true, 'label' => 'field_intro', 'default' => '', 'max' => 500],
				['key' => 'items', 'type' => 'repeater', 'localized' => true, 'label' => 'field_steps', 'default' => [], 'max_rows' => 5, 'fields' => [
					['key' => 'title', 'type' => 'text', 'label' => 'field_item_title', 'default' => '', 'max' => 100],
					['key' => 'text', 'type' => 'textarea', 'label' => 'field_item_text', 'default' => '', 'max' => 300],
				]],
				['key' => 'variant', 'type' => 'select', 'localized' => false, 'label' => 'field_variant', 'default' => 'plain', 'options' => self::BLOCK_VARIANTS],
			],
		],

		'faq' => [
			'name'  => 'block_faq',
			'icon'  => 'list-collapse',
			'group' => 'content',
			'fields' => [
				['key' => 'heading', 'type' => 'text', 'localized' => true, 'label' => 'field_heading', 'default' => '', 'max' => 200],
				['key' => 'intro', 'type' => 'textarea', 'localized' => true, 'label' => 'field_intro', 'default' => '', 'max' => 500],
				['key' => 'items', 'type' => 'repeater', 'localized' => true, 'label' => 'field_questions', 'default' => [], 'max_rows' => 8, 'fields' => [
					['key' => 'question', 'type' => 'text', 'label' => 'field_question', 'default' => '', 'max' => 200],
					['key' => 'answer', 'type' => 'textarea', 'label' => 'field_answer', 'default' => '', 'max' => 1000],
				]],
				['key' => 'variant', 'type' => 'select', 'localized' => false, 'label' => 'field_variant', 'default' => 'plain', 'options' => self::BLOCK_VARIANTS],
			],
		],

		'statement' => [
			'name'  => 'block_statement',
			'icon'  => 'quote',
			'group' => 'content',
			'fields' => [
				['key' => 'text', 'type' => 'textarea', 'localized' => true, 'label' => 'field_text', 'default' => '', 'max' => 500],
				['key' => 'foot', 'type' => 'textarea', 'localized' => true, 'label' => 'field_foot', 'default' => '', 'max' => 300],
				['key' => 'variant', 'type' => 'select', 'localized' => false, 'label' => 'field_variant', 'default' => 'dark', 'options' => self::BLOCK_VARIANTS],
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
		'section' => [
			'name'      => 'block_section',
			'icon'      => 'rows-3',
			'group'     => 'layout',
			'container' => true,
			'fields' => [
				['key' => 'structure', 'type' => 'select', 'localized' => false, 'label' => 'field_structure', 'default' => '2', 'options' => [
					'1'   => 'option_structure_1',
					'2'   => 'option_structure_2',
					'2-1' => 'option_structure_2_1',
					'1-2' => 'option_structure_1_2',
					'3'   => 'option_structure_3',
					'4'   => 'option_structure_4',
				]],
				['key' => 'gap', 'type' => 'select', 'localized' => false, 'label' => 'field_gap', 'default' => 'md', 'options' => [
					'none' => 'option_gap_none',
					'xs'   => 'option_gap_xs',
					'sm'   => 'option_gap_sm',
					'md'   => 'option_gap_md',
					'lg'   => 'option_gap_lg',
				]],
			],
		],

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
	 * Whether the code is a container block (children live in its columns).
	 *
	 * Containers render their children instead of a standalone panel; the
	 * builder treats them as drop targets and the model forces them to the
	 * top level (no nested containers).
	 */
	public static function isContainer(string $code): bool {
		return isset(self::BLOCKS[$code]['container']) && self::BLOCKS[$code]['container'];
	}

	/**
	 * Number of columns of a section structure (unknown presets: one column).
	 */
	public static function columnCount(string $structure): int {
		return isset(self::STRUCTURES[$structure]) ? count(self::STRUCTURES[$structure]['slots']) : 1;
	}

	/**
	 * Grid classes of a section structure (responsive, literal Tailwind).
	 */
	public static function gridClass(string $structure): string {
		return self::STRUCTURES[$structure]['grid'] ?? self::STRUCTURES['1']['grid'];
	}

	/**
	 * Width classes of one column inside a section structure.
	 */
	public static function columnClass(string $structure, int $index): string {
		$slots = self::STRUCTURES[$structure]['slots'] ?? self::STRUCTURES['1']['slots'];

		return $slots[$index] ?? '';
	}

	/**
	 * Gap classes between the columns of a section.
	 */
	public static function gapClass(string $gap): string {
		return self::GAPS[$gap] ?? self::GAPS['md'];
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
				'code'      => $code,
				'name'      => $block['name'],
				'icon'      => $block['icon'],
				'group'     => $block['group'],
				'container' => self::isContainer($code),
				'fields'    => self::fields($code),
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
	 * Assembles the canonical block list the landing models share.
	 *
	 * `oc_landing_block.settings` and `oc_landing_block_description.content`
	 * are stored as JSON strings, and both models (admin and catalog) used to
	 * decode them with four slightly different copies of the same loop. This
	 * is the single decoder: JSON decoded, content keyed by block_id and
	 * language_id, the row order preserved.
	 *
	 * @param array<int, array<string, mixed>> $rows oc_landing_block rows (order preserved)
	 * @param array<int, array<string, mixed>> $description_rows oc_landing_block_description rows
	 * @return array<int, array<string, mixed>>
	 */
	public static function hydrate(array $rows, array $description_rows): array {
		$content = [];

		foreach ($description_rows as $row) {
			$decoded = json_decode((string)$row['content'], true);

			$content[(int)$row['block_id']][(int)$row['language_id']] = is_array($decoded) ? $decoded : [];
		}

		$blocks = [];

		foreach ($rows as $row) {
			$settings = json_decode((string)$row['settings'], true);
			$block_id = (int)$row['block_id'];

			$blocks[] = [
				'block_id'   => $block_id,
				'parent_id'  => (int)$row['parent_id'],
				'slot'       => (int)$row['slot'],
				'code'       => (string)$row['code'],
				'sort_order' => (int)$row['sort_order'],
				'status'     => (int)$row['status'],
				'settings'   => is_array($settings) ? $settings : [],
				'content'    => $content[$block_id] ?? [],
			];
		}

		return $blocks;
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
				// The raw HTML block is deliberately NOT run through sanitizeHtml():
				// pasting embeds and analytics snippets is its purpose. It renders
				// with |raw, so it stays editable only for accounts trusted with
				// `modify design/landing`.
				return trim((string)$value);

			case 'textarea':
				// Plain multi-line copy (rendered as text + nl2br), so tags are
				// dropped exactly like the single-line text fields.
				return Text::truncate(trim(strip_tags((string)$value)), isset($field['max']) ? (int)$field['max'] : 5000);

			case 'text':
			default:
				return Text::truncate(trim(strip_tags((string)$value)), isset($field['max']) ? (int)$field['max'] : 255);
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
				// Style and icon selects always carry a default value; a fresh
				// empty row must still be recognised as empty and dropped.
				if ($key === 'style' || $key === 'icon') {
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

		// The value lands inside HTML attributes (href="…", src="…", url('…')):
		// strip the characters that could break out of a quoted attribute.
		$value = str_replace(["\x00", '"', "'", '<', '>', '\\'], '', $value);

		if ($value === '') {
			return '';
		}

		if (!self::urlSchemeAllowed($value)) {
			return '';
		}

		if (preg_match('#^(https?:)?//#i', $value) || preg_match('#^(mailto|tel):#i', $value)) {
			return Text::truncate($value, 255);
		}

		// Internal path: keep it relative but never allow protocol-relative tricks.
		return Text::truncate(ltrim($value, '/'), 255);
	}

	/**
	 * Whether a URL value carries only a safe scheme.
	 *
	 * A colon before the first / or ? introduces a scheme; that prefix must
	 * be http(s), mailto, tel or an inline media data: URI. Everything else
	 * is refused - javascript:, vbscript:, data:text/html, and the entity or
	 * raw-control-character tricks (jav&#x09;ascript:, jav&Tab;ascript:,
	 * "jav\tascript:") a browser would decode back into one of those inside
	 * an attribute. No colon before any / or ? is a relative path, anchor or
	 * query and always allowed.
	 */
	private static function urlSchemeAllowed(string $url): bool {
		$url = trim($url);

		if ($url === '') {
			return true;
		}

		$colon = strpos($url, ':');

		if ($colon === false) {
			return true;
		}

		foreach ([strpos($url, '/'), strpos($url, '?')] as $earlier) {
			if ($earlier !== false && $earlier < $colon) {
				// A path or query starts before the colon: not a scheme.
				return true;
			}
		}

		$prefix = substr($url, 0, $colon);

		if (preg_match('/^(https?|mailto|tel)$/i', $prefix)) {
			return true;
		}

		return (bool)preg_match('#^data:(image/(?:png|jpe?g|gif|webp|bmp)|video/|audio/)#i', $url);
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

		// srcdoc attributes carry a whole nested HTML document: drop them.
		$cleaned = preg_replace('#\ssrcdoc\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $value);

		if ($cleaned !== null) {
			$value = $cleaned;
		}

		// Link/media attribute values are scheme-whitelisted: anything whose
		// prefix before the first :/?# is not http(s)/mailto/tel/inline-media
		// (javascript:, vbscript:, data:text/html, "jav&#x09;ascript:" after
		// entity decoding) is replaced by a dead "#" anchor. Inline raster
		// images and media pasted by the editor stay allowed.
		$cleaned = preg_replace_callback(
			'#((?:href|src|action|formaction|poster|background|dynsrc|lowsrc|srcset|xlink:href)\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+)#i',
			static function (array $match): string {
				$raw = $match[2];
				$inner = $raw;

				if ($inner !== '' && ($inner[0] === '"' || $inner[0] === "'")) {
					$inner = substr($inner, 1, -1);
				}

				if (self::urlSchemeAllowed($inner)) {
						return $match[0];
				}

				return $match[1] . '"#"';
			},
			$value
		);

		if (is_string($cleaned)) {
			$value = $cleaned;
		}

		return Text::truncate($value, 65535);
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
		$flags = (empty($settings['hide_mobile']) ? '1' : '0')
			. (empty($settings['hide_tablet']) ? '1' : '0')
			. (empty($settings['hide_desktop']) ? '1' : '0');

		$classes = [
			'111' => '',
			'000' => 'hidden',
			'110' => 'lg:hidden',
			'101' => 'md:hidden lg:block',
			'011' => 'hidden md:block',
			'100' => 'md:hidden',
			'010' => 'hidden md:block lg:hidden',
			'001' => 'hidden md:hidden lg:block',
		];

		return $classes[$flags] ?? '';
	}
}
