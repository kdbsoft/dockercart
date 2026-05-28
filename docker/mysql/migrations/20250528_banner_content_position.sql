-- Add content_position column to oc_banner_image
ALTER TABLE `oc_banner_image`
  ADD COLUMN IF NOT EXISTS `content_position` varchar(16) NOT NULL DEFAULT 'left' AFTER `sort_order`;
