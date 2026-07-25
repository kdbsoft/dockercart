-- Traffic Sources: table, extension, settings, scheduler cleanup task

-- 1. Table
CREATE TABLE IF NOT EXISTS `oc_dockercart_traffic_source` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(32) NOT NULL,
  `source` varchar(128) NOT NULL DEFAULT '',
  `medium` varchar(64) NOT NULL DEFAULT 'none',
  `campaign` varchar(128) NOT NULL DEFAULT '',
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_session_source` (`session_id`, `source`),
  KEY `idx_date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Extension registration
INSERT INTO `oc_extension` (`type`, `code`)
SELECT * FROM (SELECT 'dashboard' AS `type`, 'traffic_source' AS `code`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` = 'traffic_source') LIMIT 1;

-- 3. Dashboard widget settings
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_traffic_source' AS `code`, 'dashboard_traffic_source_status' AS `key`, '1' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_traffic_source_status') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_traffic_source' AS `code`, 'dashboard_traffic_source_width' AS `key`, '4' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_traffic_source_width') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_traffic_source' AS `code`, 'dashboard_traffic_source_sort_order' AS `key`, '9' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_traffic_source_sort_order') LIMIT 1;

-- 4. Scheduler cleanup task (daily, system-level)
INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('traffic_source_cleanup', 'Traffic Source Cleanup', 0, 'php /var/www/html/bin/dockercart_traffic_cleanup.php', 1, 'daily', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `date_modified` = NOW();
