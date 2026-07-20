SET @exists = 0;
SELECT COUNT(*) INTO @exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'oc_option_value'
    AND COLUMN_NAME = 'image';
SET @sql = IF(@exists > 0, 'ALTER TABLE `oc_option_value` DROP COLUMN `image`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
