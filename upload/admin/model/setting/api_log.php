<?php
/**
 * DockerCart admin — API request journal model.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ModelSettingApiLog extends Model {
	public function getLogs(array $data = []): array {
		$sql = "SELECT l.* FROM `" . DB_PREFIX . "api_log` l";

		$where = [];

		if (!empty($data['filter_api_key_id'])) {
			$where[] = "l.`api_key_id` = '" . (int)$data['filter_api_key_id'] . "'";
		}

		if (!empty($data['filter_status'])) {
			$where[] = "l.`status_code` = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_error_code'])) {
			$where[] = "l.`error_code` = '" . $this->db->escape((string)$data['filter_error_code']) . "'";
		}

		if (!empty($data['filter_path'])) {
			$where[] = "l.`path` LIKE '%" . $this->db->escape((string)$data['filter_path']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$where[] = "DATE(l.`date_added`) = DATE('" . $this->db->escape((string)$data['filter_date_added']) . "')";
		}

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql .= " ORDER BY l.`api_log_id` DESC";

		$start = isset($data['start']) ? (int)$data['start'] : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$sql .= " LIMIT " . $start . "," . $limit;

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalLogs(array $data = []): int {
		$sql = "SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "api_log` l";

		$where = [];

		if (!empty($data['filter_api_key_id'])) {
			$where[] = "l.`api_key_id` = '" . (int)$data['filter_api_key_id'] . "'";
		}

		if (!empty($data['filter_status'])) {
			$where[] = "l.`status_code` = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_error_code'])) {
			$where[] = "l.`error_code` = '" . $this->db->escape((string)$data['filter_error_code']) . "'";
		}

		if (!empty($data['filter_path'])) {
			$where[] = "l.`path` LIKE '%" . $this->db->escape((string)$data['filter_path']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$where[] = "DATE(l.`date_added`) = DATE('" . $this->db->escape((string)$data['filter_date_added']) . "')";
		}

		if ($where) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function clear(): int {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_log`");

		return (int)$this->db->countAffected();
	}

	public function purgeOlderThan(int $days): int {
		$days = max(0, $days);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "api_log` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)");

		return (int)$this->db->countAffected();
	}
}
