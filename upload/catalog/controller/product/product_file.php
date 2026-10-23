<?php
declare(strict_types=1);

/**
 * Public download of a product file (instruction, datasheet, ...).
 *
 * The payload lives outside the webroot in DIR_STORAGE . 'documents/product/'
 * and is only reachable through this action, so the original filename is never
 * exposed as a path and cannot be guessed. A disabled file or a file of a
 * disabled product is not served.
 */
class ControllerProductProductFile extends Controller {
	public function download() {
		$product_file_id = isset($this->request->get['product_file_id']) ? (int)$this->request->get['product_file_id'] : 0;

		$this->load->model('catalog/product_file');

		$file_info = $this->model_catalog_product_file->getFile($product_file_id);

		if (!$file_info) {
			$this->response->redirect($this->url->link('common/home'));

			return;
		}

		$storage_key = basename((string)$file_info['storage_key']);
		$path = DIR_STORAGE . 'documents/product/' . $storage_key;

		if ($storage_key === '' || $storage_key === '.' || $storage_key === '..' || !is_file($path)) {
			$this->response->redirect($this->url->link('common/home'));

			return;
		}

		$this->model_catalog_product_file->incrementDownload($product_file_id);

		$mask = basename((string)$file_info['original_name']);

		if ($mask === '' || $mask === '.' || $mask === '..') {
			$mask = $storage_key;
		}

		$content_type = (string)$file_info['mime_type'];

		if ($content_type === '' || !preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', $content_type)) {
			$content_type = 'application/octet-stream';
		}

		if (!headers_sent()) {
			$ascii_mask = preg_replace('/[^A-Za-z0-9._ -]/', '_', $mask);

			header('Content-Type: ' . $content_type);
			header('Content-Disposition: attachment; filename="' . $ascii_mask . '"; filename*=UTF-8\'\'' . rawurlencode($mask));
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($path));
			header('X-Content-Type-Options: nosniff');

			if (ob_get_level()) {
				ob_end_clean();
			}

			readfile($path);

			exit();
		}

		exit('Error: Headers already sent out!');
	}
}
