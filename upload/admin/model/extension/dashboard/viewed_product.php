<?php
class ModelExtensionDashboardViewedProduct extends Model {
	public function getTotalViews($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_viewed_product`";

		$where = array();

		if (!empty($data['filter_date_start'])) {
			$where[] = "DATE(date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$where[] = "DATE(date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if ($where) {
			$sql .= " WHERE " . implode(" AND ", $where);
		}

		$query = $this->db->query($sql);

		return isset($query->row['total']) ? (int)$query->row['total'] : 0;
	}
}
