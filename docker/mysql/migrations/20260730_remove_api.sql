-- Remove the entire REST API layer: controllers, management UI, and related DB objects.
-- This migration is paired with code removal of catalog/controller/api/ and admin/controller/user/api/.

-- cart.api_id was used to partition carts by API session. After removing the API,
-- all carts are regular carts (api_id = 0), so the column is unnecessary.

ALTER TABLE `oc_cart` DROP INDEX IF EXISTS `cart_id`;
ALTER TABLE `oc_cart` DROP COLUMN IF EXISTS `api_id`;
ALTER TABLE `oc_cart` ADD INDEX `cart_id` (`customer_id`, `session_id`, `product_id`, `recurring_id`);

-- config_api_id was used to select which API user the admin panel uses for AJAX operations
DELETE FROM `oc_setting` WHERE `key` = 'config_api_id';

-- Remove API-related permissions from all user groups
DELETE FROM `oc_user_group` WHERE `permission` LIKE '%user/api%';

-- Drop the three API tables
DROP TABLE IF EXISTS `oc_api_session`;
DROP TABLE IF EXISTS `oc_api_ip`;
DROP TABLE IF EXISTS `oc_api`;
