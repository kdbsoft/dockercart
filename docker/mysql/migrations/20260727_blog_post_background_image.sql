ALTER TABLE `oc_blog_post`
  ADD COLUMN IF NOT EXISTS `background_image` varchar(255) DEFAULT NULL AFTER `image`;
