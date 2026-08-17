-- Add extension/store access and modify permissions to all user groups
-- Idempotent: safe to run multiple times

-- Ensure modify key exists for user groups that have only access
UPDATE `oc_user_group`
SET `permission` = JSON_INSERT(`permission`, '$.modify', JSON_OBJECT())
WHERE `permission` NOT LIKE '%"modify"%'
	AND JSON_VALID(`permission`)
	AND `permission` IS NOT NULL;

-- Add extension/store to access + modify using array appends.
-- Idempotent: guarded by JSON_CONTAINS so the route is never duplicated.
-- NOTE: never use a keyed path like '$.access.' || idx — that inserts a
-- nested object {0:..., 1:...} which breaks in_array() permission checks.
UPDATE `oc_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.access', 'extension/store')
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND JSON_KEYS(`permission`, '$.access') IS NOT NULL
	AND JSON_CONTAINS(`permission`, JSON_ARRAY('extension/store'), '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_ARRAY_APPEND(`permission`, '$.modify', 'extension/store')
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND JSON_KEYS(`permission`, '$.modify') IS NOT NULL
	AND JSON_CONTAINS(`permission`, JSON_ARRAY('extension/store'), '$.modify') = 0;
