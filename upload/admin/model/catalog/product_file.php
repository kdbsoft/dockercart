<?php
declare(strict_types=1);

/**
 * Product file attachments (public downloads shown on the product page).
 *
 * Metadata lives in oc_product_file / oc_product_file_description; the physical
 * payload lives outside the webroot in DIR_STORAGE . 'documents/product/' under
 * a random storage key. Every method that removes a row also removes the
 * payload, so a product delete or a file removal never leaves an orphan behind.
 *
 * The storefront never touches this model — it uses catalog/model/catalog/product_file.
 */
class ModelCatalogProductFile extends Model {
	public const STORAGE_DIR = 'documents/product/';

	/**
	 * All files of a product (all statuses), ordered for the admin form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getProductFiles($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_file WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC, product_file_id ASC");

		return $query->rows;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getFile($product_file_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_file WHERE product_file_id = '" . (int)$product_file_id . "'");

		return $query->num_rows ? $query->row : null;
	}

	/**
	 * Localized titles keyed by language_id.
	 *
	 * @return array<int, string>
	 */
	public function getFileDescriptions($product_file_id) {
		$descriptions = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_file_description WHERE product_file_id = '" . (int)$product_file_id . "'");

		foreach ($query->rows as $result) {
			$descriptions[(int)$result['language_id']] = (string)$result['name'];
		}

		return $descriptions;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function addFile($data) {
		$product_id = (int)($data['product_id'] ?? 0);

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_file SET product_id = '" . $product_id . "', storage_key = '" . $this->db->escape((string)($data['storage_key'] ?? '')) . "', original_name = '" . $this->db->escape((string)($data['original_name'] ?? '')) . "', file_size = '" . (int)($data['file_size'] ?? 0) . "', mime_type = '" . $this->db->escape((string)($data['mime_type'] ?? '')) . "', sort_order = '" . (int)($data['sort_order'] ?? 0) . "', status = '" . (int)!empty($data['status']) . "', date_added = NOW()");

		$product_file_id = (int)$this->db->getLastId();

		$this->saveDescriptions($product_file_id, isset($data['name']) && is_array($data['name']) ? $data['name'] : array(), (string)($data['original_name'] ?? ''));

		return $product_file_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function editFile($product_file_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "product_file SET storage_key = '" . $this->db->escape((string)($data['storage_key'] ?? '')) . "', original_name = '" . $this->db->escape((string)($data['original_name'] ?? '')) . "', file_size = '" . (int)($data['file_size'] ?? 0) . "', mime_type = '" . $this->db->escape((string)($data['mime_type'] ?? '')) . "', sort_order = '" . (int)($data['sort_order'] ?? 0) . "', status = '" . (int)!empty($data['status']) . "' WHERE product_file_id = '" . (int)$product_file_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_file_description WHERE product_file_id = '" . (int)$product_file_id . "'");

		$this->saveDescriptions($product_file_id, isset($data['name']) && is_array($data['name']) ? $data['name'] : array(), (string)($data['original_name'] ?? ''));
	}

	/**
	 * Remove a file row together with its physical payload.
	 */
	public function deleteFile($product_file_id) {
		$file_info = $this->getFile($product_file_id);

		if ($file_info) {
			$this->deletePayload((string)$file_info['storage_key']);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_file WHERE product_file_id = '" . (int)$product_file_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_file_description WHERE product_file_id = '" . (int)$product_file_id . "'");
	}

	/**
	 * Remove every file of a product (used by ModelCatalogProduct::deleteProduct).
	 */
	public function deleteFilesByProduct($product_id) {
		$query = $this->db->query("SELECT product_file_id, storage_key FROM " . DB_PREFIX . "product_file WHERE product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$this->deletePayload((string)$row['storage_key']);
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_file_description WHERE product_file_id IN (SELECT product_file_id FROM " . DB_PREFIX . "product_file WHERE product_id = '" . (int)$product_id . "')");
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_file WHERE product_id = '" . (int)$product_id . "'");
	}

	/**
	 * Duplicate every file of a product onto another product.
	 *
	 * The payload is copied (never shared) so deleting one product can never
	 * break the other's downloads. Download counters start fresh.
	 */
	public function copyFiles($product_id, $new_product_id) {
		foreach ($this->getProductFiles($product_id) as $file) {
			$storage_key = $this->duplicatePayload((string)$file['storage_key']);

			if ($storage_key === '') {
				continue;
			}

			$this->addFile(array(
				'product_id'    => (int)$new_product_id,
				'storage_key'   => $storage_key,
				'original_name' => (string)$file['original_name'],
				'file_size'     => (int)$file['file_size'],
				'mime_type'     => (string)$file['mime_type'],
				'sort_order'    => (int)$file['sort_order'],
				'status'        => (int)$file['status'],
				'name'          => $this->getFileDescriptions((int)$file['product_file_id']),
			));
		}
	}

	/**
	 * Reconcile the product's files with the submitted form rows.
	 *
	 * Rows with a known product_file_id are updated, new rows are inserted and
	 * rows missing from the submission are deleted (payload included). This is
	 * the only writer of oc_product_file besides copyFiles().
	 *
	 * @param array<int, array<string, mixed>> $files
	 */
	public function saveFiles($product_id, array $files) {
		$existing = array();

		foreach ($this->getProductFiles($product_id) as $row) {
			$existing[] = (int)$row['product_file_id'];
		}

		$kept = array();

		foreach (array_values($files) as $index => $file) {
			if (!is_array($file)) {
				continue;
			}

			$product_file_id = (int)($file['product_file_id'] ?? 0);

			$data = array(
				'storage_key'   => (string)($file['storage_key'] ?? ''),
				'original_name' => (string)($file['original_name'] ?? ''),
				'file_size'     => (int)($file['file_size'] ?? 0),
				'mime_type'     => (string)($file['mime_type'] ?? ''),
				'sort_order'    => (int)($file['sort_order'] ?? $index),
				'status'        => !empty($file['status']),
				'name'          => isset($file['name']) && is_array($file['name']) ? $file['name'] : array(),
			);

			if ($data['storage_key'] === '') {
				continue;
			}

			if ($product_file_id && in_array($product_file_id, $existing, true)) {
				$this->editFile($product_file_id, $data);
				$kept[] = $product_file_id;
			} else {
				$data['product_id'] = (int)$product_id;
				$kept[] = $this->addFile($data);
			}
		}

		foreach (array_diff($existing, $kept) as $removed_id) {
			$this->deleteFile((int)$removed_id);
		}
	}

	/**
	 * Remove a freshly uploaded payload that has no DB row yet (the admin
	 * removed it before saving the product).
	 */
	public function deleteUploadedFile($storage_key) {
		$this->deletePayload($storage_key);
	}

	/**
	 * Absolute path of the storage directory, created on demand.
	 */
	public function getStorageDirectory() {
		$directory = DIR_STORAGE . self::STORAGE_DIR;

		if (!is_dir($directory)) {
			@mkdir($directory, 0775, true);
		}

		return $directory;
	}

	/**
	 * Build a random, extension-preserving storage key.
	 */
	public function createStorageKey($original_name) {
		$extension = strtolower(pathinfo((string)$original_name, PATHINFO_EXTENSION));

		// Only keep a sane, alphanumeric extension; anything else is dropped.
		if ($extension === '' || !preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
			$extension = '';
		}

		return bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
	}

	/**
	 * @param array<int|string, mixed> $names
	 */
	private function saveDescriptions($product_file_id, array $names, $fallback = '') {
		foreach ($names as $language_id => $name) {
			$name = trim((string)$name);

			if ($name === '') {
				$name = trim((string)$fallback);
			}

			if ($name === '') {
				continue;
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "product_file_description SET product_file_id = '" . (int)$product_file_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "' ON DUPLICATE KEY UPDATE name = VALUES(name)");
		}
	}

	private function deletePayload($storage_key) {
		$storage_key = basename((string)$storage_key);

		if ($storage_key === '' || $storage_key === '.' || $storage_key === '..') {
			return;
		}

		$path = DIR_STORAGE . self::STORAGE_DIR . $storage_key;

		if (is_file($path)) {
			@unlink($path);
		}
	}

	/**
	 * Copy the payload of one file to a new random key; '' on failure.
	 */
	private function duplicatePayload($storage_key) {
		$storage_key = basename((string)$storage_key);

		if ($storage_key === '' || $storage_key === '.' || $storage_key === '..') {
			return '';
		}

		$source = DIR_STORAGE . self::STORAGE_DIR . $storage_key;

		if (!is_file($source)) {
			return '';
		}

		$new_key = $this->createStorageKey($storage_key);
		$target = $this->getStorageDirectory() . $new_key;

		if (!@copy($source, $target)) {
			return '';
		}

		return $new_key;
	}
}
