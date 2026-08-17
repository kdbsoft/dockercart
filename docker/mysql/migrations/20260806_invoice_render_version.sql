-- Track the invoice PDF template version so cached documents can be regenerated.
-- Idempotent.

-- oc_order_document is created by 20260806_order_documents.sql, which sorts
-- AFTER this file on the filesystem. Ensure the table exists so the ALTER below
-- never hits ERROR 1146 regardless of migration run order / module install state.
CREATE TABLE IF NOT EXISTS `oc_order_document` (
	`order_document_id` INT NOT NULL AUTO_INCREMENT,
	`order_id` INT NOT NULL,
	`document_type` VARCHAR(32) NOT NULL,
	`storage_key` VARCHAR(128) NOT NULL,
	`invoice_no` VARCHAR(64) NOT NULL DEFAULT '',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`order_document_id`),
	UNIQUE KEY `ux_order_document_type` (`order_id`, `document_type`),
	KEY `idx_order_document_storage_key` (`storage_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `oc_order_document`
	ADD COLUMN IF NOT EXISTS `render_version` VARCHAR(32) NULL DEFAULT NULL AFTER `public_token`;
