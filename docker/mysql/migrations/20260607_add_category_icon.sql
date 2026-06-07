ALTER TABLE `oc_category` ADD COLUMN IF NOT EXISTS `icon` varchar(255) DEFAULT NULL AFTER `image`;
