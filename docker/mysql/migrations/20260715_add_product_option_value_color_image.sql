-- Migration: 20260715 - Color images table for product option values
CREATE TABLE IF NOT EXISTS `oc_product_option_value_color_image` (
  `product_option_value_color_image_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_option_value_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_option_value_color_image_id`),
  KEY `product_option_value_id` (`product_option_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
