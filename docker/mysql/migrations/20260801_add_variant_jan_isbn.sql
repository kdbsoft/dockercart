-- Migration: 20260801 - add JAN/ISBN article codes to product variants
-- oc_product_variant already carries model/sku/upc/ean/mpn; JAN and ISBN were missing.
-- Values are indexed into the Manticore products `variant_codes` field for full-text search.
-- Idempotent: make migrate re-runs every file, so ADD COLUMN IF NOT EXISTS is required.
ALTER TABLE `oc_product_variant`
  ADD COLUMN IF NOT EXISTS `jan` varchar(13) NOT NULL DEFAULT '' AFTER `ean`,
  ADD COLUMN IF NOT EXISTS `isbn` varchar(17) NOT NULL DEFAULT '' AFTER `jan`;
