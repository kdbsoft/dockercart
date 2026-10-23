-- Product files: publicly downloadable attachments on a product page
-- (PDF instructions, datasheets, certificates, ...).
--
-- Design notes:
--   * a product can have any number of files; each file has a localized title
--     (oc_product_file_description) so the storefront label follows the
--     customer language with a fallback to the store language;
--   * the physical payload lives outside the webroot in
--     DIR_STORAGE . 'documents/product/' under a random storage_key and is only
--     reachable through catalog controller product/product_file (never by a
--     direct URL), so original filenames can never be executed or guessed;
--   * downloads_count / date_last_downloaded provide simple interest analytics.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_product_file` (
	`product_file_id` INT(11) NOT NULL AUTO_INCREMENT,
	`product_id` INT(11) NOT NULL,
	`storage_key` VARCHAR(255) NOT NULL DEFAULT '',
	`original_name` VARCHAR(255) NOT NULL DEFAULT '',
	`file_size` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`mime_type` VARCHAR(128) NOT NULL DEFAULT '',
	`sort_order` INT(3) NOT NULL DEFAULT 0,
	`status` TINYINT(1) NOT NULL DEFAULT 1,
	`downloads_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
	`date_last_downloaded` DATETIME NULL DEFAULT NULL,
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`product_file_id`),
	KEY `idx_product_file_product` (`product_id`),
	KEY `idx_product_file_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_product_file_description` (
	`product_file_id` INT(11) NOT NULL,
	`language_id` INT(11) NOT NULL,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`product_file_id`, `language_id`),
	KEY `idx_product_file_description_language` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
