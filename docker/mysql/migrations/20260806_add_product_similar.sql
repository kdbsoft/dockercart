-- Similar products: offered when the viewed product is out of stock
CREATE TABLE IF NOT EXISTS `oc_product_similar` (
  `product_id` int(11) NOT NULL,
  `similar_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`, `similar_id`),
  KEY `similar_id` (`similar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
