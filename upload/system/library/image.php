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

		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
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

			imagedestroy($this->image);
		}
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
     * @param	int		$width
	 * @param	int		$height
	 * @param	string	$default  'w' | 'h' | '' — axis hint (used by contain only)
	 * @param	string	$strategy 'contain' | 'cover'
     */
	public function resize(int $width = 0, int $height = 0, $default = '', string $strategy = 'contain') {
		if (!$this->width || !$this->height) {
			return;
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
			imagedestroy($image_old);

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
		imagedestroy($image_old);

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

		imagedestroy($watermark->getImage());
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
		imagedestroy($image_old);

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
