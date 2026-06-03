-- Multilingual banner name
-- Moves banner name from oc_banner.name (single column) to oc_banner_description (per-language)
CREATE TABLE IF NOT EXISTS `oc_banner_description` (
  `banner_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`banner_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing names to all active languages
INSERT IGNORE INTO `oc_banner_description` (`banner_id`, `language_id`, `name`)
SELECT b.banner_id, l.language_id, b.name
FROM `oc_banner` b
CROSS JOIN `oc_language` l;
