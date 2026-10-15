-- Migration: 20261015 - restore module_dockercart_search keys wiped by settings save
-- The search settings form (admin/controller/extension/module/dockercart_search.php)
-- saves via editSetting(), which deletes ALL rows with code='module_dockercart_search'
-- first. The form has no fields for module_dockercart_search_status,
-- module_dockercart_search_admin_fallback and module_dockercart_search_query_mappings,
-- so every Save wiped them. A missing status makes
-- ControllerCommonSearch/ControllerProductSearch evaluate
-- voice_search = status && voice = false, hiding the microphone button even though
-- voice search shows as "Enabled" in admin (getConfigValue falls back to default 1).
-- Idempotent: make migrate re-runs every file.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_status', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_status');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_admin_fallback', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_admin_fallback');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_query_mappings', '', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_query_mappings');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_autocomplete', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_autocomplete');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_voice', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_voice');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_autocomplete_limit', '10', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_autocomplete_limit');
