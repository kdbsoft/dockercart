-- Configurable products carry no head-level stock of their own: orders consume
-- variant rows only. The warehouses backfill mirrored the legacy aggregate
-- oc_product.quantity into variant_id = 0 rows, which double-counted variant
-- stock and made the stock matrix disagree with the product card.
--
-- Idempotent: re-runs delete nothing and set caches to the same values.

-- 1) Drop phantom head-level rows for configurable products.
-- Guard against out-of-order execution: this file sorts before
-- 20260823_00_warehouses.sql lexicographically, so on a fresh DB the
-- warehouse tables may not exist yet. Check existence via
-- information_schema and run the statements only when the required
-- tables are present. The ordered copy (20260823_00_warehouses.sql)
-- ensures the next migrate run will apply them in the correct order;
-- this guard simply prevents ERROR 1146 from aborting the batch.
SET @ws_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_warehouse_stock');
SET @pc_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_configurable');
SET @pv_exists = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_variant');
SET @do_cleanup = IF(@ws_exists > 0 AND @pc_exists > 0 AND @pv_exists > 0, 1, 0);

SET @sql = IF(@do_cleanup = 1,
'DELETE ws FROM `oc_warehouse_stock` ws JOIN `oc_product_configurable` pc ON (pc.`product_id` = ws.`product_id` AND pc.`is_configurable` = ''1'') WHERE ws.`variant_id` = 0',
'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Realign oc_product.quantity with the admin card formula:
--    quantity = SUM(quantity of ACTIVE variants across warehouses),
--    sentinel 999999 when any active variant is unlimited.
SET @sql = IF(@do_cleanup = 1,
'UPDATE `oc_product` p LEFT JOIN ( SELECT pv.`product_id`, MAX(CASE WHEN pv.`status` = ''1'' THEN COALESCE(ws.`unlimited`, 0) ELSE 0 END) AS any_unlimited, COALESCE(SUM(CASE WHEN pv.`status` = ''1'' THEN COALESCE(ws.`quantity`, 0) ELSE 0 END), 0) AS active_total FROM `oc_product_variant` pv LEFT JOIN `oc_warehouse_stock` ws ON (ws.`variant_id` = pv.`variant_id`) WHERE pv.`product_id` IN (SELECT pc2.`product_id` FROM `oc_product_configurable` pc2 WHERE pc2.`is_configurable` = ''1'') GROUP BY pv.`product_id` ) t ON (t.`product_id` = p.`product_id`) SET p.`quantity` = IF(t.`any_unlimited` = ''1'', 999999, ROUND(COALESCE(t.`active_total`, 0))) WHERE p.`product_id` IN (SELECT pc3.`product_id` FROM `oc_product_configurable` pc3 WHERE pc3.`is_configurable` = ''1'')',
'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
