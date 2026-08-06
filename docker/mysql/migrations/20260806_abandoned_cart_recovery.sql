-- Abandoned cart recovery: restore tokens, reminder-sent flag and settings.
-- Columns are added idempotently (guarded by information_schema); settings use
-- INSERT ... SELECT ... WHERE NOT EXISTS because oc_setting has no unique key
-- and migrations may re-run.

SET @db = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'restore_token');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `restore_token` varchar(64) DEFAULT NULL AFTER `last_step`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'restore_expires');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `restore_expires` datetime DEFAULT NULL AFTER `restore_token`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'reminder_sent');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `reminder_sent` tinyint(1) NOT NULL DEFAULT 0 AFTER `restore_expires`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'reminder_sent_at');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `reminder_sent_at` datetime DEFAULT NULL AFTER `reminder_sent`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for reminder sweep and token lookup.
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND INDEX_NAME = 'ux_restore_token');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD UNIQUE INDEX `ux_restore_token` (`restore_token`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Unique (session_id, recovered): the upsert in saveAbandonedCart() relies on it.
-- Kept here so fresh installs get it even if the earlier migration was skipped
-- because the module table did not exist yet.
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND INDEX_NAME = 'ux_session_recovered');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD UNIQUE INDEX `ux_session_recovered` (`session_id`, `recovered`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Settings: feature toggle, reminder delay, retention period.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_cart_abandoned_enable', '1', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_cart_abandoned_enable');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_cart_abandoned_delay_days', '1', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_cart_abandoned_delay_days');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_cart_abandoned_retention_days', '90', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_cart_abandoned_retention_days');

-- Scheduler task: send reminders and clean up old carts once per day at 04:30.
INSERT INTO `oc_dockercart_scheduler_task` (`task_type`, `task_name`, `worker_command`, `source_id`, `cron_enabled`, `cron_schedule`, `status`, `date_added`, `date_modified`)
SELECT 'abandoned_cart_cleanup', 'Abandoned cart cleanup', 'php /var/www/html/bin/dockercart_abandoned_cart_cleanup.php', '0', '1', '30 4 * * *', '1', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_dockercart_scheduler_task` WHERE `task_type` = 'abandoned_cart_cleanup' AND `source_id` = '0');
