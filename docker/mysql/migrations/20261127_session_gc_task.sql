-- Session garbage collection for file-based sessions (storage/session).
--
-- Why a scheduler task instead of PHP's built-in probabilistic GC:
--   * the old path globbed the entire session directory inside a random web
--     request (session.gc_probability / gc_divisor = 1/100), which is an
--     O(number of sessions) latency spike nobody can predict;
--   * a dedicated worker makes the cleanup deterministic and observable
--     (exit code + one log line in the scheduler journal).
--
-- The worker (bin/dockercart_session_gc.php) reuses
-- Session\File::collectGarbage(), so the "never delete a session file that a
-- live request holds locked" rule still applies. Lifetime is
-- session.gc_maxlifetime (86400 in docker/php.prod.ini). With
-- SESSION_ENGINE=redis|db DIR_SESSION stays empty and the run is a cheap
-- no-op, so the task can stay enabled in every configuration.
--
-- is_system = 1 keeps the row hidden from the scheduler UI (the model lists
-- only is_system = 0) — operators should not reschedule the janitor.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

INSERT INTO `oc_dockercart_scheduler_task`
	(`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
	('session_gc', 'Session cleanup', 0, 'php /var/www/html/bin/dockercart_session_gc.php', 1, 'hourly', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
	`worker_command` = VALUES(`worker_command`),
	`cron_enabled` = 1,
	`cron_schedule` = VALUES(`cron_schedule`),
	`is_system` = 1,
	`status` = 1,
	`date_modified` = NOW();
