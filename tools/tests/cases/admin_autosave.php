<?php
/**
 * Blog post autosave — switch, template anchors and panel wiring.
 *
 * Autosave has no server-side branch in the post controller: the browser re-posts
 * the post form to its own action once a minute. That makes three things
 * load-bearing and worth a regression test:
 *
 *   - Dockercart\Admin\Autosave (default-on switch, fixed cadence, setting key);
 *   - the blog post form is the *only* form that opts in, and it opts in from the
 *     template only when the switch is on (add-forms must never autosave);
 *   - the switch lives in the blog settings form and the labels reach the form,
 *     while the global Settings page and the shared header stay untouched.
 *
 * The JS module's guarantees (edit-mode gate, hash-based change detection, no
 * file re-upload, redirect-based success, visible status label) are asserted from
 * the source as well: they are the contract the controller code relies on.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Admin\Autosave;

const AUTOSAVE_LOCALES = ['en-gb', 'ru-ua', 'uk-ua'];

/** The one template allowed to opt into autosave. */
const AUTOSAVE_TEMPLATE = 'extension/module/dockercart_blog_post_form.twig';

/**
 * @return array<int, string> relative paths of every admin template
 */
function autosave_templates(): array {
	$found = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator(REPO_ROOT . '/upload/admin/view/template/', FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getExtension() === 'twig') {
			$found[] = str_replace(
				[REPO_ROOT . '/upload/admin/view/template/', '\\'],
				['', '/'],
				$file->getPathname()
			);
		}
	}

	sort($found);

	return $found;
}

/**
 * @return array<string, string>
 */
function autosave_language(string $locale, string $file): array {
	return read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/' . $file);
}

return [
	'switch: autosave defaults to ON when the setting was never saved' => static function (): void {
		$config = new Config();

		Assert::true(Autosave::enabled($config), 'a missing blog autosave row must mean enabled');
	},

	'switch: the stored blog setting wins' => static function (): void {
		$config = new Config();
		$config->set(Autosave::SETTING_KEY, '1');

		Assert::true(Autosave::enabled($config));

		$config->set(Autosave::SETTING_KEY, '0');

		Assert::false(Autosave::enabled($config), 'switching it off must disable autosave');

		$config->set(Autosave::SETTING_KEY, 0);

		Assert::false(Autosave::enabled($config), 'integer 0 must disable autosave');
	},

	'switch: cadence and key are the documented ones' => static function (): void {
		Assert::same(60, Autosave::INTERVAL);
		Assert::same('module_dockercart_blog_post_autosave', Autosave::SETTING_KEY);
		Assert::same('data-autosave', Autosave::FORM_ATTRIBUTE);
	},

	'templates: the blog post form is the only form that opts in' => static function (): void {
		$marked = [];

		foreach (autosave_templates() as $relative) {
			if (strpos(repo_file('upload/admin/view/template/' . $relative), 'data-autosave="1"') !== false) {
				$marked[] = $relative;
			}
		}

		Assert::same([AUTOSAVE_TEMPLATE], $marked, 'only the blog post form may autosave');
	},

	'templates: the marker and its options are rendered only when enabled' => static function (): void {
		$template = repo_file('upload/admin/view/template/' . AUTOSAVE_TEMPLATE);

		Assert::contains('{% if autosave_enabled %} data-autosave="1"', $template, 'the form must opt in conditionally');
		Assert::contains('data-autosave-interval="{{ autosave_interval }}"', $template);
		Assert::contains('data-autosave-saving=', $template);
		Assert::contains('data-autosave-saved=', $template);
		Assert::contains('data-autosave-failed=', $template);
		Assert::contains('data-autosave-pending=', $template);
	},

	'controller: the post form wires the switch, the cadence and the labels' => static function (): void {
		$controller = repo_file('upload/admin/controller/extension/module/dockercart_blog_post.php');

		Assert::contains('Dockercart\\Admin\\Autosave::enabled($this->config)', $controller);
		Assert::contains('Dockercart\\Admin\\Autosave::INTERVAL', $controller);
		Assert::contains("addScript('view/javascript/dockercart_autosave.js')", $controller);
		Assert::contains('text_autosave_pending', $controller);
		Assert::contains('text_autosave_saved', $controller);
	},

	'settings: the switch lives in the blog settings form' => static function (): void {
		$template = repo_file('upload/admin/view/template/extension/module/dockercart_blog.twig');

		Assert::contains('name="module_dockercart_blog_post_autosave" value="0"', $template, 'hidden 0 keeps an unchecked state savable');
		Assert::contains('name="module_dockercart_blog_post_autosave" value="1"', $template);
		Assert::contains('{% if module_dockercart_blog_post_autosave %}', $template);
		Assert::contains('{{ help_post_autosave }}', $template, 'help text must use a Bootstrap tooltip');

		$controller = repo_file('upload/admin/controller/extension/module/dockercart_blog.php');

		Assert::contains("'module_dockercart_blog_post_autosave'", $controller, 'the key must be read into the form data');
		Assert::contains("\$data['module_dockercart_blog_post_autosave'] = 1;", $controller, 'default must be enabled');
	},

	'revert: the global Settings page and the shared header are clean' => static function (): void {
		Assert::notContains('config_admin_autosave', repo_file('upload/admin/controller/setting/setting.php'));
		Assert::notContains('config_admin_autosave', repo_file('upload/admin/view/template/setting/setting.twig'));
		Assert::notContains('autosave', repo_file('upload/admin/controller/common/header.php'));
		Assert::notContains('autosave', repo_file('upload/admin/view/template/common/header.twig'));

		foreach (AUTOSAVE_LOCALES as $locale) {
			Assert::notContains('text_autosave', implode('', autosave_language($locale, 'common/header.php')));
			Assert::notContains('admin_autosave', implode('', autosave_language($locale, 'setting/setting.php')));
		}
	},

	'locales: labels exist in en-gb, ru-ua and uk-ua' => static function (): void {
		$form_keys = ['text_autosave_pending', 'text_autosave_saving', 'text_autosave_saved', 'text_autosave_failed'];
		$settings_keys = ['entry_post_autosave', 'help_post_autosave'];

		foreach (AUTOSAVE_LOCALES as $locale) {
			$form = autosave_language($locale, 'extension/module/dockercart_blog_post.php');
			$settings = autosave_language($locale, 'extension/module/dockercart_blog.php');

			foreach ($form_keys as $key) {
				Assert::true(isset($form[$key]) && $form[$key] !== '', $locale . ' blog_post misses ' . $key);
			}

			Assert::contains('%s', (string)$form['text_autosave_saved'], $locale . ' saved label needs the timestamp slot');

			foreach ($settings_keys as $key) {
				Assert::true(isset($settings[$key]) && $settings[$key] !== '', $locale . ' blog settings miss ' . $key);
			}
		}
	},

	'module: keeps the guarantees the design relies on' => static function (): void {
		$js = repo_file('upload/admin/view/javascript/dockercart_autosave.js');

		Assert::contains("form.getAttribute('data-autosave-' + name)", $js, 'options must be read from the form');
		Assert::contains('data-autosave="1"', $js, 'only opted-in forms may be touched');
		Assert::contains('/\\/add$/.test(route)', $js, 'the module must skip /add routes');
		Assert::contains('/_id$/.test(key.value)', $js, 'the module must require a record id in the action');
		Assert::contains("redirect: 'manual'", $js, 'the save must not follow the redirect');
		Assert::contains("response.type === 'opaqueredirect'", $js, 'success is detected from the controller redirect');
		Assert::contains('firstError(html)', $js, 'validation errors must be surfaced');
		Assert::contains("typeof value !== 'string'", $js, 'file inputs must not be re-uploaded');
		Assert::contains('_dc_autosave', $js, 'the payload must be marked as an autosave');
		Assert::contains('window.tinymce.triggerSave()', $js, 'editor content must be synced before serializing');
		Assert::contains('document.hidden', $js, 'hidden tabs must not post');
		Assert::contains('payload.hash === state.baseline', $js, 'unchanged forms must not be posted');
		Assert::contains('payload.hash === state.lastSent', $js, 'already saved payloads must not be re-posted');

		// The operator must see the outcome: an inline label before the Save
		// button, with a timestamp, plus pending/failed states.
		Assert::contains('function createStatusNode(form)', $js, 'the status must be placed in the page header');
		Assert::contains("querySelector('button[form=\"' + form.id + '\"]", $js, 'the label is anchored to the Save button');
		Assert::contains("'dc-autosave-inline dc-autosave-inline--' + kind", $js, 'inline label states');
		Assert::contains("labels.saved.replace('%s', nowLabel())", $js, 'the saved label must carry a timestamp');
		Assert::contains("showStatus(state, state.labels.pending, 'pending')", $js, 'edits must show up before the tick');
		Assert::notContains("className = 'dc-autosave'", $js, 'no floating pill: the label sits in the header');

		$css = repo_file('upload/admin/view/stylesheet/dockercart_dcx_ui.css');

		Assert::contains('.dc-autosave-inline {', $css);
		Assert::contains('.dc-autosave-inline--saved', $css);
		Assert::contains('.dc-autosave-inline--error', $css);
		Assert::contains('.dc-autosave-inline--pending', $css);
		Assert::contains('@keyframes dc-autosave-pulse', $css);
	},
];
