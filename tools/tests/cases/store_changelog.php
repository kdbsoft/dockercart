<?php
/**
 * Add-ons Store changelogs — rich text rendering and version listing.
 *
 * "What's new in version X" used to be escaped, so a vendor changelog written in
 * HTML showed the markup as literal text. It is now rendered as sanitized HTML
 * (`DockercartExtensionStore::sanitizeHtml()` — store allowlist + event handler
 * and javascript: scrubbing) and the block sits below the description with its
 * own top offset.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!defined('DIR_STORAGE')) {
	define('DIR_STORAGE', sys_get_temp_dir() . '/dockercart-store-tests/');
}

if (!class_exists('DockercartExtensionStore')) {
	require_once DIR_SYSTEM . 'library/dockercart/extension_store.php';
}

function changelog_store(): DockercartExtensionStore {
	$registry = new Registry();
	$registry->set('db', new FakeDb());

	return new DockercartExtensionStore($registry);
}

/**
 * @return array<int, array<string, string>>
 */
function changelog_versions(): array {
	return [
		['version' => '1.0.0', 'changelog' => '<p>Old release notes</p>'],
		['version' => '1.1.0', 'changelog' => '<p>Added <strong>new block</strong></p><ul><li>one</li></ul>'],
		['version' => '1.2.0', 'changelog' => '<ol><li>first</li></ol>'],
	];
}

return [
	'html: formatting tags survive the sanitizer' => static function (): void {
		$html = DockercartExtensionStore::sanitizeHtml('<p>Added <strong>new</strong> block</p><ul><li>one</li></ul>');

		Assert::contains('<strong>new</strong>', $html);
		Assert::contains('<ul><li>one</li></ul>', $html);
		Assert::notContains('&lt;strong&gt;', $html, 'markup must not be entity-escaped');
	},

	'html: scripts, event handlers and javascript: URLs are removed' => static function (): void {
		$html = DockercartExtensionStore::sanitizeHtml(
			'<script>alert(1)</script><p onclick="evil()">ok</p><a href="javascript:evil()">x</a><img src=x onerror=evil()>'
		);

		Assert::notContains('<script', $html);
		Assert::notContains('alert(1)', $html, 'script contents must not leak as visible text');
		Assert::notContains('onclick', $html);
		Assert::notContains('onerror', $html);
		Assert::notContains('javascript:', $html);
		Assert::contains('ok', $html);
		Assert::contains('href="#"', $html, 'the dangerous URL is neutralized, the link kept');
	},

	'changelog: only versions newer than the installed one are listed' => static function (): void {
		$html = changelog_store()->getChangelogHtml(changelog_versions(), '1.0.0');

		Assert::notContains('v1.0.0', $html, 'the installed version is not "what\'s new"');
		Assert::contains('v1.1.0', $html);
		Assert::contains('v1.2.0', $html);
	},

	'changelog: the installed version itself is skipped as a boundary' => static function (): void {
		$html = changelog_store()->getChangelogHtml(changelog_versions(), '1.1.0');

		Assert::notContains('v1.0.0', $html);
		Assert::notContains('v1.1.0', $html);
		Assert::contains('v1.2.0', $html);
	},

	'changelog: the body is rendered as HTML, not as escaped text' => static function (): void {
		$html = changelog_store()->getChangelogHtml(changelog_versions(), '1.0.0');

		Assert::contains('<div class="store-changelog-content"><p>Added <strong>new block</strong></p><ul><li>one</li></ul></div>', $html);
		Assert::notContains('&lt;strong&gt;', $html);
		Assert::contains('<ol><li>first</li></ol>', $html, 'ordered lists are part of the allowlist');
	},

	'changelog: an empty changelog produces no version block' => static function (): void {
		$html = changelog_store()->getChangelogHtml([
			['version' => '1.0.0', 'changelog' => ''],
			['version' => '1.1.0', 'changelog' => ''],
		], '1.0.0');

		Assert::same('', $html);
	},

	'template: update changelog is injected as HTML and offset from the description' => static function (): void {
		$template = repo_file('upload/admin/view/template/extension/store.twig');

		Assert::contains('$modalUpdateChangelog.html(updateHtml).show();', $template, 'the block must accept markup');
		Assert::contains("text_whats_new|escape('js')", $template);

		$css = repo_file('upload/admin/view/stylesheet/dockercart_store.css');

		Assert::contains(".store-modal-update-changelog {\n\tmargin-top: 16px;", $css, 'the block needs a top offset');
		Assert::contains('.store-changelog-version {', $css);
	},

	'controller: rich text from the catalog goes through the shared sanitizer' => static function (): void {
		$controller = repo_file('upload/admin/controller/extension/store.php');

		Assert::contains('DockercartExtensionStore::sanitizeHtml((string) $offer->changelog)', $controller);
		Assert::contains('DockercartExtensionStore::sanitizeHtml((string) $offer->description)', $controller);
		Assert::notContains("strip_tags((string) \$offer->description", $controller, 'the allowlist lives in the library');
	},
];
