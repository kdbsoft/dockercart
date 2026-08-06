-- Invoice & Organization settings (moved into store settings, code='config').
-- Adds: config_invoice_prefix, config_seller_name_i18n, config_seller_address_i18n,
--        config_seller_email, config_seller_telephone, config_seller_tax_numbers (JSON),
--        config_seller_bank_name, config_seller_bank_account, config_seller_bank_swift,
--        config_seller_invoice_logo (empty = use config_logo).
-- Idempotent.

-- Invoice prefix
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_invoice_prefix', 'INV-', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_invoice_prefix');

-- Seller name (multilingual JSON)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_name_i18n', '{}', '1'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_name_i18n');

-- Seller address (multilingual JSON)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_address_i18n', '{}', '1'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_address_i18n');

-- Seller fallback strings (synced from default language)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_name', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_name');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_address', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_address');

-- Seller scalar fields
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_email', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_email');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_telephone', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_telephone');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_tax_numbers', '[]', '1'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_tax_numbers');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_bank_name', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_bank_name');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_bank_account', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_bank_account');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_bank_swift', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_bank_swift');

-- Invoice logo (empty = use store logo)
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT '0', 'config', 'config_seller_invoice_logo', '', '0'
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = '0' AND `code` = 'config' AND `key` = 'config_seller_invoice_logo');

-- Clean up old separate seller settings (from previous migration)
DELETE FROM `oc_setting` WHERE `code` = 'seller';
