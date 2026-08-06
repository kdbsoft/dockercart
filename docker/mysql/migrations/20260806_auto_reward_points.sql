-- Auto award/revoke of reward points on order status changes.
-- reward_awarded guards against double-awarding; reward_revoked_points accumulates
-- revoked points so repeated partial refunds converge to zero and never go negative.

SET @db = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_order' AND COLUMN_NAME = 'reward_awarded');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_order` ADD COLUMN `reward_awarded` tinyint(1) NOT NULL DEFAULT 0 AFTER `paid_amount`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_order' AND COLUMN_NAME = 'reward_revoked_points');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_order` ADD COLUMN `reward_revoked_points` int(11) NOT NULL DEFAULT 0 AFTER `reward_awarded`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Settings: auto-award on complete status, auto-revoke on refund/reversal.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_reward_auto_award', '1', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_reward_auto_award');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_reward_auto_revoke', '1', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_reward_auto_revoke');

-- Delayed award window: award points N days after the order entered its final
-- (complete) status, so refunds that arrive within the window are not
-- double-revoked. 0 = award immediately on status change (default 14).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_reward_delay_days', '14', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_reward_delay_days');

-- Scheduler task: run the delayed award sweep once per day at 04:17.
INSERT INTO `oc_dockercart_scheduler_task` (`task_type`, `task_name`, `worker_command`, `source_id`, `cron_enabled`, `cron_schedule`, `status`, `date_added`, `date_modified`)
SELECT 'reward_auto_award', 'Auto-award reward points', 'php /var/www/html/bin/dockercart_reward_award.php', '0', '1', '17 4 * * *', '1', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_dockercart_scheduler_task` WHERE `task_type` = 'reward_auto_award' AND `source_id` = '0');
