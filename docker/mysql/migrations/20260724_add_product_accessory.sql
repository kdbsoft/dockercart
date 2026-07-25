-- Accessories: compatible add-ons and accessories for a product
CREATE TABLE IF NOT EXISTS `oc_product_accessory` (
  `product_id` int(11) NOT NULL,
  `accessory_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`, `accessory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
