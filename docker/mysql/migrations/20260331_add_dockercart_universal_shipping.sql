-- DockerCart Universal Shipping Tables
-- Migration: 20260331 - Add dockercart_universal_shipping tables

-- Main shipping methods table
CREATE TABLE IF NOT EXISTS `oc_dockercart_universal_shipping` (
    `method_id` INT(11) NOT NULL AUTO_INCREMENT,
    `cost` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
    `cost_type` VARCHAR(32) NOT NULL DEFAULT 'fixed',
    `weight_rates` TEXT,
    `geo_zone_id` INT(11) NOT NULL DEFAULT '0',
    `tax_class_id` INT(11) NOT NULL DEFAULT '0',
    `min_total` DECIMAL(15,4) DEFAULT NULL,
    `max_total` DECIMAL(15,4) DEFAULT NULL,
    `min_weight` DECIMAL(15,8) DEFAULT NULL,
    `max_weight` DECIMAL(15,8) DEFAULT NULL,
    `free_shipping_threshold` DECIMAL(15,4) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT '1',
    `sort_order` INT(3) NOT NULL DEFAULT '0',
    `date_added` DATETIME NOT NULL,
    `date_modified` DATETIME NOT NULL,
    PRIMARY KEY (`method_id`),
    KEY `idx_status` (`status`),
    KEY `idx_geo_zone` (`geo_zone_id`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Method descriptions (multilingual)
CREATE TABLE IF NOT EXISTS `oc_dockercart_universal_shipping_description` (
    `method_id` INT(11) NOT NULL,
    `language_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `delivery_time` VARCHAR(128) DEFAULT NULL,
    PRIMARY KEY (`method_id`, `language_id`),
    KEY `idx_language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
