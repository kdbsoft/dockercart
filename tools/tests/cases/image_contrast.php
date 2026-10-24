<?php
/**
 * Hero background contrast helper.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\ImageContrast;

return [
	'luminance: black is 0 and white is 1' => static function (): void {
		Assert::same(0.0, ImageContrast::relativeLuminance(0, 0, 0));
		Assert::same(1.0, ImageContrast::relativeLuminance(255, 255, 255));
	},

	'luminance: green weighs more than red, red more than blue' => static function (): void {
		$red   = ImageContrast::relativeLuminance(255, 0, 0);
		$green = ImageContrast::relativeLuminance(0, 255, 0);
		$blue  = ImageContrast::relativeLuminance(0, 0, 255);

		Assert::true($green > $red, 'green should be lighter than red');
		Assert::true($red > $blue, 'red should be lighter than blue');
		Assert::true(abs($green - 0.7152) < 0.0001, 'green should match the WCAG coefficient');
	},

	'luminance: out-of-range channels are clamped' => static function (): void {
		Assert::same(0.0, ImageContrast::relativeLuminance(-20, -1, 0));
		Assert::same(1.0, ImageContrast::relativeLuminance(300, 999, 255));
	},

	'luminance: mid grey is perceptually darker than 0.5' => static function (): void {
		$grey = ImageContrast::relativeLuminance(128, 128, 128);

		Assert::true($grey > 0.2 && $grey < 0.25, 'unexpected mid-grey luminance: ' . $grey);
	},

	'contrast: an unknown luminance keeps light text' => static function (): void {
		Assert::false(ImageContrast::prefersDarkText(null));
	},

	'contrast: the decision flips at the documented threshold' => static function (): void {
		$threshold = ImageContrast::LIGHT_THRESHOLD;

		Assert::false(ImageContrast::prefersDarkText($threshold - 0.01));
		Assert::true(ImageContrast::prefersDarkText($threshold));
		Assert::true(ImageContrast::prefersDarkText(1.0));
		Assert::false(ImageContrast::prefersDarkText(0.0));
	},

	'contrast: dark photos get light text, bright photos dark text' => static function (): void {
		// A photo of a dark forest vs. a photo of a bright sky.
		$dark_photo  = ImageContrast::relativeLuminance(24, 32, 28);
		$bright_sky  = ImageContrast::relativeLuminance(220, 232, 245);

		Assert::false(ImageContrast::prefersDarkText($dark_photo), 'dark photo must keep white text');
		Assert::true(ImageContrast::prefersDarkText($bright_sky), 'bright photo must switch to dark text');
	},

	'contrast: a missing image has no luminance' => static function (): void {
		Assert::same(null, ImageContrast::averageLuminance(sys_get_temp_dir() . '/dockercart-does-not-exist.png'));
	},

	'contrast: a non-image file has no luminance' => static function (): void {
		$file = tempnam(sys_get_temp_dir(), 'dc-contrast');
		file_put_contents($file, 'not an image');

		try {
			Assert::same(null, ImageContrast::averageLuminance($file));
		} finally {
			@unlink($file);
		}
	},

	'contrast: sampled luminance matches black and white images' => static function (): void {
		if (!extension_loaded('gd')) {
			throw new SkippedTest('GD is not available');
		}

		$files = [];

		foreach ([[0, 0, 0], [255, 255, 255]] as $index => [$red, $green, $blue]) {
			$image = imagecreatetruecolor(40, 24);
			imagefilledrectangle($image, 0, 0, 40, 24, imagecolorallocate($image, $red, $green, $blue));

			$file = tempnam(sys_get_temp_dir(), 'dc-contrast') . '.png';
			imagepng($image, $file);
			imagedestroy($image);

			$files[$index] = $file;
		}

		try {
			$black = ImageContrast::averageLuminance($files[0]);
			$white = ImageContrast::averageLuminance($files[1]);

			Assert::true($black !== null && $black < 0.01, 'black image should average ~0');
			Assert::true($white !== null && $white > 0.99, 'white image should average ~1');

			Assert::false(ImageContrast::prefersDarkText($black));
			Assert::true(ImageContrast::prefersDarkText($white));
		} finally {
			foreach ($files as $file) {
				@unlink($file);
			}
		}
	},
];
