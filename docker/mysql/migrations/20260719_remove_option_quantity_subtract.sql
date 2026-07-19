-- Make quantity and subtract columns nullable with defaults to disable option stock tracking
-- This migration is idempotent - it checks if columns exist before modifying

-- For product_option_value table: make quantity nullable with default 0, subtract nullable with default 0
ALTER TABLE `oc_product_option_value`
    MODIFY COLUMN `quantity` decimal(15,2) NULL DEFAULT '0.00',
    MODIFY COLUMN `subtract` tinyint(1) NULL DEFAULT '0';

-- For product_variant table: make quantity nullable with default 0, subtract nullable with default 0
-- (variants also have their own stock tracking which we keep for products but not for options)
ALTER TABLE `oc_product_variant`
    MODIFY COLUMN `quantity` decimal(15,4) NULL DEFAULT '0.0000',
    MODIFY COLUMN `subtract` tinyint(1) NULL DEFAULT '0';