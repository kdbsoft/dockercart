-- Distinguish reward redemptions from award reversals.
-- Existing rows are classified from their sign and legacy refund descriptions.

SET @db = DATABASE();

SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_customer_reward' AND COLUMN_NAME = 'operation_type');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_customer_reward` ADD COLUMN `operation_type` varchar(16) NOT NULL DEFAULT ''legacy'' AFTER `points`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'oc_customer_reward' AND INDEX_NAME = 'idx_customer_reward_operation');
SET @sql = IF(@exists = 0,
    'ALTER TABLE `oc_customer_reward` ADD KEY `idx_customer_reward_operation` (`order_id`, `operation_type`, `points`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `oc_customer_reward`
SET operation_type = 'award'
WHERE operation_type = 'legacy' AND points > 0;

UPDATE `oc_customer_reward`
SET operation_type = 'reversal'
WHERE operation_type = 'legacy'
  AND points < 0
  AND (
      description LIKE '%Refund%'
      OR description LIKE '%refund%'
      OR description LIKE '%Возврат по заказу%'
      OR description LIKE '%Повернення по замовленню%'
  );

UPDATE `oc_customer_reward`
SET operation_type = 'redeem'
WHERE operation_type = 'legacy' AND points < 0;
