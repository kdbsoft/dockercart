-- Remove deprecated PayPal (Powered by Braintree) Button module

DELETE FROM `oc_extension` WHERE `code` = 'module/pp_braintree_button';

DELETE FROM `oc_setting` WHERE `key` LIKE 'module_pp_braintree_button%';

UPDATE `oc_user_group`
SET `permission` = REGEXP_REPLACE(`permission`, ',\\s*"\\d+":"extension\\\\/module\\\\/pp_braintree_button"', '');
