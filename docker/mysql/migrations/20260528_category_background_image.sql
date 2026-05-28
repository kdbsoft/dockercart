-- Add background_image column to oc_category

ALTER TABLE `oc_category`
  ADD COLUMN IF NOT EXISTS `background_image` varchar(255) DEFAULT NULL AFTER `image`;
