-- Migration: 20260809 - scheduled Manticore reindex task
-- Manticore stores RT indexes in a tmpfs volume: they are wiped on container
-- restart and the boot-time background reindex does not always run, leaving
-- admin autocompletes on the SQL fallback. Register a daily scheduler task
-- that rebuilds all indexes via the CLI worker.
-- Idempotent: make migrate re-runs every file.
INSERT INTO `oc_dockercart_scheduler_task`
    (`task_type`, `task_name`, `cron_enabled`, `cron_schedule`, `status`,
     `source_table`, `source_id`, `worker_command`, `date_added`, `date_modified`)
SELECT 'manticore_search_reindex', 'Manticore Search Reindex', 1, 'daily', 1,
       NULL, 0, 'php /var/www/html/bin/dockercart_search_reindex.php', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_dockercart_scheduler_task`
    WHERE `task_type` = 'manticore_search_reindex' AND `source_id` = 0
);
