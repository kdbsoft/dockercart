-- Variant axis-value integrity.
-- PK (variant_id, option_id) guarantees one option_id per variant; this unique
-- index additionally guarantees one option_value_id per (variant_id, option_id),
-- so two variants cannot share the same axis-value combination at the row level.
-- Also removes any legacy `product_combo` index name left from earlier schemas.

ALTER TABLE `oc_product_variant_value` DROP INDEX IF EXISTS `product_combo`;

CREATE UNIQUE INDEX IF NOT EXISTS `ux_variant_axis_value`
	ON `oc_product_variant_value` (`variant_id`, `option_id`, `option_value_id`);
