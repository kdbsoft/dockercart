-- Buy X Get Y (BXGY) per-product promotions
CREATE TABLE IF NOT EXISTS `oc_product_bxgy` (
    `product_bxgy_id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `reward_product_id` INT(11) NOT NULL,
    `trigger_quantity` INT(11) NOT NULL DEFAULT 1,
    `discount_type` ENUM('free','percentage') NOT NULL DEFAULT 'free',
    `discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `date_start` DATE NOT NULL DEFAULT '0000-00-00',
    `date_end` DATE NOT NULL DEFAULT '0000-00-00',
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`product_bxgy_id`),
    KEY `product_id` (`product_id`),
    KEY `reward_product_id` (`reward_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register total extension
INSERT IGNORE INTO `oc_extension` (`type`, `code`) VALUES ('total', 'bxgy');

-- Enable for default store
INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'total_bxgy', 'total_bxgy_status', '1', 0);
INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'total_bxgy', 'total_bxgy_sort_order', '2', 0);
