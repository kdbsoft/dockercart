-- Customer (storefront) REST API.
--
-- Adds the data model behind the customer-facing API served at
-- /store-api/v1/... (upload/store-api/index.php):
--   * API access is granted per customer group and overridden per customer
--     (tri-state: -1 inherit, 0 off, 1 on);
--   * what a customer may reach is a scope set (Dockercart\Api\CustomerScopes);
--   * an API key may belong to a customer (oc_api_key.customer_id) and can be
--     narrowed to a subset of the customer's effective scopes (oc_api_key.scopes).
--
-- Owner columns on oc_api_key are mutually exclusive: an admin key has
-- user_id > 0, a customer key has customer_id > 0.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

-- ------------------------------------------------------------------ oc_api_key
ALTER TABLE `oc_api_key`
	ADD COLUMN IF NOT EXISTS `customer_id` INT(11) NOT NULL DEFAULT 0 AFTER `user_id`;

ALTER TABLE `oc_api_key`
	ADD COLUMN IF NOT EXISTS `scopes` TEXT NULL DEFAULT NULL AFTER `rate_limit`;

ALTER TABLE `oc_api_key`
	ADD INDEX IF NOT EXISTS `idx_api_key_customer` (`customer_id`);

-- ----------------------------------------------------------- oc_customer_group
ALTER TABLE `oc_customer_group`
	ADD COLUMN IF NOT EXISTS `api_status` TINYINT(1) NOT NULL DEFAULT 0 AFTER `markup_percent`;

ALTER TABLE `oc_customer_group`
	ADD COLUMN IF NOT EXISTS `api_scopes` TEXT NULL DEFAULT NULL AFTER `api_status`;

-- ---------------------------------------------------------------- oc_customer
-- api_status: -1 = inherit the group setting, 0 = force off, 1 = force on.
ALTER TABLE `oc_customer`
	ADD COLUMN IF NOT EXISTS `api_status` TINYINT(1) NOT NULL DEFAULT -1 AFTER `status`;

ALTER TABLE `oc_customer`
	ADD COLUMN IF NOT EXISTS `api_scopes` TEXT NULL DEFAULT NULL AFTER `api_status`;

-- ----------------------------------------------------------------- settings
-- The customer API is a separate kill switch from the admin API
-- (config_api_status). Written by the panel through updateSetting('config', ...).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_customer_status', '0', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_customer_status'
);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_customer_max_keys', '100', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_customer_max_keys'
);
