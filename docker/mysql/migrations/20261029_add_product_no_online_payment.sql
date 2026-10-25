ALTER TABLE `oc_product` ADD COLUMN IF NOT EXISTS `no_online_payment` tinyint(1) NOT NULL DEFAULT 0 AFTER `discontinued`;
