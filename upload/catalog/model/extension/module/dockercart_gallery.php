<?php
declare(strict_types=1);

class ModelExtensionModuleDockercartGallery extends Model {
	public function getImages($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "dockercart_gallery` WHERE `status` = '1'";

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

	public function getTotalImages() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_gallery` WHERE `status` = '1'");

		return (int)$query->row['total'];
	}
}
