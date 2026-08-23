-- Warehouse multilingual names.
--
-- Adds oc_warehouse_description (per-language warehouse names) and seeds it
-- from the denormalised oc_warehouse.name for every active language. The base
-- oc_warehouse.name column stays as a fallback snapshot (admin list, order
-- rows, e-mails keep reading it).
--
-- Idempotent: make migrate re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_warehouse_description` (
	`warehouse_id` INT(11) NOT NULL,
	`language_id` INT(11) NOT NULL,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`warehouse_id`, `language_id`),
	KEY `idx_warehouse_description_language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed descriptions from the current base names for all active languages.
INSERT IGNORE INTO `oc_warehouse_description` (`warehouse_id`, `language_id`, `name`)
SELECT w.`warehouse_id`, l.`language_id`, w.`name`
FROM `oc_warehouse` w
CROSS JOIN `oc_language` l
WHERE l.`status` = '1';
