-- Add GUI "System Update" permission to the Administrator group (idempotent).
-- The permission column is a JSON string; append 'tool/update' to both
-- access and modify arrays only when it is not already present.
-- MariaDB-compatible: JSON_ARRAY_APPEND() is a MySQL-only function.
--
-- Robustness: ModelUserUserGroup::addUserGroup()/editUserGroup() store an
-- EMPTY STRING (not a JSON document) in `permission` when a group is saved
-- with no permission checkboxes ticked, and JSON_CONTAINS('') aborts the
-- whole UPDATE with "Unexpected end of JSON text" (ER 4037). Heal such rows
-- to a valid empty document first, then guard every append with JSON_VALID().

UPDATE `oc_user_group`
SET `permission` = '{"access": [], "modify": []}'
WHERE `permission` IS NULL
	OR `permission` = ''
	OR JSON_VALID(`permission`) = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE_PRESERVE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('tool/update'))
)
WHERE `permission` IS NOT NULL
	AND JSON_VALID(`permission`)
	AND JSON_CONTAINS(`permission`, '"tool/update"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE_PRESERVE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('tool/update'))
)
WHERE `permission` IS NOT NULL
	AND JSON_VALID(`permission`)
	AND JSON_CONTAINS(`permission`, '"tool/update"', '$.modify') = 0;
