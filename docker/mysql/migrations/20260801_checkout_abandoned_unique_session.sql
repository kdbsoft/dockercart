-- Unique (session_id, recovered) on oc_dockercart_checkout_abandoned:
-- concurrent saves from the same session used to create duplicate rows
-- (SELECT-then-INSERT race), causing duplicate abandoned-cart recovery emails.
-- (session_id, recovered) keeps historical recovered rows and still allows a
-- new abandoned entry after recovery.
-- The table is module-created, so guard against it not existing yet.
-- (Idempotent.)

SET @sql := IF(
	EXISTS (
		SELECT 1 FROM information_schema.tables
		WHERE table_schema = DATABASE() AND table_name = 'oc_dockercart_checkout_abandoned'
	),
	'ALTER TABLE `oc_dockercart_checkout_abandoned` ADD UNIQUE INDEX IF NOT EXISTS `ux_session_recovered` (`session_id`, `recovered`)',
	'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
