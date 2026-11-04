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
			LIMIT 1");

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

		$descriptions = [];

		$content_query = $this->db->query("SELECT block_id, language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id IN (" . implode(',', $block_ids) . ")");

		foreach ($content_query->rows as $row) {
			$decoded = json_decode((string)$row['content'], true);

			$descriptions[(int)$row['block_id']][(int)$row['language_id']] = is_array($decoded) ? $decoded : [];
		}

		$blocks = [];

		foreach ($query->rows as $row) {
			$block_id = (int)$row['block_id'];
			$settings = json_decode((string)$row['settings'], true);

			$blocks[] = [
				'block_id'   => $block_id,
				'code'       => (string)$row['code'],
				'sort_order' => (int)$row['sort_order'],
				'status'     => (int)$row['status'],
				'settings'   => is_array($settings) ? $settings : [],
				'content'    => $descriptions[$block_id] ?? [],
			];
		}

		return $blocks;
	}

	/**
	 * @return array<string, mixed>|false
	 */
	public function getBlock(int $block_id, int $landing_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "landing_block` WHERE block_id = '" . (int)$block_id . "' AND landing_id = '" . (int)$landing_id . "' LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$row = $query->row;
		$settings = json_decode((string)$row['settings'], true);
		$content = [];

		$content_query = $this->db->query("SELECT language_id, content FROM `" . DB_PREFIX . "landing_block_description` WHERE block_id = '" . (int)$block_id . "'");

		foreach ($content_query->rows as $content_row) {
			$decoded = json_decode((string)$content_row['content'], true);

			$content[(int)$content_row['language_id']] = is_array($decoded) ? $decoded : [];
		}

		return [
			'block_id'   => (int)$row['block_id'],
			'code'       => (string)$row['code'],
			'sort_order' => (int)$row['sort_order'],
			'status'     => (int)$row['status'],
			'settings'   => is_array($settings) ? $settings : [],
			'content'    => $content,
		];
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
