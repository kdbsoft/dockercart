-- Public token for QR-linked invoice PDFs.
-- Idempotent.

ALTER TABLE `oc_order_document`
	ADD COLUMN IF NOT EXISTS `public_token` VARCHAR(64) NULL DEFAULT NULL AFTER `invoice_no`;

CREATE UNIQUE INDEX IF NOT EXISTS `ux_order_document_public_token`
	ON `oc_order_document` (`public_token`);
