-- Migration: 20260809 - admin AJAX autocomplete fallback setting
-- Admin autocompletes (products, orders, customers, categories, manufacturers,
-- information) now search via Manticore with automatic SQL fallback when the
-- search engine is unavailable. This flag controls that fallback:
--   '1' (default)  -> transparently fall back to the previous MySQL LIKE path
--   '0'            -> return empty results (same as the global admin search)
-- Idempotent: make migrate re-runs every file.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_admin_fallback', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_admin_fallback');
