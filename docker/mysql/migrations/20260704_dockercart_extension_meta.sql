-- Extension metadata registry — tracks installed extensions with version info
-- Populated by the Store install/update pipeline and git/manual installs

CREATE TABLE IF NOT EXISTS `oc_dockercart_extension_meta` (
  `meta_id` int(11) NOT NULL AUTO_INCREMENT,
  `extension_type` varchar(32) NOT NULL DEFAULT 'module',
  `code` varchar(64) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `installed_version` varchar(32) DEFAULT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'store',
  `extension_install_id` int(11) DEFAULT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
