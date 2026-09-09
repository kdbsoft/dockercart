-- Remove the Dashboard extension type: widgets are auto-discovered from
-- controller/extension/dashboard/*.php and configured inline on the dashboard
-- page (edit mode, gated by the store settings permission).
-- Layout settings (dashboard_<code>_status/width/sort_order/stack) in oc_setting
-- are intentionally kept.

-- 1. Extension registration rows.
DELETE FROM `oc_extension` WHERE `type` = 'dashboard';

-- 2. Strip per-widget (extension/dashboard/<code>) and type
-- (extension/extension/dashboard) permissions from all user groups, leaving
-- every other permission and every group row intact. Widget data routes no
-- longer need these (the dashboard page itself is exempt from permission
-- checks); layout changes are gated by the modify check in saveLayout.
UPDATE `oc_user_group`
SET `permission` = JSON_OBJECT(
	'access', COALESCE((
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.access'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING'
			AND JSON_UNQUOTE(jt.el) NOT LIKE 'extension/dashboard%'
			AND JSON_UNQUOTE(jt.el) <> 'extension/extension/dashboard'
	), JSON_ARRAY()),
	'modify', COALESCE((
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.modify'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING'
			AND JSON_UNQUOTE(jt.el) NOT LIKE 'extension/dashboard%'
			AND JSON_UNQUOTE(jt.el) <> 'extension/extension/dashboard'
	), JSON_ARRAY())
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND `permission` LIKE '%extension\\/dashboard%';
