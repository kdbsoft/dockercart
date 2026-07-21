-- Denormalized variant_hash for O(1) resolveVariant lookups.
-- The hash is built from option_value_id list ordered by option_id, joined with '-'.
-- Maintained by ProductConfigurable library (buildVariantHash) on add/update/rebuild.
-- Backfill populates existing rows from oc_product_variant_value; idempotent via
-- WHERE variant_hash = '' (re-runs become no-ops once all rows are populated).
--
-- Stage 0 of the refactor plan MUST detect duplicate (product_id, hash) combos
-- before this migration runs -- the UNIQUE index creation will fail on dupes.

-- 1. Add variant_hash column.
ALTER TABLE `oc_product_variant`
	ADD COLUMN IF NOT EXISTS `variant_hash` VARCHAR(255) NOT NULL DEFAULT '' AFTER `image`;

-- 2. Backfill from oc_product_variant_value (one UPDATE ... JOIN).
--    Idempotent: only rows with empty hash are updated.
UPDATE `oc_product_variant` v
JOIN (
	SELECT variant_id, GROUP_CONCAT(option_value_id ORDER BY option_id SEPARATOR '-') AS h
	FROM `oc_product_variant_value`
	GROUP BY variant_id
) t ON t.variant_id = v.variant_id
SET v.variant_hash = t.h
WHERE v.variant_hash = '';

-- 3. Unique index for resolveVariant O(1) lookup. Fails on duplicate combos --
--    Stage 0 is responsible for resolving them before applying this migration.
CREATE UNIQUE INDEX IF NOT EXISTS `ux_product_variant_hash` ON `oc_product_variant` (`product_id`, `variant_hash`);

-- 4. Drop redundant non-unique `variant_id` index on oc_product_variant_value.
--    PK (variant_id, option_id) already covers variant_id-only lookups.
ALTER TABLE `oc_product_variant_value` DROP INDEX IF EXISTS `variant_id`;

-- 5. Lookup index for fallback/admin queries (cart.php, validation, axis checks).
CREATE INDEX IF NOT EXISTS `ix_pvv_lookup` ON `oc_product_variant_value` (`product_id`, `option_id`, `option_value_id`);
