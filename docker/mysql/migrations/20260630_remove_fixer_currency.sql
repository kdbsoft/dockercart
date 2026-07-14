-- Remove Fixer Currency Converter module settings
DELETE FROM `oc_setting`
WHERE `key` LIKE 'currency_fixer_%';

-- Remove fixer permission from all user groups (JSON uses escaped slashes: extension\/currency\/fixer)
UPDATE `oc_user_group`
SET `permission` = REPLACE(REPLACE(`permission`,
    ',"extension\\/currency\\/fixer"', ''),
    '"extension\\/currency\\/fixer","', '"')
WHERE `permission` LIKE '%extension\\/currency\\/fixer%';
