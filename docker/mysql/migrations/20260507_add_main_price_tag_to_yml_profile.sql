ALTER TABLE `oc_dockercart_import_yml_profile`
	ADD COLUMN IF NOT EXISTS `main_price_tag` VARCHAR(255) NOT NULL DEFAULT 'price' AFTER `customer_group_price_mapping`;
