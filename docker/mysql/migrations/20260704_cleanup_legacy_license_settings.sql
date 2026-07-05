-- Remove legacy DCFL-style license settings stored in oc_setting
-- These are replaced by the new oc_dockercart_license table + DCL- server-validated model

DELETE FROM `oc_setting`
WHERE `key` REGEXP '^module_dockercart_[a-z_]+_(license_key|public_key)$';
