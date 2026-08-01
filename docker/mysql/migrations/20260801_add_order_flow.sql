-- Migration: 20260801 - configurable order flow (steps chain + extra transitions)
-- Adds Confirmed/Packing/Refunded statuses and seeds default flow configuration
-- into oc_setting (store 0). All statements are idempotent: `make migrate`
-- re-runs every migration file, so nothing here may clobber user changes.

-- New flow statuses (insert or update names on re-run)
INSERT INTO `oc_order_status` (`order_status_id`, `language_id`, `name`) VALUES
(132, 1, 'Confirmed'),
(132, 2, 'Підтверджено'),
(132, 3, 'Подтверждён'),
(133, 1, 'Packing'),
(133, 2, 'Збірка'),
(133, 3, 'Сборка'),
(134, 1, 'Refunded'),
(134, 2, 'Повернення коштів'),
(134, 3, 'Возврат средств')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default flow chain: Pending -> Confirmed -> Packing -> Shipped -> Delivered.
-- Awaiting Payment (131) is NOT a step: it is reachable from Pending and
-- returns to Confirmed, so prepaid and postpaid orders follow the same chain.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_order_flow_steps', '["1","132","133","128","129"]', 1
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_order_flow_steps');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_order_flow_transitions', '{"1":["130","131"],"131":["130","132"],"132":["130","134"],"133":["130","134"],"128":["130","134"],"129":["134"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_order_flow_transitions');

-- Stock is subtracted when an order enters Packing or Shipped (processing statuses).
-- Only patch the default store config when Packing is not present yet, so manual
-- customizations are preserved.
UPDATE `oc_setting`
SET `value` = '["127","133","128"]'
WHERE `store_id` = 0
  AND `code` = 'config'
  AND `key` = 'config_processing_status'
  AND `value` NOT LIKE '%"133"%';

-- Deduplicate oc_setting: legacy migrations used INSERT IGNORE, but
-- oc_setting has no UNIQUE key on (store_id, code, key), so re-running
-- them created duplicate rows. Values of duplicates are identical, so
-- keeping the first row loses nothing. Kept here so `make migrate`
-- (which re-runs every migration file) stays idempotent.
DELETE s1 FROM `oc_setting` s1
INNER JOIN `oc_setting` s2
  ON s1.store_id = s2.store_id AND s1.code = s2.code AND s1.`key` = s2.`key`
WHERE s1.setting_id > s2.setting_id;
