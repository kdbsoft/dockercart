-- Migration: 20260808 - Order product price override
-- Marks order_product lines whose price was manually changed by the admin,
-- so they are excluded from catalog re-pricing (customer group / special /
-- quantity discount) until the admin restores the catalog price.
-- Idempotent: `make migrate` re-runs every migration file.

CREATE TABLE IF NOT EXISTS `oc_order_product_override` (
  `order_product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`order_product_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
