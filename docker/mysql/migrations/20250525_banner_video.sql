-- Add video support columns to oc_banner_image
ALTER TABLE `oc_banner_image`
  ADD COLUMN IF NOT EXISTS `video_type` varchar(16) NOT NULL DEFAULT '' AFTER `image_portrait`,
  ADD COLUMN IF NOT EXISTS `video` varchar(255) NOT NULL DEFAULT '' AFTER `video_type`;
