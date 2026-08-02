<?php
class ModelExtensionReportCoupon extends Model {
	public function getCoupons($data = array()) {
		$sql = "SELECT ch.coupon_id, COALESCE(cd.name, c.name) AS name, c.code, COUNT(DISTINCT ch.order_id) AS `orders`, SUM(ch.amount) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) LEFT JOIN `" . DB_PREFIX . "coupon_description` cd ON (cd.coupon_id = c.coupon_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(ch.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(ch.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$sql .= " GROUP BY ch.coupon_id ORDER BY total DESC";

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

	public function getTotalCoupons($data = array()) {
		$sql = "SELECT COUNT(DISTINCT coupon_id) AS total FROM `" . DB_PREFIX . "coupon_history`";

		$implode = array();

		if (!empty($data['filter_date_start'])) {
			$implode[] = "DATE(date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$implode[] = "DATE(date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
}