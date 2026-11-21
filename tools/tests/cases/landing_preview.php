<?php
/**
 * Unsaved live preview (preview_block): typing in the properties panel must
 * update the canvas without writing anything.
 *
 * The preview is a read-only render of the in-memory block (same
 * Blocks::normalize*() pipeline and same renderers as a save), so these
 * static guards pin the contract from both sides:
 *
 *   1. the catalog renderer exposes the preview_block branch and builds the
 *      transient block through the normalizers (what you see is what a save
 *      would render);
 *   2. the preview path performs no writes (no INSERT/UPDATE/DELETE, no
 *      version bump, no incrementViewed);
 *   3. the admin shell debounces typing into a preview POST (no autosave)
 *      and the iframe swaps just the one node on lp:replace.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LANDING_PREVIEW_PAGE = REPO_ROOT . '/upload/catalog/controller/landing/page.php';
const LANDING_PREVIEW_ADMIN_JS = REPO_ROOT . '/upload/admin/view/javascript/dockercart_landing_builder.js';
const LANDING_PREVIEW_IFRAME_JS = REPO_ROOT . '/upload/catalog/view/javascript/dockercart_landing_builder.js';

/**
 * The previewBlock() method body (the only place preview_block may act).
 */
function landing_preview_method(): string {
	$src = (string)file_get_contents(LANDING_PREVIEW_PAGE);
	$start = strpos($src, 'private function previewBlock(');

	if ($start === false) {
		return '';
	}

	$depth = 0;
	$length = strlen($src);

	for ($i = $start; $i < $length; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($src, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

return [
	'preview: the external tab is a clean page with a way back' => static function (): void {
		$admin = (string)file_get_contents(REPO_ROOT . '/upload/admin/controller/design/landing.php');
		$page = (string)file_get_contents(REPO_ROOT . '/upload/catalog/controller/landing/page.php');
		$twig = (string)file_get_contents(REPO_ROOT . '/upload/catalog/view/theme/dockercart/template/landing/page.twig');
		$js = (string)file_get_contents(REPO_ROOT . '/upload/admin/view/javascript/dockercart_landing_builder.js');

		Assert::contains('preview_public_bases', $admin, 'the Preview link must not carry lp_builder');
		Assert::contains('builder_back', $admin, 'the back link is sent encrypted');
		Assert::contains('publicPreviewBaseFor(', $js);
		Assert::contains("link.href = publicPreviewBaseFor(ui.lang) + '&_=' + Date.now()", $js);
		Assert::contains('$preview && !$builder', $page, 'the notice is for the external tab only');
		Assert::contains('isBuilderBackUrl(', $page, 'the decrypted link is validated');
		Assert::contains("bar.id = 'lp-preview-bar'", $twig);
		Assert::contains('text_back_to_builder', $twig);
		Assert::contains('position: fixed', $twig, 'the notice stays on top while scrolling');
		Assert::contains('body.lp-has-preview #navbar', $twig, 'the fixed shop header is pushed below the notice');
		Assert::contains('body.lp-has-preview .dc-header-spacer', $twig, 'the page content keeps its offset');
		Assert::contains('document.body.insertBefore(bar, document.body.firstChild)', $twig, 'the notice is injected inside body, before the header content');
		Assert::contains("document.body.classList.add('lp-has-preview')", $twig);

		foreach (['en-gb', 'ru-ua', 'uk-ua'] as $locale) {
			$lang = (string)file_get_contents(REPO_ROOT . '/upload/catalog/language/' . $locale . '/landing/landing.php');

			Assert::contains("\$_['text_preview_mode']", $lang, $locale);
			Assert::contains("\$_['text_back_to_builder']", $lang, $locale);
		}
	},

	'preview: the builder-only branch renders from the POSTed payload' => static function (): void {
		$src = (string)file_get_contents(LANDING_PREVIEW_PAGE);

		Assert::contains("isset(\$this->request->get['preview_block'])", $src, 'the preview branch is wired next to only_block');
		Assert::contains('private function previewBlock(', $src);
		Assert::contains('normalizeSettings', landing_preview_method(), 'settings pass through the normalizer');
		Assert::contains('normalizeContent', landing_preview_method(), 'content passes through the normalizer');
		Assert::contains('renderBlockInner', landing_preview_method(), 'widgets preview as inner markup');
		Assert::contains('renderBlock', landing_preview_method(), 'top-level blocks and sections preview with their shell');
	},

	'preview: the render path performs no writes' => static function (): void {
		$method = landing_preview_method();

		Assert::false($method === '', 'previewBlock() must exist');
		Assert::notContains('->query("INSERT', $method, 'no inserts on preview');
		Assert::notContains('->query("UPDATE', $method, 'no updates on preview');
		Assert::notContains('->query("DELETE', $method, 'no deletes on preview');
		Assert::notContains('incrementViewed', $method, 'no view counter on preview');
		Assert::notContains('version', $method, 'no version bump on preview');
	},

	'preview: typing debounces into a preview POST, not a save' => static function (): void {
		$js = (string)file_get_contents(LANDING_PREVIEW_ADMIN_JS);

		Assert::contains('schedulePreview', $js, 'property edits schedule a preview');
		Assert::contains('preview_block=1', $js, 'the preview posts to the preview branch');
		Assert::contains('lp:replace', $js, 'the preview swaps one node');
		Assert::notContains('scheduleSave', $js, 'no autosave scheduler may remain');
	},

	'preview: the iframe swaps one node on lp:replace' => static function (): void {
		$js = (string)file_get_contents(LANDING_PREVIEW_IFRAME_JS);

		Assert::contains('function replaceNode', $js);
		Assert::contains("data.type === 'lp:replace'", $js);
	},
];
