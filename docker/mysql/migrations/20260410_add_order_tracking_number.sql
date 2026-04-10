-- Migration: 20260410 - Add tracking_number to order table
ALTER TABLE `oc_order`
  ADD COLUMN IF NOT EXISTS `tracking_number` VARCHAR(64) NOT NULL DEFAULT '' AFTER `shipping_code`;
