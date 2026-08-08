-- Multilingual category listing banner
-- Moves banner_image/banner_link from oc_category (single, shared across languages)
-- to oc_category_banner (per-language rows), following the oc_banner_description pattern.
--
-- Idempotent: make migrate re-runs every migration file, so this script must be safe
-- to run repeatedly against an already-migrated database.

CREATE TABLE IF NOT EXISTS `oc_category_banner` (
  `category_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `banner_link` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`category_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stage the legacy values (only exists while oc_category.banner_image still does)
CREATE TEMPORARY TABLE IF NOT EXISTS `_category_banner_legacy` (
  `category_id` int(11) NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `banner_link` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only copy when the legacy columns still exist (first run)
SET @legacy_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_category' AND COLUMN_NAME = 'banner_image'
);

SET @sql := IF(@legacy_exists > 0,
  'INSERT IGNORE INTO `_category_banner_legacy` (`category_id`, `banner_image`, `banner_link`)
   SELECT c.category_id, c.banner_image, c.banner_link
   FROM `oc_category` c
   WHERE c.banner_image IS NOT NULL AND c.banner_image != ''''',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate staged values to all active languages
INSERT IGNORE INTO `oc_category_banner` (`category_id`, `language_id`, `banner_image`, `banner_link`)
SELECT t.category_id, l.language_id, t.banner_image, t.banner_link
FROM `_category_banner_legacy` t
CROSS JOIN `oc_language` l;

-- Drop the legacy single-language columns only if they still exist
SET @sql := IF(@legacy_exists > 0,
  'ALTER TABLE `oc_category` DROP COLUMN `banner_image`, DROP COLUMN `banner_link`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TEMPORARY TABLE IF EXISTS `_category_banner_legacy`;
