-- Register optional S3 backup task (visible in admin System → Scheduler)
-- Disabled by default; user enables it in the UI after configuring BACKUP_S3_* in .env
-- Uses ON DUPLICATE KEY to be idempotent on repeated migrations

INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('backup_s3', 'Backup to S3', 0, 'php /var/www/html/bin/dockercart_backup_s3.php', 0, '0 2 * * *', 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `task_name`       = VALUES(`task_name`),
  `worker_command`  = VALUES(`worker_command`),
  `cron_schedule`   = VALUES(`cron_schedule`),
  `is_system`       = 0,
  `date_modified`   = NOW();
