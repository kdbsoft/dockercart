-- Scheduler task registry — unified schema for all cron tasks.
-- Modules register their tasks via DockercartScheduler library on install().
-- source_id=0 for singleton tasks, >0 for per-profile tasks.

CREATE TABLE IF NOT EXISTS `oc_dockercart_scheduler_task` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `task_type` varchar(50) NOT NULL,
  `task_name` varchar(100) NOT NULL DEFAULT '',
  `source_id` int(11) NOT NULL DEFAULT 0,
  `worker_command` varchar(255) NOT NULL DEFAULT '',
  `cron_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cron_schedule` varchar(100) NOT NULL DEFAULT '',
  `last_run` datetime DEFAULT NULL,
  `last_result` text,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`task_id`),
  UNIQUE KEY `task_type` (`task_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
