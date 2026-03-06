<?php
class ModelDesignBanner extends Model {
	private $schema_ensured = false;

	public function addBanner($data) {
		$this->ensureSchema();

		$this->db->query("INSERT INTO " . DB_PREFIX . "banner SET name = '" . $this->db->escape($data['name']) . "', status = '" . (int)$data['status'] . "'");

		$banner_id = $this->db->getLastId();

		if (isset($data['banner_image'])) {
			foreach ($data['banner_image'] as $language_id => $value) {
				foreach ($value as $banner_image) {
					$this->insertBannerImage((int)$banner_id, (int)$language_id, $banner_image);
				}
			}
		}

		return $banner_id;
	}

	public function editBanner($banner_id, $data) {
		$this->ensureSchema();

		$this->db->query("UPDATE " . DB_PREFIX . "banner SET name = '" . $this->db->escape($data['name']) . "', status = '" . (int)$data['status'] . "' WHERE banner_id = '" . (int)$banner_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "banner_image WHERE banner_id = '" . (int)$banner_id . "'");

		if (isset($data['banner_image'])) {
			foreach ($data['banner_image'] as $language_id => $value) {
				foreach ($value as $banner_image) {
					$this->insertBannerImage((int)$banner_id, (int)$language_id, $banner_image);
				}
			}
		}
	}

	public function deleteBanner($banner_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "banner WHERE banner_id = '" . (int)$banner_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "banner_image WHERE banner_id = '" . (int)$banner_id . "'");
	}

	public function getBanner($banner_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "banner WHERE banner_id = '" . (int)$banner_id . "'");

		return $query->row;
	}

	public function getBanners($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "banner";

		$sort_data = array(
			'name',
			'status'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getBannerImages($banner_id) {
		$this->ensureSchema();

		$banner_image_data = array();

		$banner_image_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "banner_image WHERE banner_id = '" . (int)$banner_id . "' ORDER BY sort_order ASC");

		foreach ($banner_image_query->rows as $banner_image) {
			$banner_image_data[$banner_image['language_id']][] = array(
				'title'              => $banner_image['title'],
				'subtitle'           => isset($banner_image['subtitle']) ? $banner_image['subtitle'] : '',
				'accent_text'        => isset($banner_image['accent_text']) ? $banner_image['accent_text'] : '',
				'accent_color'       => isset($banner_image['accent_color']) ? $banner_image['accent_color'] : '',
				'primary_btn_text'   => isset($banner_image['primary_btn_text']) ? $banner_image['primary_btn_text'] : '',
				'primary_btn_link'   => isset($banner_image['primary_btn_link']) ? $banner_image['primary_btn_link'] : '',
				'primary_btn_text_color' => isset($banner_image['primary_btn_text_color']) ? $banner_image['primary_btn_text_color'] : '',
				'primary_btn_bg_color'   => isset($banner_image['primary_btn_bg_color']) ? $banner_image['primary_btn_bg_color'] : '',
				'secondary_btn_text' => isset($banner_image['secondary_btn_text']) ? $banner_image['secondary_btn_text'] : '',
				'secondary_btn_link' => isset($banner_image['secondary_btn_link']) ? $banner_image['secondary_btn_link'] : '',
				'link'               => $banner_image['link'],
				'image'              => $banner_image['image'],
				'image_portrait'     => isset($banner_image['image_portrait']) ? $banner_image['image_portrait'] : '',
				'sort_order'         => $banner_image['sort_order']
			);
		}

		return $banner_image_data;
	}

	public function getTotalBanners() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "banner");

		return $query->row['total'];
	}

	private function insertBannerImage($banner_id, $language_id, $banner_image) {
		$image_portrait      = isset($banner_image['image_portrait']) ? $this->db->escape($banner_image['image_portrait']) : '';
		$subtitle            = isset($banner_image['subtitle']) ? $this->db->escape($banner_image['subtitle']) : '';
		$accent_text         = isset($banner_image['accent_text']) ? $this->db->escape($banner_image['accent_text']) : '';
		$accent_color        = isset($banner_image['accent_color']) ? $this->db->escape($banner_image['accent_color']) : '';
		$primary_btn_text    = isset($banner_image['primary_btn_text']) ? $this->db->escape($banner_image['primary_btn_text']) : '';
		$primary_btn_link    = isset($banner_image['primary_btn_link']) ? $this->db->escape($banner_image['primary_btn_link']) : '';
		$primary_btn_text_color = isset($banner_image['primary_btn_text_color']) ? $this->db->escape($banner_image['primary_btn_text_color']) : '';
		$primary_btn_bg_color   = isset($banner_image['primary_btn_bg_color']) ? $this->db->escape($banner_image['primary_btn_bg_color']) : '';
		$secondary_btn_text  = isset($banner_image['secondary_btn_text']) ? $this->db->escape($banner_image['secondary_btn_text']) : '';
		$secondary_btn_link  = isset($banner_image['secondary_btn_link']) ? $this->db->escape($banner_image['secondary_btn_link']) : '';

		$this->db->query("INSERT INTO " . DB_PREFIX . "banner_image SET
			banner_id = '"           . (int)$banner_id . "',
			language_id = '"         . (int)$language_id . "',
			title = '"               . $this->db->escape($banner_image['title']) . "',
			subtitle = '"            . $subtitle . "',
			accent_text = '"         . $accent_text . "',
			accent_color = '"        . $accent_color . "',
			primary_btn_text = '"    . $primary_btn_text . "',
			primary_btn_link = '"    . $primary_btn_link . "',
			primary_btn_text_color = '" . $primary_btn_text_color . "',
			primary_btn_bg_color = '" . $primary_btn_bg_color . "',
			secondary_btn_text = '"  . $secondary_btn_text . "',
			secondary_btn_link = '"  . $secondary_btn_link . "',
			link = '"                . $this->db->escape($banner_image['link']) . "',
			image = '"               . $this->db->escape($banner_image['image']) . "',
			image_portrait = '"      . $image_portrait . "',
			sort_order = '"          . (int)$banner_image['sort_order'] . "'");
	}

	private function ensureSchema() {
		if ($this->schema_ensured) {
			return;
		}

		$this->schema_ensured = true;

		// Migrate existing tables: add new columns if they don't exist
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'subtitle',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `subtitle` varchar(255) NOT NULL DEFAULT '' AFTER `title`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'accent_text',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `accent_text` varchar(128) NOT NULL DEFAULT '' AFTER `subtitle`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'accent_color',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `accent_color` varchar(16) NOT NULL DEFAULT '' AFTER `accent_text`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'primary_btn_text',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `primary_btn_text` varchar(64) NOT NULL DEFAULT '' AFTER `accent_color`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'primary_btn_link',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `primary_btn_link` varchar(255) NOT NULL DEFAULT '' AFTER `primary_btn_text`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'primary_btn_text_color',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `primary_btn_text_color` varchar(16) NOT NULL DEFAULT '' AFTER `primary_btn_link`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'primary_btn_bg_color',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `primary_btn_bg_color` varchar(16) NOT NULL DEFAULT '' AFTER `primary_btn_text_color`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'secondary_btn_text',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `secondary_btn_text` varchar(64) NOT NULL DEFAULT '' AFTER `primary_btn_bg_color`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'secondary_btn_link',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `secondary_btn_link` varchar(255) NOT NULL DEFAULT '' AFTER `secondary_btn_text`");
		$this->addColumnIfMissing(DB_PREFIX . 'banner_image', 'image_portrait',
			"ALTER TABLE `" . DB_PREFIX . "banner_image` ADD `image_portrait` varchar(255) NOT NULL DEFAULT '' AFTER `image`");

		// Expand title column if still varchar(64)
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "banner_image` MODIFY `title` varchar(255) NOT NULL");
	}

	private function addColumnIfMissing($table, $column, $alterSql) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape($table) . "' AND COLUMN_NAME = '" . $this->db->escape($column) . "'");

		if (!$query->row['total']) {
			$this->db->query($alterSql);
		}
	}
}


