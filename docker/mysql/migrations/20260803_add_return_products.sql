-- Migration: 20260803 - multi-item returns (full / partial / exchange)
-- oc_return gains return type, refund amount and a refunded flag; order
-- line items move to oc_return_product (multiple products per return,
-- linked to oc_order_product so quantities/amounts can be validated and
-- restocked). All statements are idempotent.

ALTER TABLE `oc_return`
  ADD COLUMN IF NOT EXISTS `type` varchar(16) NOT NULL DEFAULT 'full' AFTER `order_id`,
  ADD COLUMN IF NOT EXISTS `amount` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `quantity`,
  ADD COLUMN IF NOT EXISTS `refunded` tinyint(1) NOT NULL DEFAULT 0 AFTER `amount`,
  ADD INDEX IF NOT EXISTS `order_id` (`order_id`),
  ADD INDEX IF NOT EXISTS `customer_id` (`customer_id`),
  ADD INDEX IF NOT EXISTS `return_status_id` (`return_status_id`);

CREATE TABLE IF NOT EXISTS `oc_return_product` (
  `return_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `order_product_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `variant_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `model` varchar(64) NOT NULL DEFAULT '',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(15,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`return_product_id`),
  KEY `return_id` (`return_id`),
  KEY `order_product_id` (`order_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `oc_return_history`
  ADD INDEX IF NOT EXISTS `return_id` (`return_id`);
