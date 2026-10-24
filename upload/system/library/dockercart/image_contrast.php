<?php
/**
 * Hero image contrast helper.
 *
 * The blog post (and any other hero block) draws its heading on top of a
 * background image. Which text colour is readable depends on how light that
 * image is, so this helper answers a single question:
 *
 *     ImageContrast::prefersDarkText(ImageContrast::averageLuminance($file));
 *
 * The image is averaged with the WCAG 2.1 relative-luminance formula (0 = black,
 * 1 = white) on a small downscaled copy, so a bright photo gets dark text and a
 * dark photo gets white text. The result is cached on disk (keyed by path +
 * mtime + size) because decoding a full-size photo on every request would be
 * wasteful; delete `image/cache/contrast/` (or the whole image cache) to
 * recompute.
 *
 * GD is optional: without it `averageLuminance()` returns null and callers fall
 * back to the light-on-dark default. The decision functions are pure, so they
 * stay unit-testable without the extension.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart;

class ImageContrast {
	/**
	 * Average relative luminance at (or above) which the background counts as
	 * light and the hero switches to dark text.
	 *
	 * WCAG puts the white/black text crossover at ~0.18, but a mid-tone photo
	 * reads better with dark text plus a light scrim, so the bar is set higher.
	 */
	public const LIGHT_THRESHOLD = 0.45;

	/** Longest side of the downscaled copy used for averaging. */
	private const SAMPLE_SIZE = 24;

	/** Disk cache directory, relative to DIR_IMAGE. */
	private const CACHE_DIR = 'cache/contrast/';

	/**
	 * WCAG 2.1 relative luminance of an sRGB colour.
	 *
	 * @param  int   $red   0..255
	 * @param  int   $green 0..255
	 * @param  int   $blue  0..255
	 * @return float 0.0 (black) .. 1.0 (white)
	 */
	public static function relativeLuminance(int $red, int $green, int $blue): float {
		$linearize = static function (int $channel): float {
			$value = max(0, min(255, $channel)) / 255;

			return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
		};

		return 0.2126 * $linearize($red) + 0.7152 * $linearize($green) + 0.0722 * $linearize($blue);
	}

	/**
	 * Should the text drawn on top of a background with this luminance be dark?
	 *
	 * An unknown luminance (no image, unreadable file, no GD) keeps the light
	 * text of the default gradient hero.
	 *
	 * @param  float|null $luminance Result of averageLuminance(); null = unknown
	 * @return bool
	 */
	public static function prefersDarkText(?float $luminance): bool {
		return $luminance !== null && $luminance >= self::LIGHT_THRESHOLD;
	}

	/**
	 * Average relative luminance of an image file.
	 *
	 * @param  string $file Absolute path to the image
	 * @return float|null   null when the image cannot be read (missing file, no GD)
	 */
	public static function averageLuminance(string $file): ?float {
		if (!is_file($file) || !extension_loaded('gd')) {
			return null;
		}

		$cache_file = self::cacheFile($file);

		if ($cache_file !== null && is_file($cache_file)) {
			$cached = trim((string)@file_get_contents($cache_file));

			if ($cached !== '' && is_numeric($cached)) {
				return (float)$cached;
			}
		}

		$luminance = self::sample($file);

		if ($luminance !== null && $cache_file !== null) {
			$directory = dirname($cache_file);

			if (is_dir($directory) || @mkdir($directory, 0777, true) || is_dir($directory)) {
				@file_put_contents($cache_file, sprintf('%.6F', $luminance));
			}
		}

		return $luminance;
	}

	/**
	 * Cache file for a source image, or null when caching is not possible
	 * (no DIR_IMAGE, unresolvable path). The key includes mtime and size, so a
	 * replaced image is recomputed automatically.
	 *
	 * @param  string $file
	 * @return string|null
	 */
	private static function cacheFile(string $file): ?string {
		if (!defined('DIR_IMAGE')) {
			return null;
		}

		$real = realpath($file);

		if ($real === false) {
			return null;
		}

		$key = sha1($real . '|' . (string)@filemtime($real) . '|' . (string)@filesize($real));

		return rtrim(DIR_IMAGE, '/') . '/' . self::CACHE_DIR . $key . '.txt';
	}

	/**
	 * Downscale the image (aspect ratio kept, no padding, no crop), flatten it
	 * onto white so transparency is not mistaken for black, and average the
	 * relative luminance of every sampled pixel.
	 *
	 * @param  string $file
	 * @return float|null
	 */
	private static function sample(string $file): ?float {
		$info = @getimagesize($file);

		if (!$info || empty($info[0]) || empty($info[1])) {
			return null;
		}

		$mime = isset($info['mime']) ? (string)$info['mime'] : '';

		switch ($mime) {
			case 'image/jpeg':
				$source = @imagecreatefromjpeg($file);
				break;
			case 'image/png':
				$source = @imagecreatefrompng($file);
				break;
			case 'image/gif':
				$source = @imagecreatefromgif($file);
				break;
			case 'image/webp':
				$source = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
				break;
			default:
				$source = false;
		}

		if (!$source) {
			return null;
		}

		$width  = (int)$info[0];
		$height = (int)$info[1];

		$sample_width  = self::SAMPLE_SIZE;
		$sample_height = max(1, (int)round($sample_width * $height / $width));

		if ($sample_height > self::SAMPLE_SIZE) {
			$sample_height = self::SAMPLE_SIZE;
			$sample_width  = max(1, (int)round($sample_height * $width / $height));
		}

		$sample = imagecreatetruecolor($sample_width, $sample_height);

		if (!$sample) {
			imagedestroy($source);

			return null;
		}

		$white = imagecolorallocate($sample, 255, 255, 255);
		imagefilledrectangle($sample, 0, 0, $sample_width, $sample_height, $white);
		imagecopyresampled($sample, $source, 0, 0, 0, 0, $sample_width, $sample_height, $width, $height);

		$total = 0.0;

		for ($x = 0; $x < $sample_width; $x++) {
			for ($y = 0; $y < $sample_height; $y++) {
				$rgb = imagecolorsforindex($sample, imagecolorat($sample, $x, $y));

				$total += self::relativeLuminance((int)$rgb['red'], (int)$rgb['green'], (int)$rgb['blue']);
			}
		}

		imagedestroy($sample);
		imagedestroy($source);

		return $total / ($sample_width * $sample_height);
	}
}
