-- Central license registry — one row per module
-- Stores DCL-... server-validated license keys, status, and verification timestamps
-- Replaces scattered oc_setting module_*_license_key / module_*_public_key entries

CREATE TABLE IF NOT EXISTS `oc_dockercart_license` (
  `license_id` int(11) NOT NULL AUTO_INCREMENT,
  `module_code` varchar(64) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `license_key` varchar(128) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `fingerprint` varchar(128) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'unknown',
  `is_test` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `last_verified` datetime DEFAULT NULL,
  `last_valid` datetime DEFAULT NULL,
  `consecutive_failures` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`license_id`),
  UNIQUE KEY `module_code` (`module_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
