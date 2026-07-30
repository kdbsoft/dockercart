CREATE TABLE IF NOT EXISTS `oc_dockercart_product_variant_discount` (
  `variant_discount_id` int(11) NOT NULL AUTO_INCREMENT,
  `variant_id` int(11) NOT NULL,
  `customer_group_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 1,
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `date_start` date NOT NULL DEFAULT '0000-00-00',
  `date_end` date NOT NULL DEFAULT '0000-00-00',
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`variant_discount_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
