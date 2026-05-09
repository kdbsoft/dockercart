-- Add phone_format column to country for input masks
-- Format syntax: 'X' = digit placeholder, other characters = literal separators
-- Example: +1 (XXX) XXX-XXXX for USA

ALTER TABLE `oc_country`
	ADD COLUMN IF NOT EXISTS `phone_format` VARCHAR(64) NOT NULL DEFAULT ''
	AFTER `address_format`;

UPDATE `oc_country` SET `phone_format` = '+380 (XX) XXX-XX-XX' WHERE `country_id` = 220;
UPDATE `oc_country` SET `phone_format` = '+7 (XXX) XXX-XX-XX'   WHERE `country_id` = 176;
UPDATE `oc_country` SET `phone_format` = '+1 (XXX) XXX-XXXX'    WHERE `country_id` = 223;
