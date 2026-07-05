-- Register system license check task (hidden from admin UI)
-- Uses ON DUPLICATE KEY to be idempotent on repeated migrations

INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('license_check', 'License Verification', 0, 'php /var/www/html/bin/dockercart_license_check.php', 1, 'every_3d', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `date_modified` = NOW();
