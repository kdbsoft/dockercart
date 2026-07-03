-- Add extension/store access and modify permissions to all user groups
-- Idempotent: safe to run multiple times

-- Ensure modify key exists for user groups that have only access
UPDATE `oc_user_group`
SET `permission` = JSON_INSERT(`permission`, '$.modify', JSON_OBJECT())
WHERE `permission` NOT LIKE '%"modify"%'
	AND JSON_VALID(`permission`)
	AND `permission` IS NOT NULL;

-- Add extension/store to access using max index + 1
UPDATE `oc_user_group`
SET `permission` = JSON_INSERT(
	`permission`,
	CONCAT('$.access.', (
		SELECT COALESCE(MAX(CAST(`k` AS UNSIGNED)), -1) + 1
		FROM JSON_TABLE(
			JSON_KEYS(`permission`, '$.access'),
			'$[*]' COLUMNS (`k` VARCHAR(10) PATH '$')
		) AS `jt`
	)),
	'extension/store'
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND JSON_KEYS(`permission`, '$.access') IS NOT NULL
	AND JSON_EXTRACT(`permission`, '$.access') NOT LIKE '%extension/store%';

-- Add extension/store to modify using max index + 1
UPDATE `oc_user_group`
SET `permission` = JSON_INSERT(
	`permission`,
	CONCAT('$.modify.', (
		SELECT COALESCE(MAX(CAST(`k` AS UNSIGNED)), -1) + 1
		FROM JSON_TABLE(
			JSON_KEYS(`permission`, '$.modify'),
			'$[*]' COLUMNS (`k` VARCHAR(10) PATH '$')
		) AS `jt`
	)),
	'extension/store'
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND JSON_KEYS(`permission`, '$.modify') IS NOT NULL
	AND JSON_EXTRACT(`permission`, '$.modify') NOT LIKE '%extension/store%';
