-- Multilingual layout name
-- Moves layout name from oc_layout.name (single column) to oc_layout_description (per-language)
CREATE TABLE IF NOT EXISTS `oc_layout_description` (
  `layout_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`layout_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing names to all active languages
INSERT IGNORE INTO `oc_layout_description` (`layout_id`, `language_id`, `name`)
SELECT l.layout_id, lang.language_id, l.name
FROM `oc_layout` l
CROSS JOIN `oc_language` lang;
