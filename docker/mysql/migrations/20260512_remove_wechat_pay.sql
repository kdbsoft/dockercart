-- Remove legacy WeChat Pay extension references
-- Idempotent: safe to run multiple times

DELETE FROM `oc_extension` WHERE `type` = 'payment' AND `code` = 'wechat_pay';

DELETE FROM `oc_setting` WHERE `code` = 'payment_wechat_pay';

UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(
	`permission`,
	',"[0-9]+":"extension\\\\/payment\\\\/wechat_pay"',
	''
)
WHERE `permission` LIKE '%extension\\\\/payment\\\\/wechat_pay%';
