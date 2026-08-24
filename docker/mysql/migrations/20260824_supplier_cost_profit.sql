-- Supplier purchase price + dropship supplier profit analytics.
--
-- 1) oc_order_product.supplier_cost — per-unit purchase price from the
--    supplier, in the store default currency. NULL = not set: such lines are
--    excluded from purchase/profit sums and reported separately.
-- 2) Registers the `extension/report/supplier_profit` report on the admin
--    Analytics page (oc_extension row, status/sort_order settings, ACL).
--
-- (Idempotent: make migrate re-runs every file.)

ALTER TABLE `oc_order_product`
	ADD COLUMN IF NOT EXISTS `supplier_cost` DECIMAL(15,4) NULL DEFAULT NULL AFTER `supplier_tracking`;

-- oc_extension has no unique key on (type, code), hence NOT EXISTS.
INSERT INTO `oc_extension` (`type`, `code`)
SELECT 'report', 'supplier_profit'
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_extension` WHERE `type` = 'report' AND `code` = 'supplier_profit'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_supplier_profit', 'report_supplier_profit_status', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'report_supplier_profit_status');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'report_supplier_profit', 'report_supplier_profit_sort_order', '12', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'report_supplier_profit_sort_order');

-- Grant the Administrator group access & modify to the report route
-- (MariaDB-compatible JSON merge, no-op when already present).
UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('extension/report/supplier_profit'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/report/supplier_profit"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('extension/report/supplier_profit'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/report/supplier_profit"', '$.modify') = 0;
