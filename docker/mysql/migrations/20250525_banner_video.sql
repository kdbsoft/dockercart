-- Add video support columns to oc_banner_image
ALTER TABLE `oc_banner_image`
  ADD COLUMN `video_type` varchar(16) NOT NULL DEFAULT '' AFTER `image_portrait`,
  ADD COLUMN `video` varchar(255) NOT NULL DEFAULT '' AFTER `video_type`;
