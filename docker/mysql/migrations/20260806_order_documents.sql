-- Persisted order documents. PDF files are stored outside the webroot.
-- Idempotent.

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
