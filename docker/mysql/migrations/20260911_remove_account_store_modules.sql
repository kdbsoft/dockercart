-- Removes legacy Account and Store layout widgets.
--
-- Account menu is rendered directly via common/account_menu (not a layout
-- widget) and Store is a legacy single-store-unused multistore switcher.
-- Both are hidden from the admin module lists in code; this uninstalls them:
-- mirrors ModelSettingExtension::uninstall() + deleteModulesByCode().
--
-- Idempotent: every statement is a no-op once applied (safe to re-run).

DELETE FROM `oc_extension` WHERE `type` = 'module' AND `code` IN ('account', 'store');

DELETE FROM `oc_setting` WHERE `code` IN ('module_account', 'module_store');

DELETE FROM `oc_module` WHERE `code` IN ('account', 'store');

DELETE FROM `oc_layout_module`
WHERE `code` IN ('account', 'store')
   OR `code` LIKE 'account.%'
   OR `code` LIKE 'store.%';

-- Strip module permissions from user groups. Permission JSON stores routes with
-- plain slashes ("extension/module/account"); the escaped variant is handled too
-- in case any row was written with JSON_UNESCAPED_SLASHES off. Handles
-- first/middle/last entries (same pattern as 20260823_remove_store_locations.sql).
UPDATE `oc_user_group` SET `permission` = REPLACE(REPLACE(REPLACE(`permission`,
  '"extension/module/account", ', ''),
  ', "extension/module/account"', ''),
  '"extension/module/account"', '');

UPDATE `oc_user_group` SET `permission` = REPLACE(REPLACE(REPLACE(`permission`,
  '"extension\\/module\\/account", ', ''),
  ', "extension\\/module\\/account"', ''),
  '"extension\\/module\\/account"', '');

UPDATE `oc_user_group` SET `permission` = REPLACE(REPLACE(REPLACE(`permission`,
  '"extension/module/store", ', ''),
  ', "extension/module/store"', ''),
  '"extension/module/store"', '');

UPDATE `oc_user_group` SET `permission` = REPLACE(REPLACE(REPLACE(`permission`,
  '"extension\\/module\\/store", ', ''),
  ', "extension\\/module\\/store"', ''),
  '"extension\\/module\\/store"', '');
