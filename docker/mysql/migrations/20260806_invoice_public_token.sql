-- Public token for QR-linked invoice PDFs.
-- Idempotent.

-- The table is normally created by 20260806_order_documents.sql, which sorts
-- AFTER this file -- ensure it exists so the ALTER/INDEX below never hit
-- ERROR 1146 (table doesn't exist) regardless of migration run order or whether
-- the order-documents module was installed.
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
	ADD COLUMN IF NOT EXISTS `public_token` VARCHAR(64) NULL DEFAULT NULL AFTER `invoice_no`;

CREATE UNIQUE INDEX IF NOT EXISTS `ux_order_document_public_token`
	ON `oc_order_document` (`public_token`);
