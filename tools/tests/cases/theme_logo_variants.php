<?php
/**
 * Named logo variants exposed by the common header and footer.
 *
 * `$data['logo']` is the legacy, theme-agnostic logo consumed by meta tags and
 * old templates: the header resolves light_setting -> config_logo -> '', the
 * footer stays on config_logo -> ''. Both controllers now also expose explicit
 * light/dark variants so themes can pick the right artwork for their surface.
 *
 * The dark variant must NEVER fall back to config_logo: that artwork is usually
 * dark and disappears on a dark header. An empty string is the correct fallback.
 *
 * Static scan only: no DB, no image files, no HTTP.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const LOGO_HEADER_CONTROLLER = 'upload/catalog/controller/common/header.php';
const LOGO_FOOTER_CONTROLLER = 'upload/catalog/controller/common/footer.php';

/** The header logo block must stay above this marker. */
const LOGO_VARIANTS_MARKER = '// Named variants for themes with light/dark headers.';

/**
 * Byte offsets of assignments to the exact `$data['logo']` key (not to the
 * logo_light/logo_dark variants).
 *
 * @return array<int, int>
 */
function theme_logo_assignment_offsets(string $source): array {
	preg_match_all('/\$data\[\'logo\'\]\s*=/', $source, $matches, PREG_OFFSET_CAPTURE);

	return array_map(static fn (array $match): int => $match[1], $matches[0]);
}

return [
	'header: both theme logo settings are consumed' => static function (): void {
		$source = repo_file(LOGO_HEADER_CONTROLLER);

		Assert::contains("get('dockercart_theme_logo_light')", $source);
		Assert::contains("get('dockercart_theme_logo_dark')", $source);
		Assert::contains("\$data['logo_light'] = \$data['logo'];", $source);
		Assert::contains("\$data['logo_dark'] = (\$logo_dark !== '' && is_file(DIR_IMAGE . \$logo_dark))", $source);
	},

	'header: the legacy $data[logo] resolution is untouched' => static function (): void {
		$source = repo_file(LOGO_HEADER_CONTROLLER);
		$marker = strpos($source, LOGO_VARIANTS_MARKER);

		Assert::true($marker !== false, 'the named variants must be added after the existing logo block');
		Assert::count(3, theme_logo_assignment_offsets($source), 'header must keep exactly light, config_logo and empty assignments to $data[logo]');
		Assert::contains("\$data['logo'] = \$server . 'image/' . \$logo_light;", $source);
		Assert::contains("\$data['logo'] = \$server . 'image/' . \$this->config->get('config_logo');", $source);

		foreach (theme_logo_assignment_offsets($source) as $offset) {
			Assert::true($offset < $marker, 'no assignment to $data[logo] may follow the named variants');
		}
	},

	'header: the dark variant falls back to empty, never to config_logo' => static function (): void {
		$source = repo_file(LOGO_HEADER_CONTROLLER);
		$variants = substr($source, (int)strpos($source, LOGO_VARIANTS_MARKER));

		Assert::contains("? \$server . 'image/' . \$logo_dark", $variants);
		Assert::contains(": '';", $variants);
		Assert::notContains('config_logo', $variants, 'a dark logo must not fall back to the usually dark config_logo artwork');
		Assert::notContains("\$data['logo'] =", $variants, 'named variants must not overwrite the legacy logo');
	},

	'footer: both theme logo settings are consumed' => static function (): void {
		$source = repo_file(LOGO_FOOTER_CONTROLLER);

		Assert::contains("get('dockercart_theme_logo_light')", $source);
		Assert::contains("get('dockercart_theme_logo_dark')", $source);
	},

	'footer: the light variant uses the theme setting and falls back to logo' => static function (): void {
		$source = repo_file(LOGO_FOOTER_CONTROLLER);

		Assert::contains("\$logo_light_path !== '' && is_file(DIR_IMAGE . \$logo_light_path)", $source);
		Assert::contains("\$data['logo_light'] = \$server . 'image/' . \$logo_light_path;", $source);
		Assert::contains("\$data['logo_light'] = \$data['logo'];", $source);
		Assert::count(2, theme_logo_assignment_offsets($source), 'footer $data[logo] must stay on config_logo -> empty');
	},

	'footer: the dark variant keeps its current empty fallback' => static function (): void {
		$source = repo_file(LOGO_FOOTER_CONTROLLER);

		Assert::contains("\$data['logo_dark'] = \$server . 'image/' . \$logo_dark_path;", $source);
		Assert::contains("\$data['logo_dark'] = '';", $source);
		Assert::notContains("\$data['logo_dark'] = \$data['logo']", $source);
	},
];
