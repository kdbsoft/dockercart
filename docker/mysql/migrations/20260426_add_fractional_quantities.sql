-- Support fractional product quantities and per-product quantity step
ALTER TABLE `oc_product`
  ADD COLUMN IF NOT EXISTS `quantity_step` DECIMAL(15,2) NOT NULL DEFAULT 1.00 AFTER `minimum`;

ALTER TABLE `oc_product`
  MODIFY COLUMN `quantity` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `minimum` DECIMAL(15,2) NOT NULL DEFAULT 1.00;

ALTER TABLE `oc_cart`
  MODIFY COLUMN `quantity` DECIMAL(15,2) NOT NULL;

ALTER TABLE `oc_order_product`
  MODIFY COLUMN `quantity` DECIMAL(15,2) NOT NULL;

ALTER TABLE `oc_product_option_value`
  MODIFY COLUMN `quantity` DECIMAL(15,2) NOT NULL;