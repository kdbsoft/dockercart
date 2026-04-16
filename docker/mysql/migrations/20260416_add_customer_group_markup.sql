-- Adds B2B markup percent support for customer groups
ALTER TABLE `oc_customer_group`
  ADD COLUMN IF NOT EXISTS `markup_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount_percent`;
