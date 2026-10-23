-- Admin REST API (v1).
--
-- Adds the API key registry and the request journal used by the `api`
-- application (`/api/v1/...`, see upload/api/index.php and
-- upload/system/library/dockercart/api/).
--
-- Design notes:
--   * only the SHA-256 hash of the bearer token is stored — the plain token is
--     shown to the admin exactly once (on create / rotate);
--   * a key is granted permissions through an existing admin user group
--     (oc_user_group.permission: "access" = read, "modify" = write), so there is
--     no second, parallel permission model to keep in sync;
--   * oc_api_log.key_name is a snapshot, so the journal stays readable after a
--     key is deleted.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_api_key` (
	`api_key_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(128) NOT NULL DEFAULT '',
	`comment` TEXT NULL DEFAULT NULL,
	`prefix` VARCHAR(20) NOT NULL DEFAULT '',
	`token_hash` CHAR(64) NOT NULL,
	`user_group_id` INT(11) NOT NULL DEFAULT 0,
	`status` TINYINT(1) NOT NULL DEFAULT 1,
	`expires_at` DATETIME NULL DEFAULT NULL,
	`ip_allowlist` TEXT NULL DEFAULT NULL,
	`rate_limit` INT(11) NOT NULL DEFAULT 0,
	`last_used_at` DATETIME NULL DEFAULT NULL,
	`last_used_ip` VARCHAR(45) NOT NULL DEFAULT '',
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`api_key_id`),
	UNIQUE KEY `uniq_api_key_token_hash` (`token_hash`),
	KEY `idx_api_key_user_group` (`user_group_id`),
	KEY `idx_api_key_status_expires` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_api_log` (
	`api_log_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`api_key_id` INT(11) NOT NULL DEFAULT 0,
	`key_name` VARCHAR(128) NOT NULL DEFAULT '',
	`method` VARCHAR(10) NOT NULL DEFAULT '',
	`path` VARCHAR(255) NOT NULL DEFAULT '',
	`route` VARCHAR(128) NOT NULL DEFAULT '',
	`status_code` SMALLINT(5) NOT NULL DEFAULT 0,
	`duration_ms` INT(11) NOT NULL DEFAULT 0,
	`ip` VARCHAR(45) NOT NULL DEFAULT '',
	`user_agent` VARCHAR(255) NOT NULL DEFAULT '',
	`request_id` VARCHAR(40) NOT NULL DEFAULT '',
	`error_code` VARCHAR(64) NOT NULL DEFAULT '',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`api_log_id`),
	KEY `idx_api_log_key_date` (`api_key_id`, `date_added`),
	KEY `idx_api_log_date` (`date_added`),
	KEY `idx_api_log_status` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings. They live in the shared `config` code, but are written
-- directly (upsert of three keys) instead of editSetting(), which would wipe
-- the ~100 other config_* store settings.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_status', '0', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_status'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_rate_limit', '120', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_rate_limit'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_log_retention_days', '30', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_log_retention_days'
);

-- Daily API journal cleanup (hidden from the admin scheduler UI).
INSERT INTO `oc_dockercart_scheduler_task`
	(`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
	('api_log_cleanup', 'API log cleanup', 0, 'php /var/www/html/bin/dockercart_api_log_cleanup.php', 1, 'daily', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
	`worker_command` = VALUES(`worker_command`),
	`is_system` = 1,
	`date_modified` = NOW();
