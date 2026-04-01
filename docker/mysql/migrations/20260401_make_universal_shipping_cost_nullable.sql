-- Make cost nullable for Universal Shipping module
-- Allows for methods without a price (only used for conditions validation)
ALTER TABLE `oc_dockercart_universal_shipping` MODIFY `cost` DECIMAL(15,4) NULL DEFAULT NULL;
