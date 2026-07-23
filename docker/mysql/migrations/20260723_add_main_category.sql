-- Add main_category_id to product table
ALTER TABLE `oc_product` ADD COLUMN IF NOT EXISTS `main_category_id` INT(11) NOT NULL DEFAULT 0 AFTER `model`;
