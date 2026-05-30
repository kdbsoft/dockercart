ALTER TABLE `oc_product` ADD COLUMN `preorder` tinyint(1) NOT NULL DEFAULT 0 AFTER `quantity`;
UPDATE `oc_product` SET `preorder` = 1 WHERE `stock_status_id` = 8;
