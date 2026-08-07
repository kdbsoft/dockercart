ALTER TABLE `oc_product` ADD COLUMN IF NOT EXISTS `image_360` VARCHAR(255) NOT NULL DEFAULT '' AFTER `model_3d`;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_product_image_360_enable', '1', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_product_image_360_enable');
