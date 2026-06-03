-- Remove button link/color columns from oc_banner_image — buttons are now decorative only
ALTER TABLE `oc_banner_image`
  DROP COLUMN IF EXISTS `primary_btn_link`,
  DROP COLUMN IF EXISTS `primary_btn_text_color`,
  DROP COLUMN IF EXISTS `primary_btn_bg_color`,
  DROP COLUMN IF EXISTS `secondary_btn_text`,
  DROP COLUMN IF EXISTS `secondary_btn_link`;
