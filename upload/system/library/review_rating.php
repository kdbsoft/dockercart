<?php
/**
 * ReviewRating
 *
 * Rating math for the review system: fractional averages (e.g. 4.2),
 * star distributions and star display components. Pure static helpers,
 * no registry / DB access.
 */
class ReviewRating {
	/**
	 * Average of non-empty ratings rounded to one decimal place.
	 *
	 * @param array<int|float|string> $ratings
	 */
	public static function average(array $ratings): float {
		$clean = array();

		foreach ($ratings as $rating) {
			if (is_numeric($rating) && (float)$rating > 0) {
				$clean[] = (float)$rating;
			}
		}

		if (!$clean) {
			return 0.0;
		}

		return round(array_sum($clean) / count($clean), 1);
	}

	/**
	 * Format a rating as a short string: 4.2, 4.5, 5. A trailing ".0"
	 * is dropped so whole ratings render without a fractional part.
	 */
	public static function format(float $rating): string {
		$formatted = number_format($rating, 1, '.', '');

		if (str_ends_with($formatted, '.0')) {
			return substr($formatted, 0, -2);
		}

		return $formatted;
	}

	/**
	 * Build a star distribution map [1..5 => count] from raw rating rows.
	 * Rows may be rows of oc_review (['rating' => ..]) or plain values.
	 *
	 * @param array<int, array<string, mixed>|float|int|string> $rows
	 * @return array<int, int>
	 */
	public static function distribution(array $rows): array {
		$distribution = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);

		foreach ($rows as $row) {
			$value = is_array($row) ? (isset($row['rating']) ? $row['rating'] : null) : $row;

			if (!is_numeric($value)) {
				continue;
			}

			$bucket = (int)round((float)$value);
			$bucket = max(1, min(5, $bucket));

			$distribution[$bucket]++;
		}

		return $distribution;
	}

	/**
	 * Decompose a fractional rating into full / half / empty star counts
	 * for 1..5 star displays.
	 *
	 * @return array{full: int, half: bool, empty: int}
	 */
	public static function starComponents(float $rating): array {
		$full = (int)floor($rating);
		$fraction = $rating - $full;
		$half = false;

		if ($fraction >= 0.75) {
			$full++;
		} elseif ($fraction >= 0.25) {
			$half = true;
		}

		$full = (int)min(5, $full);
		$empty = max(0, 5 - $full - ($half ? 1 : 0));

		return array('full' => $full, 'half' => $half, 'empty' => $empty);
	}
}
