-- Bundle lines with variants: a configurable product cannot be added without a variant.
--
-- Design notes:
--   * oc_product_bundle_product.variant_id pins the exact variant chosen by the
--     admin when the bundle was created (0 = simple product, no variant).
--   * The storefront adds exactly those variants to the cart; the bundle total
--     matches cart lines by (product_id, variant_id), not by product_id alone.
--   * Validation (admin + storefront) rejects bundles that contain a configurable
--     product with variant_id = 0 — such bundles are hidden until fixed.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_product_bundle_product`
	ADD COLUMN IF NOT EXISTS `variant_id` INT(11) NOT NULL DEFAULT 0 AFTER `product_id`;

-- Backfill: existing lines that point at a configurable product get its default
-- variant so old bundles keep working ("исправить на варианты"). Lines without
-- a default variant stay 0 and are hidden/validated until the admin fixes them.
UPDATE `oc_product_bundle_product` AS `bp`
INNER JOIN `oc_product_configurable` AS `pc`
	ON (`pc`.`product_id` = `bp`.`product_id` AND `pc`.`is_configurable` = '1' AND `pc`.`default_variant_id` IS NOT NULL AND `pc`.`default_variant_id` > 0)
SET `bp`.`variant_id` = `pc`.`default_variant_id`
WHERE `bp`.`variant_id` = '0';
