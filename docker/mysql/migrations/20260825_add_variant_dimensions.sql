-- Migration: 20260825 - variant dimensions per variant
-- Makes weight+dimensions manageable per variant in Dimensions & Weight panel (Qty by variants style).
-- weight_class_id/length_class_id remain global on oc_product (shared), only L/W/H + weight per variant.
-- Idempotent: ADD COLUMN IF NOT EXISTS so `make migrate` (which re-runs every file) stays safe.

ALTER TABLE `oc_product_variant`
	ADD COLUMN IF NOT EXISTS `length` decimal(15,8) NOT NULL DEFAULT 0.00000000,
	ADD COLUMN IF NOT EXISTS `width` decimal(15,8) NOT NULL DEFAULT 0.00000000,
	ADD COLUMN IF NOT EXISTS `height` decimal(15,8) NOT NULL DEFAULT 0.00000000;
