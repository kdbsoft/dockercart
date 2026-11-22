<?php
/**
 * Landing page model (storefront side).
 *
 * Read-only access to a published/being-previewed landing page: the page row,
 * its per-language descriptions and its ordered blocks. Gating (status, publish
 * window, preview token) belongs to the controller, so this model stays a plain
 * data source.
 *
 * @package    DockerCart
 * @subpackage Model
 */

declare(strict_types=1);

class ModelDesignLanding extends Model {
	/**
	 * @return array<string, mixed>|false
	 */
	public function getLanding(int $landing_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing` WHERE landing_id = '" . (int)$landing_id . "'");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * @return array<string, mixed>|false
	 */
	public function getLandingBySlug(string $slug, int $language_id) {
		$query = $this->db->query("SELECT l.* FROM `" . DB_PREFIX . "landing` l
			INNER JOIN `" . DB_PREFIX . "landing_description` d ON (d.landing_id = l.landing_id)
			WHERE d.slug = '" . $this->db->escape($slug) . "' AND d.language_id = '" . (int)$language_id . "'
			AND l.information_id IS NULL AND l.post_id IS NULL
			LIMIT 1");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * The landing page bound to an information page as its composed body
	 * (information/information delegates to landing/page for it).
	 *
	 * @return array<string, mixed>|false
	 */
	public function getLandingByInformation(int $information_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing` WHERE information_id = '" . (int)$information_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * The landing page bound to a blog post as its composed page
	 * (blog/post delegates to landing/page for it).
	 *
	 * @return array<string, mixed>|false
	 */
	public function getLandingByPost(int $post_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing` WHERE post_id = '" . (int)$post_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * @return array<int, array<string, mixed>> keyed by language_id
	 */
	public function getLandingDescriptions(int $landing_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . (int)$landing_id . "'");

		$descriptions = [];

		foreach ($query->rows as $row) {
			$descriptions[(int)$row['language_id']] = $row;
		}

		return $descriptions;
	}

	/**
	 * @return array<string, mixed>|false
	 */
	public function getLandingDescription(int $landing_id, int $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_description` WHERE landing_id = '" . (int)$landing_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * Ordered blocks with decoded settings and per-language content.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlocks(int $landing_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_block` WHERE landing_id = '" . (int)$landing_id . "' ORDER BY sort_order ASC, block_id ASC");

		if (!$query->num_rows) {
			return [];
		}

		$block_ids = array_map(static function ($row) {
			return (int)$row['block_id'];
		}, $query->rows);

		$content_query = $this->db->query("SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id IN (" . implode(',', $block_ids) . ")");

		return \Dockercart\Landing\Blocks::hydrate($query->rows, $content_query->rows);
	}

	/**
	 * @return array<string, mixed>|false
	 */
	public function getBlock(int $block_id, int $landing_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '" . (int)$block_id . "' AND landing_id = '" . (int)$landing_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$content_query = $this->db->query("SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");

		$blocks = \Dockercart\Landing\Blocks::hydrate([$query->row], $content_query->rows);

		return $blocks[0] ?? false;
	}

	/**
	 * Children of one section (its columns' widgets), in slot/sort order.
	 *
	 * Used by the builder's single-block re-render (only_block=<section id>).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlockChildren(int $block_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_block` WHERE parent_id = '" . (int)$block_id . "' ORDER BY slot ASC, sort_order ASC, block_id ASC");

		if (!$query->num_rows) {
			return [];
		}

		$block_ids = array_map(static function ($row) {
			return (int)$row['block_id'];
		}, $query->rows);

		$content_query = $this->db->query("SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id IN (" . implode(',', $block_ids) . ")");

		return \Dockercart\Landing\Blocks::hydrate($query->rows, $content_query->rows);
	}

	/**
	 * Published and inside its publish window.
	 *
	 * @param array<string, mixed> $landing
	 */
	public function isLive(array $landing): bool {
		if (empty($landing['status'])) {
			return false;
		}

		$now = time();

		if (!empty($landing['date_publish']) && strtotime((string)$landing['date_publish']) > $now) {
			return false;
		}

		if (!empty($landing['date_unpublish']) && strtotime((string)$landing['date_unpublish']) <= $now) {
			return false;
		}

		return true;
	}

	public function incrementViewed(int $landing_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "landing` SET viewed = viewed + 1 WHERE landing_id = '" . (int)$landing_id . "'");
	}
}
