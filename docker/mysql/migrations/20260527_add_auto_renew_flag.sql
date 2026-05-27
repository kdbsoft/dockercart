-- Auto-renew flag for promotions (expired → duplicate with shifted dates)
ALTER TABLE `oc_coupon` ADD COLUMN IF NOT EXISTS `auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;
ALTER TABLE `oc_product_special` ADD COLUMN IF NOT EXISTS `auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `date_end`;
ALTER TABLE `oc_product_discount` ADD COLUMN IF NOT EXISTS `auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `date_end`;
ALTER TABLE `oc_product_bundle` ADD COLUMN IF NOT EXISTS `auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `sort_order`;
ALTER TABLE `oc_product_gift` ADD COLUMN IF NOT EXISTS `auto_renew` tinyint(1) NOT NULL DEFAULT 0 AFTER `date_end`;
