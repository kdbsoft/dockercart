<?php
declare(strict_types=1);

class ModelToolDockercartScheduler extends Model {

	/**
	 * Get all scheduled tasks from the universal registry.
	 *
	 * @return array
	 */
	public function getAllScheduledTasks(): array {
		$result = $this->db->query(
			"SELECT `task_id`, `task_type`, `task_name` AS `task_name_fallback`, `source_id`,
			        `cron_enabled`, `cron_schedule`, `last_run`, `status`, `is_system`
			 FROM `" . DB_PREFIX . "dockercart_scheduler_task`
			 WHERE `status` = 1 AND `is_system` = 0
			 ORDER BY `task_type` ASC, `task_name` ASC"
		);

		return $result->rows;
	}

	/**
	 * Get multilingual display names for a task.
	 *
	 * @param int $taskId
	 * @return array  map of language_id => name
	 */
	public function getTaskNames(int $taskId): array {
		$result = $this->db->query(
			"SELECT n.`language_id`, n.`name`
			 FROM `" . DB_PREFIX . "dockercart_scheduler_task` t
			 LEFT JOIN `" . DB_PREFIX . "dockercart_scheduler_task_name` n
			        ON n.`task_type` = t.`task_type` AND n.`source_id` = t.`source_id`
			 WHERE t.`task_id` = " . (int)$taskId
		);

		$names = [];

		foreach ($result->rows as $row) {
			$names[(int)$row['language_id']] = $row['name'];
		}

		return $names;
	}

	/**
	 * Save multilingual display names for a task (replace-all for its task_type + source_id).
	 *
	 * @param int   $taskId
	 * @param array $names  map of language_id => name (empty values are skipped)
	 * @return bool
	 */
	public function saveTaskNames(int $taskId, array $names): bool {
		$result = $this->db->query(
			"SELECT `task_type`, `source_id`
			 FROM `" . DB_PREFIX . "dockercart_scheduler_task`
			 WHERE `task_id` = " . (int)$taskId
		);

		if (!$result->num_rows) {
			return false;
		}

		$taskType = $result->row['task_type'];
		$sourceId = (int)$result->row['source_id'];

		$this->db->query(
			"DELETE FROM `" . DB_PREFIX . "dockercart_scheduler_task_name`
			 WHERE `task_type` = '" . $this->db->escape($taskType) . "'
			   AND `source_id` = " . $sourceId
		);

		foreach ($names as $languageId => $name) {
			$name = trim((string)$name);

			if ($name === '') {
				continue;
			}

			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "dockercart_scheduler_task_name`
				 SET `task_type`   = '" . $this->db->escape($taskType) . "',
				     `source_id`   = " . $sourceId . ",
				     `language_id` = " . (int)$languageId . ",
				     `name`        = '" . $this->db->escape($name) . "'
				 ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)"
			);
		}

		return true;
	}

	/**
	 * Toggle cron_enabled for a task.
	 *
	 * @param int $taskId
	 * @param int $enabled 1 = enabled, 0 = disabled
	 * @return bool
	 */
	public function toggleTask(int $taskId, int $enabled): bool {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_scheduler_task`
			 SET `cron_enabled` = " . ($enabled ? '1' : '0') . ",
			     `date_modified` = NOW()
			 WHERE `task_id` = " . (int)$taskId
		);

		return true;
	}

	/**
	 * Update cron_schedule (and auto-toggle cron_enabled).
	 *
	 * @param int    $taskId
	 * @param string $schedule Cron expression or preset key (empty = disabled)
	 * @return bool
	 */
	public function updateSchedule(int $taskId, string $schedule): bool {
		$enabled = ($schedule !== '') ? 1 : 0;

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_scheduler_task`
			 SET `cron_schedule` = '" . $this->db->escape($schedule) . "',
			     `cron_enabled`  = " . $enabled . ",
			     `date_modified` = NOW()
			 WHERE `task_id` = " . (int)$taskId
		);

		return true;
	}
}
