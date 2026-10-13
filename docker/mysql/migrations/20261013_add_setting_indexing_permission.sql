-- Grant the "Indexing" settings page permission (access + modify) to the
-- Administrator group (user_group_id = 1). Idempotent, and runs on fresh
-- installs too (migrations are applied after the base seed).
--
-- `permission` is stored as a JSON string; addUserGroup()/editUserGroup() can
-- leave an empty string in it, and JSON_CONTAINS('') aborts, so normalize
-- empty/invalid rows first (same guard as 20260815_add_tool_update_permission.sql).

UPDATE `oc_user_group`
SET `permission` = '{"access": [], "modify": []}'
WHERE `permission` IS NULL
	OR `permission` = ''
	OR JSON_VALID(`permission`) = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE_PRESERVE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('setting/indexing'))
)
WHERE `user_group_id` = 1
	AND JSON_VALID(`permission`)
	AND JSON_CONTAINS(`permission`, '"setting/indexing"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE_PRESERVE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('setting/indexing'))
)
WHERE `user_group_id` = 1
	AND JSON_VALID(`permission`)
	AND JSON_CONTAINS(`permission`, '"setting/indexing"', '$.modify') = 0;
