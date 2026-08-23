-- Warehouse multilingual address fields.
--
-- Adds city / address_1 / address_2 columns to oc_warehouse_description (the
-- same per-language table already used for names). The base oc_warehouse
-- columns keep a denormalised fallback (default-language snapshot) consumed
-- by the storefront pickup method via COALESCE.
--
-- Idempotent: make migrate re-runs every *.sql file.

ALTER TABLE `oc_warehouse_description`
	ADD COLUMN IF NOT EXISTS `city` VARCHAR(255) NOT NULL DEFAULT '',
	ADD COLUMN IF NOT EXISTS `address_1` VARCHAR(255) NOT NULL DEFAULT '',
	ADD COLUMN IF NOT EXISTS `address_2` VARCHAR(255) NOT NULL DEFAULT '';

-- Seed per-language rows from the current base columns (rows already exist
-- from the name migration, one per active language). Re-running just copies
-- the same values again, which is harmless.
UPDATE `oc_warehouse_description` wd
	JOIN `oc_warehouse` w ON (w.`warehouse_id` = wd.`warehouse_id`)
SET
	wd.`city` = w.`city`,
	wd.`address_1` = w.`address_1`,
	wd.`address_2` = w.`address_2`;
