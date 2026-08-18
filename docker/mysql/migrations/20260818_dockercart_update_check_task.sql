-- Register the scheduled "update available" check (system task, hidden from
-- the admin scheduler UI). Runs hourly via the scheduler daemon and keeps the
-- update-check cache in oc_setting warm, so admin page loads and the header
-- update-bell never block on a network request to the remote repo.
-- Idempotent: re-running this migration updates the existing row.
INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('dockercart_update_check', 'Update availability check', 0, 'php /var/www/html/bin/dockercart_update_check.php', 1, 'hourly', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `date_modified` = NOW();