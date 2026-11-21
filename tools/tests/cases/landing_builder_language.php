<?php
/**
 * Landing builder language behaviour: the canvas follows the selected tab and
 * the top-bar name is per language for information-page landings.
 *
 * Static checks on the three surfaces that together make the language switch
 * work (they are easy to break by accident, and the failure is only visible in
 * a browser):
 *
 *   1. the storefront keyword->language tie-break prefers the session
 *      language (a shared keyword must not pin the default language);
 *   2. builder preview URLs are language-prefixed (explicit beats keyword
 *      inference), and the no-keyword fallback keeps the route form;
 *   3. enforceCleanSeoUrls leaves preview_token URLs alone (the fallback
 *      canvas would be canonicalised back to the default language);
 *   4. the admin shell syncs the top-bar name per language for owned
 *      landings and writes through catalog/information/updateNames;
 *   5. the locales carry the builder strings.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LANGUAGE_FILES = [
	'admin' => '/upload/admin/controller/design/landing.php',
	'startup' => '/upload/catalog/controller/startup/startup.php',
	'seo_url' => '/upload/catalog/controller/startup/seo_url.php',
	'admin_js' => '/upload/admin/view/javascript/dockercart_landing_builder.js',
	'twig' => '/upload/admin/view/template/design/landing_builder.twig',
];

/**
 * @return array<string, string>
 */
function builder_language_sources(): array {
	$sources = [];

	foreach (LANGUAGE_FILES as $key => $file) {
		$sources[$key] = (string)file_get_contents(REPO_ROOT . $file);
	}

	return $sources;
}

return [
	'storefront: a shared keyword prefers the session language' => static function (): void {
		$src = builder_language_sources()['startup'];

		Assert::contains('$session_language_id = 0;', $src);
		Assert::contains(
			'ORDER BY (language_id = \'" . $session_language_id . "\') DESC, (language_id = \'" . $default_language_id . "\') DESC',
			$src,
			'the keyword tie-break must rank the session language first'
		);
	},

	'builder: preview bases carry the language prefix' => static function (): void {
		$src = builder_language_sources()['admin'];

		Assert::contains(
			"HTTP_CATALOG . \$language['code'] . '/' . \$keyword",
			$src,
			'keyword preview bases must be language-prefixed (/uk-ua/about-us)'
		);
		Assert::contains('$fallback_base', $src, 'languages without a keyword keep the route form');
	},

	'seo: preview_token URLs are never canonicalised' => static function (): void {
		$src = builder_language_sources()['seo_url'];

		Assert::contains('function enforceCleanSeoUrls', $src);

		$block = substr($src, strpos($src, 'function enforceCleanSeoUrls'), 2200);

		Assert::contains('preview_token', $block, 'enforceCleanSeoUrls must skip builder preview URLs');
	},

	'builder: changed scripts are cache-busted (nginx serves immutable)' => static function (): void {
		$sources = builder_language_sources();

		Assert::contains(
			'dockercart_landing_builder.js?v={{ builder_js_version }}',
			$sources['twig'],
			'the admin shell must version the builder script by mtime'
		);
		Assert::contains(
			"filemtime(DIR_APPLICATION . 'view/javascript/dockercart_landing_builder.js')",
			$sources['admin'],
			'the version is the file mtime'
		);

		$catalog = (string)file_get_contents(REPO_ROOT . '/upload/catalog/controller/landing/page.php');

		Assert::contains("dockercart_landing_builder.js?ts='", $catalog, 'the iframe bridge is mtime-busted too');
		Assert::contains('DIR_APPLICATION', $catalog, 'the catalog app has no DIR_CATALOG constant');

		$header = (string)file_get_contents(REPO_ROOT . '/upload/catalog/view/theme/dockercart/template/common/header.twig');

		Assert::contains("'?' not in script", $header, 'the shared version append must not stack on a busted URL');
	},

	'builder: the page name is per language for owned landings' => static function (): void {
		$sources = builder_language_sources();

		Assert::contains('owner_titles', $sources['admin'], 'the controller passes the information titles');
		Assert::contains('updateNames', $sources['admin'], 'edits write through the information names endpoint');

		Assert::contains('ownerTitles', $sources['twig']);
		Assert::contains('canEditOwnerTitles', $sources['twig']);
		Assert::contains('ownerNames', $sources['twig']);

		$js = $sources['admin_js'];

		Assert::contains('function syncNameInput', $js);
		Assert::contains('markOwnerTitleDirty', $js, 'title edits wait for the explicit save, not a debounced autosave');
		Assert::contains('flushOwnerTitle', $js, 'the explicit save flushes the pending per-language title');
		Assert::contains('names[', $js, 'the save posts the per-language names map');
		Assert::contains('syncNameInput();', $js, 'the input follows the language tab');
	},
];
