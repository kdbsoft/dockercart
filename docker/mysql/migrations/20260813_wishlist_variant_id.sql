-- Wishlist variant support: oc_customer_wishlist gains a variant_id column
-- (0 = base product) and the primary key is widened to
-- (customer_id, product_id, variant_id) so each variant can be saved
-- independently as its own wishlist row.
--
-- Idempotent: make migrate re-runs every migration file, so this script must
-- be safe to run repeatedly against an already-migrated database.

ALTER TABLE `oc_customer_wishlist` ADD COLUMN IF NOT EXISTS `variant_id` int(11) NOT NULL DEFAULT 0 AFTER `product_id`;

-- Rebuild the primary key only when it does not yet include variant_id.
SET @has_variant_pk := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE table_schema = DATABASE()
	  AND table_name = 'oc_customer_wishlist'
	  AND index_name = 'PRIMARY'
	  AND column_name = 'variant_id'
);

SET @sql := IF(
	@has_variant_pk = 0,
	'ALTER TABLE `oc_customer_wishlist` DROP PRIMARY KEY, ADD PRIMARY KEY (`customer_id`, `product_id`, `variant_id`)',
	'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
