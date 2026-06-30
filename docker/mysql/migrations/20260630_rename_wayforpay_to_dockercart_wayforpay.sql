-- Rename wayforpay_order table to dockercart_wayforpay_order (idempotent)
-- Only rename if old table exists and new table doesn't
SET @old_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_wayforpay_order');
SET @new_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_dockercart_wayforpay_order');

SET @sql = IF(@old_exists > 0 AND @new_exists = 0,
  'RENAME TABLE `oc_wayforpay_order` TO `oc_dockercart_wayforpay_order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate settings from payment_wayforpay_* to payment_dockercart_wayforpay_*
UPDATE `oc_setting`
SET `key` = REPLACE(`key`, 'payment_wayforpay_', 'payment_dockercart_wayforpay_')
WHERE `key` LIKE 'payment_wayforpay_%';

-- Migrate order payment_code from 'wayforpay' to 'dockercart_wayforpay'
UPDATE `oc_order`
SET `payment_code` = 'dockercart_wayforpay'
WHERE `payment_code` = 'wayforpay';
