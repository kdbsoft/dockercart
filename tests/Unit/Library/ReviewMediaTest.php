<?php
declare(strict_types=1);

namespace Tests\Unit\Library;

use PHPUnit\Framework\TestCase;
use ReviewMedia;

require_once __DIR__ . '/../../../upload/system/library/review_media.php';

class ReviewMediaTest extends TestCase {
	public function testExtractYouTubeId(): void {
		$this->assertSame('dQw4w9WgXcQ', ReviewMedia::extractYouTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
		$this->assertSame('dQw4w9WgXcQ', ReviewMedia::extractYouTubeId('https://youtu.be/dQw4w9WgXcQ'));
		$this->assertSame('dQw4w9WgXcQ', ReviewMedia::extractYouTubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'));
		$this->assertSame('dQw4w9WgXcQ', ReviewMedia::extractYouTubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
		$this->assertSame('dQw4w9WgXcQ', ReviewMedia::extractYouTubeId('dQw4w9WgXcQ'));
		$this->assertSame('', ReviewMedia::extractYouTubeId('https://example.com/video/123'));
		$this->assertSame('', ReviewMedia::extractYouTubeId(''));
	}

	public function testSanitizeExtension(): void {
		$this->assertSame('jpg', ReviewMedia::sanitizeExtension('JPG'));
		$this->assertSame('png', ReviewMedia::sanitizeExtension('png'));
		$this->assertSame('mp4', ReviewMedia::sanitizeExtension('mp4'));
		$this->assertSame('', ReviewMedia::sanitizeExtension('php'));
	}

	public function testValidateImageAcceptsRealJpeg(): void {
		if (!function_exists('imagecreatetruecolor')) {
			$this->markTestSkipped('GD not available');
		}

		$tmp = tempnam(sys_get_temp_dir(), 'rvw');
		$image = imagecreatetruecolor(100, 50);
		imagejpeg($image, $tmp);
		imagedestroy($image);

		$result = ReviewMedia::validateImage([
			'name'     => 'photo.jpg',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => (int)filesize($tmp),
		], ['check_uploaded' => false]);

		@unlink($tmp);

		$this->assertTrue($result['ok']);
		$this->assertSame('image/jpeg', $result['mime']);
	}

	public function testValidateImageRejectsNonImage(): void {
		$tmp = tempnam(sys_get_temp_dir(), 'rvw');
		file_put_contents($tmp, '<?php echo "hi"; ?>');

		$result = ReviewMedia::validateImage([
			'name'     => 'evil.php',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => (int)filesize($tmp),
		], ['check_uploaded' => false]);

		@unlink($tmp);

		$this->assertFalse($result['ok']);
		$this->assertSame('image', $result['error']);
	}

	public function testValidateImageRejectsOversize(): void {
		$result = ReviewMedia::validateImage([
			'name'     => 'photo.jpg',
			'tmp_name' => '/tmp/nonexistent.jpg',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 99999999,
		], ['check_uploaded' => false, 'max_size' => 1024]);

		$this->assertFalse($result['ok']);
		$this->assertSame('size', $result['error']);
	}

	public function testValidateVideo(): void {
		$result = ReviewMedia::validateVideo([
			'name'     => 'clip.mp4',
			'tmp_name' => '/tmp/clip.mp4',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1000,
			'type'     => 'video/mp4',
		], ['check_uploaded' => false]);

		$this->assertTrue($result['ok']);
		$this->assertSame('mp4', $result['ext']);
	}

	public function testValidateVideoRejectsWrongExtension(): void {
		$result = ReviewMedia::validateVideo([
			'name'     => 'clip.exe',
			'tmp_name' => '/tmp/clip.exe',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1000,
			'type'     => 'application/x-msdownload',
		], ['check_uploaded' => false]);

		$this->assertFalse($result['ok']);
		$this->assertSame('type', $result['error']);
	}

	public function testReviewImageDirectory(): void {
		$this->assertSame('catalog/reviews/42', ReviewMedia::reviewImageDirectory(42));
		$this->assertSame('catalog/reviews/42', ReviewMedia::reviewImageDirectory(42, ''));
	}
}
