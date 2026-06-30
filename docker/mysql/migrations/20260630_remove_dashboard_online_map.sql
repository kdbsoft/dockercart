-- Remove People Online and World Map dashboard modules

DROP TABLE IF EXISTS `oc_customer_online`;

DELETE FROM `oc_setting` WHERE `setting_key` LIKE 'dashboard_online_%';
DELETE FROM `oc_setting` WHERE `setting_key` LIKE 'dashboard_map_%';
DELETE FROM `oc_setting` WHERE `setting_key` = 'config_customer_online';

DELETE FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` IN ('online', 'map');
