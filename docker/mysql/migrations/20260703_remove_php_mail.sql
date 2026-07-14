-- Remove PHP mail() — force SMTP only
-- Add OAuth 2.0 settings

UPDATE `oc_setting` SET `value` = 'smtp' WHERE `key` = 'config_mail_engine' AND `value` = 'mail';

DELETE FROM `oc_setting` WHERE `key` = 'config_mail_parameter';

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT `store_id`, 'config', 'config_mail_smtp_auth_method', 'login', 0
FROM `oc_setting` WHERE `key` = 'config_mail_engine' GROUP BY `store_id`;

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT `store_id`, 'config', 'config_mail_smtp_oauth_client_id', '', 0
FROM `oc_setting` WHERE `key` = 'config_mail_engine' GROUP BY `store_id`;

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT `store_id`, 'config', 'config_mail_smtp_oauth_client_secret', '', 0
FROM `oc_setting` WHERE `key` = 'config_mail_engine' GROUP BY `store_id`;

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT `store_id`, 'config', 'config_mail_smtp_oauth_refresh_token', '', 0
FROM `oc_setting` WHERE `key` = 'config_mail_engine' GROUP BY `store_id`;

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT `store_id`, 'config', 'config_mail_smtp_oauth_token', '', 0
FROM `oc_setting` WHERE `key` = 'config_mail_engine' GROUP BY `store_id`;
