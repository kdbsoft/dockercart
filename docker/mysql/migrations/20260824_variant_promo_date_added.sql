-- Add date_added to variant promotion tables so variant promos can be
-- round-tripped and ordered together with product-level promotions in the
-- admin product form Promotions panel.
ALTER TABLE `oc_dockercart_product_variant_special`
  ADD COLUMN IF NOT EXISTS `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;

ALTER TABLE `oc_dockercart_product_variant_discount`
  ADD COLUMN IF NOT EXISTS `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `auto_renew`;
