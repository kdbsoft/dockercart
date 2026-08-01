-- Dashboard analytics widgets: Top Products (dockercart_top_products) and
-- Revenue by Category (dockercart_category_revenue), placed just above
-- the existing Traffic Sources widget.

-- 1. Extension registration
INSERT INTO `oc_extension` (`type`, `code`)
SELECT * FROM (SELECT 'dashboard' AS `type`, 'dockercart_top_products' AS `code`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` = 'dockercart_top_products') LIMIT 1;

INSERT INTO `oc_extension` (`type`, `code`)
SELECT * FROM (SELECT 'dashboard' AS `type`, 'dockercart_category_revenue' AS `code`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` = 'dockercart_category_revenue') LIMIT 1;

-- 2. Widget settings (status / width / sort order)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_top_products' AS `code`, 'dashboard_dockercart_top_products_status' AS `key`, '1' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_top_products_status') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_top_products' AS `code`, 'dashboard_dockercart_top_products_width' AS `key`, '6' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_top_products_width') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_top_products' AS `code`, 'dashboard_dockercart_top_products_sort_order' AS `key`, '9' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_top_products_sort_order') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_category_revenue' AS `code`, 'dashboard_dockercart_category_revenue_status' AS `key`, '1' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_category_revenue_status') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_category_revenue' AS `code`, 'dashboard_dockercart_category_revenue_width' AS `key`, '6' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_category_revenue_width') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'dashboard_dockercart_category_revenue' AS `code`, 'dashboard_dockercart_category_revenue_sort_order' AS `key`, '10' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'dashboard_dockercart_category_revenue_sort_order') LIMIT 1;

-- 3. Move Traffic Sources below the new widgets (idempotent: only shifts value 9)
UPDATE `oc_setting` SET `value` = '11' WHERE `key` = 'dashboard_traffic_source_sort_order' AND `value` = '9';

-- 4. Grant admin user group access+modify for the new widget routes
UPDATE `oc_user_group` SET `permission` = JSON_ARRAY_APPEND(
    JSON_ARRAY_APPEND(`permission`, '$.access', 'extension/dashboard/dockercart_top_products'),
    '$.modify', 'extension/dashboard/dockercart_top_products'
)
WHERE `user_group_id` = 1
AND JSON_CONTAINS(`permission`, JSON_ARRAY('extension/dashboard/dockercart_top_products'), '$.access') = 0;

UPDATE `oc_user_group` SET `permission` = JSON_ARRAY_APPEND(
    JSON_ARRAY_APPEND(`permission`, '$.access', 'extension/dashboard/dockercart_category_revenue'),
    '$.modify', 'extension/dashboard/dockercart_category_revenue'
)
WHERE `user_group_id` = 1
AND JSON_CONTAINS(`permission`, JSON_ARRAY('extension/dashboard/dockercart_category_revenue'), '$.access') = 0;
