CREATE TABLE IF NOT EXISTS `oc_dockercart_product_customer_group_price` (
    `product_id` INT(11) NOT NULL,
    `customer_group_id` INT(11) NOT NULL,
    `price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (`product_id`, `customer_group_id`),
    KEY `customer_group_id` (`customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
