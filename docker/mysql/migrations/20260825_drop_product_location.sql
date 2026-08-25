-- Remove the unused `location` field from the product entity
ALTER TABLE `oc_product` DROP COLUMN IF EXISTS `location`;
