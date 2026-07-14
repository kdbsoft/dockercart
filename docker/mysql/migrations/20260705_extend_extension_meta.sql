-- Extend oc_dockercart_extension_meta with module manifest fields
-- Author, license type, link, etc. are populated at install time from the Store API.

ALTER TABLE `oc_dockercart_extension_meta`
  ADD COLUMN IF NOT EXISTS `name` varchar(128) DEFAULT NULL AFTER `code`,
  ADD COLUMN IF NOT EXISTS `author` varchar(128) DEFAULT NULL AFTER `installed_version`,
  ADD COLUMN IF NOT EXISTS `author_email` varchar(255) DEFAULT NULL AFTER `author`,
  ADD COLUMN IF NOT EXISTS `license_type` varchar(64) DEFAULT NULL AFTER `author_email`,
  ADD COLUMN IF NOT EXISTS `link` varchar(255) DEFAULT NULL AFTER `license_type`;

-- Seed metadata for built-in dockercart_gallery (pilot module)
INSERT INTO `oc_dockercart_extension_meta`
  (`code`, `name`, `sku`, `installed_version`, `source`, `author`, `author_email`, `license_type`, `link`, `date_added`, `date_modified`)
VALUES
  ('dockercart_gallery', 'Gallery', 'dockercart_gallery', '1.0.0', 'store',
   'DockerCart', 'support@dockercart.net', 'Proprietary',
   'https://dockercart.net/modules/gallery',
   NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `author` = VALUES(`author`),
  `author_email` = VALUES(`author_email`),
  `license_type` = VALUES(`license_type`),
  `link` = VALUES(`link`);
