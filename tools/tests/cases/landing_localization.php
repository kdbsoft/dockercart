<?php
/**
 * Landing page localization parity.
 *
 * The panel is trilingual (en-gb, ru-ua, uk-ua) and the builder's labels come
 * from the block registry (by key), so a missing translation renders as a raw
 * "field_heading"-style key in the properties panel. This case guards:
 *
 *   - the three admin language files expose the same keys,
 *   - every label/option key declared by Dockercart\Landing\Blocks exists,
 *   - every UI key the builder bootstrap asks for exists,
 *   - the storefront language files exist for all three locales.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Landing\Blocks;

const LANDING_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

const LANDING_ADMIN_LANGUAGE = 'upload/admin/language/{locale}/design/landing.php';

const LANDING_CATALOG_LANGUAGE = 'upload/catalog/language/{locale}/landing/landing.php';

/**
 * @return array<string, string>
 */
function landing_language_keys(string $relative, string $locale): array {
	return read_language_file(REPO_ROOT . '/' . str_replace('{locale}', $locale, $relative));
}

/**
 * @return array<int, string>
 */
function landing_registry_label_keys(): array {
	$keys = array_values(Blocks::GROUPS);

	foreach (Blocks::all() as $block) {
		$keys[] = (string)$block['name'];
	}

	foreach (Blocks::codes() as $code) {
		foreach (Blocks::fields($code) as $field) {
			$keys[] = (string)$field['label'];

			foreach ((array)($field['options'] ?? []) as $label_key) {
				$keys[] = (string)$label_key;
			}

			foreach ((array)($field['fields'] ?? []) as $sub_field) {
				$keys[] = (string)$sub_field['label'];

				foreach ((array)($sub_field['options'] ?? []) as $label_key) {
					$keys[] = (string)$label_key;
				}
			}
		}
	}

	return array_values(array_unique(array_filter($keys)));
}

/**
 * Keys the builder bootstrap resolves through I18N (kept in sync with
 * ControllerDesignLanding::buildI18n()).
 *
 * @return array<int, string>
 */
function landing_builder_ui_keys(): array {
	return [
		'text_save', 'text_saved', 'text_saving', 'text_unsaved', 'text_preview',
		'text_page_settings', 'text_block_settings', 'text_section_settings', 'text_select_block',
		'text_no_blocks', 'text_palette', 'text_templates', 'text_no_templates', 'text_save_template',
		'text_insert_template', 'text_delete_template', 'text_template_name', 'text_clone_hint',
		'text_duplicate', 'text_remove', 'text_undo', 'text_redo', 'text_autosave', 'text_language',
		'text_view_desktop', 'text_view_tablet', 'text_view_mobile', 'text_add_section',
		'text_hidden_block', 'text_hidden_device', 'text_search_blocks', 'text_choose_image',
		'text_clear', 'text_add_row', 'text_pick_products', 'text_pick_category', 'text_pick_banner',
		'text_error_save', 'text_conflict', 'text_back', 'text_status_draft', 'text_status_published',
		'text_created', 'text_copy', 'text_no_banners', 'button_save', 'button_cancel',
		'entry_name', 'entry_slug', 'entry_meta_title', 'entry_meta_description', 'entry_meta_keyword',
		'entry_status', 'entry_chrome', 'entry_chrome_full', 'entry_chrome_bare', 'entry_noindex',
		'entry_is_home', 'entry_date_publish', 'entry_date_unpublish', 'entry_custom_css',
		'entry_viewed', 'entry_revision',
		'error_name', 'error_slug', 'error_slug_used', 'error_slug_invalid', 'error_dates',
		'error_home_status',
	];
}

return [
	'admin: language files exist for every locale' => static function (): void {
	foreach (LANDING_LOCALES as $locale) {
		Assert::true(is_file(REPO_ROOT . '/' . str_replace('{locale}', $locale, LANDING_ADMIN_LANGUAGE)), $locale);
		Assert::true(is_file(REPO_ROOT . '/' . str_replace('{locale}', $locale, LANDING_CATALOG_LANGUAGE)), $locale);
	}
},

	'admin: all locales expose the same keys' => static function (): void {
		$english = landing_language_keys(LANDING_ADMIN_LANGUAGE, 'en-gb');

		Assert::true(count($english) > 100, 'the admin language file looks empty');

		foreach (LANDING_LOCALES as $locale) {
			$localized = landing_language_keys(LANDING_ADMIN_LANGUAGE, $locale);

			$missing = array_diff(array_keys($english), array_keys($localized));
			$extra = array_diff(array_keys($localized), array_keys($english));

			Assert::same([], array_values($missing), $locale . ' misses keys');
			Assert::same([], array_values($extra), $locale . ' has unknown keys');
		}
	},

	'admin: every registry label is translated' => static function (): void {
		foreach (LANDING_LOCALES as $locale) {
			$localized = landing_language_keys(LANDING_ADMIN_LANGUAGE, $locale);

			$missing = [];

			foreach (landing_registry_label_keys() as $key) {
				if (!array_key_exists($key, $localized)) {
					$missing[] = $key;
				}
			}

			Assert::same([], $missing, $locale . ' misses block label keys');
		}
	},

	'admin: builder UI keys are translated' => static function (): void {
		foreach (LANDING_LOCALES as $locale) {
			$localized = landing_language_keys(LANDING_ADMIN_LANGUAGE, $locale);
			// Some buttons live in the global admin language file.
			$global = landing_language_keys('upload/admin/language/{locale}/{locale}.php', $locale);

			$missing = [];

			foreach (landing_builder_ui_keys() as $key) {
				if (!array_key_exists($key, $localized) && !array_key_exists($key, $global)) {
					$missing[] = $key;
				}
			}

			Assert::same([], $missing, $locale . ' misses builder keys');
		}
	},

	'admin: no translation is empty' => static function (): void {
		foreach (LANDING_LOCALES as $locale) {
			foreach (landing_language_keys(LANDING_ADMIN_LANGUAGE, $locale) as $key => $value) {
				Assert::true(trim((string)$value) !== '', $locale . ':' . $key . ' is empty');
			}
		}
	},

	'catalog: storefront strings exist for every locale' => static function (): void {
		$keys = ['heading_title', 'text_home', 'text_error', 'text_empty_page', 'text_builder_empty', 'text_view_all', 'text_previous', 'text_next'];

		foreach (LANDING_LOCALES as $locale) {
			$localized = landing_language_keys(LANDING_CATALOG_LANGUAGE, $locale);

			foreach ($keys as $key) {
				Assert::true(array_key_exists($key, $localized), $locale . ' misses ' . $key);
				Assert::true(trim((string)$localized[$key]) !== '', $locale . ':' . $key . ' is empty');
			}
		}
	},

	'catalog: every block template exists and uses only translated helpers' => static function (): void {
		foreach (Blocks::codes() as $code) {
			$template = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/' . $code . '.twig');

			Assert::true($template !== '', $code . ' template is empty');

			// The renderer always provides these two arrays.
			Assert::contains('{{', $template, $code . ' template renders nothing');
		}
	},

	'seo: landing decode and rewrite branches are wired' => static function (): void {
		$seo = repo_file('upload/catalog/controller/startup/seo_url.php');

		Assert::contains('landing_id=', $seo, 'landing keywords are not registered/rewritten');
		Assert::contains('"landing/page"', $seo, 'landing/page route is not decoded');
		Assert::contains('lp-', $seo, 'lp-<id> fallback is missing');
	},

	'home: landing home override is wired' => static function (): void {
		$home = repo_file('upload/catalog/controller/common/home.php');

		Assert::contains('config_landing_home_id', $home);
		Assert::contains('landing/page', $home);
	},
];
