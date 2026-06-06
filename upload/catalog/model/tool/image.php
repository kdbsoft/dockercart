<?php
class ModelToolImage extends Model {
	/**
	 * Resize an image and return its public URL.
	 *
	 * The resize strategy is controlled by the theme setting
	 * `theme_dockercart_image_resize_mode` (values: 'contain' | 'cover').
	 *
	 * 'contain' (default) — fit inside the rectangle, pad with background.
	 * 'cover'             — fill and crop from center, no padding.
	 *
	 * @param  string $filename  Relative path inside DIR_IMAGE.
	 * @param  int    $width
	 * @param  int    $height
	 * @param  string $strategy  Override strategy; empty = read from config.
	 * @param  bool   $skip_webp  Skip WebP conversion for this image.
	 * @return string|null
	 */
	public function resize($filename, $width, $height, $strategy = '', $skip_webp = false) {
		if (!is_file(DIR_IMAGE . $filename) || substr(str_replace('\\', '/', realpath(DIR_IMAGE . $filename)), 0, strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)) {
			return;
		}

		// Determine strategy: caller can override, otherwise use theme setting.
		if ($strategy === '') {
			$strategy = $this->config->get('theme_dockercart_image_resize_mode') ?: 'contain';
		}
		$strategy = ($strategy === 'cover' || $strategy === 'hybrid') ? $strategy : 'contain';

		$source_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$webp_enabled = (bool)$this->config->get('theme_dockercart_image_webp_status');
		$webp_quality = (int)$this->config->get('theme_dockercart_image_webp_quality');

		if ($webp_quality < 1 || $webp_quality > 100) {
			$webp_quality = 90;
		}

		$webp_supported = function_exists('imagewebp') && function_exists('imagecreatefromwebp');

		// Keep transparent PNGs as original format; convert only opaque PNGs to WebP
		if ($source_extension === 'png' && !$skip_webp && $webp_enabled && $webp_supported) {
			$skip_webp = Image::hasTransparency(DIR_IMAGE . $filename);
		}

		$target_extension = ($webp_enabled && $webp_supported && !$skip_webp) ? 'webp' : $source_extension;

		$image_old = $filename;
		// Cover/hybrid images get a distinct cache filename to avoid collisions with contain.
		if ($strategy === 'cover') {
			$suffix = '-cover';
		} elseif ($strategy === 'hybrid') {
			$suffix = '-hybrid';
		} else {
			$suffix = '';
		}
		if ($target_extension === 'webp') {
			$suffix .= '-webp-q' . $webp_quality;
		}
		$image_new = 'cache/' . utf8_substr($filename, 0, utf8_strrpos($filename, '.')) . '-' . (int)$width . 'x' . (int)$height . $suffix . '.' . $target_extension;

		if (!is_file(DIR_IMAGE . $image_new) || (filemtime(DIR_IMAGE . $image_old) > filemtime(DIR_IMAGE . $image_new))) {
			$image_info = getimagesize(DIR_IMAGE . $image_old);
			if ($image_info === false) {
				if ($this->request->server['HTTPS']) {
					return $this->config->get('config_ssl') . 'image/' . $image_old;
				} else {
					return $this->config->get('config_url') . 'image/' . $image_old;
				}
			}
			list($width_orig, $height_orig, $image_type) = $image_info;

			if (!in_array($image_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP))) {
				if ($this->request->server['HTTPS']) {
					return $this->config->get('config_ssl') . 'image/' . $image_old;
				} else {
					return $this->config->get('config_url') . 'image/' . $image_old;
				}
			}

			$path = '';
			$cache_path_ready = true;

			$directories = explode('/', dirname($image_new));

			foreach ($directories as $directory) {
				$path = $path . '/' . $directory;

				if (!is_dir(DIR_IMAGE . $path)) {
					if (!@mkdir(DIR_IMAGE . $path, 0777) && !is_dir(DIR_IMAGE . $path)) {
						error_log('Error: Could not create image cache directory: ' . DIR_IMAGE . $path);
						$cache_path_ready = false;
						break;
					}
				}
			}

			if (!$cache_path_ready) {
				$image_new = $image_old;
			}

			if ($cache_path_ready && $image_type == IMAGETYPE_WEBP && !$webp_supported) {
				// Server GD has no WebP decode support: don't crash, return original image URL.
				$image_new = $image_old;
			} elseif ($cache_path_ready && ($width_orig != $width || $height_orig != $height || $target_extension != $source_extension)) {
				$image = new Image(DIR_IMAGE . $image_old);

				if (!$image->getImage()) {
					$image_new = $image_old;
				} else {
					$image->resize($width, $height, '', $strategy);
					$image->save(DIR_IMAGE . $image_new, $webp_quality);
				}
			} elseif ($cache_path_ready) {
				copy(DIR_IMAGE . $image_old, DIR_IMAGE . $image_new);
			}
		}

		$image_new = str_replace(' ', '%20', $image_new);  // fix bug when attach image on email (gmail.com). it is automatic changing space " " to +

		if ($this->request->server['HTTPS']) {
			return $this->config->get('config_ssl') . 'image/' . $image_new;
		} else {
			return $this->config->get('config_url') . 'image/' . $image_new;
		}
	}

	/**
	 * Convert an image to WebP without changing its dimensions.
	 *
	 * Returns the URL of the converted (cache) file when WebP conversion is
	 * enabled and the source is a candidate; falls back to the original image
	 * URL otherwise.
	 *
	 * @param  string $filename  Relative path inside DIR_IMAGE.
	 * @return string            Public URL of the (converted) image.
	 */
	public function convert(string $filename): string {
		if (!is_file(DIR_IMAGE . $filename) || substr(str_replace('\\', '/', realpath(DIR_IMAGE . $filename)), 0, strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)) {
			// Fallback: return original URL (file doesn't exist or is outside image dir)
			if ($this->request->server['HTTPS']) {
				return $this->config->get('config_ssl') . 'image/' . $filename;
			}

			return $this->config->get('config_url') . 'image/' . $filename;
		}

		$source_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		// Check whether WebP conversion is enabled
		$webp_enabled  = (bool)$this->config->get('theme_dockercart_image_webp_status');
		$webp_quality  = (int)$this->config->get('theme_dockercart_image_webp_quality');

		if ($webp_quality < 1 || $webp_quality > 100) {
			$webp_quality = 90;
		}

		$webp_supported = function_exists('imagewebp') && function_exists('imagecreatefromwebp');

		// Decide whether we should actually convert to WebP
		$should_convert = $webp_enabled
			&& $webp_supported
			&& $source_extension !== 'webp'
			&& !($source_extension === 'png' && Image::hasTransparency(DIR_IMAGE . $filename));

		if (!$should_convert) {
			if ($this->request->server['HTTPS']) {
				return $this->config->get('config_ssl') . 'image/' . $filename;
			}

			return $this->config->get('config_url') . 'image/' . $filename;
		}

		// Build target cache path
		$target_file = 'cache/' . utf8_substr($filename, 0, utf8_strrpos($filename, '.')) . '-webp-q' . $webp_quality . '.webp';

		// Regenerate if source is newer than cached version
		if (!is_file(DIR_IMAGE . $target_file) || filemtime(DIR_IMAGE . $filename) > filemtime(DIR_IMAGE . $target_file)) {
			$dir = dirname(DIR_IMAGE . $target_file);

			if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
				error_log('ModelToolImage::convert: Could not create cache directory: ' . $dir);

				if ($this->request->server['HTTPS']) {
					return $this->config->get('config_ssl') . 'image/' . $filename;
				}

				return $this->config->get('config_url') . 'image/' . $filename;
			}

			Image::convertToFormat(DIR_IMAGE . $filename, DIR_IMAGE . $target_file, $webp_quality);
		}

		// If the cache file exists, serve it; otherwise fall back to original
		if (is_file(DIR_IMAGE . $target_file)) {
			$image_new = str_replace(' ', '%20', $target_file);

			if ($this->request->server['HTTPS']) {
				return $this->config->get('config_ssl') . 'image/' . $image_new;
			}

			return $this->config->get('config_url') . 'image/' . $image_new;
		}

		if ($this->request->server['HTTPS']) {
			return $this->config->get('config_ssl') . 'image/' . $filename;
		}

		return $this->config->get('config_url') . 'image/' . $filename;
	}
}
