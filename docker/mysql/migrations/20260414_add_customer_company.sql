-- Add optional company field for customer profile (My Account > Edit)
ALTER TABLE `oc_customer`
  ADD COLUMN IF NOT EXISTS `company` VARCHAR(64) NOT NULL DEFAULT '' AFTER `telephone`;
