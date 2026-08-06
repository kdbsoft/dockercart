ALTER TABLE `oc_product` ADD COLUMN IF NOT EXISTS `discontinued` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;
