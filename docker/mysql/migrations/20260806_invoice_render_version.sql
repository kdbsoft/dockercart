-- Track the invoice PDF template version so cached documents can be regenerated.
-- Idempotent.

ALTER TABLE `oc_order_document`
	ADD COLUMN IF NOT EXISTS `render_version` VARCHAR(32) NULL DEFAULT NULL AFTER `public_token`;
