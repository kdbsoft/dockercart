<?php
/**
 * Theme settings parity: every theme key the catalog reads must still exist.
 *
 * dc4e2360 removed `theme_*_image_thumb_*` from the dockercart theme in favour
 * of orientation-based display, but the product-page gift/BXGY block kept
 * reading it. On a clean install the config value is null, `Image::resize()`
 * declares `int $width, int $height`, and the whole product page dies with a
 * TypeError. `ModelToolImage::resize()` now normalises dimensions so a lost key
 * degrades instead of crashing, but the healthier rule is that the catalog must
 * only read theme settings the theme actually declares.
 *
 * Checked here (static scan, no DB, no GD):
 *  - upload/catalog/**.php never reads the removed `_image_thumb_*` keys;
 *  - every dynamic `theme_<config_theme>_<key>` read by the catalog is declared
 *    by upload/admin/controller/extension/theme/dockercart.php;
 *  - every hardcoded `theme_dockercart_*` key read by the catalog is declared
 *    there too (whitelisted backward-compatibility reads aside);
 *  - both ModelToolImage implementations cast dimensions to int before they
 *    can reach the typed `Image::resize()`.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const THEME_SETTINGS_CONTROLLER = 'upload/admin/controller/extension/theme/dockercart.php';

const THEME_SETTINGS_CATALOG_DIR = 'upload/catalog/';

/**
 * Keys the catalog may read although the theme form does not save them.
 *
 * @var array<string, true>
 */
const THEME_SETTINGS_READ_WHITELIST = [
	// common/header.php and checkout/dockercart_checkout.php read the pre-module
	// storage location of the master favicon as a backward-compatibility
	// fallback; an empty value degrades to config_icon.
	'theme_dockercart_favicon_master' => true,
];

/**
 * Repository-relative PHP files under upload/catalog/.
 *
 * @return array<int, string>
 */
function theme_settings_catalog_files(): array {
	$root = REPO_ROOT . '/' . THEME_SETTINGS_CATALOG_DIR;
	$found = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
			$found[] = substr($file->getPathname(), strlen(REPO_ROOT) + 1);
		}
	}

	sort($found);

	return $found;
}

/**
 * Full theme_dockercart_* keys named anywhere in the theme controller.
 *
 * @return array<string, true>
 */
function theme_settings_declared_keys(): array {
	$declared = [];

	preg_match_all('/\btheme_dockercart_[a-z0-9_]+\b/', repo_file(THEME_SETTINGS_CONTROLLER), $matches);

	foreach ($matches[0] as $key) {
		$declared[$key] = true;
	}

	return $declared;
}

/**
 * Dynamic `theme_<config_theme>_<key>` reads: file => list of full keys.
 *
 * @return array<string, array<int, string>>
 */
function theme_settings_dynamic_reads(): array {
	$reads = [];

	foreach (theme_settings_catalog_files() as $file) {
		$pattern = '/\'theme_\'\s*\.\s*\$this->config->get\(\'config_theme\'\)\s*\.\s*\'_([a-z0-9_]+)\'/';

		if (!preg_match_all($pattern, repo_file($file), $matches)) {
			continue;
		}

		$reads[$file] = array_map(static function (string $suffix): string {
			return 'theme_dockercart_' . $suffix;
		}, $matches[1]);
	}

	return $reads;
}

/**
 * Hardcoded quoted `theme_dockercart_*` reads: file => list of full keys.
 *
 * @return array<string, array<int, string>>
 */
function theme_settings_hardcoded_reads(): array {
	$reads = [];

	foreach (theme_settings_catalog_files() as $file) {
		if (!preg_match_all('/[\'"]theme_dockercart_[a-z0-9_]+[\'"]/', repo_file($file), $matches)) {
			continue;
		}

		$reads[$file] = array_map(static function (string $key): string {
			return trim($key, '\'"');
		}, $matches[0]);
	}

	return $reads;
}

return [
	'theme settings: catalog no longer reads the removed image_thumb sizes' => static function (): void {
		$offenders = [];

		foreach (theme_settings_catalog_files() as $file) {
			if (strpos(repo_file($file), '_image_thumb_') !== false) {
				$offenders[] = $file;
			}
		}

		Assert::same([], $offenders, 'theme_*_image_thumb_* was removed from every theme (dc4e2360, orientation-based display replaced it); use image_additional_*/image_product_* instead - found in: ' . implode(', ', $offenders));
	},

	'theme settings: every dynamic theme key read by the catalog is declared' => static function (): void {
		$declared = theme_settings_declared_keys();
		$undeclared = [];

		foreach (theme_settings_dynamic_reads() as $file => $keys) {
			foreach (array_unique($keys) as $key) {
				if (!isset($declared[$key])) {
					$undeclared[] = $key . ' (' . $file . ')';
				}
			}
		}

		sort($undeclared);

		Assert::same([], $undeclared, 'the catalog reads theme settings the dockercart theme no longer declares (removed from the theme form but still read by a controller): ' . implode(', ', $undeclared));
	},

	'theme settings: every hardcoded theme key read by the catalog is declared or whitelisted' => static function (): void {
		$declared = theme_settings_declared_keys();
		$undeclared = [];

		foreach (theme_settings_hardcoded_reads() as $file => $keys) {
			foreach (array_unique($keys) as $key) {
				if (isset(THEME_SETTINGS_READ_WHITELIST[$key]) || isset($declared[$key])) {
					continue;
				}

				$undeclared[] = $key . ' (' . $file . ')';
			}
		}

		sort($undeclared);

		Assert::same([], $undeclared, 'the catalog reads hardcoded theme settings the dockercart theme no longer declares: ' . implode(', ', $undeclared));
	},

	'theme settings: both ModelToolImage implementations sanitise dimensions' => static function (): void {
		$models = [
			'upload/catalog/model/tool/image.php',
			'upload/admin/model/tool/image.php',
		];

		foreach ($models as $model) {
			$source = repo_file($model);

			$width_cast = strpos($source, '$width = (int)$width;');
			$height_cast = strpos($source, '$height = (int)$height;');
			$delegate = strpos($source, '->resize($width, $height');

			Assert::true(
				$width_cast !== false && $height_cast !== false && $delegate !== false && $width_cast < $delegate && $height_cast < $delegate,
				$model . ' must cast $width/$height to int (with a 200x200 fallback) before delegating to the typed Image::resize()'
			);

			Assert::true(
				strpos($source, '$width = 200;') !== false && strpos($source, '$height = 200;') !== false,
				$model . ' must fall back to 200x200 when a theme does not define a size'
			);
		}
	},
];
