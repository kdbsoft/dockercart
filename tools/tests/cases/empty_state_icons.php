<?php
/**
 * Empty-state icons: every `icon:` used by `common/empty_state.twig` must exist.
 *
 * `empty_state.twig` does `{% include 'common/empty_icons/' ~ icon ~ '.twig' %}`,
 * so a missing icon file is a Twig LoaderError at render time. Admin templates
 * are rendered after the entry point has already started streaming, and the
 * template library turns the exception into `trigger_error() + exit()`, so the
 * operator sees a blank page instead of an error - and only when the list is
 * empty (demo/dev databases, a fresh install, a filtered-out result set).
 *
 * This case makes that failure a build failure instead of a production one.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const EMPTY_ICONS_DIR = 'upload/admin/view/template/common/empty_icons/';

const EMPTY_STATE_INCLUDE = 'common/empty_state.twig';

/**
 * Admin templates that include the empty state.
 *
 * @return array<int, string> repository-relative paths
 */
function empty_state_consumer_templates(): array {
	$root = REPO_ROOT . '/upload';
	$found = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'twig') {
			continue;
		}

		$source = (string)file_get_contents($file->getPathname());

		if (strpos($source, EMPTY_STATE_INCLUDE) === false) {
			continue;
		}

		$found[] = substr($file->getPathname(), strlen(REPO_ROOT) + 1);
	}

	sort($found);

	return $found;
}

/**
 * Literal `icon: '…'` values passed to the empty state together with their file.
 *
 * @return array<string, string> icon name => template that requests it
 */
function empty_state_icon_usage(): array {
	$usage = [];

	foreach (empty_state_consumer_templates() as $template) {
		$source = repo_file($template);

		if (!preg_match_all('/\{%-?\s*include\s+\'' . preg_quote(EMPTY_STATE_INCLUDE, '/') . '\'(.*?)-?%\}/s', $source, $matches)) {
			continue;
		}

		foreach ($matches[1] as $arguments) {
			if (!preg_match("/icon\s*:\s*'([^']+)'/", $arguments, $icon)) {
				// Dynamic icon names cannot be verified here, so forbid them:
				// the fallback also documents that `default` must exist.
				Assert::false(
					preg_match('/icon\s*:/', $arguments) === 1,
					$template . ': the empty-state icon must be a quoted literal so it can be verified'
				);

				$usage['default'] = $template;

				continue;
			}

			$usage[$icon[1]] = $template;
		}
	}

	return $usage;
}

return [
	'empty state: consumer templates are found' => static function (): void {
		Assert::true(empty_state_consumer_templates() !== [], 'no template includes ' . EMPTY_STATE_INCLUDE);
	},

	'empty state: every requested icon file exists' => static function (): void {
		$missing = [];

		foreach (empty_state_icon_usage() as $icon => $template) {
			if (!is_file(REPO_ROOT . '/' . EMPTY_ICONS_DIR . $icon . '.twig')) {
				$missing[] = $icon . ' (used by ' . $template . ')';
			}
		}

		Assert::same([], $missing, 'missing empty-state icons: ' . implode(', ', $missing));
	},

	'empty state: default fallback icon exists' => static function (): void {
		Assert::true(
			is_file(REPO_ROOT . '/' . EMPTY_ICONS_DIR . 'default.twig'),
			'empty_state.twig falls back to icon "default", so ' . EMPTY_ICONS_DIR . 'default.twig is required'
		);
	},

	'empty state: icon include is the only place icons are resolved' => static function (): void {
		Assert::contains(
			"{% include 'common/empty_icons/' ~ icon ~ '.twig' %}",
			repo_file('upload/admin/view/template/' . EMPTY_STATE_INCLUDE),
			'empty_state.twig must keep resolving icons through the icon variable'
		);
	},

	'empty state: icons are self-contained svg documents' => static function (): void {
		$files = glob(REPO_ROOT . '/' . EMPTY_ICONS_DIR . '*.twig') ?: [];

		Assert::true($files !== [], 'no empty-state icons found');

		foreach ($files as $path) {
			$name = basename($path, '.twig');
			$source = (string)file_get_contents($path);

			Assert::true(
				strpos(ltrim($source), '<svg') === 0,
				$name . '.twig must start with <svg'
			);
			Assert::true(
				substr(rtrim($source), -6) === '</svg>',
				$name . '.twig must end with </svg>'
			);
		}
	},

	'empty state: gradient ids are unique across icons' => static function (): void {
		// Several empty states can share one page (standalone + table variants),
		// so a duplicated id would make one icon pick up another icon's gradient.
		$seen = [];
		$duplicates = [];

		foreach (glob(REPO_ROOT . '/' . EMPTY_ICONS_DIR . '*.twig') ?: [] as $path) {
			$name = basename($path, '.twig');
			$source = (string)file_get_contents($path);

			if (!preg_match_all('/\bid="([^"]+)"/', $source, $matches)) {
				continue;
			}

			foreach ($matches[1] as $id) {
				if (isset($seen[$id])) {
					$duplicates[] = $id . ' (' . $seen[$id] . ', ' . $name . ')';

					continue;
				}

				$seen[$id] = $name;
			}
		}

		Assert::same([], $duplicates, 'duplicate svg ids: ' . implode(', ', $duplicates));
	},
];
