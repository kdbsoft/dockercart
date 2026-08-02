<?php
declare(strict_types=1);

/**
 * Resolves the flexible "what is rated" criteria group for a product.
 *
 * The group is picked per category (oc_category.review_criteria_group_id)
 * with a fallback chain: product main category -> first assigned category ->
 * the store's default group. The default group is seeded with two text
 * criteria: Pros and Cons.
 */
class ModelCatalogReviewCriteria extends Model {
	/**
	 * ID of the default criteria group (is_default = 1).
	 */
	public function getDefaultCriteriaGroup(): int {
		$query = $this->db->query("SELECT criteria_group_id FROM " . DB_PREFIX . "review_criteria_group WHERE is_default = '1' ORDER BY criteria_group_id ASC LIMIT 1");

		return $query->num_rows ? (int)$query->row['criteria_group_id'] : 0;
	}

	/**
	 * Resolve the criteria group id for a product via the category chain.
	 */
	public function getProductCriteriaGroupId(int $product_id): int {
		$candidate_ids = array();

		$main = $this->db->query("SELECT main_category_id FROM " . DB_PREFIX . "product WHERE product_id = '" . $product_id . "' LIMIT 1");

		if ($main->num_rows && (int)$main->row['main_category_id'] > 0) {
			$candidate_ids[] = (int)$main->row['main_category_id'];
		}

		$categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $product_id . "'");

		foreach ($categories->rows as $row) {
			$candidate_ids[] = (int)$row['category_id'];
		}

		$candidate_ids = array_unique($candidate_ids);

		foreach ($candidate_ids as $category_id) {
			$query = $this->db->query("SELECT review_criteria_group_id FROM " . DB_PREFIX . "category WHERE category_id = '" . $category_id . "' AND review_criteria_group_id IS NOT NULL AND review_criteria_group_id > 0 LIMIT 1");

			if ($query->num_rows) {
				return (int)$query->row['review_criteria_group_id'];
			}
		}

		return $this->getDefaultCriteriaGroup();
	}

	/**
	 * Resolve the full criteria group (id + localized criteria) for a product.
	 *
	 * @return array{criteria_group_id: int, criteria: array<int, array<string, mixed>>}
	 */
	public function getProductCriteriaGroup(int $product_id): array {
		return $this->getCriteriaGroup($this->getProductCriteriaGroupId($product_id));
	}

	/**
	 * Return a criteria group with its localized criteria.
	 *
	 * @return array{criteria_group_id: int, criteria: array<int, array<string, mixed>>}
	 */
	public function getCriteriaGroup(int $criteria_group_id): array {
		if ($criteria_group_id <= 0) {
			$criteria_group_id = $this->getDefaultCriteriaGroup();
		}

		$criteria = $this->getCriteriaWithNames($criteria_group_id);

		return array(
			'criteria_group_id' => $criteria_group_id,
			'criteria'          => $criteria,
		);
	}

	/**
	 * Return the default group with its localized criteria.
	 *
	 * @return array{criteria_group_id: int, criteria: array<int, array<string, mixed>>}
	 */
	public function getDefaultGroup(): array {
		return $this->getCriteriaGroup($this->getDefaultCriteriaGroup());
	}

	/**
	 * All group ids that exist, in display order.
	 *
	 * @return array<int, int>
	 */
	public function getGroupIds(): array {
		$query = $this->db->query("SELECT criteria_group_id FROM " . DB_PREFIX . "review_criteria_group ORDER BY is_default DESC, sort_order ASC, criteria_group_id ASC");

		return array_map('intval', array_column($query->rows, 'criteria_group_id'));
	}

	/**
	 * Group name for the current language.
	 */
	public function getGroupName(int $criteria_group_id): string {
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id = '" . $criteria_group_id . "' AND language_id = '" . $language_id . "' LIMIT 1");

		if ($query->num_rows) {
			return $query->row['name'];
		}

		$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "review_criteria_group_description WHERE criteria_group_id = '" . $criteria_group_id . "' ORDER BY language_id ASC LIMIT 1");

		return $query->num_rows ? $query->row['name'] : '';
	}

	/**
	 * Criteria of a group enriched with localized names (current language
	 * with a fallback to the first available description).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function getCriteriaWithNames(int $criteria_group_id): array {
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT criteria_id, type, is_required, sort_order FROM " . DB_PREFIX . "review_criteria WHERE criteria_group_id = '" . $criteria_group_id . "' ORDER BY sort_order ASC, criteria_id ASC");

		$criteria = $query->rows;

		if (!$criteria) {
			return $criteria;
		}

		$ids = array_map('intval', array_column($criteria, 'criteria_id'));

		$descriptions = $this->db->query("SELECT criteria_id, language_id, name, help FROM " . DB_PREFIX . "review_criteria_description WHERE criteria_id IN (" . implode(',', $ids) . ") ORDER BY language_id ASC");

		$by_id = array();

		foreach ($descriptions->rows as $row) {
			$criteria_id = (int)$row['criteria_id'];

			if (!isset($by_id[$criteria_id])) {
				$by_id[$criteria_id] = array();
			}

			$by_id[$criteria_id][(int)$row['language_id']] = array(
				'name' => (string)$row['name'],
				'help' => (string)$row['help'],
			);
		}

		foreach ($criteria as &$item) {
			$criteria_id = (int)$item['criteria_id'];

			if (isset($by_id[$criteria_id][$language_id])) {
				$item['name'] = $by_id[$criteria_id][$language_id]['name'];
				$item['help'] = $by_id[$criteria_id][$language_id]['help'];
			} elseif (!empty($by_id[$criteria_id])) {
				$fallback = reset($by_id[$criteria_id]);
				$item['name'] = $fallback['name'];
				$item['help'] = $fallback['help'];
			} else {
				$item['name'] = '';
				$item['help'] = '';
			}
		}

		unset($item);

		return $criteria;
	}
}
