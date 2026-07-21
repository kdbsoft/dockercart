-- Cleanup: zero out any axis product_option_value.price that is non-zero.
-- For configurable products the source of truth for price is oc_product_variant.price;
-- axis POV.price must always be 0 (configured via setConfigurableOptions, but legacy
-- data or manual edits may have left non-zero values). This migration is a one-shot
-- cleanup that is safe to re-run (idempotent: WHERE price <> '0' matches nothing
-- after the first run zeroed everything).

UPDATE `oc_product_option_value` pov
INNER JOIN `oc_product_configurable_option` pco
	ON (pov.product_id = pco.product_id AND pov.option_id = pco.option_id)
SET pov.price = '0'
WHERE pov.price <> '0';
