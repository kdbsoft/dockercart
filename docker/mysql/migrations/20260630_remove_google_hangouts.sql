-- Remove deprecated Google Hangouts module

DELETE FROM `oc_extension` WHERE `code` = 'module/google_hangouts';

DELETE FROM `oc_setting` WHERE `key` LIKE 'module_google_hangouts%';

UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, ',\\s*"\\d+":"extension\\\\/module\\\\/google_hangouts"', '');
