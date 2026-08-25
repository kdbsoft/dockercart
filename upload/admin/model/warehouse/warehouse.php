<?php
/**
 * DockerCart Warehouse admin model: repository CRUD for oc_warehouse,
 * schedules, schedule windows and holidays.
 */

declare(strict_types=1);

class ModelWarehouseWarehouse extends Model {
	/**
	 * Create a warehouse with its schedule + holidays. Returns new id.
	 */
	public function addWarehouse(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse` SET
			`name` = '" . $this->db->escape($this->resolveBaseName($data)) . "',
			`type` = '" . $this->db->escape((string)($data['type'] ?? 'physical')) . "',
			`is_default` = '" . (int)!empty($data['is_default']) . "',
			`priority` = '" . (int)($data['priority'] ?? 0) . "',
			`status` = '" . (int)!empty($data['status']) . "',
			`sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
			`address_1` = '" . $this->db->escape($this->resolveBaseAddress($data)['address_1']) . "',
			`address_2` = '" . $this->db->escape($this->resolveBaseAddress($data)['address_2']) . "',
			`city` = '" . $this->db->escape($this->resolveBaseAddress($data)['city']) . "',
			`postcode` = '" . $this->db->escape((string)($data['postcode'] ?? '')) . "',
			`country_id` = '" . (int)($data['country_id'] ?? 0) . "',
			`zone_id` = '" . (int)($data['zone_id'] ?? 0) . "',
			`latitude` = '" . (float)($data['latitude'] ?? 0) . "',
			`longitude` = '" . (float)($data['longitude'] ?? 0) . "',
			`phone` = '" . $this->db->escape((string)($data['phone'] ?? '')) . "',
			`email` = '" . $this->db->escape((string)($data['email'] ?? '')) . "',
			`map_url` = '" . $this->db->escape((string)($data['map_url'] ?? '')) . "',
			`prepare_days` = '" . (int)($data['prepare_days'] ?? 0) . "',
			`low_stock` = '" . (float)($data['low_stock'] ?? 0) . "',
			`allow_pickup` = '" . (int)!empty($data['allow_pickup']) . "',
			`pickup_cost` = '" . (float)($data['pickup_cost'] ?? 0) . "',
			`pickup_note` = '" . $this->db->escape((string)($data['pickup_note'] ?? '')) . "',
			`supplier_name` = '" . $this->db->escape((string)($data['supplier_name'] ?? '')) . "',
			`supplier_phone` = '" . $this->db->escape((string)($data['supplier_phone'] ?? '')) . "',
			`supplier_email` = '" . $this->db->escape((string)($data['supplier_email'] ?? '')) . "',
			`supplier_lead_time` = '" . (int)($data['supplier_lead_time'] ?? 0) . "',
			`supplier_note` = '" . $this->db->escape((string)($data['supplier_note'] ?? '')) . "',
			`date_added` = NOW(), `date_modified` = NOW()");

		$warehouse_id = $this->db->getLastId();

		if (!empty($data['is_default'])) {
			$this->clearDefault((int)$warehouse_id);
		}

		$this->saveDescriptions((int)$warehouse_id, $data['warehouse_description'] ?? []);

		$this->saveSchedule($warehouse_id, $data['schedule'] ?? []);
		$this->saveHolidays($warehouse_id, $data['holiday'] ?? []);

		$this->cache->delete('warehouse');

		return (int)$warehouse_id;
	}

	/**
	 * Update a warehouse (id -1 => legacy default id 1 when none exists).
	 */
	public function editWarehouse(int $warehouse_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET
			`name` = '" . $this->db->escape($this->resolveBaseName($data)) . "',
			`type` = '" . $this->db->escape((string)($data['type'] ?? 'physical')) . "',
			`is_default` = '" . (int)!empty($data['is_default']) . "',
			`priority` = '" . (int)($data['priority'] ?? 0) . "',
			`status` = '" . (int)!empty($data['status']) . "',
			`sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
			`address_1` = '" . $this->db->escape($this->resolveBaseAddress($data)['address_1']) . "',
			`address_2` = '" . $this->db->escape($this->resolveBaseAddress($data)['address_2']) . "',
			`city` = '" . $this->db->escape($this->resolveBaseAddress($data)['city']) . "',
			`postcode` = '" . $this->db->escape((string)($data['postcode'] ?? '')) . "',
			`country_id` = '" . (int)($data['country_id'] ?? 0) . "',
			`zone_id` = '" . (int)($data['zone_id'] ?? 0) . "',
			`latitude` = '" . (float)($data['latitude'] ?? 0) . "',
			`longitude` = '" . (float)($data['longitude'] ?? 0) . "',
			`phone` = '" . $this->db->escape((string)($data['phone'] ?? '')) . "',
			`email` = '" . $this->db->escape((string)($data['email'] ?? '')) . "',
			`map_url` = '" . $this->db->escape((string)($data['map_url'] ?? '')) . "',
			`prepare_days` = '" . (int)($data['prepare_days'] ?? 0) . "',
			`low_stock` = '" . (float)($data['low_stock'] ?? 0) . "',
			`allow_pickup` = '" . (int)!empty($data['allow_pickup']) . "',
			`pickup_cost` = '" . (float)($data['pickup_cost'] ?? 0) . "',
			`pickup_note` = '" . $this->db->escape((string)($data['pickup_note'] ?? '')) . "',
			`supplier_name` = '" . $this->db->escape((string)($data['supplier_name'] ?? '')) . "',
			`supplier_phone` = '" . $this->db->escape((string)($data['supplier_phone'] ?? '')) . "',
			`supplier_email` = '" . $this->db->escape((string)($data['supplier_email'] ?? '')) . "',
			`supplier_lead_time` = '" . (int)($data['supplier_lead_time'] ?? 0) . "',
			`supplier_note` = '" . $this->db->escape((string)($data['supplier_note'] ?? '')) . "',
			`date_modified` = NOW()
			WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		if (!empty($data['is_default'])) {
			$this->clearDefault((int)$warehouse_id);
		}

		$this->saveDescriptions((int)$warehouse_id, $data['warehouse_description'] ?? []);

		$this->saveSchedule($warehouse_id, $data['schedule'] ?? []);
		$this->saveHolidays($warehouse_id, $data['holiday'] ?? []);

		$this->cache->delete('warehouse');
	}

	public function deleteWarehouse(int $warehouse_id): void {
		// Never allow deleting the last remaining warehouse.
		$count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "warehouse`");
		$is_default = $this->db->query("SELECT is_default FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		if ((int)$count->row['total'] <= 1) {
			throw new \RuntimeException('Cannot delete the last warehouse');
		}

		if ($is_default->num_rows && (int)$is_default->row['is_default'] === 1) {
			// Reassign default to the highest-priority remaining warehouse.
			$next = $this->db->query("SELECT `warehouse_id` FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` <> '" . (int)$warehouse_id . "' ORDER BY `priority` DESC, `warehouse_id` ASC LIMIT 1");

			if ($next->num_rows) {
				$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET `is_default` = '1' WHERE `warehouse_id` = '" . (int)$next->row['warehouse_id'] . "'");
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_description` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_holiday_description` WHERE `holiday_id` IN (SELECT `holiday_id` FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `warehouse_id` = '" . (int)$warehouse_id . "')");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_stock` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		$this->cache->delete('warehouse');
	}

	/**
	 * Fetch one warehouse (registry row + schedule + holidays) for the form.
	 */
	public function getWarehouse(int $warehouse_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		if (!$query->num_rows) {
			return [];
		}

		$warehouse = $query->row;
		$warehouse['schedule'] = $this->getScheduleRows($warehouse_id);
		$warehouse['holiday'] = $this->getHolidayRows($warehouse_id);

		return $warehouse;
	}

	public function getWarehouses(array $data = []): array {
		// Localized name for the current admin language, base name as fallback.
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT w.*, COALESCE(wd.name, w.name) AS name FROM `" . DB_PREFIX . "warehouse` w LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . $language_id . "')";

		if (!empty($data['filter_name'])) {
			$sql .= " WHERE COALESCE(wd.name, w.name) LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		$sort_data = ['name', 'type', 'priority', 'is_default', 'status'];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY `" . $data['sort'] . "`";
		} else {
			$sql .= " ORDER BY `is_default` DESC, `priority` DESC";
		}

		if (isset($data['order']) && $data['order'] === 'DESC') {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			$sql .= " LIMIT " . (int)($data['start'] ?? 0) . "," . (int)($data['limit'] ?? 20);
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalWarehouses(array $data = []): int {
		$language_id = (int)$this->config->get('config_language_id');

		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "warehouse` w LEFT JOIN `" . DB_PREFIX . "warehouse_description` wd ON (wd.warehouse_id = w.warehouse_id AND wd.language_id = '" . $language_id . "')";

		if (!empty($data['filter_name'])) {
			$sql .= " WHERE COALESCE(wd.name, w.name) LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Schedule rows for one warehouse: day_of_week => ['is_open'=>bool,'windows'=>[...]].
	 */
	public function getScheduleRows(int $warehouse_id): array {
		$result = [];

		for ($d = 1; $d <= 7; $d++) {
			$result[$d] = ['is_open' => true, 'windows' => []];
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		$schedule_ids = [];

		foreach ($query->rows as $row) {
			$result[(int)$row['day_of_week']]['is_open'] = (bool)$row['is_open'];
			$schedule_ids[] = (int)$row['schedule_id'];
		}

		if ($schedule_ids) {
			$in = implode(',', $schedule_ids);
			$wq = $this->db->query("SELECT `schedule_id`, `time_from`, `time_to` FROM `" . DB_PREFIX . "warehouse_schedule_window` WHERE `schedule_id` IN (" . $in . ") ORDER BY `time_from` ASC");

			$by_schedule = [];

			foreach ($wq->rows as $row) {
				$by_schedule[(int)$row['schedule_id']][] = [
					'time_from' => $row['time_from'],
					'time_to' => $row['time_to'],
				];
			}

			foreach ($query->rows as $row) {
				$result[(int)$row['day_of_week']]['windows'] = $by_schedule[(int)$row['schedule_id']] ?? [];
			}
		}

		return $result;
	}

	public function getHolidayRows(int $warehouse_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' ORDER BY `date` ASC");

		return $this->attachHolidayDescriptions($query->rows);
	}

	/**
	 * Per-language names for a single holiday, keyed by language_id.
	 */
	public function getHolidayDescriptions(int $holiday_id): array {
		$descriptions = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_holiday_description` WHERE `holiday_id` = '" . (int)$holiday_id . "'");

		foreach ($query->rows as $result) {
			$descriptions[(int)$result['language_id']] = ['name' => $result['name']];
		}

		return $descriptions;
	}

	/**
	 * Attach a `description` key (language_id => name) to each holiday row.
	 */
	protected function attachHolidayDescriptions(array $rows): array {
		if (!$rows) {
			return $rows;
		}

		$ids = array_map(static fn($r) => (int)$r['holiday_id'], $rows);
		$in = implode(',', $ids);

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_holiday_description` WHERE `holiday_id` IN (" . $in . ")");

		$by_holiday = [];

		foreach ($query->rows as $result) {
			$by_holiday[(int)$result['holiday_id']][(int)$result['language_id']] = $result['name'];
		}

		foreach ($rows as &$row) {
			$descriptions = [];

			foreach ($by_holiday[(int)$row['holiday_id']] ?? [] as $language_id => $name) {
				$descriptions[$language_id] = ['name' => $name];
			}

			$row['description'] = $descriptions;
		}

		return $rows;
	}

	/**
	 * Per-language names keyed by language_id ([language_id => ['name' => ...]]).
	 */
	public function getDescriptions(int $warehouse_id): array {
		$description_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_description` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		foreach ($query->rows as $result) {
			$description_data[(int)$result['language_id']] = [
				'name' => $result['name'],
				'city' => $result['city'],
				'address_1' => $result['address_1'],
				'address_2' => $result['address_2'],
			];
		}

		return $description_data;
	}

	protected function saveDescriptions(int $warehouse_id, array $descriptions): void {
		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description) || !isset($description['name'])) {
				continue;
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_description` WHERE `warehouse_id` = '" . (int)$warehouse_id . "' AND `language_id` = '" . (int)$language_id . "'");
			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_description` SET `warehouse_id` = '" . (int)$warehouse_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $this->db->escape((string)$description['name']) . "', `city` = '" . $this->db->escape((string)($description['city'] ?? '')) . "', `address_1` = '" . $this->db->escape((string)($description['address_1'] ?? '')) . "', `address_2` = '" . $this->db->escape((string)($description['address_2'] ?? '')) . "'");
		}
	}

	/**
	 * Denormalised base name = default-language description (first non-empty
	 * as last resort). Keeps the admin list, order snapshots and e-mails
	 * working without joins.
	 */
	protected function resolveBaseName(array $data): string {
		$descriptions = isset($data['warehouse_description']) && is_array($data['warehouse_description']) ? $data['warehouse_description'] : [];

		if ($descriptions) {
			$default_language_id = (int)$this->config->get('config_language_id');

			if (!empty($descriptions[$default_language_id]['name'])) {
				return (string)$descriptions[$default_language_id]['name'];
			}

			foreach ($descriptions as $description) {
				if (!empty($description['name'])) {
					return (string)$description['name'];
				}
			}
		}

		return (string)($data['name'] ?? '');
	}

	/**
	 * Denormalised base address = default-language description values (first
	 * non-empty as last resort). Keeps the storefront pickup method working
	 * without per-language joins when no translation exists.
	 */
	protected function resolveBaseAddress(array $data): array {
		$descriptions = isset($data['warehouse_description']) && is_array($data['warehouse_description']) ? $data['warehouse_description'] : [];
		$default_language_id = (int)$this->config->get('config_language_id');

		$pick = function (string $key) use ($descriptions, $default_language_id): string {
			if (!empty($descriptions[$default_language_id][$key])) {
				return (string)$descriptions[$default_language_id][$key];
			}

			foreach ($descriptions as $description) {
				if (!empty($description[$key])) {
					return (string)$description[$key];
				}
			}

			return '';
		};

		return [
			'city' => $pick('city'),
			'address_1' => $pick('address_1'),
			'address_2' => $pick('address_2'),
		];
	}

	/**
	 * Shared holidays (warehouse_id = 0).
	 */
	public function getSharedHolidays(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `warehouse_id` = '0' ORDER BY `date` ASC");

		return $this->attachHolidayDescriptions($query->rows);
	}

	protected function saveSchedule(int $warehouse_id, array $schedule): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_schedule_window` WHERE `schedule_id` IN (SELECT `schedule_id` FROM `" . DB_PREFIX . "warehouse_schedule` WHERE `warehouse_id` = '" . (int)$warehouse_id . "')");

		for ($day = 1; $day <= 7; $day++) {
			$day_data = $schedule[$day] ?? ['is_open' => 1, 'windows' => []];

			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_schedule` SET `warehouse_id` = '" . (int)$warehouse_id . "', `day_of_week` = '" . (int)$day . "', `is_open` = '" . (int)!empty($day_data['is_open']) . "'");

			$schedule_id = $this->db->getLastId();

			foreach ($day_data['windows'] ?? [] as $window) {
				if (empty($window['time_from']) || empty($window['time_to'])) {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_schedule_window` SET `schedule_id` = '" . (int)$schedule_id . "', `time_from` = '" . $this->db->escape($window['time_from']) . "', `time_to` = '" . $this->db->escape($window['time_to']) . "'");
			}
		}
	}

	protected function saveHolidays(int $warehouse_id, array $holidays): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "warehouse_holiday` WHERE `warehouse_id` = '" . (int)$warehouse_id . "'");

		foreach ($holidays as $holiday) {
			if (empty($holiday['date'])) {
				continue;
			}

			$base_name = $this->resolveBaseHolidayName($holiday);

			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_holiday` SET `warehouse_id` = '" . (int)$warehouse_id . "', `date` = '" . $this->db->escape($holiday['date']) . "', `name` = '" . $this->db->escape($base_name) . "', `is_open` = '" . (int)!empty($holiday['is_open']) . "'");

			$holiday_id = $this->db->getLastId();

			$this->saveHolidayDescriptions($holiday_id, $holiday['description'] ?? []);
		}
	}

	/**
	 * Denormalised base name = default-language description name (first
	 * non-empty as last resort). Keeps the base `name` column meaningful
	 * without joins, mirroring resolveBaseName() for warehouses.
	 */
	protected function resolveBaseHolidayName(array $holiday): string {
		$descriptions = isset($holiday['description']) && is_array($holiday['description']) ? $holiday['description'] : [];

		if ($descriptions) {
			$default_language_id = (int)$this->config->get('config_language_id');

			if (!empty($descriptions[$default_language_id]['name'])) {
				return (string)$descriptions[$default_language_id]['name'];
			}

			foreach ($descriptions as $description) {
				if (!empty($description['name'])) {
					return (string)$description['name'];
				}
			}
		}

		return (string)($holiday['name'] ?? '');
	}

	protected function saveHolidayDescriptions(int $holiday_id, array $descriptions): void {
		foreach ($descriptions as $language_id => $description) {
			if (!is_array($description) || !isset($description['name'])) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "warehouse_holiday_description` SET `holiday_id` = '" . (int)$holiday_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $this->db->escape((string)$description['name']) . "'");
		}
	}

	protected function clearDefault(int $except_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "warehouse` SET `is_default` = '0' WHERE `warehouse_id` <> '" . (int)$except_id . "'");
	}
}