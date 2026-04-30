-- Add indexes for quantity sorting in getProducts()
-- Optimizes: ORDER BY (p.quantity <= 0) ASC, p.quantity and WHERE status

ALTER TABLE `oc_product`
  ADD INDEX IF NOT EXISTS `idx_product_status_quantity` (`status`, `quantity`);
