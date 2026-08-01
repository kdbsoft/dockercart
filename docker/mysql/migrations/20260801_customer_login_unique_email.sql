-- Unique email on oc_customer_login: concurrent failed logins used to create
-- duplicate rows (SELECT-then-INSERT race), undercounting the lockout counter.
-- Merge existing duplicates first, then add the unique index.
-- (Idempotent.)

-- Merge duplicate rows: keep the earliest row, sum totals into it.
UPDATE `oc_customer_login` c
JOIN (
	SELECT `email`, SUM(`total`) AS `total`, MIN(`customer_login_id`) AS `keep_id`
	FROM `oc_customer_login`
	GROUP BY `email`
) x ON c.`customer_login_id` = x.`keep_id`
SET c.`total` = x.`total`;

-- Remove the now-redundant duplicate rows.
DELETE c FROM `oc_customer_login` c
LEFT JOIN (
	SELECT MIN(`customer_login_id`) AS `keep_id`
	FROM `oc_customer_login`
	GROUP BY `email`
) x ON c.`customer_login_id` = x.`keep_id`
WHERE x.`keep_id` IS NULL;

ALTER TABLE `oc_customer_login` ADD UNIQUE INDEX IF NOT EXISTS `ux_email` (`email`);
