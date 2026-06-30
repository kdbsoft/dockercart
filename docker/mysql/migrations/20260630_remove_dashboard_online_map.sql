-- Remove People Online and World Map dashboard modules

DROP TABLE IF EXISTS `oc_customer_online`;

DELETE FROM `oc_setting` WHERE `code` = 'dashboard_online';
DELETE FROM `oc_setting` WHERE `code` = 'dashboard_map';
DELETE FROM `oc_setting` WHERE `code` = 'config' AND `key` = 'config_customer_online';

DELETE FROM `oc_extension` WHERE `type` = 'dashboard' AND `code` IN ('online', 'map');
