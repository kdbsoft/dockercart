<?php
/**
 * Landing block registry: codes, schema, normalisation and wrapper CSS.
 *
 * The registry is the single source of truth shared by the admin builder, the
 * admin model and the storefront renderer, so its invariants are worth a test:
 * every advertised code must have a template, every field must declare a known
 * type with a usable default, and untrusted payloads must be normalised (never
 * passed through).
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Landing\Blocks;

const LANDING_TEMPLATE_DIR = '/upload/catalog/view/theme/dockercart/template/landing/blocks/';

const LANDING_FIELD_TYPES = [
	'text', 'textarea', 'richtext', 'code', 'number', 'toggle', 'select',
	'color', 'image', 'link', 'products', 'categories', 'category', 'banner', 'repeater',
];

return [
	'registry: codes are stable and complete' => static function (): void {
		Assert::same(
			['hero', 'text', 'cta', 'products', 'products_carousel', 'categories', 'image', 'image_text', 'banner', 'spacer', 'html'],
			Blocks::codes()
		);
	},

	'registry: every block has a storefront template' => static function (): void {
		foreach (Blocks::codes() as $code) {
			Assert::true(
				is_file(REPO_ROOT . LANDING_TEMPLATE_DIR . $code . '.twig'),
				'missing template landing/blocks/' . $code . '.twig'
			);
		}
	},

	'registry: unknown codes have no definition' => static function (): void {
		Assert::false(Blocks::exists('nope'));
		Assert::same([], Blocks::fields('nope'));
		Assert::same([], Blocks::localizedFields('nope'));
	},

	'registry: every block belongs to a known palette group' => static function (): void {
		foreach (Blocks::all() as $code => $definition) {
			Assert::true(isset(Blocks::GROUPS[$definition['group']]), $code . ' has group ' . $definition['group']);
			Assert::true($definition['name'] !== '' && $definition['icon'] !== '', $code . ' has a name and an icon');
		}
	},

	'registry: field types, labels and defaults are valid' => static function (): void {
		foreach (Blocks::codes() as $code) {
			foreach (Blocks::fields($code) as $field) {
				Assert::true(in_array($field['type'], LANDING_FIELD_TYPES, true), $code . '::' . $field['key'] . ' type ' . $field['type']);
				Assert::true((string)$field['label'] !== '', $code . '::' . $field['key'] . ' has a label key');
				Assert::true(array_key_exists('default', $field), $code . '::' . $field['key'] . ' has a default');
				Assert::true(array_key_exists('localized', $field), $code . '::' . $field['key'] . ' declares localization');

				if ($field['type'] === 'select') {
					Assert::true(!empty($field['options']), $code . '::' . $field['key'] . ' select needs options');
					Assert::true(array_key_exists((string)$field['default'], $field['options']), $code . '::' . $field['key'] . ' default is an option');
				}
			}
		}
	},

	'registry: every block carries the shared wrapper fields' => static function (): void {
		$wrapper = array_map(static function (array $field): string {
			return (string)$field['key'];
		}, Blocks::wrapperFields());

		foreach (Blocks::codes() as $code) {
			$defaults = Blocks::defaults($code);

			foreach ($wrapper as $key) {
				Assert::true(array_key_exists($key, $defaults['settings']), $code . ' is missing wrapper field ' . $key);
			}
		}
	},

	'schema: exposes fields and icons for the builder' => static function (): void {
		$schema = Blocks::schema();

		Assert::count(count(Blocks::codes()), $schema);
		Assert::true(isset($schema['hero']['fields'][0]['key']));
		Assert::same('hero', $schema['hero']['code']);
	},

	'normalizeSettings: casts numbers, whitelists selects, drops unknown keys' => static function (): void {
		$settings = Blocks::normalizeSettings('products', [
			'source' => 'not-a-source',
			'limit' => 9999,
			'product_ids' => ['5', '5', '-3', '7', 'x'],
			'columns' => '5',
			'bg_type' => 'color',
			'bg_color' => '#AABBCC',
			'padding' => 'md',
			'unknown_field' => 'nope',
			'heading' => 'localised, must not land in settings',
		]);

		Assert::same('manual', $settings['source'], 'invalid select falls back to the default');
		Assert::same(24, $settings['limit'], 'number is clamped');
		Assert::same([5, 7], $settings['product_ids'], 'ids are deduped and sanitised');
		Assert::same('5', $settings['columns']);
		Assert::same('#aabbcc', $settings['bg_color'], 'colours are lowercased');
		Assert::false(array_key_exists('unknown_field', $settings));
		Assert::false(array_key_exists('heading', $settings), 'localised fields never land in settings');
	},

	'normalizeContent: only localised fields survive' => static function (): void {
		$content = Blocks::normalizeContent('products', [
			'heading' => '  Big sale  ',
			'link_label' => 'All deals',
			'source' => 'latest',
			'limit' => 12,
		]);

		Assert::same('Big sale', $content['heading'], 'text is trimmed');
		Assert::same('All deals', $content['link_label']);
		Assert::false(array_key_exists('source', $content), 'settings do not land in content');
		Assert::false(array_key_exists('limit', $content));
	},

	'normalizeContent: a missing language falls back to field defaults' => static function (): void {
		$content = Blocks::normalizeContent('hero', []);

		Assert::same('', $content['heading']);
		Assert::same([], $content['buttons']);
	},

	'normalize: richtext drops script tags and inline handlers' => static function (): void {
		$content = Blocks::normalizeContent('text', [
			'body' => '<p onclick="steal()">Hello</p><script>evil()</script><a href="/x">link</a>',
		]);

		Assert::contains('<p>Hello</p>', $content['body']);
		Assert::contains('<a href="/x">link</a>', $content['body']);
		Assert::notContains('script', $content['body']);
		Assert::notContains('onclick', $content['body']);
	},

	'normalize: links reject script-capable schemes' => static function (): void {
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'javascript:alert(1)'])['href']);
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'data:text/html;base64,xx'])['href']);
		Assert::same('https://example.com/a', Blocks::normalizeSettings('image', ['href' => 'https://example.com/a'])['href']);
		Assert::same('black-friday', Blocks::normalizeSettings('image', ['href' => '/black-friday'])['href']);
	},

	'normalize: repeater rows are bounded and empty rows dropped' => static function (): void {
		$content = Blocks::normalizeContent('hero', [
			'buttons' => [
				['label' => 'Buy', 'href' => '/checkout/cart', 'style' => 'primary'],
				['label' => '', 'href' => '', 'style' => 'primary'],
				['label' => 'More', 'href' => '/more', 'style' => 'not-a-style'],
				['label' => 'Third', 'href' => '/3', 'style' => 'secondary'],
			],
		]);

		Assert::count(2, $content['buttons'], 'empty rows are dropped and max_rows is honoured');
		Assert::same('Buy', $content['buttons'][0]['label']);
		Assert::same('primary', $content['buttons'][1]['style'], 'unknown style falls back to the default');
	},

	'wrapper: padding, background colour and background image' => static function (): void {
		$styles = Blocks::wrapperStyles('text', [
			'padding' => 'md',
			'bg_type' => 'image',
			'bg_image' => 'catalog/hero.jpg',
			'overlay' => 40,
		]);

		Assert::same('2.5rem', $styles['padding-top']);
		Assert::same("url('image/catalog/hero.jpg')", $styles['background-image']);
		Assert::same('cover', $styles['background-size']);
		Assert::same(40, Blocks::overlay(['bg_type' => 'image', 'overlay' => 40]));
		Assert::same(0, Blocks::overlay(['bg_type' => 'color', 'overlay' => 40]), 'overlay only applies to image backgrounds');

		$attribute = Blocks::wrapperStyleAttribute('text', ['padding' => 'none']);
		Assert::same('', $attribute, 'padding none emits nothing');
	},

	'wrapper: responsive visibility classes' => static function (): void {
		Assert::same('', Blocks::visibilityClass([]));
		Assert::same('hidden', Blocks::visibilityClass(['hide_mobile' => 1, 'hide_tablet' => 1, 'hide_desktop' => 1]));
		Assert::same('lg:hidden', Blocks::visibilityClass(['hide_desktop' => 1]));
		Assert::same('hidden md:block', Blocks::visibilityClass(['hide_mobile' => 1]));
		Assert::same('md:hidden', Blocks::visibilityClass(['hide_tablet' => 1, 'hide_desktop' => 1]));
		Assert::same('md:hidden lg:block', Blocks::visibilityClass(['hide_tablet' => 1]));
	},

	'wrapper: builder mode suppresses visibility classes' => static function (): void {
		$classes = Blocks::wrapperClasses('hero', ['hide_mobile' => 1, 'custom_class' => 'my-page-hero'], true);

		Assert::contains('lp-block', $classes);
		Assert::contains('lp-block--hero', $classes);
		Assert::contains('my-page-hero', $classes);
		Assert::notContains('hidden', $classes);

		Assert::contains('hidden md:block', Blocks::wrapperClasses('hero', ['hide_mobile' => 1], false));
	},
];
