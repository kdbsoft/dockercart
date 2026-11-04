-- Landing page builder: pages composed of drag & drop sections (blocks).
--
-- Design notes:
--   * oc_landing is the page; oc_landing_description holds the per-language slug
--     and meta fields. The slug is mirrored into oc_seo_url
--     (query = landing_id=<id>, store_id = 0) so /black-friday resolves through
--     the same SEO pipeline as products, pages and blog posts.
--   * oc_landing_block is a flat, ordered list of sections
--     (Dockercart\Landing\Blocks is the registry of allowed codes). Fields that
--     are not translatable live in `settings` (JSON), translated copy lives in
--     oc_landing_block_description.content (JSON per language).
--   * oc_landing_template stores reusable section sets saved from the builder.
--   * version implements a cheap optimistic lock for the autosaving builder,
--     viewed is the public view counter (preview/builder views are not counted).
--
-- Idempotent: `make migrate` re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_landing` (
	`landing_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`status` TINYINT(1) NOT NULL DEFAULT 0,
	`chrome` VARCHAR(16) NOT NULL DEFAULT 'full',
	`is_home` TINYINT(1) NOT NULL DEFAULT 0,
	`noindex` TINYINT(1) NOT NULL DEFAULT 0,
	`custom_css` TEXT NULL DEFAULT NULL,
	`viewed` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`version` INT(10) UNSIGNED NOT NULL DEFAULT 1,
	`date_publish` DATETIME NULL DEFAULT NULL,
	`date_unpublish` DATETIME NULL DEFAULT NULL,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`landing_id`),
	KEY `idx_landing_status` (`status`),
	KEY `idx_landing_home` (`is_home`),
	KEY `idx_landing_date_publish` (`date_publish`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_landing_description` (
	`landing_id` INT(11) NOT NULL,
	`language_id` INT(11) NOT NULL,
	`slug` VARCHAR(255) NOT NULL DEFAULT '',
	`meta_title` VARCHAR(255) NOT NULL DEFAULT '',
	`meta_description` VARCHAR(255) NOT NULL DEFAULT '',
	`meta_keyword` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`landing_id`, `language_id`),
	KEY `idx_landing_description_slug` (`slug`),
	KEY `idx_landing_description_language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_landing_block` (
	`block_id` INT(11) NOT NULL AUTO_INCREMENT,
	`landing_id` INT(11) NOT NULL,
	`code` VARCHAR(32) NOT NULL DEFAULT '',
	`sort_order` INT(3) NOT NULL DEFAULT 0,
	`status` TINYINT(1) NOT NULL DEFAULT 1,
	`settings` MEDIUMTEXT NULL DEFAULT NULL,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`block_id`),
	KEY `idx_landing_block_landing` (`landing_id`, `sort_order`),
	KEY `idx_landing_block_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_landing_block_description` (
	`block_id` INT(11) NOT NULL,
	`language_id` INT(11) NOT NULL,
	`content` MEDIUMTEXT NULL DEFAULT NULL,
	PRIMARY KEY (`block_id`, `language_id`),
	KEY `idx_landing_block_description_language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_landing_template` (
	`template_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`blocks` MEDIUMTEXT NULL DEFAULT NULL,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`template_id`),
	KEY `idx_landing_template_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store home override: the landing page assigned as the storefront home page
-- (written by ModelDesignLanding through ModelSettingSetting::updateSetting()).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_landing_home_id', '0', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_landing_home_id'
);
