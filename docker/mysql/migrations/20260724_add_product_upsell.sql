-- Upsell products: more expensive / better alternatives to suggest alongside a product
CREATE TABLE IF NOT EXISTS `oc_product_upsell` (
  `product_id` int(11) NOT NULL,
  `upsell_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`, `upsell_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
