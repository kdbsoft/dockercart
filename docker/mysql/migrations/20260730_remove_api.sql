-- Remove the entire REST API layer: controllers, management UI, and related DB objects.
-- This migration is paired with code removal of catalog/controller/api/ and admin/controller/user/api/.

-- cart.api_id was used to partition carts by API session. After removing the API,
-- all carts are regular carts (api_id = 0), so the column is unnecessary.

ALTER TABLE `oc_cart` DROP INDEX IF EXISTS `cart_id`;
ALTER TABLE `oc_cart` DROP COLUMN IF EXISTS `api_id`;
ALTER TABLE `oc_cart` ADD INDEX `cart_id` (`customer_id`, `session_id`, `product_id`, `recurring_id`);

-- config_api_id was used to select which API user the admin panel uses for AJAX operations
DELETE FROM `oc_setting` WHERE `key` = 'config_api_id';

-- Remove API-related permissions from all user groups.
-- IMPORTANT: do NOT DELETE entire group rows — the old pattern
-- `DELETE FROM oc_user_group WHERE permission LIKE '%user/api%'` would wipe
-- any group merely containing the (string) route 'user/api'. Instead strip
-- only the 'user/api' string elements from the access/modify arrays, leaving
-- every other permission and every group row intact.
UPDATE `oc_user_group`
SET `permission` = JSON_OBJECT(
	'access', (
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.access'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING' AND JSON_UNQUOTE(jt.el) <> 'user/api'
	),
	'modify', (
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.modify'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING' AND JSON_UNQUOTE(jt.el) <> 'user/api'
	)
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND (
		JSON_CONTAINS(`permission`, JSON_ARRAY('user/api'), '$.access') = 1
		OR JSON_CONTAINS(`permission`, JSON_ARRAY('user/api'), '$.modify') = 1
	);

-- Drop the three API tables
DROP TABLE IF EXISTS `oc_api_session`;
DROP TABLE IF EXISTS `oc_api_ip`;
DROP TABLE IF EXISTS `oc_api`;
