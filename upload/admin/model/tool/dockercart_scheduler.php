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
			"SELECT `task_id`, `task_type`, `task_name`, `source_id`,
			        `cron_enabled`, `cron_schedule`, `last_run`, `status`
			 FROM `" . DB_PREFIX . "dockercart_scheduler_task`
			 WHERE `status` = 1
			 ORDER BY `task_type` ASC, `task_name` ASC"
		);

		return $result->rows;
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
