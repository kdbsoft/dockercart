-- ---------------------------------------------------------------------------
-- Multilingual Holiday names
-- ---------------------------------------------------------------------------
-- The base `name` column on `oc_warehouse_holiday` stays as a denormalised
-- default-language value (mirrors `oc_warehouse` / `oc_warehouse_description`).
-- Per-language names live in `oc_warehouse_holiday_description`.

CREATE TABLE IF NOT EXISTS `oc_warehouse_holiday_description` (
	`holiday_id` INT(11) NOT NULL,
	`language_id` INT(11) NOT NULL,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`holiday_id`, `language_id`),
	KEY `idx_holiday_desc_lang` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
