<?php
declare(strict_types=1);

/**
 * Storefront side of the product file attachments (public downloads).
 *
 * Only enabled files of an enabled product are exposed. The physical payload
 * is never referenced here: the download is streamed by
 * ControllerProductProductFile from DIR_STORAGE . 'documents/product/'.
 */
class ModelCatalogProductFile extends Model {
	/**
	 * Enabled files of a product, titled in the current language.
	 *
	 * The title falls back to the original filename when a language has no
	 * translation, so a file is never shown without a label.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getFiles($product_id) {
		$language_id = (int)$this->config->get('config_language_id');

		$query = $this->db->query("SELECT pf.product_file_id, pf.original_name, pf.file_size, pf.mime_type, pf.sort_order, pf.downloads_count, pfd.name FROM " . DB_PREFIX . "product_file pf LEFT JOIN " . DB_PREFIX . "product_file_description pfd ON (pf.product_file_id = pfd.product_file_id AND pfd.language_id = '" . $language_id . "') WHERE pf.product_id = '" . (int)$product_id . "' AND pf.status = '1' ORDER BY pf.sort_order ASC, pf.product_file_id ASC");

		$files = array();

		foreach ($query->rows as $row) {
			$file_size = (int)$row['file_size'];
			$name = isset($row['name']) && $row['name'] !== null && $row['name'] !== '' ? (string)$row['name'] : (string)$row['original_name'];

			$files[] = array(
				'product_file_id' => (int)$row['product_file_id'],
				'name'            => $name,
				'original_name'   => (string)$row['original_name'],
				'file_size'       => $file_size,
				'file_size_label' => $this->formatFileSize($file_size),
				'mime_type'       => (string)$row['mime_type'],
				'downloads_count' => (int)$row['downloads_count'],
			);
		}

		return $files;
	}

	/**
	 * An enabled file of an enabled product, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function getFile($product_file_id) {
		$query = $this->db->query("SELECT pf.* FROM " . DB_PREFIX . "product_file pf LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id) WHERE pf.product_file_id = '" . (int)$product_file_id . "' AND pf.status = '1' AND p.status = '1'");

		return $query->num_rows ? $query->row : null;
	}

	public function incrementDownload($product_file_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product_file SET downloads_count = downloads_count + 1, date_last_downloaded = NOW() WHERE product_file_id = '" . (int)$product_file_id . "'");
	}

	private function formatFileSize($bytes) {
		$bytes = max(0, (int)$bytes);

		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$index = 0;
		$size = (float)$bytes;

		while ($size >= 1024 && $index < count($units) - 1) {
			$size /= 1024;
			$index++;
		}

		return ($index === 0 ? (string)(int)$size : number_format($size, 1, '.', '')) . ' ' . $units[$index];
	}
}
