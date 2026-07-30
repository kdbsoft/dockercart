ALTER TABLE `oc_product_variant`
  ADD COLUMN IF NOT EXISTS `model` varchar(64) NOT NULL DEFAULT '' AFTER `sku`;
