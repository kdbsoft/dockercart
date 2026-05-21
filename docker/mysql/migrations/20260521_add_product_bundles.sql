-- Product bundles ("buy together cheaper")
CREATE TABLE IF NOT EXISTS `oc_product_bundle` (
    `bundle_id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `date_start` DATE NOT NULL DEFAULT '0000-00-00',
    `date_end` DATE NOT NULL DEFAULT '0000-00-00',
    `status` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`bundle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_product_bundle_product` (
    `bundle_product_id` INT(11) NOT NULL AUTO_INCREMENT,
    `bundle_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    PRIMARY KEY (`bundle_product_id`),
    KEY `bundle_id` (`bundle_id`),
    KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_product_bundle_store` (
    `bundle_id` INT(11) NOT NULL,
    `store_id` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`bundle_id`, `store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register total extension
INSERT IGNORE INTO `oc_extension` (`type`, `code`) VALUES ('total', 'product_bundle');

-- Enable for default store
INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'total_product_bundle', 'total_product_bundle_status', '1', 0);
INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'total_product_bundle', 'total_product_bundle_sort_order', '3', 0);
