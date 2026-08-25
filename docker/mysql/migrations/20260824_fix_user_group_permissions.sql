-- Repairs the Administrator user-group permission document.
--
-- Root causes addressed:
--   * Blind re-runs of pre-20260816 migrations (via `make migrate`) convert the
--     permission lists into object form {"0":"route", ...}. PHP's json_decode
--     tolerates that, but tooling and dumps expect true arrays.
--   * extension/analytics/tiktok and
--     extension/shipping/dockercart_warehouse_pickup were granted only through
--     the admin user-groups UI and never by a migration, so fresh installs
--     hit "Permission Denied!" on those pages.
--   * extension/shipping/dockercart_warehouse_pickup accumulated duplicate
--     entries from ad-hoc manual grants.
--
-- Idempotent: every statement is a no-op once applied (safe to re-run).

SET SESSION group_concat_max_len = 4194304;

-- 1. Rebuild access & modify as sorted, unique, true arrays.
--    '$[*]' reads proper arrays, '$.*' reads the malformed object form;
--    exactly one branch produces rows per document.
SELECT COALESCE(GROUP_CONCAT(DISTINCT JSON_QUOTE(r) ORDER BY r SEPARATOR ','), '') INTO @access_list
FROM (
	SELECT jt.r
	FROM `oc_user_group`, JSON_TABLE(`permission`, '$.access[*]' COLUMNS(r VARCHAR(128) PATH '$')) jt
	WHERE `user_group_id` = 1
	UNION ALL
	SELECT jt.r
	FROM `oc_user_group`, JSON_TABLE(`permission`, '$.access.*' COLUMNS(r VARCHAR(128) PATH '$')) jt
	WHERE `user_group_id` = 1
) u;

SELECT COALESCE(GROUP_CONCAT(DISTINCT JSON_QUOTE(r) ORDER BY r SEPARATOR ','), '') INTO @modify_list
FROM (
	SELECT jt.r
	FROM `oc_user_group`, JSON_TABLE(`permission`, '$.modify[*]' COLUMNS(r VARCHAR(128) PATH '$')) jt
	WHERE `user_group_id` = 1
	UNION ALL
	SELECT jt.r
	FROM `oc_user_group`, JSON_TABLE(`permission`, '$.modify.*' COLUMNS(r VARCHAR(128) PATH '$')) jt
	WHERE `user_group_id` = 1
) u;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access', JSON_EXTRACT(CONCAT('[', @access_list, ']'), '$'),
	'$.modify', JSON_EXTRACT(CONCAT('[', @modify_list, ']'), '$')
)
WHERE `user_group_id` = 1;

-- 2. Grant extension/analytics/tiktok (never covered by a migration before).
UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('extension/analytics/tiktok'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/analytics/tiktok"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('extension/analytics/tiktok'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/analytics/tiktok"', '$.modify') = 0;

-- 3. Grant extension/shipping/dockercart_warehouse_pickup.
UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('extension/shipping/dockercart_warehouse_pickup'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/shipping/dockercart_warehouse_pickup"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('extension/shipping/dockercart_warehouse_pickup'))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"extension/shipping/dockercart_warehouse_pickup"', '$.modify') = 0;
