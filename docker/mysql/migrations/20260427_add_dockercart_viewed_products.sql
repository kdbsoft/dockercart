CREATE TABLE IF NOT EXISTS `oc_dockercart_viewed_product` (
  `viewed_id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `product_id` INT(11) NOT NULL,
  `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`viewed_id`),
  UNIQUE KEY `uniq_customer_product` (`customer_id`, `product_id`),
  UNIQUE KEY `uniq_session_product` (`session_id`, `product_id`),
  KEY `idx_customer_modified` (`customer_id`, `date_modified`),
  KEY `idx_session_modified` (`session_id`, `date_modified`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
