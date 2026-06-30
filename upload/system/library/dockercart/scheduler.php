<?php
/**
 * DockerCart Scheduler — core registry API.
 *
 * Unified task registry backing both the admin UI and the CLI daemon.
 * Modules call registerTask / unregisterTask in their install() / uninstall()
 * and registerProfileTask / unregisterProfileTask when profiles are created or deleted.
 *
 * Load via: $this->load->library('dockercart/scheduler');
 */
class DockercartScheduler {
	private $db;
	private $db_prefix;

	public function __construct($registry) {
		$this->db = $registry->get('db');
		$this->db_prefix = DB_PREFIX;
	}

	// ────────────────────────────────────────────────────────────
	//  Singleton tasks (one row per task_type)
	// ────────────────────────────────────────────────────────────

	/**
	 * Register a singleton global task (e.g. currency_refresh, novapost_sync).
	 *
	 * Idempotent — ignores rows that already exist (ON DUPLICATE KEY).
	 *
	 * @param string $type          task_type UNIQUE key
	 * @param string $name          human-readable label
	 * @param string $workerCommand full CLI command with optional %d/%s placeholders
	 * @param string $schedule      cron expression or preset key (empty = disabled)
	 * @param bool   $enabled       initial cron_enabled state
	 * @return int  task_id
	 */
	public function registerTask(string $type, string $name, string $workerCommand, string $schedule = '', bool $enabled = false): int {
		$table = $this->db_prefix . 'dockercart_scheduler_task';

		$this->db->query(
			"INSERT INTO `" . $this->db->escape($table) . "`
			 SET `task_type`    = '" . $this->db->escape($type) . "',
			     `task_name`    = '" . $this->db->escape($name) . "',
			     `worker_command` = '" . $this->db->escape($workerCommand) . "',
			     `source_id`    = 0,
			     `cron_enabled` = " . ($enabled ? '1' : '0') . ",
			     `cron_schedule`= '" . $this->db->escape($schedule) . "',
			     `status`       = 1,
			     `date_added`   = NOW(),
			     `date_modified`= NOW()
			 ON DUPLICATE KEY UPDATE
			     `task_name`    = VALUES(`task_name`),
			     `worker_command` = VALUES(`worker_command`),
			     `date_modified`= NOW()"
		);

		$id = (int)$this->db->getLastId();
		if ($id === 0) {
			$result = $this->db->query(
				"SELECT `task_id` FROM `" . $this->db->escape($table) . "`
				 WHERE `task_type` = '" . $this->db->escape($type) . "'"
			);
			$id = $result->num_rows ? (int)$result->row['task_id'] : 0;
		}

		return $id;
	}

	/**
	 * Remove all tasks of a given type.
	 */
	public function unregisterTask(string $type): void {
		$this->db->query(
			"DELETE FROM `" . $this->db_prefix . "dockercart_scheduler_task`
			 WHERE `task_type` = '" . $this->db->escape($type) . "'"
		);
	}

	// ────────────────────────────────────────────────────────────
	//  Profile-scoped tasks (one row per profile)
	// ────────────────────────────────────────────────────────────

	/**
	 * Register (upsert) a per-profile schedulable task.
	 *
	 * @param string $type          task_type (e.g. import_yml)
	 * @param int    $sourceId      profile_id from the owner table
	 * @param string $name          human-readable name
	 * @param string $workerCommand CLI template with %d for source_id
	 * @param string $schedule
	 * @param bool   $enabled
	 * @return int  task_id
	 */
	public function registerProfileTask(string $type, int $sourceId, string $name, string $workerCommand, string $schedule = '', bool $enabled = false): int {
		$table = $this->db_prefix . 'dockercart_scheduler_task';

		$this->db->query(
			"INSERT INTO `" . $this->db->escape($table) . "`
			 SET `task_type`     = '" . $this->db->escape($type) . "',
			     `task_name`     = '" . $this->db->escape($name) . "',
			     `source_id`     = " . (int)$sourceId . ",
			     `worker_command` = '" . $this->db->escape($workerCommand) . "',
			     `cron_enabled`  = " . ($enabled ? '1' : '0') . ",
			     `cron_schedule` = '" . $this->db->escape($schedule) . "',
			     `status`        = 1,
			     `date_added`    = NOW(),
			     `date_modified` = NOW()
			 ON DUPLICATE KEY UPDATE
			     `task_name`     = VALUES(`task_name`),
			     `worker_command` = VALUES(`worker_command`),
			     `date_modified` = NOW()"
		);

		$id = (int)$this->db->getLastId();
		if ($id === 0) {
			$result = $this->db->query(
				"SELECT `task_id` FROM `" . $this->db->escape($table) . "`
				 WHERE `task_type` = '" . $this->db->escape($type) . "'
				   AND `source_id` = " . (int)$sourceId
			);
			$id = $result->num_rows ? (int)$result->row['task_id'] : 0;
		}

		return $id;
	}

	/**
	 * Remove a profile-scoped task.
	 */
	public function unregisterProfileTask(string $type, int $sourceId): void {
		$this->db->query(
			"DELETE FROM `" . $this->db_prefix . "dockercart_scheduler_task`
			 WHERE `task_type` = '" . $this->db->escape($type) . "'
			   AND `source_id` = " . (int)$sourceId
		);
	}

	// ────────────────────────────────────────────────────────────
	//  Runtime helpers
	// ────────────────────────────────────────────────────────────

	/**
	 * Update schedule (and auto-enable/disable).
	 */
	public function setSchedule(int $taskId, string $schedule): void {
		$enabled = ($schedule !== '') ? 1 : 0;

		$this->db->query(
			"UPDATE `" . $this->db_prefix . "dockercart_scheduler_task`
			 SET `cron_schedule` = '" . $this->db->escape($schedule) . "',
			     `cron_enabled`  = " . $enabled . ",
			     `date_modified` = NOW()
			 WHERE `task_id` = " . (int)$taskId
		);
	}

	/**
	 * Set enabled flag for a task.
	 */
	public function setEnabled(int $taskId, bool $enabled): void {
		$this->db->query(
			"UPDATE `" . $this->db_prefix . "dockercart_scheduler_task`
			 SET `cron_enabled` = " . ($enabled ? '1' : '0') . ",
			     `date_modified` = NOW()
			 WHERE `task_id` = " . (int)$taskId
		);
	}

	/**
	 * Get a single task row by task_id.
	 *
	 * @return array|null
	 */
	public function getTask(int $taskId): ?array {
		$result = $this->db->query(
			"SELECT * FROM `" . $this->db_prefix . "dockercart_scheduler_task`
			 WHERE `task_id` = " . (int)$taskId
		);

		return $result->num_rows ? $result->row : null;
	}

	/**
	 * Get all tasks for a given task_type (including disabled).
	 *
	 * @return array
	 */
	public function getTasksByType(string $type): array {
		$result = $this->db->query(
			"SELECT * FROM `" . $this->db_prefix . "dockercart_scheduler_task`
			 WHERE `task_type` = '" . $this->db->escape($type) . "'"
		);

		return $result->rows;
	}

	/**
	 * Upsert profile task from profile data (used by saveProfile-style controllers).
	 * Convenience wrapper around registerProfileTask.
	 *
	 * @param string $type
	 * @param int    $sourceId
	 * @param string $name
	 * @param string $workerCommand
	 * @param array  $data            form data (may contain cron_enabled, cron_schedule)
	 * @return int  task_id
	 */
	public function upsertProfileTask(string $type, int $sourceId, string $name, string $workerCommand, array $data): int {
		$schedule = isset($data['cron_schedule']) ? (string)$data['cron_schedule'] : '';
		$enabled  = !empty($data['cron_enabled']);

		return $this->registerProfileTask($type, $sourceId, $name, $workerCommand, $schedule, $enabled);
	}
}
