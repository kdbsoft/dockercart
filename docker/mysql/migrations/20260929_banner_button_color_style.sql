-- Add action-button color + style columns to oc_banner_image
ALTER TABLE `oc_banner_image`
  ADD COLUMN IF NOT EXISTS `primary_btn_color` varchar(16) NOT NULL DEFAULT '' AFTER `primary_btn_text`,
  ADD COLUMN IF NOT EXISTS `primary_btn_style` varchar(16) NOT NULL DEFAULT 'modern' AFTER `primary_btn_color`;
