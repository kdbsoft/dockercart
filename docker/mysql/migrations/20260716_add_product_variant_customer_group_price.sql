CREATE TABLE IF NOT EXISTS `oc_dockercart_product_variant_customer_group_price` (
  `variant_customer_group_price_id` int(11) NOT NULL AUTO_INCREMENT,
  `variant_id` int(11) NOT NULL,
  `customer_group_id` int(11) NOT NULL DEFAULT 0,
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`variant_customer_group_price_id`),
  UNIQUE KEY `variant_group` (`variant_id`,`customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
