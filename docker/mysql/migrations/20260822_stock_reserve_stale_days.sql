-- Stock reservation: release holds bound to orders that never reached a
-- fulfilled status (processing/complete) after N days untouched. Safety net
-- so abandoned pending orders cannot lock stock forever now that bound holds
-- no longer expire via expires_at.
--
-- config_stock_reserve_stale_days — 0 disables the sweep.
-- (Idempotent: make migrate re-runs every file.)

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_stock_reserve_stale_days', '14', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_stock_reserve_stale_days');
