-- Apply the VAT 5% rate (tax_rate_id 88) to the "Taxable Goods" tax class (9),
-- replacing the demo VAT 20% + Eco Tax 2.00 combination, and add missing
-- uk/ru translations for the legacy demo rates (86/87) so the tax engine
-- (which joins oc_tax_rate on the current language_id) keeps finding them
-- for "Downloadable Products" (tax class 10) on non-English storefronts.
-- Idempotent: rules are deleted before re-insert, translations use
-- INSERT ... WHERE NOT EXISTS.

-- 1. Tax class 9 ("Taxable Goods") now uses VAT 5% (rate 88), based = shipping.

DELETE FROM `oc_tax_rule` WHERE `tax_class_id` = 9;

INSERT INTO `oc_tax_rule` (`tax_class_id`, `tax_rate_id`, `based`, `priority`)
SELECT 9, 88, 'shipping', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rule`
    WHERE `tax_class_id` = 9 AND `tax_rate_id` = 88 AND `based` = 'shipping'
);

-- 2. Legacy demo rates keep working on uk/ru storefronts: add translations.

INSERT INTO `oc_tax_rate` (`tax_rate_id`, `language_id`, `geo_zone_id`, `name`, `rate`, `type`, `date_added`, `date_modified`)
SELECT 86, 2, 3, 'ПДВ (20%)', 20.0000, 'P', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rate` WHERE `tax_rate_id` = 86 AND `language_id` = 2
);

INSERT INTO `oc_tax_rate` (`tax_rate_id`, `language_id`, `geo_zone_id`, `name`, `rate`, `type`, `date_added`, `date_modified`)
SELECT 86, 3, 3, 'НДС (20%)', 20.0000, 'P', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rate` WHERE `tax_rate_id` = 86 AND `language_id` = 3
);

INSERT INTO `oc_tax_rate` (`tax_rate_id`, `language_id`, `geo_zone_id`, `name`, `rate`, `type`, `date_added`, `date_modified`)
SELECT 87, 2, 3, 'Екологічний податок (2,00)', 2.0000, 'F', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rate` WHERE `tax_rate_id` = 87 AND `language_id` = 2
);

INSERT INTO `oc_tax_rate` (`tax_rate_id`, `language_id`, `geo_zone_id`, `name`, `rate`, `type`, `date_added`, `date_modified`)
SELECT 87, 3, 3, 'Экологический налог (2,00)', 2.0000, 'F', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `oc_tax_rate` WHERE `tax_rate_id` = 87 AND `language_id` = 3
);
