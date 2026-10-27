-- Register the scheduled "extension store cache warm" task (system task,
-- hidden from the admin scheduler UI like dockercart_update_check). Runs
-- hourly via the scheduler daemon and refreshes the local YML catalog cache
-- (storage/dockercart/extension_store/yml_<lang>.xml), so the green "updates
-- available" badge in the admin sidebar shows fresh data on any admin page
-- without anyone having to open the Add-ons Store first. The badge itself
-- stays cache-only and never touches the network on page load.
-- Idempotent: re-running this migration updates the existing row.
INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('dockercart_store_cache_warm', 'Extension store cache warm', 0, 'php /var/www/html/bin/dockercart_store_cache_warm.php', 1, 'hourly', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `date_modified` = NOW();