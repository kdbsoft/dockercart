-- Restore the demo VAT 20% + Eco Tax for "Taxable Goods" (tax class 9).
-- The previous migration (20260811_apply_vat5_to_taxable_goods.sql) replaced
-- the class-9 rules with a single VAT 5% rate, which made the cart apply 5%
-- to all 77 regular products. Revert class 9 to the original demo setup:
--   (9, 86, 'shipping', 1)  VAT (20%), P
--   (9, 87, 'shipping', 2)  Eco Tax (2.00), F
-- Idempotent: rules are deleted before re-insert.
-- The 5% rate (88) and the uk/ru translations added for 86/87 are kept,
-- they are still used by nothing else and harmless.

DELETE FROM `oc_tax_rule` WHERE `tax_class_id` = 9;

INSERT INTO `oc_tax_rule` (`tax_class_id`, `tax_rate_id`, `based`, `priority`)
SELECT 9, 86, 'shipping', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rule`
    WHERE `tax_class_id` = 9 AND `tax_rate_id` = 86 AND `based` = 'shipping'
);

INSERT INTO `oc_tax_rule` (`tax_class_id`, `tax_rate_id`, `based`, `priority`)
SELECT 9, 87, 'shipping', 2
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rule`
    WHERE `tax_class_id` = 9 AND `tax_rate_id` = 87 AND `based` = 'shipping'
);
