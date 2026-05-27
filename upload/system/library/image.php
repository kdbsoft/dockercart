<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Image class
*/
class Image {
	private $file;
	private $image;
	private $width;
	private $height;
	private $bits;
	private $mime;

	/**
	 * Constructor
	 *
	 * @param	string	$file
	 *
 	*/
	public function __construct($file) {
		if (!extension_loaded('gd')) {
			exit('Error: PHP GD is not installed!');
		}
		
		if (is_file($file)) {
			$this->file = $file;

			$info = getimagesize($file);

			$this->width  = $info[0];
			$this->height = $info[1];
			$this->bits = isset($info['bits']) ? $info['bits'] : '';
			$this->mime = isset($info['mime']) ? $info['mime'] : '';

			if ($this->mime == 'image/gif') {
				$this->image = imagecreatefromgif($file);
			} elseif ($this->mime == 'image/png') {
				$this->image = imagecreatefrompng($file);
			} elseif ($this->mime == 'image/jpeg') {
				$this->image = imagecreatefromjpeg($file);
			} elseif ($this->mime == 'image/webp') {
				if (function_exists('imagecreatefromwebp')) {
					$this->image = imagecreatefromwebp($file);
				} else {
					$this->image = false;
					error_log('Warning: GD WebP support is not available (imagecreatefromwebp missing): ' . $file);
				}
			}

			if (!$this->image) {
				error_log('Error: Could not create GD image resource for ' . $file . ' (mime: ' . $this->mime . ')');
			}
		} else {
			error_log('Error: Could not load image ' . $file . '!');
		}
	}
	
	/**
     * 
	 * 
	 * @return	string
     */
	public function getFile() {
		return $this->file;
	}

	/**
     * 
	 * 
	 * @return	array
     */
	public function getImage() {
		return $this->image;
	}
	
	/**
     * 
	 * 
	 * @return	string
     */
	public function getWidth() {
		return $this->width;
	}
	
	/**
     * 
	 * 
	 * @return	string
     */
	public function getHeight() {
		return $this->height;
	}
	
	/**
     * 
	 * 
	 * @return	string
     */
	public function getBits() {
		return $this->bits;
	}
	
	/**
     * 
	 * 
	 * @return	string
     */
	public function getMime() {
		return $this->mime;
	}
	
	/**
     * 
     *
     * @param	string	$file
	 * @param	int		$quality
     */
	public function save($file, int $quality = 90) {
		$info = pathinfo($file);

		$extension = strtolower($info['extension']);
		$directory = dirname($file);

		if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
			error_log('Error: Could not create image cache directory: ' . $directory);
			return;
		}

		if (is_object($this->image) || is_resource($this->image)) {
			if ($extension == 'jpeg' || $extension == 'jpg') {
				if (!imagejpeg($this->image, $file, $quality)) {
					error_log('Error: Failed to write JPEG image: ' . $file);
				}
			} elseif ($extension == 'png') {
				if (!imagepng($this->image, $file)) {
					error_log('Error: Failed to write PNG image: ' . $file);
				}
			} elseif ($extension == 'gif') {
				if (!imagegif($this->image, $file)) {
					error_log('Error: Failed to write GIF image: ' . $file);
				}
			} elseif ($extension == 'webp') {
				if (function_exists('imagewebp')) {
					if (!imagewebp($this->image, $file, $quality)) {
						error_log('Error: Failed to write WebP image: ' . $file);
					}
				} else {
					error_log('Warning: GD WebP support is not available (imagewebp missing), fallback to JPEG: ' . $file);
					$fallback_file = preg_replace('/\.webp$/i', '.jpg', $file);
					if (!imagejpeg($this->image, $fallback_file, $quality)) {
						error_log('Error: Failed to write fallback JPEG image: ' . $fallback_file);
					}
				}
			}

		}
	}
	
	/**
	 * Optimize image at upload time: shrink oversized dimensions and recompress.
	 *
	 * If the longest side exceeds $maxDimension, the image is proportionally
	 * resized down to $maxDimension.  In addition, every supported image is
	 * re-saved with optimised encoder settings:
	 *   - JPEG:  quality 95, implicit EXIF strip
	 *   - PNG:   maximum lossless compression (level 9)
	 *   - WebP:  quality 95
	 *   - GIF:   left untouched (preserves animation)
	 *
	 * The original file is overwritten in-place.
	 *
	 * @param string $file         Absolute path to the image file
	 * @param int    $maxDimension Longest side limit in pixels; 0 = skip optimisation
	 * @return bool                True if the file was modified
	 */
	public static function optimize(string $file, int $maxDimension): bool {
		if (!extension_loaded('gd') || !is_file($file) || $maxDimension <= 0) {
			return false;
		}

		$info = getimagesize($file);

		if (!$info || empty($info[0]) || empty($info[1])) {
			return false;
		}

		$width   = (int)$info[0];
		$height  = (int)$info[1];
		$mime    = isset($info['mime']) ? $info['mime'] : '';
		$ext     = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		$needs_resize  = max($width, $height) > $maxDimension;
		$needs_reencode = in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true);

		if (!$needs_resize && !$needs_reencode) {
			return false;
		}

		$image = null;

		if ($mime == 'image/gif') {
			$image = imagecreatefromgif($file);
		} elseif ($mime == 'image/png') {
			$image = imagecreatefrompng($file);
		} elseif ($mime == 'image/jpeg') {
			$image = imagecreatefromjpeg($file);
		} elseif ($mime == 'image/webp' && function_exists('imagecreatefromwebp')) {
			$image = imagecreatefromwebp($file);
		}

		if (!$image) {
			error_log('Image::optimize: Could not open image ' . $file);
			return false;
		}

		if ($needs_resize) {
			$scale = $maxDimension / max($width, $height);
			$new_w = (int)round($width  * $scale);
			$new_h = (int)round($height * $scale);

			$resized = imagecreatetruecolor($new_w, $new_h);

			if ($mime == 'image/png' || $mime == 'image/webp') {
				imagealphablending($resized, false);
				imagesavealpha($resized, true);
			}

			imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
			imagedestroy($image);
			$image = $resized;
		}

		$success = false;

		if ($ext === 'jpeg' || $ext === 'jpg') {
			$success = imagejpeg($image, $file, 95);
		} elseif ($ext === 'png') {
			$success = imagepng($image, $file, 9);
		} elseif ($ext === 'gif') {
			$success = imagegif($image, $file);
		} elseif ($ext === 'webp') {
			if (function_exists('imagewebp')) {
				$success = imagewebp($image, $file, 95);
			} else {
				$fallback = preg_replace('/\.webp$/i', '.jpg', $file);
				$success  = imagejpeg($image, $fallback, 95);
				if ($success) {
					@unlink($file);
				}
			}
		}

		imagedestroy($image);

		if (!$success) {
			error_log('Image::optimize: Failed to save ' . $file);
		}

		return (bool)$success;
	}

	/**
	 * Analyze the source image and decide whether 'cover' or 'contain'
	 * will produce the best visual result.
	 *
	 * Samples all four edges, then calculates the average colour and
	 * standard deviation (variance) of the non-transparent border
	 * pixels.  Three criteria must be met for 'contain':
	 *
	 *   1. Border is uniform    — stddev < 25  (low colour variation)
	 *   2. Border is light      — average R/G/B all >= 240
	 *
	 * If the border has significant transparency (>= 50 % of samples)
	 * 'contain' is also returned regardless of colour, because
	 * transparent padding blends invisibly.
	 *
	 * Everything else → 'cover'.
	 *
	 * @return string 'cover' | 'contain'
	 */
	private function detectStrategy(): string {
		$w = $this->width;
		$h = $this->height;

		$sample_step   = (int) max(1, min($w, $h) / 20);
		$samples_r     = array();
		$samples_g     = array();
		$samples_b     = array();
		$transparent   = 0;
		$total_samples = 0;

		$collect = function ($x, $y) use (&$samples_r, &$samples_g, &$samples_b, &$transparent, &$total_samples) {
			$total_samples++;
			$color_index = imagecolorat($this->image, $x, $y);
			$rgba        = imagecolorsforindex($this->image, $color_index);

			if (isset($rgba['alpha']) && $rgba['alpha'] >= 96) {
				$transparent++;
				return;
			}

			$samples_r[] = $rgba['red'];
			$samples_g[] = $rgba['green'];
			$samples_b[] = $rgba['blue'];
		};

		// top edge
		for ($x = 0; $x < $w; $x += $sample_step) {
			$collect($x, 0);
		}

		// bottom edge
		for ($x = 0; $x < $w; $x += $sample_step) {
			$collect($x, $h - 1);
		}

		// left edge (skip corners already sampled)
		for ($y = $sample_step; $y < $h - 1; $y += $sample_step) {
			$collect(0, $y);
		}

		// right edge (skip corners already sampled)
		for ($y = $sample_step; $y < $h - 1; $y += $sample_step) {
			$collect($w - 1, $y);
		}

		if ($total_samples === 0) {
			return 'contain';
		}

		// Predominantly transparent border → contain
		if (($transparent / $total_samples) >= 0.50) {
			return 'contain';
		}

		$opaque_count = count($samples_r);
		if ($opaque_count < 8) {
			return 'cover';
		}

		// Average colour of non-transparent border samples
		$avg_r = (int) (array_sum($samples_r) / $opaque_count);
		$avg_g = (int) (array_sum($samples_g) / $opaque_count);
		$avg_b = (int) (array_sum($samples_b) / $opaque_count);

		// Standard deviation (how much border colour varies)
		$var = 0.0;
		for ($i = 0; $i < $opaque_count; $i++) {
			$var += ($samples_r[$i] - $avg_r) ** 2
			      + ($samples_g[$i] - $avg_g) ** 2
			      + ($samples_b[$i] - $avg_b) ** 2;
		}
		$var    /= $opaque_count;
		$stddev  = sqrt($var);

		$is_uniform = $stddev < 25;
		$is_white   = $avg_r >= 240 && $avg_g >= 240 && $avg_b >= 240;

		return ($is_uniform && $is_white) ? 'contain' : 'cover';
	}

	/**
     * Resize image using the specified strategy.
     *
     * Strategy 'contain' (default): scales the image to fit within the target
     * rectangle, padding with a white/transparent background.
     *
     * Strategy 'cover': scales the image so it fully fills the target rectangle
     * and crops from the center — no padding, no distortion, minimal quality loss.
     *
     * Strategy 'hybrid': automatically chooses between 'contain' and 'cover'
     * per image based on border pixel analysis.
     *
     * @param	int		$width
	 * @param	int		$height
	 * @param	string	$default  'w' | 'h' | '' — axis hint (used by contain only)
	 * @param	string	$strategy 'contain' | 'cover' | 'hybrid'
     */
	public function resize(int $width = 0, int $height = 0, $default = '', string $strategy = 'contain') {
		if (!$this->width || !$this->height) {
			return;
		}

		// ── HYBRID strategy — auto-detect best mode per image ──────────────────
		if ($strategy === 'hybrid') {
			$strategy = $this->detectStrategy();
		}

		$scale_w = $width / $this->width;
		$scale_h = $height / $this->height;

		// ── COVER strategy ─────────────────────────────────────────────────────
		// Scale so the image fills the entire target area, then crop the excess
		// from the center. Result has exactly the requested dimensions.
		if ($strategy === 'cover') {
			$scale = max($scale_w, $scale_h);

			// How many source pixels we actually need
			$src_w = (int)round($width  / $scale);
			$src_h = (int)round($height / $scale);

			// Crop from center of the source image
			$src_x = (int)(($this->width  - $src_w) / 2);
			$src_y = (int)(($this->height - $src_h) / 2);

			// Clamp to source boundaries (edge case: target is larger than source)
			$src_x = max(0, $src_x);
			$src_y = max(0, $src_y);
			$src_w = min($src_w, $this->width);
			$src_h = min($src_h, $this->height);

			$image_old = $this->image;
			$this->image = imagecreatetruecolor($width, $height);

			if ($this->mime == 'image/png' || $this->mime == 'image/webp') {
				imagealphablending($this->image, false);
				imagesavealpha($this->image, true);
			}

		imagecopyresampled($this->image, $image_old, 0, 0, $src_x, $src_y, $width, $height, $src_w, $src_h);

		$this->width  = $width;
			$this->height = $height;
			return;
		}

		// ── CONTAIN strategy (original behaviour) ──────────────────────────────
		$scale = 1;

		if ($default == 'w') {
			$scale = $scale_w;
		} elseif ($default == 'h') {
			$scale = $scale_h;
		} else {
			$scale = min($scale_w, $scale_h);
		}

		if ($scale == 1 && $scale_h == $scale_w && ($this->mime != 'image/png' && $this->mime != 'image/webp')) {
			return;
		}

		$new_width  = (int)($this->width  * $scale);
		$new_height = (int)($this->height * $scale);
		$xpos = (int)(($width  - $new_width)  / 2);
		$ypos = (int)(($height - $new_height) / 2);

		$image_old = $this->image;
		$this->image = imagecreatetruecolor($width, $height);

		if ($this->mime == 'image/png' || $this->mime == 'image/webp') {
			imagealphablending($this->image, false);
			imagesavealpha($this->image, true);
			// Fully transparent background
			$background = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
		} else {
			$background = imagecolorallocate($this->image, 255, 255, 255);
		}

		imagefilledrectangle($this->image, 0, 0, $width, $height, $background);

		imagecopyresampled($this->image, $image_old, $xpos, $ypos, 0, 0, $new_width, $new_height, $this->width, $this->height);

		$this->width  = $width;
		$this->height = $height;
	}
	
	/**
     * 
     *
     * @param	string	$watermark
	 * @param	string	$position
     */
	public function watermark($watermark, $position = 'bottomright') {
		switch($position) {
			case 'topleft':
				$watermark_pos_x = 0;
				$watermark_pos_y = 0;
				break;
			case 'topcenter':
				$watermark_pos_x = intval(($this->width - $watermark->getWidth()) / 2);
				$watermark_pos_y = 0;
				break;
			case 'topright':
				$watermark_pos_x = $this->width - $watermark->getWidth();
				$watermark_pos_y = 0;
				break;
			case 'middleleft':
				$watermark_pos_x = 0;
				$watermark_pos_y = intval(($this->height - $watermark->getHeight()) / 2);
				break;
			case 'middlecenter':
				$watermark_pos_x = intval(($this->width - $watermark->getWidth()) / 2);
				$watermark_pos_y = intval(($this->height - $watermark->getHeight()) / 2);
				break;
			case 'middleright':
				$watermark_pos_x = $this->width - $watermark->getWidth();
				$watermark_pos_y = intval(($this->height - $watermark->getHeight()) / 2);
				break;
			case 'bottomleft':
				$watermark_pos_x = 0;
				$watermark_pos_y = $this->height - $watermark->getHeight();
				break;
			case 'bottomcenter':
				$watermark_pos_x = intval(($this->width - $watermark->getWidth()) / 2);
				$watermark_pos_y = $this->height - $watermark->getHeight();
				break;
			case 'bottomright':
				$watermark_pos_x = $this->width - $watermark->getWidth();
				$watermark_pos_y = $this->height - $watermark->getHeight();
				break;
		}
		
		imagealphablending( $this->image, true);
		imagesavealpha( $this->image, true);
		imagecopy($this->image, $watermark->getImage(), $watermark_pos_x, $watermark_pos_y, 0, 0, $watermark->getWidth(), $watermark->getHeight());
	}
	
	/**
     * 
     *
     * @param	int		$top_x
	 * @param	int		$top_y
	 * @param	int		$bottom_x
	 * @param	int		$bottom_y
     */
	public function crop($top_x, $top_y, $bottom_x, $bottom_y) {
		$image_old = $this->image;
		$this->image = imagecreatetruecolor($bottom_x - $top_x, $bottom_y - $top_y);

		imagecopy($this->image, $image_old, 0, 0, $top_x, $top_y, $this->width, $this->height);

		$this->width = $bottom_x - $top_x;
		$this->height = $bottom_y - $top_y;
	}
	
	/**
     * 
     *
     * @param	int		$degree
	 * @param	string	$color
     */
	public function rotate($degree, $color = 'FFFFFF') {
		$rgb = $this->html2rgb($color);

		$this->image = imagerotate($this->image, $degree, imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]));

		$this->width = imagesx($this->image);
		$this->height = imagesy($this->image);
	}
	
	/**
     * 
     *
     */
	private function filter() {
        $args = func_get_args();

        call_user_func_array('imagefilter', $args);
	}
	
	/**
     * 
     *
     * @param	string	$text
	 * @param	int		$x
	 * @param	int		$y 
	 * @param	int		$size
	 * @param	string	$color
     */
	private function text($text, $x = 0, $y = 0, $size = 5, $color = '000000') {
		$rgb = $this->html2rgb($color);

		imagestring($this->image, $size, $x, $y, $text, imagecolorallocate($this->image, $rgb[0], $rgb[1], $rgb[2]));
	}
	
	/**
     * 
     *
     * @param	object	$merge
	 * @param	object	$x
	 * @param	object	$y
	 * @param	object	$opacity
     */
	private function merge($merge, $x = 0, $y = 0, $opacity = 100) {
		imagecopymerge($this->image, $merge->getImage(), $x, $y, 0, 0, $merge->getWidth(), $merge->getHeight(), $opacity);
	}
	
	/**
     * 
     *
     * @param	string	$color
	 * 
	 * @return	array
     */
	private function html2rgb($color) {
		if ($color[0] == '#') {
			$color = substr($color, 1);
		}

		if (strlen($color) == 6) {
			list($r, $g, $b) = [$color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]];
		} elseif (strlen($color) == 3) {
			list($r, $g, $b) = [$color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]];
		} else {
			return false;
		}

		$r = hexdec($r);
		$g = hexdec($g);
		$b = hexdec($b);

		return [$r, $g, $b];
	}
}
