<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use ReviewCriteria;

require_once __DIR__ . '/../../../upload/system/library/review_rating.php';
require_once __DIR__ . '/../../../upload/system/library/review_criteria.php';

class ReviewCriteriaTest extends TestCase {
	public function testValidateRatingValue(): void {
		$this->assertSame('', ReviewCriteria::validateValue('rating', '4'));
		$this->assertSame('rating', ReviewCriteria::validateValue('rating', 4.5));
		$this->assertSame('rating', ReviewCriteria::validateValue('rating', '7'));
		$this->assertSame('rating', ReviewCriteria::validateValue('rating', 'abc'));
		$this->assertSame('required', ReviewCriteria::validateValue('rating', '', ['required' => true]));
		$this->assertSame('', ReviewCriteria::validateValue('rating', '', ['required' => false]));
	}

	public function testValidateTextValue(): void {
		$this->assertSame('', ReviewCriteria::validateValue('text', '  Good product  '));
		$this->assertSame('required', ReviewCriteria::validateValue('text', '   ', ['required' => true]));
		$this->assertSame('', ReviewCriteria::validateValue('text', '', ['required' => false]));
		$this->assertSame('length', ReviewCriteria::validateValue('text', str_repeat('a', 1001), ['max_length' => 1000]));
	}

	public function testHasRatingCriteria(): void {
		$this->assertTrue(ReviewCriteria::hasRatingCriteria([['type' => 'text'], ['type' => 'rating']]));
		$this->assertFalse(ReviewCriteria::hasRatingCriteria([['type' => 'text'], ['type' => 'text']]));
		$this->assertFalse(ReviewCriteria::hasRatingCriteria([]));
	}

	public function testComputeOverallRating(): void {
		$criteria = [
			['criteria_id' => 1, 'type' => 'text'],
			['criteria_id' => 2, 'type' => 'rating'],
			['criteria_id' => 3, 'type' => 'rating'],
		];

		$this->assertSame(4.5, ReviewCriteria::computeOverallRating($criteria, [2 => '5', 3 => '4']));
		$this->assertSame(0.0, ReviewCriteria::computeOverallRating($criteria, []));
		$this->assertSame(0.0, ReviewCriteria::computeOverallRating([['type' => 'text']], ['x' => '1']));
	}

	public function testExtractTextValues(): void {
		$criteria = [
			['criteria_id' => 1, 'type' => 'text'],
			['criteria_id' => 2, 'type' => 'rating'],
			['criteria_id' => 3, 'type' => 'text'],
		];

		$values = ReviewCriteria::extractTextValues($criteria, [1 => '  Solid build  ', 2 => '4', 3 => '']);

		$this->assertSame([1 => 'Solid build'], $values);
	}
}
