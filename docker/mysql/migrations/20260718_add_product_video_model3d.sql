CREATE TABLE IF NOT EXISTS `oc_product_video` (
  `product_video_id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `language_id` INT DEFAULT NULL,
  `video_type` VARCHAR(16) NOT NULL DEFAULT '',
  `video` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_video_id`),
  KEY `product_id` (`product_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `oc_product` ADD COLUMN IF NOT EXISTS `model_3d` VARCHAR(255) NOT NULL DEFAULT '' AFTER `image`;
