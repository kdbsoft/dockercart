-- Add GUI "System Update" permission to the Administrator group (idempotent).
-- The permission column is a JSON string; append 'tool/update' to both
-- access and modify arrays only when it is not already present.
-- MariaDB-compatible: JSON_ARRAY_APPEND() is a MySQL-only function.

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
    `permission`,
    '$.access',
    JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('tool/update'))
)
WHERE JSON_CONTAINS(`permission`, '"tool/update"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
    `permission`,
    '$.modify',
    JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('tool/update'))
)
WHERE JSON_CONTAINS(`permission`, '"tool/update"', '$.modify') = 0;
