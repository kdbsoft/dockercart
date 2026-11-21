<?php
/**
 * Layout Builder — data safety and interaction contract.
 *
 * The builder replaces the classic layout form, so three things are load-bearing:
 *
 *   - the canvas only draws the positions of the layout's first route, but a
 *     save rewrites every layout_module row: modules in other positions (or in
 *     positions unknown to the route map) must round-trip untouched;
 *   - every edit (including drag&drop) marks the page dirty and navigation is
 *     guarded, because Back used to silently discard work;
 *   - modules can be added without dragging, and the responsive preview must
 *     reflow/collapse zones instead of squeezing the desktop row into 375px.
 *
 * Tooltips and the translated labels are checked too: this is a standalone
 * template, it does not inherit the admin panel's common.js initialisation.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LB_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

/**
 * @return array<string, string>
 */
function layout_builder_language(string $locale): array {
	return read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/design/layout.php');
}

return [
	'ui: selects use the shared dockercart chevron' => static function (): void {
		$css = repo_file('upload/admin/view/stylesheet/dockercart_layout_builder.css');

		Assert::contains('width: 100%; height: 32px;', $css, 'inputs and selects share one height');
		Assert::contains('select.lb-input', $css);
		Assert::contains('var(--dc-select-chevron)', $css);
		Assert::contains('--dc-select-chevron:', repo_file('upload/admin/view/stylesheet/dockercart_tokens.css'));
	},

	'hidden: positions outside the canvas are rendered as untouched inputs' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');

		Assert::contains('{% if hidden_modules %}', $tpl);
		Assert::contains('name="hidden_module[{{ loop.index0 }}][code]"', $tpl);
		Assert::contains('name="hidden_module[{{ loop.index0 }}][position]"', $tpl);
		Assert::contains('name="hidden_module[{{ loop.index0 }}][sort_order]"', $tpl);
	},

	'hidden: the browser posts those inputs back on save' => static function (): void {
		$js = repo_file('upload/admin/view/javascript/dockercart_layout_builder.js');

		Assert::contains("querySelectorAll('#hidden-modules input[name]')", $js);
		Assert::contains('body.append(input.name, input.value)', $js);
	},

	'hidden: the controller merges them back instead of deleting them' => static function (): void {
		$php = repo_file('upload/admin/controller/design/layout.php');

		Assert::contains("!empty(\$this->request->post['hidden_module'])", $php);
		Assert::contains("\$this->request->post['layout_module'][] = array(", $php);
		Assert::contains("unset(\$this->request->post['hidden_module']);", $php);
		Assert::notContains("\$data['modules_by_position']['content_top'][] = \$module_entry;", $php, 'unknown positions must not be silently remapped');
	},

	'hidden: the canvas explains which modules are not drawn' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');

		Assert::contains('{{ hidden_modules_notice }}', $tpl);
		Assert::contains("sprintf(\$this->language->get('text_hidden_modules')", repo_file('upload/admin/controller/design/layout.php'));

		foreach (LB_LOCALES as $locale) {
			Assert::contains('%s', layout_builder_language($locale)['text_hidden_modules'] ?? '', $locale . ' notice needs the position placeholder');
		}
	},

	'dirty: every edit path marks the page dirty and guards navigation' => static function (): void {
		$js = repo_file('upload/admin/view/javascript/dockercart_layout_builder.js');

		Assert::contains("window.addEventListener('beforeunload'", $js);
		Assert::contains('config.texts.unsavedConfirm', $js);
		Assert::contains('markDirty();', $js);
		Assert::contains('markClean();', $js);
		Assert::contains("target.matches('.layout-name-row input, #routes-list input, #routes-list select, #prop-position, #prop-sort-order')", $js);

		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');
		Assert::contains('id="save-state"', $tpl);
		Assert::contains('aria-live="polite"', $tpl);
	},

	'dirty: a second save while one is in flight is ignored' => static function (): void {
		$js = repo_file('upload/admin/view/javascript/dockercart_layout_builder.js');

		Assert::contains('if (saving) return;', $js);
		Assert::contains('saving = false;', $js);
	},

	'add: palette cards are keyboard accessible and add to the chosen zone' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');
		$js = repo_file('upload/admin/view/javascript/dockercart_layout_builder.js');

		Assert::contains('role="button" tabindex="0"', $tpl);
		Assert::contains('function initPaletteAdd()', $js);
		Assert::contains('function addModuleFromPalette(card)', $js);
		Assert::contains("e.key !== 'Enter' && e.key !== ' '", $js);
		Assert::contains('pendingZone', $js);
		Assert::contains('zone-targeted', $js);
	},

	'preview: hidden zones collapse and side columns stack on mobile' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');
		$css = repo_file('upload/admin/view/stylesheet/dockercart_layout_builder.css');

		Assert::contains('canvas-zone-row', $tpl);
		Assert::contains('zone-hidden-note', $tpl);
		Assert::contains('flex-direction: column', $css);
		Assert::contains('display: none !important', $css);
		Assert::notContains('zone-hidden-badge', $tpl . $css, 'the squeezed badge must not wrap in the narrow canvas');
	},

	'assets: builder styles live in a cache-busted stylesheet' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');

		Assert::contains('dockercart_layout_builder.css?v={{ builder_css_version }}', $tpl);
		Assert::notContains('<style>', $tpl);
		Assert::contains("\$data['builder_css_version']", repo_file('upload/admin/controller/design/layout.php'));
		Assert::contains('html { font-size: 16px; }', repo_file('upload/admin/view/stylesheet/dockercart_layout_builder.css'));
	},

	'tooltips: the standalone page has none (labels use aria instead)' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');
		$js = repo_file('upload/admin/view/javascript/dockercart_layout_builder.js');

		Assert::notContains('data-toggle="tooltip"', $tpl);
		Assert::notContains('title=', $tpl);
		Assert::notContains('jquery-3.7.1.min.js', $tpl);
		Assert::notContains('bootstrap.min.js', $tpl);
		Assert::notContains('tooltip', strtolower($js));
		Assert::contains('aria-label="{{ text_edit_module }}"', $tpl);
		Assert::contains('aria-label="{{ text_remove_module }}"', $tpl);
		Assert::contains("setAttribute('aria-label', config.texts.editModule)", $js);
		Assert::contains("setAttribute('aria-label', config.texts.removeModule)", $js);
	},

	'header: no tooltips, bold Back caption and compact Save label' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');
		$start = strpos($tpl, '<header');
		$end = strpos($tpl, '</header>');
		$header = substr($tpl, $start, $end - $start);

		Assert::notContains('data-toggle="tooltip"', $header);
		Assert::notContains('title=', $header);
		Assert::same(0, substr_count($header, 'data-placement='));
		Assert::contains('<span class="font-semibold whitespace-nowrap">{{ text_back }}</span>', $header);
		Assert::contains('no-underline hover:no-underline focus:no-underline', $header);
		Assert::contains('aria-label="{{ text_back_to_layouts }}"', $header);
		Assert::same(3, substr_count($header, 'aria-label="{{ text_view_'), 'three viewport buttons');
		Assert::contains('{{ button_builder_save }}</span>', $header);

		$expected = ['en-gb' => 'Save', 'ru-ua' => 'Сохранить', 'uk-ua' => 'Зберегти'];
		foreach (LB_LOCALES as $locale) {
			Assert::same($expected[$locale], layout_builder_language($locale)['button_builder_save'] ?? '', $locale . ' save label');
		}
	},

	'labels: module properties use a module-specific, translated label' => static function (): void {
		$tpl = repo_file('upload/admin/view/template/design/layout_builder.twig');

		Assert::contains('{{ text_module_name }}</label>', $tpl);

		foreach (LB_LOCALES as $locale) {
			Assert::same(
				$locale === 'en-gb' ? 'Module Name' : ($locale === 'ru-ua' ? 'Название модуля' : 'Назва модуля'),
				layout_builder_language($locale)['text_module_name'] ?? '',
				$locale . ' module-name label'
			);
		}
	},
];
