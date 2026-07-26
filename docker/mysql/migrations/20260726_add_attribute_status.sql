ALTER TABLE `oc_attribute` ADD COLUMN IF NOT EXISTS `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`;
ALTER TABLE `oc_attribute_group` ADD COLUMN IF NOT EXISTS `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`;
