CREATE TABLE IF NOT EXISTS `oc_product_configurable` (
  `product_id` int(11) NOT NULL,
  `is_configurable` tinyint(1) NOT NULL DEFAULT 0,
  `default_variant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oc_product_configurable_option` (
  `product_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`,`option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oc_product_variant` (
  `variant_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sku` varchar(64) NOT NULL DEFAULT '',
  `upc` varchar(12) NOT NULL DEFAULT '',
  `ean` varchar(14) NOT NULL DEFAULT '',
  `mpn` varchar(64) NOT NULL DEFAULT '',
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtract` tinyint(1) NOT NULL DEFAULT 1,
  `weight` decimal(15,8) NOT NULL DEFAULT 0.00000000,
  `weight_class_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`variant_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oc_product_variant_value` (
  `variant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `option_value_id` int(11) NOT NULL,
  PRIMARY KEY (`variant_id`,`option_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `oc_order_product` ADD COLUMN IF NOT EXISTS `variant_id` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `oc_order_product` ADD COLUMN IF NOT EXISTS `variant_sku` varchar(64) NOT NULL DEFAULT '';
ALTER TABLE `oc_product_variant_value` DROP INDEX IF EXISTS `product_combo`;
