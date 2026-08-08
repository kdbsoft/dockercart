<?php
declare(strict_types=1);

/**
 * Secure upload / serve / delete for invoice signature & stamp images.
 *
 * Files are stored in DIR_STORAGE . 'documents/signature/' (outside the
 * webroot) under random token names and are never directly reachable by URL.
 * Only this controller can read them, and only for logged-in admins with the
 * setting/setting permission.
 */
class ControllerSettingInvoiceImage extends Controller {
	private const STORAGE_DIR = 'documents/signature/';
	private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
	private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
	private const MAX_DIMENSION = 512;

	public function upload() {
		$this->load->language('setting/setting');

		$json = array();

		if (!$this->user->hasPermission('modify', 'setting/setting')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error']) && !$this->isValidType($this->request->get['name'] ?? '')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error'])) {
			if (!empty($this->request->files['file']['name']) && is_file($this->request->files['file']['tmp_name'])) {
				$file = $this->request->files['file'];

				$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

				if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
					$json['error'] = $this->language->get('error_filetype');
				} elseif (!in_array($file['type'], self::ALLOWED_MIME, true)) {
					$json['error'] = $this->language->get('error_filetype');
				} elseif ($file['error'] != UPLOAD_ERR_OK) {
					$json['error'] = $this->language->get('error_upload_' . $file['error']);
				} elseif ($file['size'] > $this->maxUploadSize()) {
					$json['error'] = $this->language->get('error_upload_2');
				}
			} else {
				$json['error'] = $this->language->get('error_upload');
			}
		}

		if (!isset($json['error'])) {
			$directory = DIR_STORAGE . self::STORAGE_DIR;

			if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
				$json['error'] = $this->language->get('error_upload');
			} else {
				$filename = bin2hex(random_bytes(16)) . '.' . $extension;

				if (!move_uploaded_file($file['tmp_name'], $directory . $filename)) {
					$json['error'] = $this->language->get('error_upload');
				} elseif (!\Image::optimize($directory . $filename, self::MAX_DIMENSION)) {
					// Re-encode failed — the payload is not a real image. Discard it.
					@unlink($directory . $filename);
					$json['error'] = $this->language->get('error_filetype');
				} else {
					$json['filename'] = $filename;
					$json['success'] = $this->language->get('text_upload');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function image() {
		if (!$this->user->hasPermission('access', 'setting/setting')) {
			return;
		}

		$name = $this->request->get['name'] ?? '';

		if (!$this->isValidType($name)) {
			return;
		}

		$filename = basename((string)$this->config->get('config_seller_' . $name . '_image'));

		if ($filename === '' || $filename === '.' || $filename === '..') {
			return;
		}

		$path = DIR_STORAGE . self::STORAGE_DIR . $filename;

		if (!is_file($path)) {
			return;
		}

		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		$mime = match ($extension) {
			'png'  => 'image/png',
			'jpg', 'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			default => 'application/octet-stream',
		};

		$this->response->addHeader('Content-Type: ' . $mime);
		$this->response->addHeader('Content-Length: ' . (string)filesize($path));
		$this->response->addHeader('Cache-Control: private, no-store');
		$this->response->addHeader('X-Content-Type-Options: nosniff');
		$this->response->setOutput((string)file_get_contents($path));
	}

	public function delete() {
		$this->load->language('setting/setting');

		$json = array();

		if (!$this->user->hasPermission('modify', 'setting/setting')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error'])) {
			$name = $this->request->get['name'] ?? '';

			if (!$this->isValidType($name)) {
				$json['error'] = $this->language->get('error_permission');
			} else {
				$filename = basename((string)$this->config->get('config_seller_' . $name . '_image'));

				if ($filename !== '' && $filename !== '.' && $filename !== '..') {
					$path = DIR_STORAGE . self::STORAGE_DIR . $filename;

					if (is_file($path)) {
						@unlink($path);
					}
				}

				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function isValidType(string $name): bool {
		return in_array($name, ['signature', 'stamp'], true);
	}

	private function maxUploadSize(): int {
		$max = (int)$this->config->get('config_file_max_size');

		if ($max < 2097152) {
			$max = 10485760;
		}

		return $max;
	}
}
