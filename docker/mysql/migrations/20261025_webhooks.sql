-- Outbound webhooks: subscriptions, delivery outbox, settings and workers.
--
-- Design notes:
--   * the delivery row is the outbox: it is inserted in the same transaction as
--     the business change (Dispatcher::emit()), so an event cannot be lost
--     between COMMIT and a send;
--   * the payload column stores the exact JSON body that is sent. Retries reuse
--     it byte-for-byte, so a subscription edit cannot change what a retry sends;
--   * HMAC secrets are stored encrypted with config_encryption (AES-256-GCM,
--     see upload/system/library/encryption.php) and shown only once on
--     create/rotate;
--   * delivery is performed by the scheduler worker
--     bin/dockercart_webhook_deliver.php (task type webhook_deliver, every
--     minute, hidden from the scheduler UI) with exponential backoff
--     (1m/5m/30m/2h/12h) and auto-disable of repeatedly failing subscriptions.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_dockercart_webhook_subscription` (
	`subscription_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(128) NOT NULL DEFAULT '',
	`url` VARCHAR(500) NOT NULL DEFAULT '',
	`secret_encrypted` VARCHAR(1024) NOT NULL DEFAULT '',
	`secret_hint` VARCHAR(16) NOT NULL DEFAULT '',
	`events` TEXT NULL DEFAULT NULL,
	`filters` TEXT NULL DEFAULT NULL,
	`full_payload` TINYINT(1) NOT NULL DEFAULT 0,
	`status` TINYINT(1) NOT NULL DEFAULT 1,
	`consecutive_failures` INT(11) NOT NULL DEFAULT 0,
	`disabled_reason` VARCHAR(32) NOT NULL DEFAULT '',
	`disabled_at` DATETIME NULL DEFAULT NULL,
	`last_success_at` DATETIME NULL DEFAULT NULL,
	`last_failure_at` DATETIME NULL DEFAULT NULL,
	`created_by_api_key_id` INT(11) NOT NULL DEFAULT 0,
	`created_by_user_id` INT(11) NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`subscription_id`),
	KEY `idx_webhook_subscription_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_webhook_delivery` (
	`delivery_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`subscription_id` INT(11) NOT NULL DEFAULT 0,
	`event_id` CHAR(30) NOT NULL DEFAULT '',
	`event_type` VARCHAR(64) NOT NULL DEFAULT '',
	`payload` MEDIUMTEXT NOT NULL,
	`is_test` TINYINT(1) NOT NULL DEFAULT 0,
	`status` VARCHAR(16) NOT NULL DEFAULT 'pending',
	`attempts` INT(11) NOT NULL DEFAULT 0,
	`next_attempt_at` DATETIME NOT NULL,
	`locked_until` DATETIME NULL DEFAULT NULL,
	`response_code` SMALLINT(5) NOT NULL DEFAULT 0,
	`response_body` TEXT NULL DEFAULT NULL,
	`duration_ms` INT(11) NOT NULL DEFAULT 0,
	`last_error` VARCHAR(255) NOT NULL DEFAULT '',
	`occurred_at` DATETIME NOT NULL,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`delivery_id`),
	UNIQUE KEY `uniq_webhook_delivery_event` (`subscription_id`, `event_id`),
	KEY `idx_webhook_delivery_due` (`status`, `next_attempt_at`),
	KEY `idx_webhook_delivery_subscription` (`subscription_id`, `date_added`),
	KEY `idx_webhook_delivery_date` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global switches, written with updateSetting('config', …) — never
-- editSetting(), which would wipe the ~100 other config_* store settings.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_webhook_status', '1', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_webhook_status'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_webhook_timeout', '10', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_webhook_timeout'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_webhook_max_attempts', '6', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_webhook_max_attempts'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_webhook_disable_after', '4', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_webhook_disable_after'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_webhook_log_retention_days', '30', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_webhook_log_retention_days'
);

-- Delivery worker: every minute, hidden from the scheduler UI.
INSERT INTO `oc_dockercart_scheduler_task`
	(`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
	('webhook_deliver', 'Webhook delivery', 0, 'php /var/www/html/bin/dockercart_webhook_deliver.php', 1, '* * * * *', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
	`worker_command` = VALUES(`worker_command`),
	`cron_enabled` = 1,
	`cron_schedule` = VALUES(`cron_schedule`),
	`is_system` = 1,
	`date_modified` = NOW();

-- Retention purge: daily, hidden from the scheduler UI.
INSERT INTO `oc_dockercart_scheduler_task`
	(`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
	('webhook_cleanup', 'Webhook log cleanup', 0, 'php /var/www/html/bin/dockercart_webhook_cleanup.php', 1, 'daily', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
	`worker_command` = VALUES(`worker_command`),
	`cron_enabled` = 1,
	`cron_schedule` = VALUES(`cron_schedule`),
	`is_system` = 1,
	`date_modified` = NOW();
