<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use ReviewRating;

require_once __DIR__ . '/../../../upload/system/library/review_rating.php';

class ReviewRatingTest extends TestCase {
	public function testAverageOfEmptyReturnsZero(): void {
		$this->assertSame(0.0, ReviewRating::average([]));
		$this->assertSame(0.0, ReviewRating::average([0, -1, '']));
	}

	public function testAverageRoundsToOneDecimal(): void {
		// (5 + 4 + 3) / 3 = 4.0
		$this->assertSame(4.0, ReviewRating::average([5, 4, 3]));
		// (5 + 4) / 2 = 4.5
		$this->assertSame(4.5, ReviewRating::average([5, 4]));
		// (5 + 4 + 4 + 4 + 4) / 5 = 4.2
		$this->assertSame(4.2, ReviewRating::average([5, 4, 4, 4, 4]));
		// (5 + 4 + 4 + 3) / 4 = 4.0
		$this->assertSame(4.0, ReviewRating::average([5, 4, 4, 3]));
	}

	public function testAverageIgnoresZeroValues(): void {
		// zeros are ignored: (5 + 5) / 2 = 5
		$this->assertSame(5.0, ReviewRating::average([5, 5, 0]));
	}

	public function testFormat(): void {
		$this->assertSame('4.2', ReviewRating::format(4.2));
		$this->assertSame('5', ReviewRating::format(5.0));
		$this->assertSame('0', ReviewRating::format(0.0));
	}

	public function testDistributionBucketsByRoundedRating(): void {
		$rows = [
			['rating' => 5],
			['rating' => 5],
			['rating' => 4],
			['rating' => 4.5], // rounds to 5
			['rating' => 3.2], // rounds to 3
			['rating' => 2],
		];

		$distribution = ReviewRating::distribution($rows);

		$this->assertSame(3, $distribution[5]);
		$this->assertSame(1, $distribution[4]);
		$this->assertSame(1, $distribution[3]);
		$this->assertSame(1, $distribution[2]);
		$this->assertSame(0, $distribution[1]);
	}

	public function testDistributionAcceptsPlainValues(): void {
		$distribution = ReviewRating::distribution([5, 1, 3]);

		$this->assertSame(1, $distribution[5]);
		$this->assertSame(1, $distribution[3]);
		$this->assertSame(1, $distribution[1]);
	}

	public function testDistributionClampsOutOfRange(): void {
		$distribution = ReviewRating::distribution([9, 0, 6]);

		$this->assertSame(2, $distribution[5]);
		$this->assertSame(1, $distribution[1]);
	}

	public function testStarComponents(): void {
		$this->assertSame(['full' => 4, 'half' => false, 'empty' => 1], ReviewRating::starComponents(4.2));
		$this->assertSame(['full' => 4, 'half' => true, 'empty' => 0], ReviewRating::starComponents(4.5));
		$this->assertSame(['full' => 5, 'half' => false, 'empty' => 0], ReviewRating::starComponents(4.9));
		$this->assertSame(['full' => 0, 'half' => false, 'empty' => 5], ReviewRating::starComponents(0.0));
	}
}
