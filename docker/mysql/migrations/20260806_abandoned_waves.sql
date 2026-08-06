-- Multi-wave abandoned cart reminders + per-wave coupon support.
-- reminder_wave: which wave number was last sent (0 = none yet).
-- reminder_coupon_id: auto-created coupon attached to the reminder.

-- Checkout analytics table (module-created; ensure it exists so conversion
-- statistics work even if the module install() never ran on this database).
CREATE TABLE IF NOT EXISTS `oc_dockercart_checkout_analytics` (
    `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
    `session_id` varchar(255) NOT NULL,
    `customer_id` int(11) DEFAULT 0,
    `step` varchar(50) NOT NULL,
    `data` text,
    `date_added` datetime NOT NULL,
    PRIMARY KEY (`analytics_id`),
    KEY `session_id` (`session_id`),
    KEY `step` (`step`),
    KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'reminder_wave');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `reminder_wave` int(11) NOT NULL DEFAULT 0 AFTER `reminder_sent_at`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_dockercart_checkout_abandoned' AND COLUMN_NAME = 'reminder_coupon_id');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD COLUMN `reminder_coupon_id` int(11) DEFAULT NULL AFTER `reminder_wave`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Waves configuration: JSON array [{days: N, discount: N}, ...]. Default: one
-- wave after 1 day without a coupon.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_cart_abandoned_waves', '[{"days":1,"discount":0}]', '1'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_cart_abandoned_waves');
