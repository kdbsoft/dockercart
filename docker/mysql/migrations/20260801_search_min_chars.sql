-- Migration: 20260801 - lower minimum search query length to 2 chars
-- Short article codes (e.g. variant SKU "A5") were unreachable: queries shorter than
-- `module_dockercart_search_min_chars` fell back to plain MySQL search, which does not
-- index product/variant codes. Lower to 2 so 2-char codes are found by Manticore while
-- 1-char noise is still filtered.
-- Idempotent: make migrate re-runs every file.
UPDATE `oc_setting`
SET `value` = '2'
WHERE `store_id` = 0
  AND `key` = 'module_dockercart_search_min_chars'
  AND `value` <> '2';

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'module_dockercart_search', 'module_dockercart_search_min_chars', '2', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'module_dockercart_search_min_chars');
