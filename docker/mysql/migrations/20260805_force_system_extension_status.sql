-- Mark dockercart_theme and dockercart_checkout as always-enabled system
-- modules: force their status setting to 1 on every store.
-- (Idempotent: make migrate re-runs every file.)

UPDATE `oc_setting`
SET `value` = '1'
WHERE `code` IN ('dockercart_theme', 'module_dockercart_checkout')
  AND `key` IN ('dockercart_theme_status', 'module_dockercart_checkout_status');
