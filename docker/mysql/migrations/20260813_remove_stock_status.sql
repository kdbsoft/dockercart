-- Remove the stock_status reference directory. Product availability is driven by
-- quantity + preorder + language strings (text_instock / text_preorder / text_out_of_stock).

-- The column was never edited in the admin product form; availability logic no longer reads it.
ALTER TABLE `oc_product` DROP COLUMN IF EXISTS `stock_status_id`;

-- Remove stock_status from access/modify arrays in all user groups.
UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(
	REGEXP_REPLACE(`permission`, ',"localisation/stock_status"', ''),
	'"localisation/stock_status",', ''
)
WHERE `permission` LIKE '%localisation/stock_status%';

-- Drop the reference table
DROP TABLE IF EXISTS `oc_stock_status`;
