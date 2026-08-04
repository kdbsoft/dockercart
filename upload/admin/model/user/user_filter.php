<?php
declare(strict_types=1);

/**
 * Generic per-admin saved filters for list pages.
 * One table (oc_admin_filter) serves every entity: "order", "product",
 * "customer", etc. Controllers load this model and pass their entity key.
 */
class ModelUserUserFilter extends Model {
	private function entityKey(string $entity): string {
		return substr($entity, 0, 64);
	}

	public function addFilter(int $user_id, string $entity, string $name, array $conditions): int {
		$entity = $this->entityKey($entity);

		$sort_order = (int)$this->db->query(
			"SELECT COALESCE(MAX(sort_order), 0) AS max_order FROM `" . DB_PREFIX . "admin_filter` WHERE user_id = '" . (int)$user_id . "' AND entity = '" . $this->db->escape($entity) . "'"
		)->row['max_order'] + 1;

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "admin_filter` SET user_id = '" . (int)$user_id . "', entity = '" . $this->db->escape($entity) . "', name = '" . $this->db->escape($name) . "', conditions = '" . $this->db->escape(json_encode($conditions)) . "', sort_order = '" . $sort_order . "', date_added = NOW()"
		);

		return $this->db->getLastId();
	}

	public function editFilter(int $filter_id, int $user_id, string $name, array $conditions): void {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "admin_filter` SET name = '" . $this->db->escape($name) . "', conditions = '" . $this->db->escape(json_encode($conditions)) . "' WHERE filter_id = '" . (int)$filter_id . "' AND user_id = '" . (int)$user_id . "'"
		);
	}

	public function deleteFilter(int $filter_id, int $user_id): void {
		$this->db->query(
			"DELETE FROM `" . DB_PREFIX . "admin_filter` WHERE filter_id = '" . (int)$filter_id . "' AND user_id = '" . (int)$user_id . "'"
		);
	}

	public function getFilters(int $user_id, string $entity): array {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "admin_filter` WHERE user_id = '" . (int)$user_id . "' AND entity = '" . $this->db->escape($this->entityKey($entity)) . "' ORDER BY sort_order ASC, filter_id ASC"
		);

		$filters = array();

		foreach ($query->rows as $row) {
			$conditions = json_decode($row['conditions'], true);

			$filters[] = array(
				'filter_id'  => (int)$row['filter_id'],
				'name'       => $row['name'],
				'conditions' => is_array($conditions) ? $conditions : array()
			);
		}

		return $filters;
	}

	public function getFilter(int $filter_id, int $user_id, string $entity): ?array {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "admin_filter` WHERE filter_id = '" . (int)$filter_id . "' AND user_id = '" . (int)$user_id . "' AND entity = '" . $this->db->escape($this->entityKey($entity)) . "'"
		);

		if (!$query->num_rows) {
			return null;
		}

		$conditions = json_decode($query->row['conditions'], true);

		return array(
			'filter_id'  => (int)$query->row['filter_id'],
			'name'       => $query->row['name'],
			'conditions' => is_array($conditions) ? $conditions : array()
		);
	}
}
