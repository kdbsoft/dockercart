-- Add image link column back to oc_banner_image for per-slide image click-through URLs
ALTER TABLE `oc_banner_image`
  ADD COLUMN `link` varchar(255) NOT NULL DEFAULT '' AFTER `image_portrait`;
