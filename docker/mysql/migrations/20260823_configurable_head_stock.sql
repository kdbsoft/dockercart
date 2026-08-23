-- Configurable products carry no head-level stock of their own: orders consume
-- variant rows only. The warehouses backfill mirrored the legacy aggregate
-- oc_product.quantity into variant_id = 0 rows, which double-counted variant
-- stock and made the stock matrix disagree with the product card.
--
-- Idempotent: re-runs delete nothing and set caches to the same values.

-- 1) Drop phantom head-level rows for configurable products.
DELETE ws FROM `oc_warehouse_stock` ws
JOIN `oc_product_configurable` pc ON (pc.`product_id` = ws.`product_id` AND pc.`is_configurable` = '1')
WHERE ws.`variant_id` = 0;

-- 2) Realign oc_product.quantity with the admin card formula:
--    quantity = SUM(quantity of ACTIVE variants across warehouses),
--    sentinel 999999 when any active variant is unlimited.
UPDATE `oc_product` p
LEFT JOIN (
	SELECT pv.`product_id`,
	       MAX(CASE WHEN pv.`status` = '1' THEN COALESCE(ws.`unlimited`, 0) ELSE 0 END) AS any_unlimited,
	       COALESCE(SUM(CASE WHEN pv.`status` = '1' THEN COALESCE(ws.`quantity`, 0) ELSE 0 END), 0) AS active_total
	FROM `oc_product_variant` pv
	LEFT JOIN `oc_warehouse_stock` ws ON (ws.`variant_id` = pv.`variant_id`)
	WHERE pv.`product_id` IN (SELECT pc2.`product_id` FROM `oc_product_configurable` pc2 WHERE pc2.`is_configurable` = '1')
	GROUP BY pv.`product_id`
) t ON (t.`product_id` = p.`product_id`)
SET p.`quantity` = IF(t.`any_unlimited` = '1', 999999, ROUND(COALESCE(t.`active_total`, 0)))
WHERE p.`product_id` IN (SELECT pc3.`product_id` FROM `oc_product_configurable` pc3 WHERE pc3.`is_configurable` = '1');
