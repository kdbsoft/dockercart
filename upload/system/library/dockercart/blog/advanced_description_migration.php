<?php
/**
 * Blog Builder -> Advanced Description data migration.
 *
 * document() is a pure converter from hydrated Landing\Blocks rows to the
 * editor document used by Dockercart\Description. migrate() applies it to old
 * post-owned landings, one post per transaction, and removes the landing only
 * after both Advanced Description halves have been written.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Blog;

use Dockercart\Description\Sections;
use Dockercart\Landing\Blocks;
use Throwable;

final class AdvancedDescriptionMigration {
	/**
	 * Converts hydrated landing blocks to a normalised Advanced Description
	 * editor document. Classic bodies are the fallback when no usable block
	 * survived (missing landing, empty page, disabled/spacer-only page).
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks::hydrate() rows
	 * @param array<int, int> $language_ids
	 * @param array<int, string> $classic_bodies classic WYSIWYG content per language
	 * @return array<string, mixed>
	 */
	public static function document(array $blocks, array $language_ids, array $classic_bodies = []): array {
		$language_ids = self::languageIds($language_ids);
		$candidates = [];

		foreach (self::orderedBlocks($blocks) as $block) {
			$candidates = array_merge($candidates, self::mapBlock($block, $language_ids));
		}

		if ($candidates === []) {
			$bodies = [];

			foreach ($language_ids as $language_id) {
				$bodies[$language_id] = self::decode((string)($classic_bodies[$language_id] ?? ''));
			}

			$candidates = Sections::fromClassic($bodies, $language_ids)['sections'];
		}

		$candidates = self::capSections($candidates, $language_ids);
		$normalized = Sections::normalizeDocument(['sections' => $candidates], $language_ids);

		return Sections::editorDocument($normalized['structure'], $normalized['content'], $language_ids);
	}

	/**
	 * Applies the conversion to every legacy Builder post and deletes every
	 * post-owned landing (including orphans) after its content is safe.
	 *
	 * @param object $registry OpenCart registry with db/config/cache
	 * @return array{converted: int, preserved: int, deleted_landings: int, post_ids: array<int, int>}
	 */
	public static function migrate(object $registry): array {
		$db = $registry->get('db');
		$config = $registry->get('config');
		$language_rows = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` ORDER BY language_id")->rows;
		$language_ids = array_map(static fn (array $row): int => (int)$row['language_id'], $language_rows);

		if ($language_ids === []) {
			throw new \RuntimeException('Cannot migrate blog descriptions without store languages.');
		}

		$default_language_id = (int)$config->get('config_language_id');

		if (!in_array($default_language_id, $language_ids, true)) {
			$default_language_id = $language_ids[0];
		}

		$posts = $db->query(
			"SELECT p.post_id, p.use_builder, p.use_advanced_description, p.advanced_description"
			. " FROM `" . DB_PREFIX . "blog_post` p"
			. " WHERE p.use_builder = '1' OR EXISTS (SELECT 1 FROM `" . DB_PREFIX . "landing` l WHERE l.post_id = p.post_id)"
			. " ORDER BY p.post_id"
		)->rows;

		$converted = 0;
		$preserved = 0;
		$deleted_landings = 0;
		$post_ids = [];

		foreach ($posts as $post) {
			$post_id = (int)$post['post_id'];
			$db->query("START TRANSACTION");

			try {
				$descriptions = $db->query(
					"SELECT language_id, content, advanced_content FROM `" . DB_PREFIX . "blog_post_description`"
					. " WHERE post_id = '" . $post_id . "' ORDER BY language_id"
				)->rows;
				$landing_rows = $db->query(
					"SELECT landing_id FROM `" . DB_PREFIX . "landing` WHERE post_id = '" . $post_id . "' ORDER BY landing_id"
				)->rows;
				$stored_structure = json_decode((string)($post['advanced_description'] ?? ''), true);
				$has_advanced = is_array($stored_structure) && !empty($stored_structure['sections']);

				if ($has_advanced) {
					// An operator already composed Advanced Description: keep both
					// halves and the chosen mode, only retire the Builder flag.
					$db->query("UPDATE `" . DB_PREFIX . "blog_post` SET use_builder = '0' WHERE post_id = '" . $post_id . "'");
					$preserved++;
				} else {
					$blocks = [];
					$classic_bodies = [];

					foreach ($descriptions as $description) {
						$language_id = (int)$description['language_id'];
						$classic_bodies[$language_id] = (string)($description['content'] ?? '');
					}

					foreach ($landing_rows as $landing_row) {
						$landing_id = (int)$landing_row['landing_id'];
						$block_rows = $db->query(
							"SELECT block_id, parent_id, slot, code, sort_order, status, settings FROM `" . DB_PREFIX . "landing_block`"
							. " WHERE landing_id = '" . $landing_id . "' ORDER BY sort_order, block_id"
						)->rows;
						$content_rows = $db->query(
							"SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description`"
							. " WHERE block_id IN (SELECT block_id FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "')"
						)->rows;
						$blocks = array_merge($blocks, Blocks::hydrate($block_rows, $content_rows));
					}

					$document = self::document($blocks, $language_ids, $classic_bodies);
					$normalized = Sections::normalizeDocument($document, $language_ids);
					$structure_json = (string)json_encode($normalized['structure'], JSON_UNESCAPED_UNICODE);
					$use_advanced = !empty($post['use_builder']) ? 1 : 0;

					$db->query(
						"UPDATE `" . DB_PREFIX . "blog_post` SET use_advanced_description = '" . $use_advanced . "',"
						. " advanced_description = '" . $db->escape($structure_json) . "' WHERE post_id = '" . $post_id . "'"
					);

					foreach ($descriptions as $description) {
						$language_id = (int)$description['language_id'];
						$content_json = (string)json_encode($normalized['content'][$language_id] ?? [], JSON_UNESCAPED_UNICODE);

						$db->query(
							"UPDATE `" . DB_PREFIX . "blog_post_description` SET advanced_content = '" . $db->escape($content_json) . "'"
							. " WHERE post_id = '" . $post_id . "' AND language_id = '" . $language_id . "'"
						);
					}

					$db->query("UPDATE `" . DB_PREFIX . "blog_post` SET use_builder = '0' WHERE post_id = '" . $post_id . "'");
					$converted++;
				}

				foreach ($landing_rows as $landing_row) {
					self::deleteLanding($db, (int)$landing_row['landing_id']);
					$deleted_landings++;
				}

				$db->query("COMMIT");
				$post_ids[] = $post_id;
			} catch (Throwable $e) {
				$db->query("ROLLBACK");
				throw new \RuntimeException('Blog Advanced Description migration failed for post ' . $post_id . ': ' . $e->getMessage(), 0, $e);
			}
		}

		// A missing FK means old data can contain an orphan owner. It must be
		// removed before DROP COLUMN post_id, otherwise it becomes standalone.
		$orphans = $db->query(
			"SELECT l.landing_id FROM `" . DB_PREFIX . "landing` l"
			. " LEFT JOIN `" . DB_PREFIX . "blog_post` p ON (p.post_id = l.post_id)"
			. " WHERE l.post_id IS NOT NULL AND p.post_id IS NULL ORDER BY l.landing_id"
		)->rows;

		foreach ($orphans as $orphan) {
			$db->query("START TRANSACTION");

			try {
				self::deleteLanding($db, (int)$orphan['landing_id']);
				$db->query("COMMIT");
				$deleted_landings++;
			} catch (Throwable $e) {
				$db->query("ROLLBACK");
				throw new \RuntimeException('Could not remove orphan blog landing ' . (int)$orphan['landing_id'] . ': ' . $e->getMessage(), 0, $e);
			}
		}

		if ($registry->has('cache')) {
			foreach (['blog.post', 'blog.popular', 'blog.recent', 'blog.archive'] as $key) {
				$registry->get('cache')->delete($key);
			}
		}

		return [
			'converted'        => $converted,
			'preserved'        => $preserved,
			'deleted_landings' => $deleted_landings,
			'post_ids'         => $post_ids,
		];
	}

	/**
	 * @param object $db
	 */
	private static function deleteLanding(object $db, int $landing_id): void {
		$db->query(
			"DELETE lbd FROM `" . DB_PREFIX . "landing_block_description` lbd"
			. " INNER JOIN `" . DB_PREFIX . "landing_block` lb ON (lb.block_id = lbd.block_id)"
			. " WHERE lb.landing_id = '" . $landing_id . "'"
		);
		$db->query("DELETE FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . $landing_id . "'");
		$db->query("DELETE FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . $landing_id . "'");
		$db->query("DELETE FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . $landing_id . "'");
	}

	/**
	 * Flattens section containers at their position. Children are ordered by
	 * column slot and then by their own sort order; missing parents are treated
	 * as top-level so malformed legacy data never silently disappears.
	 *
	 * @param array<int, array<string, mixed>> $blocks
	 * @return array<int, array<string, mixed>>
	 */
	private static function orderedBlocks(array $blocks): array {
		$by_id = [];
		$children = [];
		$top = [];

		foreach ($blocks as $block) {
			$block_id = (int)($block['block_id'] ?? 0);
			$by_id[$block_id] = $block;
			$parent_id = (int)($block['parent_id'] ?? 0);
			$children[$parent_id][] = $block;
		}

		foreach ($children as $parent_id => $rows) {
			usort($rows, static function (array $a, array $b): int {
				return [(int)($a['sort_order'] ?? 0), (int)($a['block_id'] ?? 0)]
					<=> [(int)($b['sort_order'] ?? 0), (int)($b['block_id'] ?? 0)];
			});
			$children[$parent_id] = $rows;
		}

		foreach ($children[0] ?? [] as $block) {
			$top[] = $block;
		}

		foreach ($blocks as $block) {
			$parent_id = (int)($block['parent_id'] ?? 0);

			if ($parent_id > 0 && !isset($by_id[$parent_id])) {
				$top[] = $block;
			}
		}

		$ordered = [];
		$visited = [];

		foreach ($top as $block) {
			$block_id = (int)($block['block_id'] ?? 0);

			if (isset($visited[$block_id])) {
				continue;
			}

			$visited[$block_id] = true;
			$enabled = !isset($block['status']) || (int)$block['status'] === 1;

			if (Blocks::isContainer((string)($block['code'] ?? ''))) {
				$rows = $children[$block_id] ?? [];
				usort($rows, static function (array $a, array $b): int {
					return [(int)($a['slot'] ?? 0), (int)($a['sort_order'] ?? 0), (int)($a['block_id'] ?? 0)]
						<=> [(int)($b['slot'] ?? 0), (int)($b['sort_order'] ?? 0), (int)($b['block_id'] ?? 0)];
				});

				foreach ($rows as $child) {
					$child_id = (int)($child['block_id'] ?? 0);
					$visited[$child_id] = true;

					if ($enabled && (!isset($child['status']) || (int)$child['status'] === 1)) {
						$ordered[] = $child;
					}
				}

				continue;
			}

			if ($enabled) {
				$ordered[] = $block;
			}
		}

		return $ordered;
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<int, array<string, mixed>>
	 */
	private static function mapBlock(array $block, array $language_ids): array {
		switch ((string)($block['code'] ?? '')) {
			case 'text':
				return [self::textSection($block, $language_ids)];

			case 'text_image':
			case 'image_text':
				return [self::textImageSection($block, $language_ids)];

			case 'features':
				return self::featuresSections($block, $language_ids);

			case 'stats':
				return self::statsSections($block, $language_ids);

			case 'statement':
				return [self::statementSection($block, $language_ids)];

			case 'image':
				return [self::gallerySection($block, $language_ids)];

			case 'html':
				return [self::htmlSection($block, $language_ids)];

			case 'spacer':
				return [];

			default:
				return [self::genericTextSection($block, $language_ids)];
		}
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function textSection(array $block, array $language_ids): array {
		$content = [];
		$align = self::shared($block, 'align') === 'center' ? 'center' : 'left';

		foreach ($language_ids as $language_id) {
			$content[$language_id] = [
				'heading' => self::localized($block, $language_id, 'heading'),
				'body'    => self::localized($block, $language_id, 'body'),
			];
		}

		return ['type' => 'text', 'settings' => ['align' => $align], 'content' => $content];
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function textImageSection(array $block, array $language_ids): array {
		$content = [];
		$image = self::shared($block, 'image');
		$position_key = (string)($block['code'] ?? '') === 'image_text' ? 'image_position' : 'position';
		$position = self::shared($block, $position_key) === 'left' ? 'left' : 'right';

		foreach ($language_ids as $language_id) {
			$body = self::localized($block, $language_id, 'body');
			$body = self::appendBody($body, self::plain(self::localized($block, $language_id, 'bullets')));
			$body = self::appendButton($body, self::localized($block, $language_id, 'button_label'), self::shared($block, 'button_href'));
			$content[$language_id] = [
				'heading' => self::localized($block, $language_id, 'heading'),
				'body'    => $body,
			];
		}

		return ['type' => 'text_image', 'settings' => ['image' => $image, 'position' => $position], 'content' => $content];
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<int, array<string, mixed>>
	 */
	private static function featuresSections(array $block, array $language_ids): array {
		$sections = [];
		$intro = [];

		foreach ($language_ids as $language_id) {
			$intro[$language_id] = self::localized($block, $language_id, 'intro');
		}

		if (self::hasText($intro)) {
			$sections[] = [
				'type'    => 'text',
				'settings' => ['align' => 'left'],
				'content' => array_map(static fn (string $body): array => ['heading' => '', 'body' => self::plain($body)], $intro),
			];
		}

		$rows = [];
		$row_count = 0;

		foreach ($language_ids as $language_id) {
			$row_count = max($row_count, count(self::localizedRows($block, $language_id, 'items')));
		}

		for ($index = 0; $index < min($row_count, Sections::MAX_ROWS); $index++) {
			$icon = 'sparkles';

			foreach ($language_ids as $language_id) {
				$candidate = (string)(self::localizedRows($block, $language_id, 'items')[$index]['icon'] ?? '');

				if ($candidate !== '') {
					$icon = isset(Sections::ICONS[$candidate]) ? $candidate : 'sparkles';
					break;
				}
			}

			$row = ['icon' => $icon, 'content' => []];

			foreach ($language_ids as $language_id) {
				$source = self::localizedRows($block, $language_id, 'items')[$index] ?? [];
				$row['content'][$language_id] = [
					'title' => self::decode((string)($source['title'] ?? '')),
					'text'  => self::decode((string)($source['text'] ?? '')),
				];
			}

			$rows[] = $row;
		}

		$content = [];

		foreach ($language_ids as $language_id) {
			$content[$language_id] = ['heading' => self::localized($block, $language_id, 'heading')];
		}

		$sections[] = [
			'type'     => 'features',
			'settings' => ['columns' => self::columns($block, '4')],
			'rows'     => $rows,
			'content'  => $content,
		];

		return $sections;
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<int, array<string, mixed>>
	 */
	private static function statsSections(array $block, array $language_ids): array {
		$sections = [];
		$intro = [];

		foreach ($language_ids as $language_id) {
			$heading = self::localized($block, $language_id, 'heading');
			$body = self::localized($block, $language_id, 'intro');
			$intro[$language_id] = trim($heading . "\n" . $body);
		}

		if (self::hasText($intro)) {
			$sections[] = [
				'type'    => 'text',
				'settings' => ['align' => 'left'],
				'content' => array_map(static fn (string $body): array => ['heading' => '', 'body' => self::plain($body)], $intro),
			];
		}

		$rows = [];
		$row_count = 0;

		foreach ($language_ids as $language_id) {
			$row_count = max($row_count, count(self::localizedRows($block, $language_id, 'items')));
		}

		for ($index = 0; $index < min($row_count, Sections::MAX_ROWS); $index++) {
			$row = ['content' => []];

			foreach ($language_ids as $language_id) {
				$source = self::localizedRows($block, $language_id, 'items')[$index] ?? [];
				$row['content'][$language_id] = [
					'value' => self::decode((string)($source['value'] ?? '')),
					'label' => self::decode((string)($source['label'] ?? '')),
				];
			}

			$rows[] = $row;
		}

		$content = [];

		foreach ($language_ids as $language_id) {
			$content[$language_id] = ['heading' => self::localized($block, $language_id, 'heading')];
		}

		$sections[] = [
			'type'     => 'stats',
			'settings' => ['columns' => self::columns($block, '3')],
			'rows'     => $rows,
			'content'  => $content,
		];

		return $sections;
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function statementSection(array $block, array $language_ids): array {
		$content = [];

		foreach ($language_ids as $language_id) {
			$content[$language_id] = [
				'text'     => self::localized($block, $language_id, 'text'),
				'footnote' => self::localized($block, $language_id, 'foot') ?: self::localized($block, $language_id, 'footnote'),
			];
		}

		return ['type' => 'statement', 'settings' => [], 'content' => $content];
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function gallerySection(array $block, array $language_ids): array {
		$content = [];

		foreach ($language_ids as $language_id) {
			$content[$language_id] = [
				'caption' => self::localized($block, $language_id, 'caption') ?: self::localized($block, $language_id, 'alt'),
			];
		}

		return [
			'type'     => 'gallery',
			'settings' => ['columns' => '3'],
			'rows'     => [['image' => self::shared($block, 'image'), 'content' => $content]],
		];
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function htmlSection(array $block, array $language_ids): array {
		$content = [];

		foreach ($language_ids as $language_id) {
			$content[$language_id] = ['heading' => '', 'body' => self::localized($block, $language_id, 'html')];
		}

		return ['type' => 'text', 'settings' => ['align' => 'left'], 'content' => $content];
	}

	/**
	 * Generic editorial/commerce fallback: keep every localized copy field in
	 * registry order. Shared widget selections (products, categories, banners)
	 * cannot be represented by Advanced Description and are intentionally lost.
	 *
	 * @param array<int, int> $language_ids
	 * @return array<string, mixed>
	 */
	private static function genericTextSection(array $block, array $language_ids): array {
		$code = (string)($block['code'] ?? '');
		$definition = Blocks::all()[$code] ?? [];
		$fields = (array)($definition['fields'] ?? []);
		$content = [];

		foreach ($language_ids as $language_id) {
			$heading = self::localized($block, $language_id, 'heading');
			$body = '';

			foreach ($fields as $field) {
				$key = (string)($field['key'] ?? '');

				if ($key === '' || $key === 'heading' || empty($field['localized'])) {
					continue;
				}

				if (($field['type'] ?? '') === 'repeater') {
					foreach (self::localizedRows($block, $language_id, $key) as $row) {
						$parts = [];

						foreach ((array)($field['fields'] ?? []) as $sub) {
							$sub_key = (string)($sub['key'] ?? '');

							if ($sub_key === '' || in_array($sub_key, ['icon', 'style', 'rating', 'featured'], true)) {
								continue;
							}

							$value = self::decode((string)($row[$sub_key] ?? ''));

							if ($value !== '') {
								$parts[] = ($sub['type'] ?? '') === 'richtext' ? $value : self::plain($value);
							}
						}

						if ($parts !== []) {
							$body = self::appendBody($body, '<p>' . implode(' — ', $parts) . '</p>');
						}
					}

					continue;
				}

				$value = self::localized($block, $language_id, $key);
				$body = self::appendBody($body, in_array($field['type'] ?? '', ['richtext', 'code'], true) ? $value : self::plain($value));
			}

			$body = self::appendButton($body, self::localized($block, $language_id, 'button_label'), self::shared($block, 'button_href'));
			$body = self::appendButton($body, self::localized($block, $language_id, 'link_label'), self::shared($block, 'link_href'));
			$content[$language_id] = ['heading' => $heading, 'body' => $body];
		}

		return ['type' => 'text', 'settings' => ['align' => self::shared($block, 'align') === 'center' ? 'center' : 'left'], 'content' => $content];
	}

	/**
	 * @param array<int, array<string, mixed>> $candidates
	 * @param array<int, int> $language_ids
	 * @return array<int, array<string, mixed>>
	 */
	private static function capSections(array $candidates, array $language_ids): array {
		if (count($candidates) <= Sections::MAX_SECTIONS) {
			return $candidates;
		}

		$kept = array_slice($candidates, 0, Sections::MAX_SECTIONS - 1);
		$overflow = array_slice($candidates, Sections::MAX_SECTIONS - 1);
		$content = [];

		foreach ($language_ids as $language_id) {
			$parts = [];

			foreach ($overflow as $section) {
				$html = self::sectionHtml($section, $language_id);

				if ($html !== '') {
					$parts[] = $html;
				}
			}

			$content[$language_id] = ['heading' => '', 'body' => implode('<hr>', $parts)];
		}

		$kept[] = ['type' => 'text', 'settings' => ['align' => 'left'], 'content' => $content];

		return $kept;
	}

	/**
	 * @param array<string, mixed> $section
	 */
	private static function sectionHtml(array $section, int $language_id): string {
		$type = (string)($section['type'] ?? 'text');
		$content = (array)($section['content'][$language_id] ?? []);
		$parts = [];

		if ((string)($content['heading'] ?? '') !== '') {
			$parts[] = '<h2>' . self::plain((string)$content['heading']) . '</h2>';
		}

		if ((string)($content['body'] ?? '') !== '') {
			$parts[] = (string)$content['body'];
		}

		if ((string)($content['text'] ?? '') !== '') {
			$parts[] = '<blockquote>' . self::plain((string)$content['text']) . '</blockquote>';
		}

		if ((string)($content['footnote'] ?? '') !== '') {
			$parts[] = '<p>' . self::plain((string)$content['footnote']) . '</p>';
		}

		foreach ((array)($section['rows'] ?? []) as $row) {
			$row_content = (array)($row['content'][$language_id] ?? []);
			$row_parts = [];

			foreach (['title', 'value', 'text', 'label', 'caption'] as $key) {
				if ((string)($row_content[$key] ?? '') !== '') {
					$row_parts[] = self::plain((string)$row_content[$key]);
				}
			}

			if ($row_parts !== []) {
				$parts[] = '<p>' . implode(' — ', $row_parts) . '</p>';
			}
		}

		return implode('', $parts);
	}

	/**
	 * @param array<string, mixed> $block
	 * @return array<int, array<string, mixed>>
	 */
	private static function localizedRows(array $block, int $language_id, string $key): array {
		$rows = $block['content'][$language_id][$key] ?? [];

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function localized(array $block, int $language_id, string $key): string {
		$value = $block['content'][$language_id][$key] ?? '';

		return is_scalar($value) ? self::decode((string)$value) : '';
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function shared(array $block, string $key): string {
		$value = $block['settings'][$key] ?? '';

		return is_scalar($value) ? self::decode((string)$value) : '';
	}

	/**
	 * @param array<string, mixed> $block
	 */
	private static function columns(array $block, string $default): string {
		$columns = self::shared($block, 'columns');

		return in_array($columns, ['2', '3', '4'], true) ? $columns : $default;
	}

	/**
	 * @param array<int, string> $values
	 */
	private static function hasText(array $values): bool {
		foreach ($values as $value) {
			if (trim(strip_tags($value)) !== '') {
				return true;
			}
		}

		return false;
	}

	private static function appendBody(string $body, string $addition): string {
		if (trim(strip_tags($addition)) === '') {
			return $body;
		}

		return $body . $addition;
	}

	private static function appendButton(string $body, string $label, string $href): string {
		if ($label === '' && $href === '') {
			return $body;
		}

		$text = $label !== '' ? $label : $href;
		$html = '<p>';

		if ($href !== '') {
			$html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . self::plain($text) . '</a>';
		} else {
			$html .= self::plain($text);
		}

		return self::appendBody($body, $html . '</p>');
	}

	private static function plain(string $value): string {
		return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
	}

	private static function decode(string $value): string {
		return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	 * @param array<int, int> $language_ids
	 * @return array<int, int>
	 */
	private static function languageIds(array $language_ids): array {
		return array_values(array_unique(array_filter(array_map('intval', $language_ids), static fn (int $id): bool => $id > 0)));
	}
}
