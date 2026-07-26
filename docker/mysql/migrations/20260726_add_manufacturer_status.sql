ALTER TABLE `oc_manufacturer` ADD COLUMN IF NOT EXISTS `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `sort_order`;
