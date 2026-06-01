-- Remove the unused slide wrapper link column from oc_banner_image
ALTER TABLE `oc_banner_image`
  DROP COLUMN IF EXISTS `link`;
