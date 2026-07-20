-- Add per-product display order to product option values.
-- Order is controlled via drag-and-drop in the product admin form and stored
-- on oc_product_option_value.sort_order (overrides the global oc_option_value.sort_order).
--
-- Idempotent: make migrate re-applies every migration file on each run, so the
-- column must only be added when it does not already exist.

SET @db = DATABASE();
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_product_option_value' AND COLUMN_NAME = 'sort_order');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_product_option_value` ADD COLUMN `sort_order` int(11) NOT NULL DEFAULT 0 AFTER `is_hit`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
