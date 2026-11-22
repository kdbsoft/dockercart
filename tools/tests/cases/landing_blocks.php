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

const LANDING_BLOCKS_CSS = 'upload/catalog/view/stylesheet/landing/landing-blocks.css';

const LANDING_EDITORIAL_CSS = 'upload/catalog/view/stylesheet/landing/landing-editorial.css';

const LANDING_EDITOR_LOCALES = ['en-gb', 'uk-ua', 'ru-ua'];

const LANDING_EDITOR_KEYS = [
	'block_features', 'block_steps', 'block_faq', 'block_statement',
	'field_intro', 'field_items', 'field_steps', 'field_questions',
	'field_icon', 'field_item_title', 'field_item_text', 'field_question',
	'field_answer', 'field_foot', 'field_variant',
	'option_variant_plain', 'option_variant_soft', 'option_variant_dark',
	'option_icon_sparkles', 'option_icon_shield_check', 'option_icon_credit_card',
	'option_icon_truck', 'option_icon_package_check', 'option_icon_headset',
	'option_icon_wallet', 'option_icon_landmark', 'option_icon_refresh_cw',
	'option_icon_store',
	'field_accent_color', 'field_surface_color', 'field_ink_color',
	'field_muted_color', 'field_line_color', 'field_success_color',
	'field_radius', 'field_padding_y', 'field_content_width',
	'option_radius_none', 'option_radius_sm', 'option_radius_md',
	'option_padding_y_xs', 'option_padding_y_sm', 'option_padding_y_md',
	'option_padding_y_lg', 'option_padding_y_xl',
	'option_content_width_narrow', 'option_content_width_normal',
	'option_content_width_wide', 'option_content_width_full',

	'block_testimonials', 'block_stats', 'block_pricing',
	'field_quotes', 'field_quote', 'field_author', 'field_role', 'field_rating',
	'option_rating_0', 'option_rating_1', 'option_rating_2', 'option_rating_3',
	'option_rating_4', 'option_rating_5',
	'field_value', 'field_label', 'field_plans', 'field_plan', 'field_price',
	'field_description', 'field_features', 'field_featured',
	'option_plan_standard', 'option_plan_featured',
];

const LANDING_FIELD_TYPES = [
	'text', 'textarea', 'richtext', 'code', 'number', 'toggle', 'select',
	'color', 'image', 'link', 'products', 'categories', 'category', 'banner', 'repeater',
];

return [
	'registry: codes are stable and complete' => static function (): void {
		Assert::same(
			['hero', 'text', 'cta', 'features', 'steps', 'faq', 'statement', 'testimonials', 'stats', 'products', 'products_carousel', 'pricing', 'categories', 'image', 'image_text', 'banner', 'section', 'spacer', 'html'],
			Blocks::codes()
		);
	},

	'registry: section is the only container' => static function (): void {
		foreach (Blocks::codes() as $code) {
			Assert::same($code === 'section', Blocks::isContainer($code), $code);
		}

		Assert::true(Blocks::schema()['section']['container']);
		Assert::false(Blocks::schema()['hero']['container']);
	},

	'registry: section structures map to column layouts' => static function (): void {
		Assert::same(1, Blocks::columnCount('1'));
		Assert::same(2, Blocks::columnCount('2'));
		Assert::same(2, Blocks::columnCount('2-1'));
		Assert::same(4, Blocks::columnCount('4'));
		Assert::same(1, Blocks::columnCount('nope'));

		Assert::same('md:col-span-2', Blocks::columnClass('2-1', 0));
		Assert::same('', Blocks::columnClass('2-1', 1));
		Assert::same('md:col-span-2', Blocks::columnClass('1-2', 1));
		Assert::same('', Blocks::columnClass('nope', 0));

		Assert::contains('md:grid-cols-3', Blocks::gridClass('3'));
		Assert::same('gap-0', Blocks::gapClass('none'));
		Assert::same('gap-6', Blocks::gapClass('md'));
		Assert::same('gap-6', Blocks::gapClass('nope'), 'unknown gap falls back');

		$settings = Blocks::normalizeSettings('section', ['structure' => 'bogus', 'gap' => 'xl']);

		Assert::same('2', $settings['structure'], 'unknown structure falls back');
		Assert::same('md', $settings['gap']);
	},

	'registry: every block has a storefront template' => static function (): void {
		foreach (Blocks::codes() as $code) {
			Assert::true(
				is_file(REPO_ROOT . LANDING_TEMPLATE_DIR . $code . '.twig'),
				'missing template landing/blocks/' . $code . '.twig'
			);
		}
	},

	'registry: an empty repeater row with a select default is dropped' => static function (): void {
		$content = Blocks::normalizeContent('features', [
			'items' => [
				['icon' => 'truck', 'title' => '', 'text' => ''],
				['icon' => 'truck', 'title' => 'Same-day dispatch', 'text' => 'Paid before 14:00.'],
			],
		]);

		Assert::count(1, $content['items'], 'the untouched icon default must not keep an empty row alive');
		Assert::same('Same-day dispatch', $content['items'][0]['title']);
	},

	'registry: editor blocks have a shared theme-independent stylesheet' => static function (): void {
		Assert::true(is_file(REPO_ROOT . '/' . LANDING_BLOCKS_CSS), 'missing shared landing block CSS');
		Assert::true(is_file(REPO_ROOT . '/' . LANDING_EDITORIAL_CSS), 'missing editorial demo CSS');

		$css = (string)file_get_contents(REPO_ROOT . '/' . LANDING_BLOCKS_CSS);

		foreach (['.lp-native--features', '.lp-native--steps', '.lp-native--faq', '.lp-native--statement', '.lp-native--testimonials', '.lp-native--stats', '.lp-native--pricing'] as $selector) {
			Assert::contains($selector, $css, $selector . ' is not styled');
		}

		foreach (['testimonials', 'stats', 'pricing'] as $code) {
			Assert::contains('#lp-page .lp-block--' . $code . ' .lp-block__body', $css, $code . ' must use the full-width editorial body');
		}

		Assert::contains("#lp-page .lp-block--hero .lp-block__body.w-full > div", $css, 'full-width hero must not be rounded');
		Assert::contains("#lp-page .lp-block--cta .lp-block__body.w-full > div", $css, 'full-width CTA must not be rounded');
		Assert::contains("#lp-page .lp-block--features .lp-block__body", $css, 'native block backgrounds must be full width');
		Assert::contains("#lp-page .lp-block--image_text li > svg", $css, 'bullet icons must not be drawn twice');
		Assert::contains('var(--lp-accent', $css, 'native blocks must consume the accent token');
		Assert::contains('var(--lp-pad-y', $css, 'native blocks must consume the padding token');

		$editorial = (string)file_get_contents(REPO_ROOT . '/' . LANDING_EDITORIAL_CSS);

		Assert::notContains('lp-story', $editorial, 'editorial CSS must not depend on a magic class');
		Assert::contains('var(--lp-ink', $editorial, 'editorial CSS must consume the ink token');
		Assert::contains('var(--lp-content-w', $editorial, 'editorial CSS must consume the width token');

		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');
		$admin = repo_file('upload/admin/controller/design/landing.php');
		$template = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::contains('openSiteSettings', $builder, 'builder can open Site Settings');
		Assert::contains('openPageSettings', $builder, 'builder can switch back to Page Settings');
		Assert::contains('siteDesignUrl', $builder, 'builder saves site design through its own endpoint');
		Assert::contains('saveSiteDesign', $admin, 'admin controller exposes the save endpoint');
		Assert::contains('site_design_schema', $template, 'builder receives the design schema');
		Assert::notContains('lp-settings-menu', $template, 'settings button has no dropdown');
		Assert::contains('$(\'lp-site-settings-btn\').addEventListener(\'click\', function () {', $builder, 'settings button switches the panel directly');
		Assert::contains('lp-settings-mode-label', $template, 'settings button has a dynamic label');
		Assert::true(
			strpos($template, 'NAVIGATOR (PAGE TREE)') > strpos($template, 'CENTER: LIVE PREVIEW')
			&& strpos($template, 'NAVIGATOR (PAGE TREE)') < strpos($template, 'RIGHT: PROPERTIES'),
			'Navigator panel must sit between the canvas and Properties'
		);
		Assert::contains("close.classList.toggle('hidden', ui.siteSettings)", $builder, 'Site Settings has no close button');
		Assert::contains('view/stylesheet/stylesheet.css?v={{ admin_stylesheet_version }}', $template, 'the injected filemanager needs the admin stylesheet');
		Assert::contains('admin_stylesheet_version', $admin, 'the stylesheet must be cache-busted like the other admin assets');
		Assert::contains('function openMediaPicker(', $builder, 'the builder reuses common/filemanager');
		Assert::contains('<span class="font-semibold whitespace-nowrap">{{ i18n.text_back }}</span>', $template, 'Back has a label like Layout Manager');
		Assert::notContains('id="lp-name-input"', $template, 'no page name field in the top bar');

		$controller = repo_file('upload/catalog/controller/landing/page.php');

		Assert::contains("'catalog/view/stylesheet/landing/landing-blocks.css", $controller, 'landing controller must load the CSS for every theme');
		Assert::contains("'catalog/view/stylesheet/landing/landing-editorial.css", $controller, 'editorial CSS must be loaded without page Custom CSS');
		Assert::contains('design_style', $controller, 'storefront receives global design variables');
	},

	'background: hero and cta use the shared Style image' => static function (): void {
		foreach (['hero', 'cta'] as $code) {
			foreach (Blocks::fields($code) as $field) {
				Assert::false(
					$field['type'] === 'image' && in_array($field['key'], ['image', 'image_mobile'], true),
					$code . ' still owns a second background image field'
				);
			}
		}

		$wrappers = array_column(Blocks::wrapperFields(), 'key');

		Assert::true(in_array('bg_color', $wrappers, true));
		Assert::true(in_array('bg_image', $wrappers, true));
		Assert::true(in_array('bg_image_mobile', $wrappers, true));
		Assert::false(in_array('bg_type', $wrappers, true), 'the background type select is gone');

		$hero = Blocks::normalizeSettings('hero', ['image' => 'catalog/hero.jpg', 'image_overlay' => 55]);

		Assert::same('catalog/hero.jpg', $hero['bg_image'], 'old hero images migrate');
		Assert::same(55, $hero['overlay'], 'old hero overlay migrates');
		Assert::false(array_key_exists('bg_type', $hero), 'a migrated image needs no type flag');

		$cta = Blocks::normalizeSettings('cta', ['image' => 'catalog/cta.jpg']);

		Assert::same('catalog/cta.jpg', $cta['bg_image'], 'old cta images migrate');

		$styles = Blocks::wrapperStyles('hero', [
			'bg_color' => '#123456',
			'bg_image' => 'catalog/desktop.jpg',
			'bg_image_mobile' => 'catalog/mobile.jpg',
		]);

		Assert::same('#123456', $styles['background-color'], 'the colour stays under the image');
		Assert::same("url('image/catalog/desktop.jpg')", $styles['background-image']);
		Assert::contains('catalog/mobile.jpg', $styles['--lp-bg-image-mobile']);

		$hero_twig = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/hero.twig');
		$cta_twig = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/cta.twig');
		$css = repo_file('upload/catalog/view/stylesheet/landing/landing-editorial.css');

		Assert::notContains('settings.image', $hero_twig);
		Assert::notContains('<img src="{{ image }}', $cta_twig);
		Assert::contains('lp-panel-wrapper-bg { background: transparent', $css, 'the wrapper background must not be hidden');
		Assert::contains('lp-block--bg-mobile { background-image: var(--lp-bg-image-mobile)', $css);

		$builder_js = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');
		$builder_php = repo_file('upload/admin/controller/design/landing.php');

		Assert::contains("'bg_color', 'bg_image', 'bg_image_mobile', 'overlay', 'padding', 'container', 'align'", $builder_js, 'mobile background belongs to Style');
		Assert::notContains("'bg_type'", $builder_js, 'the Style tab has no background type select');

		$page_controller = repo_file('upload/catalog/controller/landing/page.php');
		Assert::notContains('bg_type', $page_controller, 'the renderer infers the background from the values');

		foreach (LANDING_EDITOR_LOCALES as $locale) {
			$locale_file = repo_file('upload/admin/language/' . $locale . '/design/landing.php');

			Assert::notContains('$_[\'field_bg_type\']', $locale_file, $locale . ' still labels the removed select');
			Assert::notContains('$_[\'option_bg_none\']', $locale_file, $locale . ' still labels the removed option');
		}

		Assert::contains('$blocks = $this->model_design_landing->getBlocks($landing_id);', $builder_php);
		Assert::contains('Dockercart\\Landing\\Blocks::normalizeSettings($code, (array)$block[\'settings\'])', $builder_php, 'the builder must show migrated settings');
	},

	'editor: selects use the shared dockercart chevron' => static function (): void {
		$template = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::contains('select.lp-input', $template);
		Assert::contains('var(--dc-select-chevron)', $template);
		Assert::contains('input.lp-input, select.lp-input { height: 32px; }', $template, 'inputs and selects share one height');
		Assert::contains('.lp-align-group { display: inline-flex; align-items: stretch; overflow: hidden; border: 1px solid var(--dc-border); border-radius: var(--dc-radius-md); background: #fff; height: 32px; }', $template, 'alignment segments match the inputs');
	},

	'editor: left/center/right selects use icon buttons' => static function (): void {
		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains('function isAlignmentOptions(', $builder);
		Assert::contains('function renderAlignmentOptions(', $builder);
		Assert::contains('function bindAlignmentField(', $builder, 'the segmented control is clickable without hidden radios');
		Assert::contains('data-align-value=', $builder);
		Assert::contains('class="lp-align-group"', $builder);
		Assert::notContains('type="radio" class="hidden" data-input', $builder);
		Assert::contains('.lp-align-group button[aria-pressed="true"]', repo_file('upload/admin/view/template/design/landing_builder.twig'));

		foreach (['align-start-vertical', 'align-center-vertical', 'align-end-vertical'] as $icon) {
			Assert::contains($icon, $builder, 'missing alignment icon ' . $icon);
		}

		foreach (Blocks::codes() as $code) {
			foreach (Blocks::fields($code) as $field) {
				$keys = array_keys((array)($field['options'] ?? []));

				if (!$keys || array_diff($keys, ['left', 'center', 'right'])) {
					continue;
				}

				Assert::true($field['type'] === 'select', $code . '.' . $field['key'] . ' must stay a select in the registry');
			}
		}
	},

	'editor: advanced fields live only in the Advanced tab' => static function (): void {
		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');
		$template = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::contains('data-props-tab="advanced"', $template, 'the dedicated tab must stay');
		Assert::contains("ui.propsTab === 'advanced' ? advancedHtml : contentHtml", $builder);
		Assert::notContains('<details class="mt-4 border-t border-gray-100 pt-3">', $builder, 'no duplicate Advanced block under Content/Style');
	},

	'editor: visibility switches are clickable and hidden blocks are marked on canvas' => static function (): void {
		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');
		$template = repo_file('upload/admin/view/template/design/landing_builder.twig');
		$canvas = repo_file('upload/catalog/view/javascript/dockercart_landing_builder.js');
		$page = repo_file('upload/catalog/controller/landing/page.php');
		$registry = repo_file('upload/system/library/dockercart/landing/blocks.php');

		// The visible switch track is a label around a 0x0 checkbox; a bare
		// <span> over it swallowed every click on the Advanced toggles.
		Assert::contains('<label class="lp-switch" for="\' + id + \'"><input type="checkbox" id="\' + id + \'" data-input', $builder, 'toggle fields need a label-wrapped switch');
		Assert::contains('<label class="lp-switch" for="\' + statusId + \'"><input type="checkbox" id="\' + statusId + \'" data-block-status', $builder, 'Status needs the same label-wrapped switch');
		Assert::notContains('<span class="lp-switch"><input type="checkbox"', $builder, 'no unclickable switch markup');
		Assert::contains('.lp-switch { position: relative; display: inline-flex; width: 34px; height: 20px; flex-shrink: 0; cursor: pointer; }', $template);
		Assert::contains('.lp-switch input:focus-visible + .lp-switch-track', $template, 'keyboard focus stays visible');

		foreach (['hide_desktop', 'hide_tablet', 'hide_mobile'] as $device) {
			Assert::contains("['key' => '" . $device . "', 'type' => 'toggle', 'localized' => false", $registry, $device . ' must stay a toggle');
		}

		// Parent broadcasts the active device on switch and after every reload.
		Assert::contains("type: 'lp:viewport',", $builder);
		Assert::contains("label: t('text_hidden_device', 'Hidden on this device')", $builder);
		$readyPos = strpos($builder, "case 'lp:ready':");
		Assert::true($readyPos !== false, 'builder handles the iframe ready handshake');
		Assert::contains('sendViewport();', substr($builder, $readyPos, 400), 'reload restores viewport feedback');
		$viewportPos = strpos($builder, 'function initViewport()');
		Assert::true($viewportPos !== false, 'builder has a viewport switcher');
		Assert::contains('sendViewport();', substr($builder, $viewportPos, 900), 'viewport switch updates the canvas');

		// Iframe consumes it via the data attribute the storefront shell emits.
		Assert::contains("if (data.type === 'lp:viewport') {", $canvas);
		Assert::contains('function hiddenOnDevice(block, device) {', $canvas);
		Assert::contains('function syncDeviceHidden() {', $canvas);
		Assert::contains("badge.className = 'lp-device-hidden-badge';", $canvas);
		Assert::contains('badge.textContent = hiddenDeviceLabel;', $canvas, 'badge text comes from the admin locale');
		Assert::contains("'html.lp-builder-mode [data-landing-block].lp-hidden-on-device { opacity: .5; }',", $canvas, 'hidden blocks are dimmed');
		Assert::contains('.lp-device-hidden-badge { position: absolute;', $canvas, 'badge must not affect layout');
		Assert::contains('pointer-events: none;', $canvas, 'badge stays non-interactive');
		Assert::contains("\t\tmarkEmptyBlocks();\n\t\tsyncDeviceHidden();\n\t\tinitSortables();", $canvas, 'live preview re-applies device feedback');
		Assert::contains("' data-lp-hide=\"' . implode(',', \$hidden_devices) . '\"'", $page, 'server still supplies per-device hide flags');
	},

	'editor: add/remove/template actions are styled as buttons' => static function (): void {
		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');
		$template = repo_file('upload/admin/view/template/design/landing_builder.twig');

		Assert::contains('px-2 py-1.5 text-[11px] font-medium leading-none text-blue-700 bg-white hover:bg-blue-50 border border-blue-200 rounded" data-repeater-add', $builder, 'Add row must be a compact button');
		Assert::contains('px-2 py-1.5 text-[11px] font-medium leading-none text-red-600 bg-white hover:bg-red-50 border border-red-200 rounded" data-repeater-remove', $builder, 'Remove must be a compact button');
		Assert::contains('text-blue-700 bg-white hover:bg-blue-50 border border-blue-200 rounded" data-media-pick', $builder, 'Choose must read as the primary action');
		Assert::contains('text-gray-500 bg-white hover:text-red-600 hover:bg-red-50 hover:border-red-200 border border-gray-200 rounded" data-clear', $builder, 'Clear must be a subdued button with a destructive hover');
		Assert::notContains('text-gray-500 hover:text-red-600 rounded" data-clear', $builder, 'no link-styled Clear');
		Assert::contains('id="lp-template-save" class="inline-flex items-center gap-1 px-2 py-1.5 text-[11px] font-medium leading-none text-blue-700 bg-white hover:bg-blue-50 border border-blue-200 rounded"', $template, 'Save template must be a compact button');
		Assert::notContains('class="mt-1 text-[11px] text-blue-600', $builder, 'no link-styled Add row');
	},

	'registry: editor block labels exist in all three locales' => static function (): void {
		foreach (LANDING_EDITOR_LOCALES as $locale) {
			$file = repo_file('upload/admin/language/' . $locale . '/design/landing.php');

			foreach (LANDING_EDITOR_KEYS as $key) {
				Assert::contains('$_[\'' . $key . '\']', $file, $locale . ' is missing ' . $key);
			}
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
		Assert::false(array_key_exists('bg_type', $settings), 'the legacy type flag is not normalised');
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

	'normalize: richtext defangs script-capable URL schemes and srcdoc' => static function (): void {
		$content = Blocks::normalizeContent('text', [
			'body' => '<a href="javascript:alert(1)">x</a>' .
				'<a href=\'vbscript:msgbox\'>y</a>' .
				'<a href="data:text/html;base64,PHNjcmlwdD4=">z</a>' .
				'<a href="jav&#x09;ascript:alert(2)">entity</a>' .
				'<a href="jav&Tab;ascript:alert(3)">named</a>' .
				'<img src="data:image/png;base64,iVBORw0KGgo=" alt="" />' .
				'<a href="/relative">ok</a>' .
				'<iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe>',
		]);

		Assert::notContains('javascript:', $content['body']);
		Assert::notContains('vbscript:', $content['body']);
		Assert::notContains('data:text/html', $content['body']);
		// The scheme whitelist runs on the raw value, so entity/tab tricks that
		// a browser would decode back into javascript: never survive either.
		Assert::notContains('jav&#x09;ascript:', $content['body']);
		Assert::notContains('jav&Tab;ascript:', $content['body']);
		Assert::notContains('srcdoc', $content['body']);
		Assert::contains('href="#"', $content['body'], 'dangerous schemes collapse to a dead anchor');
		Assert::contains('data:image/png;base64,iVBORw0KGgo=', $content['body'], 'inline raster images stay allowed');
		Assert::contains('href="/relative"', $content['body'], 'relative links stay intact');
	},

	'normalize: textarea fields are plain text' => static function (): void {
		// Textarea fields render as text + nl2br (autoescape is off in Twig),
		// so a tag stored through them would execute on the storefront.
		$content = Blocks::normalizeContent('faq', [
			'intro' => '<img src=x onerror=alert(1)>Plain <b>intro</b>',
			'items' => [
				['question' => 'Q?', 'answer' => '<script>evil()</script>Real <i>answer</i>'],
			],
		]);

		// strip_tags keeps the inner text (same as the text fields) but never a
		// tag, so nothing stored through a textarea can execute on render.
		Assert::same('Plain intro', $content['intro']);
		Assert::same('evil()Real answer', $content['items'][0]['answer']);
		Assert::notContains('<', $content['intro'] . $content['items'][0]['answer']);
	},

	'normalize: link and image values cannot break out of an attribute' => static function (): void {
		$settings = Blocks::normalizeSettings('image', [
			'href' => 'x.jpg" onerror="alert(1)',
			'image' => "catalog/a'b\\c.jpg",
		]);

		Assert::notContains('"', $settings['href']);
		Assert::notContains("'", $settings['image']);
		Assert::notContains('<', $settings['href']);

		// Entity-encoded and raw-tab scheme tricks a browser would decode in
		// the attribute are refused by the prefix whitelist, not by name.
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'jav&#x09;ascript:alert(1)'])['href']);
		Assert::same('', Blocks::normalizeSettings('image', ['href' => "jav\tascript:alert(1)"])['href']);
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'data:text/html,x'])['href']);

		// Relative paths, queries and anchors always stay.
		Assert::same('index.php?route=product/search&sort=p.price', Blocks::normalizeSettings('image', ['href' => 'index.php?route=product/search&sort=p.price'])['href']);
		Assert::same('offer#faq', Blocks::normalizeSettings('image', ['href' => '/offer#faq'])['href']);
	},

	'hydrate: decodes settings and per-language content once' => static function (): void {
		$rows = [
			['block_id' => '7', 'parent_id' => '0', 'slot' => '1', 'code' => 'hero', 'sort_order' => '3', 'status' => '1', 'settings' => '{"height":"full"}'],
			['block_id' => '9', 'parent_id' => '7', 'slot' => '1', 'code' => 'text', 'sort_order' => '0', 'status' => '0', 'settings' => 'not json'],
		];

		$descriptions = [
			['block_id' => '7', 'language_id' => '1', 'content' => '{"heading":"Hi"}'],
			['block_id' => '9', 'language_id' => '1', 'content' => '{"body":"<p>Bye</p>"}'],
			['block_id' => '9', 'language_id' => '3', 'content' => 'broken'],
		];

		$blocks = Blocks::hydrate($rows, $descriptions);

		Assert::count(2, $blocks);
		Assert::same(7, $blocks[0]['block_id']);
		Assert::same('hero', $blocks[0]['code']);
		Assert::same(['height' => 'full'], $blocks[0]['settings']);
		// language ids become ints, broken JSON becomes an empty array
		Assert::same(['heading' => 'Hi'], $blocks[0]['content'][1]);
		Assert::same([], $blocks[1]['settings']);
		Assert::same(['body' => '<p>Bye</p>'], $blocks[1]['content'][1]);
		Assert::same([], $blocks[1]['content'][3]);
	},

	'normalize: links reject script-capable schemes' => static function (): void {
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'javascript:alert(1)'])['href']);
		Assert::same('', Blocks::normalizeSettings('image', ['href' => 'data:text/html;base64,xx'])['href']);
		Assert::same('https://example.com/a', Blocks::normalizeSettings('image', ['href' => 'https://example.com/a'])['href']);
		Assert::same('black-friday', Blocks::normalizeSettings('image', ['href' => '/black-friday'])['href']);
	},

	'normalize: default select values do not keep new repeater rows alive' => static function (): void {
		$quotes = Blocks::normalizeContent('testimonials', [
			'items' => [
				['quote' => '', 'author' => '', 'role' => '', 'rating' => '0'],
				['quote' => '', 'author' => '', 'role' => '', 'rating' => '4'],
				['quote' => 'Great service', 'author' => 'Ann', 'role' => '', 'rating' => '5'],
			],
		]);

		Assert::count(1, $quotes['items'], 'a select alone must not keep an empty quote alive');
		Assert::same('5', $quotes['items'][0]['rating']);

		$plans = Blocks::normalizeContent('pricing', [
			'items' => [
				['plan' => '', 'price' => '', 'description' => '', 'features' => '', 'button_label' => '', 'button_href' => '', 'featured' => 'no'],
				['plan' => 'Pro', 'price' => '9 €', 'description' => '', 'features' => "One\nTwo", 'button_label' => '', 'button_href' => '', 'featured' => 'yes'],
			],
		]);

		Assert::count(1, $plans['items'], 'the default featured flag must not keep an empty plan alive');
		Assert::same("One\nTwo", $plans['items'][0]['features'], 'textarea newlines survive normalisation');
		Assert::same('yes', $plans['items'][0]['featured']);
	},

	'editor: repeater rows support multiline subfields' => static function (): void {
		$builder = repo_file('upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains("} else if (sub.type === 'textarea') {", $builder);
		Assert::contains('rows="3" data-repeater-key="', $builder, 'quotes and plan features are textareas in the panel');
	},

	'templates: new editorial blocks escape copy and split feature lines' => static function (): void {
		$quotes = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/testimonials.twig');
		$stats = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/stats.twig');
		$pricing = repo_file('upload/catalog/view/theme/dockercart/template/landing/blocks/pricing.twig');

		foreach (['testimonials' => $quotes, 'stats' => $stats, 'pricing' => $pricing] as $code => $template) {
			Assert::contains('lp-native--' . $code, $template);
			Assert::contains('|escape', $template);
			Assert::notContains('|raw', $template, $code . ' must never render editor copy as HTML');
		}

		Assert::contains('lp-native__star--on', $quotes);
		Assert::contains('data-columns', $stats);
		Assert::contains('|split("\n")', $pricing, 'pricing features are one bullet per line');
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

	'wrapper: padding, background colour and background image compose' => static function (): void {
		$styles = Blocks::wrapperStyles('text', [
			'padding' => 'md',
			'bg_color' => '#aabbcc',
			'bg_image' => 'catalog/hero.jpg',
			'overlay' => 40,
		]);

		Assert::same('2.5rem', $styles['padding-top']);
		Assert::same('#aabbcc', $styles['background-color']);
		Assert::same("url('image/catalog/hero.jpg')", $styles['background-image']);
		Assert::same('cover', $styles['background-size']);
		Assert::true(Blocks::hasWrapperBackground(['bg_color' => '#aabbcc']));
		Assert::true(Blocks::hasWrapperBackground(['bg_image' => 'catalog/hero.jpg']));
		Assert::false(Blocks::hasWrapperBackground(['overlay' => 40]));
		Assert::same(40, Blocks::overlay(['bg_image' => 'catalog/hero.jpg', 'overlay' => 40]));
		Assert::same(40, Blocks::overlay(['bg_image_mobile' => 'catalog/mobile.jpg', 'overlay' => 40]), 'a mobile image has an overlay too');
		Assert::same(0, Blocks::overlay(['bg_color' => '#aabbcc', 'overlay' => 40]), 'colour-only backgrounds have no overlay');
		Assert::same(0, Blocks::overlay(['bg_type' => 'image', 'overlay' => 40]), 'the old type flag alone paints nothing');

		$attribute = Blocks::wrapperStyleAttribute('text', ['padding' => 'none']);
		Assert::same('', $attribute, 'padding none emits nothing');
	},

	'site design: global tokens are sanitised and expose CSS variables' => static function (): void {
		$defaults = \Dockercart\Landing\Design::defaults();

		Assert::same('#2563eb', $defaults['accent_color']);
		Assert::same('normal', $defaults['content_width']);
		Assert::same('0px', \Dockercart\Landing\Design::cssVariables(['radius' => 'none'])['--lp-radius']);
		Assert::same('4.75rem', \Dockercart\Landing\Design::cssVariables(['padding_y' => 'lg'])['--lp-pad-y']);
		Assert::same('1240px', \Dockercart\Landing\Design::cssVariables(['content_width' => 'wide'])['--lp-content-w']);

		$design = \Dockercart\Landing\Design::normalize([
			'accent_color' => '#FF0000',
			'surface_color' => 'javascript:alert(1)',
			'radius' => 'bogus',
		]);

		Assert::same('#ff0000', $design['accent_color']);
		Assert::same('#f6f8fc', $design['surface_color'], 'invalid colour falls back');
		Assert::same('md', $design['radius'], 'unknown preset falls back');
		Assert::contains('--lp-accent:#ff0000', \Dockercart\Landing\Design::styleAttribute($design));
	},

	'wrapper: responsive visibility classes' => static function (): void {
		Assert::same('', Blocks::visibilityClass([]));
		Assert::same('hidden', Blocks::visibilityClass(['hide_mobile' => 1, 'hide_tablet' => 1, 'hide_desktop' => 1]));
		Assert::same('lg:hidden', Blocks::visibilityClass(['hide_desktop' => 1]));
		Assert::same('hidden md:block', Blocks::visibilityClass(['hide_mobile' => 1]));
		Assert::same('md:hidden', Blocks::visibilityClass(['hide_tablet' => 1, 'hide_desktop' => 1]));
		Assert::same('md:hidden lg:block', Blocks::visibilityClass(['hide_tablet' => 1]));

		// The old if-chain fell through for this combination and rendered the
		// block tablet-only instead of desktop-only.
		Assert::same('hidden md:hidden lg:block', Blocks::visibilityClass(['hide_mobile' => 1, 'hide_tablet' => 1]));
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
