-- Frequently Bought Together: companion products offered alongside a product
CREATE TABLE IF NOT EXISTS `oc_product_fbt` (
  `product_id` int(11) NOT NULL,
  `fbt_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`, `fbt_id`),
  KEY `fbt_id` (`fbt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
