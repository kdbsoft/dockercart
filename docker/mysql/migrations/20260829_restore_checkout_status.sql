-- Ensure the system checkout extension always has its status setting.
-- The admin settings form no longer posts this hidden system value, so a
-- full editSetting() save could otherwise delete the row and create a
-- redirect loop between checkout/checkout and fast-checkout.

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_checkout', 'module_dockercart_checkout_status', '1', 0
WHERE NOT EXISTS (
    SELECT 1
    FROM `oc_setting`
    WHERE `store_id` = 0
      AND `code` = 'module_dockercart_checkout'
      AND `key` = 'module_dockercart_checkout_status'
);

UPDATE `oc_setting`
SET `value` = '1', `serialized` = 0
WHERE `code` = 'module_dockercart_checkout'
  AND `key` = 'module_dockercart_checkout_status';
