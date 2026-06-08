-- Add is_hit flag to product_option_value for marking popular color options
ALTER TABLE `oc_product_option_value`
  ADD COLUMN IF NOT EXISTS `is_hit` TINYINT(1) NOT NULL DEFAULT 0 AFTER `weight_prefix`;
