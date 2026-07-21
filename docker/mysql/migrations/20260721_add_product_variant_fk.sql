-- Foreign keys: cascade row-level integrity for configurable product tables.
-- The PHP layer (ProductConfigurable::deleteAllVariants, called from
-- admin/model/catalog/product.php deleteProduct) remains the primary deletion
-- path because it invalidates caches and calls touchProduct(). These FKs are a
-- second-level guard against orphaned rows if rows are deleted directly via SQL.
--
-- Idempotent: each FK is added only if it does not already exist (information_schema
-- guard). MariaDB does not support ADD CONSTRAINT IF NOT EXISTS directly.

-- Helper: add a FK only if no matching constraint name exists yet.
-- We use named constraints so the guard can match on CONSTRAINT_NAME.

-- fk_pc_product: oc_product_configurable.product_id -> oc_product.product_id
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_configurable' AND CONSTRAINT_NAME = 'fk_pc_product');
SET @sql = IF(@exists = 0,
	'ALTER TABLE `oc_product_configurable` ADD CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `oc_product`(`product_id`) ON DELETE CASCADE',
	'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_pco_product: oc_product_configurable_option.product_id -> oc_product.product_id
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_configurable_option' AND CONSTRAINT_NAME = 'fk_pco_product');
SET @sql = IF(@exists = 0,
	'ALTER TABLE `oc_product_configurable_option` ADD CONSTRAINT `fk_pco_product` FOREIGN KEY (`product_id`) REFERENCES `oc_product`(`product_id`) ON DELETE CASCADE',
	'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_pv_product: oc_product_variant.product_id -> oc_product.product_id
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_variant' AND CONSTRAINT_NAME = 'fk_pv_product');
SET @sql = IF(@exists = 0,
	'ALTER TABLE `oc_product_variant` ADD CONSTRAINT `fk_pv_product` FOREIGN KEY (`product_id`) REFERENCES `oc_product`(`product_id`) ON DELETE CASCADE',
	'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_pvv_variant: oc_product_variant_value.variant_id -> oc_product_variant.variant_id
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oc_product_variant_value' AND CONSTRAINT_NAME = 'fk_pvv_variant');
SET @sql = IF(@exists = 0,
	'ALTER TABLE `oc_product_variant_value` ADD CONSTRAINT `fk_pvv_variant` FOREIGN KEY (`variant_id`) REFERENCES `oc_product_variant`(`variant_id`) ON DELETE CASCADE',
	'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
