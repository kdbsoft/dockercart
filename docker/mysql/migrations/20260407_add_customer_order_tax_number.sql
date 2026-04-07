-- Add optional customer Tax Number (INN) and snapshot in orders
ALTER TABLE `oc_customer`
  ADD COLUMN IF NOT EXISTS `tax_number` VARCHAR(32) NOT NULL DEFAULT '' AFTER `telephone`;

ALTER TABLE `oc_order`
  ADD COLUMN IF NOT EXISTS `tax_number` VARCHAR(32) NOT NULL DEFAULT '' AFTER `telephone`;
