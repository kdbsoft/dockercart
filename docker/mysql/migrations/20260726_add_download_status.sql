ALTER TABLE `oc_download` ADD COLUMN IF NOT EXISTS `status` tinyint(1) NOT NULL DEFAULT 1 AFTER `mask`;
