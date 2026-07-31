-- Migration: 20260731 - Allow multiple tracking numbers (| separated) per order
ALTER TABLE `oc_order`
  MODIFY COLUMN `tracking_number` VARCHAR(1024) NOT NULL DEFAULT '' AFTER `shipping_code`;
