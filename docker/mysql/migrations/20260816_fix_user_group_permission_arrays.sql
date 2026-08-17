-- One-time repair: ensure oc_user_group.permission remains a plain
-- {"access":[...strings...], "modify":[...strings...]} structure.
--
-- A previous bug (20260703_add_extension_store_permissions.sql) used a
-- keyed JSON_INSERT path ('$.access.' || idx) that MariaDB rendered as a
-- nested object {0:..., 1:...} inside the array (JSON_OBJECT, not a string
-- element). Cart\User::hasPermission() does in_array($route, $perm['access']),
-- so an object element makes every permission check fail ("lost all rights").
--
-- This migration is idempotent and safe to re-run:
--   * it flattens any nested-object element back into its string values,
--   * it strips the dead "user/api" entry (controller removed in 20260730),
--   * rows that are already clean pass through unchanged.

UPDATE `oc_user_group`
SET `permission` = JSON_OBJECT(
	'access', (
		SELECT JSON_ARRAYAGG(
			CASE
				WHEN JSON_TYPE(jt.el) = 'STRING'
					THEN JSON_UNQUOTE(jt.el)
				ELSE JSON_UNQUOTE(JSON_EXTRACT(jt.el, CONCAT('$.', jk.k)))
			END
		)
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.access'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		LEFT JOIN JSON_TABLE(JSON_KEYS(jt.el), '$[*]' COLUMNS (k VARCHAR(50) PATH '$')) AS jk
			ON JSON_TYPE(jt.el) = 'OBJECT'
		WHERE JSON_UNQUOTE(
			CASE
				WHEN JSON_TYPE(jt.el) = 'STRING'
					THEN JSON_UNQUOTE(jt.el)
				ELSE JSON_UNQUOTE(JSON_EXTRACT(jt.el, CONCAT('$.', jk.k)))
			END
		) <> 'user/api'
	),
	'modify', (
		SELECT JSON_ARRAYAGG(
			CASE
				WHEN JSON_TYPE(jt.el) = 'STRING'
					THEN JSON_UNQUOTE(jt.el)
				ELSE JSON_UNQUOTE(JSON_EXTRACT(jt.el, CONCAT('$.', jk.k)))
			END
		)
		FROM JSON_TABLE(JSON_EXTRACT(`permission`, '$.modify'), '$[*]' COLUMNS (el JSON PATH '$')) AS jt
		LEFT JOIN JSON_TABLE(JSON_KEYS(jt.el), '$[*]' COLUMNS (k VARCHAR(50) PATH '$')) AS jk
			ON JSON_TYPE(jt.el) = 'OBJECT'
		WHERE JSON_UNQUOTE(
			CASE
				WHEN JSON_TYPE(jt.el) = 'STRING'
					THEN JSON_UNQUOTE(jt.el)
				ELSE JSON_UNQUOTE(JSON_EXTRACT(jt.el, CONCAT('$.', jk.k)))
			END
		) <> 'user/api'
	)
)
WHERE JSON_VALID(`permission`)
	AND `permission` IS NOT NULL
	AND (
		JSON_TYPE(JSON_EXTRACT(`permission`, '$.access')) = 'ARRAY'
			AND JSON_EXTRACT(`permission`, '$.access') LIKE '%{%'
		OR JSON_TYPE(JSON_EXTRACT(`permission`, '$.modify')) = 'ARRAY'
			AND JSON_EXTRACT(`permission`, '$.modify') LIKE '%{%'
		OR JSON_CONTAINS(`permission`, JSON_ARRAY('user/api'), '$.access') = 1
		OR JSON_CONTAINS(`permission`, JSON_ARRAY('user/api'), '$.modify') = 1
	);
