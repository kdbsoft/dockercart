-- Scheduler task multilingual names — per-language display names editable
-- in the admin Scheduler UI (fallback to oc_dockercart_scheduler_task.task_name).
-- Keyed by task_type + source_id + language_id so per-profile tasks of the
-- same type (e.g. import_yml with different profiles) keep separate names.
-- No FK constraints: language removal leaves orphan rows, which is acceptable.

CREATE TABLE IF NOT EXISTS `oc_dockercart_scheduler_task_name` (
  `task_type` varchar(50) NOT NULL,
  `source_id` int(11) NOT NULL DEFAULT 0,
  `language_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`task_type`, `source_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
