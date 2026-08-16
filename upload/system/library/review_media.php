<?php
/**
 * ReviewMedia
 *
 * Validators for review attachments: up to 3 images and 1 video per review.
 * Images must be real image files (verified with getimagesize) of an allowed
 * mime type; the app re-encodes them through GD before persisting to strip
 * embedded payloads. Video is either a YouTube URL (server-side ID
 * extraction) or an uploaded mp4 file.
 */
class ReviewMedia {
	/** @var array<int, string> */
	private const IMAGE_MIMES = array('image/jpeg', 'image/png', 'image/webp');

	/** @var array<int, string> */
	private const IMAGE_EXTS = array('jpg', 'jpeg', 'png', 'webp');

	/** @var array<int, string> */
	private const VIDEO_EXTS = array('mp4', 'm4v', 'webm');

	/** @var array<int, string> */
	private const VIDEO_MIMES = array('video/mp4', 'video/webm', 'application/mp4');

	/**
	 * Validate an uploaded review image.
	 *
	 * @param array<string, mixed> $file A $_FILES entry.
	 * @param array<string, mixed> $opts ['max_size' => int bytes, 'check_uploaded' => bool]
	 * @return array{ok: bool, error: string, mime: string, width: int, height: int}
	 */
	public static function validateImage(array $file, array $opts = array()): array {
		$max_size = isset($opts['max_size']) ? (int)$opts['max_size'] : 5242880;
		$check_uploaded = !array_key_exists('check_uploaded', $opts) || (bool)$opts['check_uploaded'];

		$tmp = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
		$error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

		if ($tmp === '' || $error !== UPLOAD_ERR_OK) {
			return self::imageResult(false, 'upload', '');
		}

		if ($check_uploaded && !is_uploaded_file($tmp)) {
			return self::imageResult(false, 'upload', '');
		}

		if (isset($file['size']) && (int)$file['size'] > $max_size) {
			return self::imageResult(false, 'size', '');
		}

		$info = @getimagesize($tmp);

		if ($info === false || !isset($info['mime'])) {
			return self::imageResult(false, 'image', '');
		}

		if (!in_array($info['mime'], self::IMAGE_MIMES, true)) {
			return self::imageResult(false, 'type', '');
		}

		return array(
			'ok'     => true,
			'error'  => '',
			'mime'   => $info['mime'],
			'width'  => (int)$info[0],
			'height' => (int)$info[1],
		);
	}

	/**
	 * Validate an uploaded review video file.
	 *
	 * @param array<string, mixed> $file A $_FILES entry.
	 * @param array<string, mixed> $opts ['max_size' => int bytes, 'check_uploaded' => bool]
	 * @return array{ok: bool, error: string, ext: string}
	 */
	public static function validateVideo(array $file, array $opts = array()): array {
		$max_size = isset($opts['max_size']) ? (int)$opts['max_size'] : 52428800;
		$check_uploaded = !array_key_exists('check_uploaded', $opts) || (bool)$opts['check_uploaded'];

		$tmp = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
		$error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

		if ($tmp === '' || $error !== UPLOAD_ERR_OK) {
			return array('ok' => false, 'error' => 'upload', 'ext' => '');
		}

		if ($check_uploaded && !is_uploaded_file($tmp)) {
			return array('ok' => false, 'error' => 'upload', 'ext' => '');
		}

		if (isset($file['size']) && (int)$file['size'] > $max_size) {
			return array('ok' => false, 'error' => 'size', 'ext' => '');
		}

		$name = isset($file['name']) ? (string)$file['name'] : '';
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

		if (!in_array($ext, self::VIDEO_EXTS, true)) {
			return array('ok' => false, 'error' => 'type', 'ext' => '');
		}

		if (isset($file['type']) && !in_array($file['type'], self::VIDEO_MIMES, true)) {
			return array('ok' => false, 'error' => 'type', 'ext' => '');
		}

		return array('ok' => true, 'error' => '', 'ext' => $ext);
	}

	/**
	 * Extract a YouTube video ID from a URL or a bare ID.
	 */
	public static function extractYouTubeId(string $url): string {
		$patterns = array(
			'/youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})/',
			'/youtu\.be\/([A-Za-z0-9_-]{11})/',
			'/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/',
			'/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/',
			'/^([A-Za-z0-9_-]{11})$/',
		);

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, trim($url), $matches)) {
				return $matches[1];
			}
		}

		return '';
	}

	/**
	 * Whitelist an extension to the set supported for review media.
	 */
	public static function sanitizeExtension(string $ext): string {
		$ext = strtolower(trim($ext));
		$allowed = array_merge(self::IMAGE_EXTS, self::VIDEO_EXTS);

		return in_array($ext, $allowed, true) ? $ext : '';
	}

	/**
	 * Load an image into a GD resource for re-encoding (payload stripping).
	 *
	 * @return \GdImage|null
	 */
	public static function loadImage(string $path, string $mime) {
		$info = @getimagesize($path);

		if ($info === false || !in_array($info['mime'], self::IMAGE_MIMES, true)) {
			return null;
		}

		$image = null;

		if ($mime === 'image/png') {
			$image = @imagecreatefrompng($path);
		} elseif ($mime === 'image/webp') {
			$image = @imagecreatefromwebp($path);
		} else {
			$image = @imagecreatefromjpeg($path);
		}

		if ($image === false) {
			return null;
		}

		return $image;
	}

	/**
	 * Build the upload directory path for a review, relative to DIR_IMAGE.
	 */
	public static function reviewImageDirectory(int $review_id, string $sub = ''): string {
		$dir = 'catalog/reviews';

		if ($review_id > 0) {
			$dir .= '/' . intdiv($review_id, 1000) . '/' . $review_id;
		}

		if ($sub !== '') {
			$dir .= '/' . $sub;
		}

		return $dir;
	}

	/**
	 * @return array{ok: bool, error: string, mime: string, width: int, height: int}
	 */
	private static function imageResult(bool $ok, string $error, string $mime): array {
		return array('ok' => $ok, 'error' => $error, 'mime' => $mime, 'width' => 0, 'height' => 0);
	}
}
