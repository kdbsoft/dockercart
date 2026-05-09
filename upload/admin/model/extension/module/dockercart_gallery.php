<?php
declare(strict_types=1);

class ModelExtensionModuleDockercartGallery extends Model {
	private $schema_ensured = false;

	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_gallery` (
			`gallery_id` INT(11) NOT NULL AUTO_INCREMENT,
			`image` VARCHAR(255) NOT NULL DEFAULT '',
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`gallery_id`),
			KEY `status_sort_order` (`status`,`sort_order`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->ensureSchema();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_gallery`");
	}

	public function addImage($data) {
		$this->ensureSchema();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_gallery`
			SET `image` = '" . $this->db->escape((string)$data['image']) . "',
				`sort_order` = '" . (int)$data['sort_order'] . "',
				`status` = '" . (int)$data['status'] . "',
				`date_added` = NOW(),
				`date_modified` = NOW()");

		return (int)$this->db->getLastId();
	}

	public function editImage($gallery_id, $data) {
		$this->ensureSchema();

		$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_gallery`
			SET `image` = '" . $this->db->escape((string)$data['image']) . "',
				`sort_order` = '" . (int)$data['sort_order'] . "',
				`status` = '" . (int)$data['status'] . "',
				`date_modified` = NOW()
			WHERE `gallery_id` = '" . (int)$gallery_id . "'");
	}

	public function deleteImage($gallery_id) {
		$this->ensureSchema();

		$image = $this->getImage($gallery_id);
		if ($image && !empty($image['image']) && is_file(DIR_IMAGE . $image['image'])) {
			unlink(DIR_IMAGE . $image['image']);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_gallery` WHERE `gallery_id` = '" . (int)$gallery_id . "'");
	}

	public function getImage($gallery_id) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_gallery` WHERE `gallery_id` = '" . (int)$gallery_id . "'");

		return $query->num_rows ? $query->row : null;
	}

	public function getImages($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_gallery` WHERE 1=1";

		if (isset($data['status'])) {
			$sql .= " AND `status` = '" . (int)$data['status'] . "'";
		}

		$sort = isset($data['sort']) ? (string)$data['sort'] : 'sort_order';
		$order = (isset($data['order']) && strtoupper((string)$data['order']) === 'DESC') ? 'DESC' : 'ASC';

		$allowed_sort = array('gallery_id', 'sort_order', 'date_added', 'date_modified');
		if (!in_array($sort, $allowed_sort)) {
			$sort = 'sort_order';
		}

		$sql .= " ORDER BY `" . $sort . "` " . $order;

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
			if ($start < 0) $start = 0;
			if ($limit < 1) $limit = 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		return $this->db->query($sql)->rows;
	}

	public function getTotalImages($data = array()) {
		$this->ensureSchema();

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_gallery` WHERE 1=1";

		if (isset($data['status'])) {
			$sql .= " AND `status` = '" . (int)$data['status'] . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function installSeoUrls() {
		$this->load->model('localisation/language');
		$this->load->model('setting/store');
		$this->load->model('design/seo_url');

		$stores = $this->model_setting_store->getStores();
		$store_ids = array(0);
		foreach ($stores as $store) {
			$store_ids[] = (int)$store['store_id'];
		}
		$store_ids = array_unique($store_ids);

		$languages = $this->model_localisation_language->getLanguages();

		foreach ($store_ids as $store_id) {
			foreach ($languages as $language) {
				$language_id = (int)$language['language_id'];
				$exists = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url`
					WHERE `store_id` = '" . $store_id . "'
					  AND `language_id` = '" . $language_id . "'
					  AND `query` = 'extension/module/dockercart_gallery'");

				if (!$exists->num_rows) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url`
						SET `store_id` = '" . $store_id . "',
						    `language_id` = '" . $language_id . "',
						    `query` = 'extension/module/dockercart_gallery',
						    `keyword` = 'gallery'");
				}
			}
		}

		$this->model_design_seo_url->invalidateSeoUrlCache();
	}

	public function uninstallSeoUrls() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'extension/module/dockercart_gallery'");

		$this->load->model('design/seo_url');
		$this->model_design_seo_url->invalidateSeoUrlCache();
	}

	private function ensureSchema() {
		if ($this->schema_ensured) {
			return;
		}
		$this->schema_ensured = true;

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_gallery` (
			`gallery_id` INT(11) NOT NULL AUTO_INCREMENT,
			`image` VARCHAR(255) NOT NULL DEFAULT '',
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			`status` TINYINT(1) NOT NULL DEFAULT '1',
			`date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`gallery_id`),
			KEY `status_sort_order` (`status`,`sort_order`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
}
