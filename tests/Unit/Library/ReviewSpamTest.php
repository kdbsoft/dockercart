<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use ReviewSpam;

require_once __DIR__ . '/../../../upload/system/library/review_spam.php';

class ReviewSpamTest extends TestCase {
	public function testHoneypotFilled(): void {
		$this->assertTrue(ReviewSpam::honeypotFilled('http://spam'));
		$this->assertFalse(ReviewSpam::honeypotFilled(''));
		$this->assertFalse(ReviewSpam::honeypotFilled(null));
	}

	public function testIsRateLimited(): void {
		$this->assertTrue(ReviewSpam::isRateLimited(5, 5));
		$this->assertTrue(ReviewSpam::isRateLimited(6, 5));
		$this->assertFalse(ReviewSpam::isRateLimited(4, 5));
		$this->assertFalse(ReviewSpam::isRateLimited(0, 0));
	}

	public function testContainsSpamPatterns(): void {
		$this->assertTrue(ReviewSpam::containsSpamPatterns('buy now at www.spam.com and http://vip.example.net and www.bonus.example.org'));
		$this->assertFalse(ReviewSpam::containsSpamPatterns('Great quality product, worth the money.'));
		$this->assertTrue(ReviewSpam::containsSpamPatterns('Contact me at spam@example.com'));
	}

	public function testIpHashIsDeterministic(): void {
		$this->assertSame(ReviewSpam::ipHash('192.168.1.1'), ReviewSpam::ipHash('192.168.1.1'));
		$this->assertNotSame(ReviewSpam::ipHash('192.168.1.1'), ReviewSpam::ipHash('192.168.1.2'));
	}
}
