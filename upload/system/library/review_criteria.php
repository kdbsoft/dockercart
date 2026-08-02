<?php
/**
 * ReviewCriteria
 *
 * Helpers for the flexible "what is rated" criteria groups.
 * A group is an ordered list of criteria; each criteria is either a
 * `rating` dimension (1-5) or a `text` field (e.g. Pros / Cons).
 * The overall review rating is the average of all rating-type criteria
 * when at least one exists, otherwise it is provided manually.
 */
class ReviewCriteria {
	/**
	 * Validate a single criteria value by its type.
	 *
	 * @param string $type 'rating'|'text'
	 * @param mixed  $value
	 * @param array<string, mixed> $opts ['required' => bool, 'max_length' => int]
	 * @return string '' when valid, otherwise an error key.
	 */
	public static function validateValue(string $type, $value, array $opts = array()): string {
		if ($type === 'rating') {
			if ($value === '' || $value === null) {
				return !empty($opts['required']) ? 'required' : '';
			}

			if (!is_numeric($value)) {
				return 'rating';
			}

			$rating = (float)$value;

			if ($rating < 1 || $rating > 5) {
				return 'rating';
			}

			return '';
		}

		$text = (string)$value;
		$text = trim($text);

		if ($text === '') {
			return !empty($opts['required']) ? 'required' : '';
		}

		$max_length = isset($opts['max_length']) ? (int)$opts['max_length'] : 1000;

		if (mb_strlen($text) > $max_length) {
			return 'length';
		}

		return '';
	}

	/**
	 * Whether the group contains at least one rating-type criteria.
	 *
	 * @param array<int, array<string, mixed>> $criteria
	 */
	public static function hasRatingCriteria(array $criteria): bool {
		foreach ($criteria as $item) {
			if (isset($item['type']) && $item['type'] === 'rating') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compute the overall rating from rating-type criteria values.
	 * Returns 0.0 when the group has no rating criteria or no valid values.
	 *
	 * @param array<int, array<string, mixed>> $criteria
	 * @param array<int|string, mixed> $values criteria_id => value
	 */
	public static function computeOverallRating(array $criteria, array $values): float {
		$ratings = array();

		foreach ($criteria as $item) {
			if (!isset($item['type']) || $item['type'] !== 'rating') {
				continue;
			}

			$id = isset($item['criteria_id']) ? $item['criteria_id'] : 0;

			if ($id && isset($values[$id]) && is_numeric($values[$id]) && (float)$values[$id] > 0) {
				$ratings[] = (float)$values[$id];
			}
		}

		if (!$ratings) {
			return 0.0;
		}

		return ReviewRating::average($ratings);
	}

	/**
	 * Extract filled text-type criteria values (e.g. Pros / Cons).
	 *
	 * @param array<int, array<string, mixed>> $criteria
	 * @param array<int|string, mixed> $values criteria_id => value
	 * @return array<int, string> criteria_id => trimmed text
	 */
	public static function extractTextValues(array $criteria, array $values): array {
		$out = array();

		foreach ($criteria as $item) {
			if (!isset($item['type']) || $item['type'] !== 'text') {
				continue;
			}

			$id = isset($item['criteria_id']) ? (int)$item['criteria_id'] : 0;
			$value = isset($values[$id]) ? (string)$values[$id] : '';

			if ($id && trim($value) !== '') {
				$out[$id] = trim($value);
			}
		}

		return $out;
	}
}
