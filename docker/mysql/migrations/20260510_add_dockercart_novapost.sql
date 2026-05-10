-- DockerCart NovaPost Shipping Module
-- Migration: 20260510 - Add dockercart_novapost tables

-- Divisions table (warehouses, post branches, postomats, PUDO)
CREATE TABLE IF NOT EXISTS `oc_dockercart_novapost_division` (
    `division_id` INT(11) NOT NULL AUTO_INCREMENT,
    `site_key` VARCHAR(64) NOT NULL DEFAULT '',
    `number` VARCHAR(32) NOT NULL DEFAULT '',
    `type` VARCHAR(32) NOT NULL DEFAULT '',
    `category` VARCHAR(32) NOT NULL DEFAULT '',
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `short_address` VARCHAR(512) NOT NULL DEFAULT '',
    `full_address` TEXT,
    `city_ref` VARCHAR(64) NOT NULL DEFAULT '',
    `city_name` VARCHAR(255) NOT NULL DEFAULT '',
    `region_ref` VARCHAR(64) NOT NULL DEFAULT '',
    `region_name` VARCHAR(255) NOT NULL DEFAULT '',
    `country_code` VARCHAR(2) NOT NULL DEFAULT '',
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `phone` VARCHAR(64) NOT NULL DEFAULT '',
    `schedule` JSON DEFAULT NULL,
    `max_weight` INT(11) DEFAULT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT '1',
    `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`division_id`),
    UNIQUE KEY `uk_site_key` (`site_key`),
    KEY `idx_country_code` (`country_code`),
    KEY `idx_category` (`category`),
    KEY `idx_city_name` (`city_name`(100)),
    KEY `idx_region_name` (`region_name`(100)),
    KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync log table
CREATE TABLE IF NOT EXISTS `oc_dockercart_novapost_sync_log` (
    `log_id` INT(11) NOT NULL AUTO_INCREMENT,
    `status` VARCHAR(32) NOT NULL DEFAULT '',
    `total_loaded` INT(11) NOT NULL DEFAULT '0',
    `total_errors` INT(11) NOT NULL DEFAULT '0',
    `countries` VARCHAR(255) NOT NULL DEFAULT '',
    `categories` VARCHAR(255) NOT NULL DEFAULT '',
    `started_at` DATETIME NOT NULL,
    `finished_at` DATETIME DEFAULT NULL,
    `error_message` TEXT,
    PRIMARY KEY (`log_id`),
    KEY `idx_status` (`status`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
