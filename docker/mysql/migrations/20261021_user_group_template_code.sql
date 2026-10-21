-- Role templates for admin user groups.
--
-- Adds `oc_user_group.template_code`: the code of the standard role template
-- (DockercartRoles::templates(), see upload/system/library/dockercart/roles.php)
-- a group was created from. It makes "Create standard roles" idempotent even if
-- the group was renamed, and lets the group list show which role a group is
-- based on. NULL = custom group built by hand.
--
-- The groups themselves are NOT inserted here: they are created on demand from
-- the PHP templates (Admin > Users > User Groups > "Create standard roles"), so
-- the route lists live in exactly one place and never drift from SQL.
--
-- Idempotent: every statement is a no-op once applied (safe to re-run by
-- `make migrate`).

ALTER TABLE `oc_user_group`
	ADD COLUMN IF NOT EXISTS `template_code` varchar(64) NULL DEFAULT NULL AFTER `name`;

-- MariaDB has no "ADD INDEX IF NOT EXISTS" - guard by information_schema.
SET @has_index := (
	SELECT COUNT(*)
	FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'oc_user_group'
		AND INDEX_NAME = 'idx_template_code'
);

SET @sql := IF(@has_index = 0,
	'ALTER TABLE `oc_user_group` ADD INDEX `idx_template_code` (`template_code`)',
	'DO 0'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
