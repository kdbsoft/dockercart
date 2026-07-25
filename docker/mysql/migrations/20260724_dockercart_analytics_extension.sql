-- Remove dashboard_dockercart_analytics widget (deleted)
DELETE FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` = 'dockercart_analytics';
DELETE FROM `oc_setting` WHERE `code` = 'dashboard_dockercart_analytics';

-- Idempotent inserts for report extension only
INSERT INTO `oc_extension` (`type`, `code`)
SELECT * FROM (SELECT 'report' AS `type`, 'dockercart_analytics' AS `code`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_extension` WHERE `type` = 'report' AND `code` = 'dockercart_analytics') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'report_dockercart_analytics' AS `code`, 'report_dockercart_analytics_status' AS `key`, '1' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'report_dockercart_analytics_status') LIMIT 1;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT * FROM (SELECT 0 AS `store_id`, 'report_dockercart_analytics' AS `code`, 'report_dockercart_analytics_sort_order' AS `key`, '1' AS `value`, 0 AS `serialized`) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'report_dockercart_analytics_sort_order') LIMIT 1;
