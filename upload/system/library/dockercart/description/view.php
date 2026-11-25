<?php
/**
 * Advanced Description: storefront view assembly.
 *
 * One call per entity page (product, category, manufacturer): decodes the
 * stored JSON halves, merges them for the current language with the
 * default-language fallback (Dockcart\Description\Sections::forView()),
 * and resolves every shared image into a resized storefront URL through
 * model_tool_image - the same resize helper the rest of the theme uses.
 *
 * The per-language texts live on the entity's description row (one column
 * per language), so the current language's row arrives from the caller;
 * when the page is rendered in a non-default language the default
 * language's row is fetched once for the empty-field fallback.
 *
 * The classic WYSIWYG description stays the search/card snippet source and
 * the fallback when the mode is off; this helper returns an empty list then.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Description;

final class View {
	/** Resized size of a `text_image` picture. */
	public const TEXT_IMAGE_WIDTH = 720;

	public const TEXT_IMAGE_HEIGHT = 540;

	/** Resized size of a `gallery` tile. */
	public const GALLERY_WIDTH = 640;

	public const GALLERY_HEIGHT = 640;

	/** Description tables carrying advanced_content, keyed by entity type. */
	private const CONTENT_TABLES = [
		'product'      => ['table' => 'product_description', 'id' => 'product_id'],
		'category'     => ['table' => 'category_description', 'id' => 'category_id'],
		'manufacturer' => ['table' => 'manufacturer_description', 'id' => 'manufacturer_id'],
		'blog_post'    => ['table' => 'blog_post_description', 'id' => 'post_id'],
	];

	/**
	 * View-ready sections for an entity row, [] when the classic mode is on
	 * (or nothing survived the empty-section filtering).
	 *
	 * @param object $registry the catalog registry (config, loader, db, models)
	 * @param string $entity_type product|category|manufacturer|blog_post
	 * @param int $entity_id
	 * @param array<string, mixed> $entity_row the entity row carrying
	 *        use_advanced_description + advanced_description; for products and
	 *        categories the joined description row also carries the current
	 *        language's advanced_content
	 * @param array<string, mixed> $description_row the per-language description row
	 *        carrying advanced_content (manufacturer pages fetch it separately);
	 *        falls back to the entity row when omitted
	 * @return array<int, array<string, mixed>>
	 */
	public static function sections(object $registry, string $entity_type, int $entity_id, array $entity_row, array $description_row = []): array {
		if (empty($entity_row['use_advanced_description']) || !isset(self::CONTENT_TABLES[$entity_type])) {
			return [];
		}

		$structure = json_decode((string)($entity_row['advanced_description'] ?? ''), true);

		if (!is_array($structure)) {
			return [];
		}

		$config = $registry->get('config');

		$language_id = (int)$config->get('config_language_id');
		$default_language_id = self::defaultLanguageId($registry);

		$content = [];

		$raw_content = $description_row !== []
			? (string)($description_row['advanced_content'] ?? '')
			: (string)($entity_row['advanced_content'] ?? '');

		$decoded = json_decode($raw_content, true);

		if (is_array($decoded)) {
			$content[$language_id] = $decoded;
		}

		// Empty localized fields fall back to the default language's copy -
		// that row is a different description row, fetched only when needed.
		if ($language_id !== $default_language_id) {
			$fallback = self::contentRow($registry, $entity_type, $entity_id, $default_language_id);

			if ($fallback !== null) {
				$content[$default_language_id] = $fallback;
			}
		}

		$sections = Sections::forView($structure, $content, $language_id, $default_language_id);

		if ($sections === []) {
			return [];
		}

		$registry->get('load')->model('tool/image');

		$image_model = $registry->get('model_tool_image');

		foreach ($sections as &$section) {
			if ((string)$section['type'] === 'text_image' && (string)($section['settings']['image'] ?? '') !== '') {
				$section['settings']['image_url'] = $image_model->resize(
					(string)$section['settings']['image'],
					self::TEXT_IMAGE_WIDTH,
					self::TEXT_IMAGE_HEIGHT
				);
			}

			if ((string)$section['type'] === 'gallery') {
				foreach ($section['rows'] as &$row) {
					$row['image_url'] = (string)($row['image'] ?? '') !== ''
						? $image_model->resize((string)$row['image'], self::GALLERY_WIDTH, self::GALLERY_HEIGHT)
						: '';
				}
			}
		}

		return $sections;
	}

	/**
	 * The decoded advanced_content of one language's description row.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function contentRow(object $registry, string $entity_type, int $entity_id, int $language_id): ?array {
		$mapping = self::CONTENT_TABLES[$entity_type];

		$query = $registry->get('db')->query(
			"SELECT advanced_content FROM `" . DB_PREFIX . $mapping['table'] . "`"
			. " WHERE `" . $mapping['id'] . "` = '" . (int)$entity_id . "'"
			. " AND language_id = '" . (int)$language_id . "'"
		);

		if (!$query->num_rows) {
			return null;
		}

		$decoded = json_decode((string)($query->row['advanced_content'] ?? ''), true);

		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * The store's default language id (the fallback language of forView()).
	 *
	 * Mirrors ControllerLandingPage::resolveDefaultLanguageId().
	 */
	private static function defaultLanguageId(object $registry): int {
		$config = $registry->get('config');

		$registry->get('load')->model('localisation/language');

		$default_code = (string)$config->get('config_language');

		foreach ($registry->get('model_localisation_language')->getLanguages() as $language) {
			if ((string)$language['code'] === $default_code) {
				return (int)$language['language_id'];
			}
		}

		return (int)$config->get('config_language_id');
	}
}
