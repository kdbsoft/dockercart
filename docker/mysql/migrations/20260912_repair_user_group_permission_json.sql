-- Repairs oc_user_group.permission documents corrupted by REPLACE-based
-- permission migrations (e.g. 20260911_remove_account_store_modules.sql before
-- its object-form fix) when the document was in the legacy OBJECT form:
--
--   {"access":{"0":"catalog/attribute","1":"...",...}}
--
-- Removing a route's quoted value from that shape left a dangling numeric key
-- ("68":,) which made the stored JSON INVALID. PHP's json_decode() then
-- returned null, so every hasPermission() check failed and the user group
-- (including Administrator) lost ALL permissions.
--
-- 1. Heal dangling numeric keys (document becomes valid JSON again; the object
--    form is preserved by this step).
-- 2. Normalize object-form access/modify lists into true arrays. This is the
--    same goal as 20260816_fix_user_group_permission_arrays.sql /
--    20260824_fix_user_group_permissions.sql, but those are recorded as already
--    applied on installs seeded from older init.sql dumps and therefore never
--    ran there; it is also applied to every user group, not just group 1.
--
-- Idempotent: every statement is a no-op once applied (safe to re-run).

-- 1a. Dangling key in the middle of a list:  ,"68":,  ->  ,
UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, ',"[0-9]+":,', ',')
WHERE `permission` IS NOT NULL
	AND `permission` REGEXP ',"[0-9]+":,';

-- 1b. Dangling key at the end of a list:  ,"104":}  ->  }
UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, ',"[0-9]+":}', '}')
WHERE `permission` IS NOT NULL
	AND `permission` REGEXP ',"[0-9]+":}';

-- 1c. Dangling key as the only member of a list:  {"68":,  ->  {  /  {"68":}  ->  {}
UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, '\\{"[0-9]+":,', '{')
WHERE `permission` IS NOT NULL
	AND `permission` REGEXP '\\{"[0-9]+":,';

UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, '\\{"[0-9]+":}', '{}')
WHERE `permission` IS NOT NULL
	AND `permission` REGEXP '\\{"[0-9]+":}';

-- 2. Rebuild access & modify as true JSON arrays (handles both the proper
--    array form via '$[*]' and the malformed object form via '$.*'; exactly
--    one branch produces rows per list — same technique as
--    20260824_fix_user_group_permissions.sql, but for every user group).
UPDATE `oc_user_group`
SET `permission` = JSON_OBJECT(
	'access', JSON_MERGE_PRESERVE(
		COALESCE((
			SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
			FROM JSON_TABLE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
			WHERE JSON_TYPE(jt.el) = 'STRING'
		), JSON_ARRAY()),
		COALESCE((
			SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
			FROM JSON_TABLE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), '$.*' COLUMNS (el JSON PATH '$')) AS jt
			WHERE JSON_TYPE(jt.el) = 'STRING'
		), JSON_ARRAY())
	),
	'modify', JSON_MERGE_PRESERVE(
		COALESCE((
			SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
			FROM JSON_TABLE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
			WHERE JSON_TYPE(jt.el) = 'STRING'
		), JSON_ARRAY()),
		COALESCE((
			SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
			FROM JSON_TABLE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), '$.*' COLUMNS (el JSON PATH '$')) AS jt
			WHERE JSON_TYPE(jt.el) = 'STRING'
		), JSON_ARRAY())
	)
)
WHERE `permission` IS NOT NULL
	AND JSON_VALID(`permission`)
	AND (
		JSON_TYPE(JSON_EXTRACT(`permission`, '$.access')) = 'OBJECT'
		OR JSON_TYPE(JSON_EXTRACT(`permission`, '$.modify')) = 'OBJECT'
	);
