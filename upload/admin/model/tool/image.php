<?php
class ModelToolImage extends Model {
	/**
	 * Resize an image (admin side) and return its public URL.
	 *
	 * Strategy is read from `theme_dockercart_image_resize_mode`.
	 * 'contain' (default) — pad with background.
	 * 'cover'             — fill and crop from center.
	 *
	 * @param  string $filename  Relative path inside DIR_IMAGE.
	 * @param  int    $width
	 * @param  int    $height
	 * @param  string $strategy  Override strategy; empty = read from config.
	 * @return string|null
	 */
	public function resize($filename, $width, $height, $strategy = '') {
		if (!is_file(DIR_IMAGE . $filename) || substr(str_replace('\\', '/', realpath(DIR_IMAGE . $filename)), 0, strlen(DIR_IMAGE)) != str_replace('\\', '/', DIR_IMAGE)) {
			return;
		}

		// Determine strategy: caller can override, otherwise use theme setting.
		if ($strategy === '') {
			$strategy = $this->config->get('theme_dockercart_image_resize_mode') ?: 'contain';
		}
		$strategy = ($strategy === 'cover') ? 'cover' : 'contain';

		$extension = pathinfo($filename, PATHINFO_EXTENSION);

		$image_old = $filename;
		$suffix    = ($strategy === 'cover') ? '-cover' : '';
		$image_new = 'cache/' . utf8_substr($filename, 0, utf8_strrpos($filename, '.')) . '-' . $width . 'x' . $height . $suffix . '.' . $extension;

		if (!is_file(DIR_IMAGE . $image_new) || (filemtime(DIR_IMAGE . $image_old) > filemtime(DIR_IMAGE . $image_new))) {
			list($width_orig, $height_orig, $image_type) = getimagesize(DIR_IMAGE . $image_old);

			if (!in_array($image_type, array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP))) {
				if ($this->request->server['HTTPS']) {
					return HTTPS_CATALOG . 'image/' . $image_old;
				} else {
					return HTTP_CATALOG . 'image/' . $image_old;
				}
			}

			$path = '';
			$cache_path_ready = true;

			$directories = explode('/', dirname($image_new));

			foreach ($directories as $directory) {
				$path = $path . '/' . $directory;

				if (!is_dir(DIR_IMAGE . $path)) {
					if (!mkdir(DIR_IMAGE . $path, 0775) && !is_dir(DIR_IMAGE . $path)) {
						error_log('Error: Could not create image cache directory: ' . DIR_IMAGE . $path);
						$cache_path_ready = false;
						break;
					}
				}
			}

			if (!$cache_path_ready) {
				$image_new = $image_old;
			}

			if ($cache_path_ready && ($width_orig != $width || $height_orig != $height)) {
				$image = new Image(DIR_IMAGE . $image_old);
				$image->resize($width, $height, '', $strategy);
				$image->save(DIR_IMAGE . $image_new);
			} elseif ($cache_path_ready) {
				copy(DIR_IMAGE . $image_old, DIR_IMAGE . $image_new);
			}
		}

		if ($this->request->server['HTTPS']) {
			return HTTPS_CATALOG . 'image/' . $image_new;
		} else {
			return HTTP_CATALOG . 'image/' . $image_new;
		}
	}
}
