SET @db = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_option' AND COLUMN_NAME = 'show_option_price');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_option` ADD COLUMN `show_option_price` tinyint(1) NOT NULL DEFAULT 1 AFTER `status`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
