-- Security fix: dashboard widget data routes (extension/dashboard/<code>) were
-- previously exempt from permission checks (startup/permission.php skipped the
-- route and the widget controllers had no checks of their own). The guards now
-- live in the widget controllers, so the routes must exist in each user
-- group's "access" list. Grants the 13 widget routes to every group that can
-- already access the admin dashboard page (common/dashboard).
--
-- Idempotent: first STRIP all extension/dashboard* entries (same technique as
-- 20260909_remove_dashboard_extension_type.sql), then ADD the canonical set.
-- Re-running therefore always ends with exactly one copy of each route.

-- 1. Strip any existing dashboard entries from both permission lists.
UPDATE `oc_user_group`
SET `permission` = JSON_OBJECT(
	'access', COALESCE((
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.access'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING'
			AND JSON_UNQUOTE(jt.el) NOT LIKE 'extension/dashboard%'
	), JSON_ARRAY()),
	'modify', COALESCE((
		SELECT JSON_ARRAYAGG(JSON_UNQUOTE(jt.el))
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.modify'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		WHERE JSON_TYPE(jt.el) = 'STRING'
			AND JSON_UNQUOTE(jt.el) NOT LIKE 'extension/dashboard%'
	), JSON_ARRAY())
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND `permission` LIKE '%extension\\/dashboard%';

-- 2. Append the canonical widget routes to "access" for every group that has
--    an access list (common/dashboard itself is exempt from permission checks
--    in startup/permission.php, so groups don't carry it explicitly).
UPDATE `oc_user_group`
SET `permission` = JSON_MERGE_PRESERVE(
	`permission`,
	JSON_OBJECT('access', JSON_ARRAY(
		'extension/dashboard/activity',
		'extension/dashboard/chart',
		'extension/dashboard/customer',
		'extension/dashboard/dockercart_aov',
		'extension/dashboard/dockercart_category_revenue',
		'extension/dashboard/dockercart_conversion',
		'extension/dashboard/dockercart_repeat',
		'extension/dashboard/dockercart_top_products',
		'extension/dashboard/order',
		'extension/dashboard/recent',
		'extension/dashboard/sale',
		'extension/dashboard/traffic_source',
		'extension/dashboard/viewed_product'
	))
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND JSON_TYPE(JSON_EXTRACT(`permission`, '$.access')) = 'ARRAY';
