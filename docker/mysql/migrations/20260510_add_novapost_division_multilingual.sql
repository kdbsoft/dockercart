-- DockerCart NovaPost Shipping Module
-- Migration: 20260510 - Add multi-language division descriptions table

CREATE TABLE IF NOT EXISTS `oc_dockercart_novapost_division_description` (
    `division_id` INT(11) NOT NULL,
    `language_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `short_address` VARCHAR(512) NOT NULL DEFAULT '',
    `full_address` TEXT,
    `city_name` VARCHAR(255) NOT NULL DEFAULT '',
    `region_name` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`division_id`, `language_id`),
    KEY `lang_idx` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
