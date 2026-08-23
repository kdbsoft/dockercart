-- Removes the Store Locations feature (admin CRUD, contact-page block)
DROP TABLE IF EXISTS `oc_location`;

-- Selected locations per store (contact page block)
DELETE FROM `oc_setting` WHERE `key` = 'config_location';

-- Location image size fields from the dockercart theme settings
DELETE FROM `oc_setting` WHERE `code` = 'theme_dockercart'
  AND `key` IN ('theme_dockercart_image_location_width', 'theme_dockercart_image_location_height');

-- Strip localisation/location permissions from user groups
UPDATE `oc_user_group` SET `permission` = REPLACE(REPLACE(REPLACE(`permission`,
  '"localisation\\/location", ', ''),
  ', "localisation\\/location"', ''),
  '"localisation\\/location"', '');
