-- Remove the blog-specific Landing Page Builder integration.
--
-- 20261125_blog_advanced_description.php must have completed successfully before
-- this file runs: it converts every post-owned landing to Advanced Description
-- and deletes the old landing rows (including orphan owners). The marker guard
-- below makes the destructive DDL fail closed even if an older/external runner
-- knows how to apply *.sql files but does not execute companion PHP migrations.
-- Dropping post_id without that step would turn an orphan landing into a
-- standalone page.
--
-- Historical migrations and the generated init.sql still contain the legacy
-- columns; this final migration removes them from the live schema. Both
-- operations are idempotent because `make migrate` re-runs every *.sql file.

SET @dc_blog_data_migrated := (
	SELECT COUNT(*) = 1
	FROM `oc_schema_migrations`
	WHERE `filename` = '20261125_blog_advanced_description.php'
);

-- A missing marker deliberately executes a query against a non-existent table,
-- so the migration fails instead of silently marking the destructive DDL done.
SET @dc_blog_drop_sql := IF(
	@dc_blog_data_migrated,
	'ALTER TABLE `oc_blog_post` DROP COLUMN IF EXISTS `use_builder`',
	'SELECT * FROM `__dc_blog_advanced_description_data_migration_required`'
);
PREPARE dc_blog_drop_stmt FROM @dc_blog_drop_sql;
EXECUTE dc_blog_drop_stmt;
DEALLOCATE PREPARE dc_blog_drop_stmt;

SET @dc_blog_drop_sql := IF(
	@dc_blog_data_migrated,
	'ALTER TABLE `oc_landing` DROP INDEX IF EXISTS `idx_landing_post`, DROP COLUMN IF EXISTS `post_id`',
	'SELECT * FROM `__dc_blog_advanced_description_data_migration_required`'
);
PREPARE dc_blog_drop_stmt FROM @dc_blog_drop_sql;
EXECUTE dc_blog_drop_stmt;
DEALLOCATE PREPARE dc_blog_drop_stmt;
