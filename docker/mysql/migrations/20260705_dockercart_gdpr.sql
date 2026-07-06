-- GDPR / CCPA Universal Privacy Module
-- Idempotent — safe to run multiple times

CREATE TABLE IF NOT EXISTS `oc_dockercart_cookie_group` (
  `cookie_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `sort_order` int(3) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`cookie_group_id`),
  KEY `sort_order` (`sort_order`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_cookie_group_description` (
  `cookie_group_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`cookie_group_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_cookie` (
  `cookie_id` int(11) NOT NULL AUTO_INCREMENT,
  `cookie_group_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `duration` varchar(64) DEFAULT NULL,
  `sort_order` int(3) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`cookie_id`),
  KEY `cookie_group_id` (`cookie_group_id`),
  KEY `sort_order` (`sort_order`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_cookie_description` (
  `cookie_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`cookie_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_gdpr_consent` (
  `consent_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL DEFAULT 0,
  `session_id` varchar(191) DEFAULT NULL,
  `consent_type` varchar(64) NOT NULL,
  `consent_value` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`consent_id`),
  KEY `customer_id` (`customer_id`,`consent_type`),
  KEY `session_id` (`session_id`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_dockercart_gdpr_request` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `type` enum('export','delete') NOT NULL,
  `status` enum('pending','approved','denied','completed') NOT NULL DEFAULT 'pending',
  `store_id` int(11) NOT NULL DEFAULT 0,
  `language_id` int(11) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `date_added` datetime NOT NULL,
  `date_processed` datetime DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`),
  KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default cookie groups
INSERT IGNORE INTO `oc_dockercart_cookie_group` (`cookie_group_id`, `sort_order`, `is_required`, `status`, `date_added`, `date_modified`) VALUES
(1, 0, 1, 1, NOW(), NOW()),
(2, 1, 0, 1, NOW(), NOW()),
(3, 2, 0, 1, NOW(), NOW());

-- Default descriptions for en-gb (language_id = 1 is typical)
INSERT IGNORE INTO `oc_dockercart_cookie_group_description` (`cookie_group_id`, `language_id`, `name`, `description`) VALUES
(1, 1, 'Functional', 'Essential cookies that enable core functionality such as shopping cart, login, and session management. These cannot be disabled.'),
(2, 1, 'Analytics', 'Cookies that help us understand how visitors interact with our website, enabling us to improve your experience.'),
(3, 1, 'Marketing', 'Cookies used to deliver relevant advertisements and track promotional campaign effectiveness.');
