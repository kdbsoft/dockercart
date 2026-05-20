-- Product gift with purchase
CREATE TABLE IF NOT EXISTS `oc_product_gift` (
    `product_gift_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `gift_product_id` INT(11) NOT NULL,
    `minimum_quantity` INT(11) NOT NULL DEFAULT 1,
    `date_start` DATE NOT NULL DEFAULT '0000-00-00',
    `date_end` DATE NOT NULL DEFAULT '0000-00-00',
    PRIMARY KEY (`product_gift_id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
