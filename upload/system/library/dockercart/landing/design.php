<?php
/**
 * Global design tokens for the landing page builder.
 *
 * Stored in oc_setting under its own code (never `config`, whose editSetting()
 * deletes every key it does not receive). The catalog startup loads setting
 * rows into Config, so the storefront reads the same values via Config::get().
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Landing;

final class Design {
	public const SETTING_CODE = 'dockercart_landing_design';

	public const PREFIX = 'dockercart_landing_design_';

	/** Field key => [label key, default, select options or null]. */
	public const FIELDS = [
		'accent_color' => ['field_accent_color', '#2563eb', null],
		'surface_color' => ['field_surface_color', '#f6f8fc', null],
		'ink_color' => ['field_ink_color', '#0b1b33', null],
		'muted_color' => ['field_muted_color', '#5c6b7f', null],
		'line_color' => ['field_line_color', '#dce3ee', null],
		'success_color' => ['field_success_color', '#0f9d76', null],
		'radius' => ['field_radius', 'md', ['none', 'sm', 'md']],
		'padding_y' => ['field_padding_y', 'md', ['xs', 'sm', 'md', 'lg', 'xl']],
		'content_width' => ['field_content_width', 'normal', ['narrow', 'normal', 'wide', 'full']],
	];

	private const VALUES = [
		'radius' => ['none' => '0px', 'sm' => '12px', 'md' => '22px'],
		'padding_y' => ['xs' => '2rem', 'sm' => '3rem', 'md' => '4rem', 'lg' => '4.75rem', 'xl' => '6rem'],
		'content_width' => ['narrow' => '760px', 'normal' => '1120px', 'wide' => '1240px', 'full' => 'none'],
	];

	/**
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		$values = [];

		foreach (self::FIELDS as $key => $field) {
			$values[$key] = (string)$field[1];
		}

		return $values;
	}

	/**
	 * @return array<int, string>
	 */
	public static function labelKeys(): array {
		$keys = [];

		foreach (self::FIELDS as $key => $field) {
			$keys[] = (string)$field[0];

			foreach ($field[2] ?? [] as $option) {
				$keys[] = 'option_' . ($key === 'padding_y' ? 'padding_y' : ($key === 'content_width' ? 'content_width' : 'radius')) . '_' . $option;
			}
		}

		return array_values(array_unique($keys));
	}

	/**
	 * @return array<string, string>
	 */
	public static function optionLabels(string $key): array {
		$options = self::FIELDS[$key][2] ?? [];
		$labels = [];

		foreach ($options as $option) {
			$prefix = $key === 'padding_y' ? 'padding_y' : ($key === 'content_width' ? 'content_width' : 'radius');
			$labels[$option] = 'option_' . $prefix . '_' . $option;
		}

		return $labels;
	}

	/**
	 * Schema for the builder's Site Settings panel.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		$schema = [];

		foreach (self::FIELDS as $key => $field) {
			$schema[$key] = [
				'label' => (string)$field[0],
				'type' => $field[2] === null ? 'color' : 'select',
				'default' => (string)$field[1],
				'options' => self::optionLabels($key),
			];
		}

		return $schema;
	}

	/**
	 * Colours are hex-only, selects are whitelisted: no arbitrary CSS in DB.
	 *
	 * @return array<string, string>
	 */
	public static function normalize(array $data): array {
		$normalized = self::defaults();

		foreach (self::FIELDS as $key => $field) {
			if (!array_key_exists($key, $data)) {
				continue;
			}

			$value = trim((string)$data[$key]);

			if ($field[2] === null) {
				if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
					$normalized[$key] = strtolower($value);
				}
			} elseif (in_array($value, $field[2], true)) {
				$normalized[$key] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Read the saved values from Config (all setting rows are loaded there).
	 *
	 * @param object{get: callable} $config
	 * @return array<string, string>
	 */
	public static function fromConfig(object $config): array {
		$values = [];

		foreach (self::FIELDS as $key => $field) {
			$value = $config->get(self::PREFIX . $key);

			if ($value !== null) {
				$values[$key] = (string)$value;
			}
		}

		return self::normalize($values);
	}

	/**
	 * @return array<string, string>
	 */
	public static function cssVariables(array $design): array {
		$design = self::normalize($design);
		$variables = [];

		foreach (['accent', 'surface', 'ink', 'muted', 'line', 'success'] as $name) {
			$variables['--lp-' . $name] = $design[$name . '_color'];
		}

		$variables['--lp-radius'] = self::VALUES['radius'][$design['radius']];
		$variables['--lp-pad-y'] = self::VALUES['padding_y'][$design['padding_y']];
		$variables['--lp-content-w'] = self::VALUES['content_width'][$design['content_width']];

		return $variables;
	}

	/**
	 * Inline style attribute for #lp-page.
	 */
	public static function styleAttribute(array $design): string {
		$parts = [];

		foreach (self::cssVariables($design) as $property => $value) {
			$parts[] = $property . ':' . $value;
		}

		return implode(';', $parts);
	}
}
