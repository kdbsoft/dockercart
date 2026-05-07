ALTER TABLE `oc_dockercart_import_yml_profile`
	ADD COLUMN IF NOT EXISTS `customer_group_price_mapping` TEXT NULL AFTER `allow_zero_price`;
