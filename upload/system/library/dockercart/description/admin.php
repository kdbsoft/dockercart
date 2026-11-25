<?php
/**
 * Advanced Description: admin form helpers.
 *
 * The three entity forms share one section editor. This class prepares its
 * JSON configuration (localised schema + preset documents), rebuilds the
 * editor document from stored rows, and turns the single hidden JSON POST
 * field back into the two normalised storage halves. The catalog models
 * remain the only DB writers.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Description;

final class Admin {
	/**
	 * The editor configuration embedded in the shared form partial.
	 *
	 * @param object $language OpenCart Language registry
	 * @param array<int, array<string, mixed>> $languages language rows
	 * @param string $image_base catalog image URL prefix for admin previews
	 * @return array<string, mixed>
	 */
	public static function config(object $language, array $languages, string $image_base = ''): array {
		$language_ids = self::languageIds($languages);

		$schema = Sections::localizedSchema(Sections::schema(), static fn (string $key): string => $language->get($key));

		$preset_documents = [];

		foreach (array_keys(Sections::presets()) as $code) {
			$preset_documents[$code] = Sections::presetDocument($code, $language_ids);
		}

		return $schema + [
			'image_base'       => $image_base,
			'preset_documents' => $preset_documents,
			'max_sections'     => Sections::MAX_SECTIONS,
			'max_rows'         => Sections::MAX_ROWS,
			'labels'           => self::labels($language),
			'languages'        => array_values(array_map(static function (array $language): array {
				return [
					'id'   => (int)$language['language_id'],
					'code' => (string)$language['code'],
					'name' => (string)$language['name'],
				];
			}, $languages)),
		];
	}

	/**
	 * Form state shared by the three entity controllers. On POST the hidden
	 * editor JSON is normalised immediately and its per-language halves are
	 * returned so the controller can attach them to the posted descriptions;
	 * the model then persists them through the same resolveSave() path as a
	 * copy or a programmatic save.
	 *
	 * @param array<int, array<string, mixed>> $languages
	 * @param array<int, array<string, mixed>> $descriptions
	 * @param string $image_base catalog image URL prefix for admin previews
	 * @return array<string, mixed>
	 */
	public static function state(object $language, array $languages, string $stored_structure_json, array $descriptions, ?string $posted_json, bool $use, string $image_base = ''): array {
		$language_ids = self::languageIds($languages);
		$content = [];

		if ($posted_json !== null) {
			$normalized = self::posted($posted_json, $languages);
			$document = Sections::editorDocument($normalized['structure'], $normalized['content'], $language_ids);
			$payload = $normalized['structure'];
			$content = $normalized['content'];
		} else {
			$document = self::document($stored_structure_json, $descriptions, $languages);
			$payload = $stored_structure_json;
		}

		return [
			'use_advanced_description'   => $use ? 1 : 0,
			'advanced_description'       => $payload,
			'advanced_editor_document'   => $document,
			'advanced_description_json'  => (string)json_encode($document, JSON_UNESCAPED_UNICODE),
			'advanced_content'           => $content,
			'description_editor_config'  => self::config($language, $languages, $image_base),
		];
	}

	/**
	 * UI strings of the shared editor (keys live in each entity language file).
	 *
	 * @return array<string, string>
	 */
	public static function labels(object $language): array {
		$keys = [
			'text_advanced_description',
			'text_mode_classic',
			'text_mode_advanced',
			'text_classic_description_hint',
			'text_advanced_description_hint',
			'text_add_section',
			'button_templates',
			'text_empty_sections',
			'text_move_up',
			'text_move_down',
			'text_remove_section',
			'text_add_row',
			'text_remove_row',
			'text_select_image',
			'text_remove_image',
			'text_confirm_template',
			'text_section_limit',
			'text_row_limit',
			'button_show_classic_description',
			'button_hide_classic_description',
		];

		$labels = [];

		foreach ($keys as $key) {
			$labels[$key] = (string)$language->get($key);
		}

		return $labels;
	}

	/**
	 * Rebuilds the editor document from the entity's stored structure and its
	 * per-language description rows.
	 *
	 * @param string $structure_json advanced_description column
	 * @param array<int, array<string, mixed>> $descriptions language_id => description row
	 * @param array<int, array<string, mixed>> $languages
	 * @return array<string, mixed>
	 */
	public static function document(string $structure_json, array $descriptions, array $languages): array {
		$structure = json_decode($structure_json, true);
		$content = [];

		if (!is_array($structure)) {
			$structure = ['sections' => []];
		}

		foreach ($descriptions as $language_id => $description) {
			$decoded = json_decode((string)($description['advanced_content'] ?? ''), true);

			if (is_array($decoded)) {
				$content[(int)$language_id] = $decoded;
			}
		}

		return Sections::editorDocument($structure, $content, self::languageIds($languages));
	}

	/**
	 * Parses and normalises the editor's hidden JSON POST field.
	 *
	 * Request::clean() HTML-escapes posted values, so the boundary decodes
	 * entities exactly once before json_decode (the same convention as the
	 * classic form's decodeDescriptionFields()). Invalid JSON is treated as
	 * an empty document, never as a fatal save error.
	 *
	 * @return array{structure: array<string, mixed>, content: array<int, array<string, array<string, mixed>>>}
	 */
	public static function posted(?string $json, array $languages): array {
		$json = html_entity_decode((string)$json, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$document = json_decode($json, true);

		if (!is_array($document)) {
			$document = ['sections' => []];
		}

		return Sections::normalizeDocument($document, self::languageIds($languages));
	}

	/**
	 * @param array<int, array<string, mixed>> $languages
	 * @return array<int, int>
	 */
	public static function languageIds(array $languages): array {
		return array_values(array_map(static fn (array $language): int => (int)$language['language_id'], $languages));
	}
}
