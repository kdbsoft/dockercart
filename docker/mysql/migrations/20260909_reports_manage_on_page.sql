-- Reports: manage reports directly on the Reports page (report/report).
--
-- The "report" extension type page (extension/extension/report) has been
-- removed. Install/uninstall/status/sort_order now live on report/report.
--
-- This migration backfills the two reports that were never seeded in fresh
-- installs (marketing, sale_tax) so all 15 reports are installed + enabled
-- with a stable sort order:
--   existing orders in seed data: 0..3, 6..12  ->  gaps 4 and 5 are used.
--
-- Idempotent: every statement is a no-op once applied (safe to re-run).

-- 1. Register marketing + sale_tax as installed report extensions.
INSERT INTO `oc_extension` (`type`, `code`)
SELECT 'report', 'marketing'
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'report' AND `code` = 'marketing');

INSERT INTO `oc_extension` (`type`, `code`)
SELECT 'report', 'sale_tax'
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'report' AND `code` = 'sale_tax');

-- 2. Default settings (enabled, stable sort order) for those two reports.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_marketing', 'report_marketing_status', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `key` = 'report_marketing_status');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_marketing', 'report_marketing_sort_order', '4', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `key` = 'report_marketing_sort_order');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_sale_tax', 'report_sale_tax_status', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `key` = 'report_sale_tax_status');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_sale_tax', 'report_sale_tax_sort_order', '5', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `key` = 'report_sale_tax_sort_order');
