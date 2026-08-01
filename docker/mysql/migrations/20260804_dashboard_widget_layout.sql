-- Dashboard widget layout v2: stack Top Products vertically inside the Traffic
-- Sources column and make Revenue by Category a full-width row.
-- (Idempotent: updates only run when the current value differs from the target.)

UPDATE `oc_setting` SET `value` = '12' WHERE `key` = 'dashboard_dockercart_category_revenue_width' AND `value` <> '12';
UPDATE `oc_setting` SET `value` = '9'  WHERE `key` = 'dashboard_dockercart_category_revenue_sort_order' AND `value` <> '9';
UPDATE `oc_setting` SET `value` = '3'  WHERE `key` = 'dashboard_dockercart_top_products_width' AND `value` <> '3';
UPDATE `oc_setting` SET `value` = '10' WHERE `key` = 'dashboard_dockercart_top_products_sort_order' AND `value` <> '10';

-- Stack setting: render Top Products inside the Traffic Sources column
-- (the layout engine reads dashboard_<code>_stack -> host widget code).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_top_products' AS `code`, 'dashboard_dockercart_top_products_stack' AS `key`, 'traffic_source' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_top_products_stack') LIMIT 1;
