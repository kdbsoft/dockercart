-- Unify "default variant" representation.
-- Source of truth becomes product_configurable.default_variant_id (already exists);
-- the denormalized product_variant.is_default column is removed.
--
-- Backfill default_variant_id from is_default=1 first, then drop the column.
-- Idempotent: backfill WHERE only matches rows where default_variant_id IS NULL
-- AND an is_default=1 row still exists; once the column is dropped, the
-- information_schema guard short-circuits the whole migration on re-runs.

-- 1. Backfill default_variant_id from is_default=1 (only if column still exists).
SET @has_is_default = (SELECT COUNT(*) FROM information_schema.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_variant' AND COLUMN_NAME = 'is_default');

SET @sql_backfill = IF(@has_is_default > 0,
	'UPDATE `oc_product_configurable` pc JOIN `oc_product_variant` v ON v.variant_id = (SELECT variant_id FROM `oc_product_variant` WHERE product_id = pc.product_id AND is_default = 1 LIMIT 1) SET pc.default_variant_id = v.variant_id WHERE pc.default_variant_id IS NULL',
	'SELECT 1');
PREPARE stmt FROM @sql_backfill;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Drop is_default column (idempotent via information_schema guard).
SET @sql_drop = IF(@has_is_default > 0,
	'ALTER TABLE `oc_product_variant` DROP COLUMN `is_default`',
	'SELECT 1');
PREPARE stmt FROM @sql_drop;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
