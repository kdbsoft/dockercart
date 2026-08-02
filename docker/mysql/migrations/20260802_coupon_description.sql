-- Migration: 20260802 - multilingual coupon names
-- oc_coupon.name stays as the store-default language name for compatibility
-- (reports, auto-renew); per-language names live in oc_coupon_description.

CREATE TABLE IF NOT EXISTS `oc_coupon_description` (
  `coupon_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`coupon_id`, `language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `oc_coupon_description` (`coupon_id`, `language_id`, `name`)
SELECT c.`coupon_id`, l.`language_id`, c.`name`
FROM `oc_coupon` c
CROSS JOIN `oc_language` l;
