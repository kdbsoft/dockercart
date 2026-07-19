ALTER TABLE `oc_product_image` ADD COLUMN IF NOT EXISTS `language_id` INT(11) DEFAULT NULL AFTER `product_id`;
ALTER TABLE `oc_product_image` ADD INDEX IF NOT EXISTS `language_id` (`language_id`);
