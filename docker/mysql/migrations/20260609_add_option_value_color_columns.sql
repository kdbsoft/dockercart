-- Add color and color_code columns to oc_option_value for the color swatch feature
ALTER TABLE `oc_option_value`
  ADD COLUMN IF NOT EXISTS `color` varchar(255) NOT NULL DEFAULT '' AFTER `image`,
  ADD COLUMN IF NOT EXISTS `color_code` varchar(9) NOT NULL DEFAULT '' AFTER `color`;
