ALTER TABLE `oc_category` ADD COLUMN `banner_image` varchar(255) DEFAULT NULL AFTER `background_image`;
ALTER TABLE `oc_category` ADD COLUMN `banner_link` varchar(255) NOT NULL DEFAULT '' AFTER `banner_image`;
