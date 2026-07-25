ALTER TABLE `oc_product_discount` ADD COLUMN IF NOT EXISTS `date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;
ALTER TABLE `oc_product_special` ADD COLUMN IF NOT EXISTS `date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;
ALTER TABLE `oc_product_gift` ADD COLUMN IF NOT EXISTS `date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;
ALTER TABLE `oc_product_bxgy` ADD COLUMN IF NOT EXISTS `date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;
ALTER TABLE `oc_product_bundle` ADD COLUMN IF NOT EXISTS `date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;

UPDATE `oc_product_discount` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00';
UPDATE `oc_product_special` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00';
UPDATE `oc_product_gift` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00';
UPDATE `oc_product_bxgy` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00';
UPDATE `oc_product_bundle` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00';