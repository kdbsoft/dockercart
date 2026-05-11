-- Add city_name column to region_map for city-level zone mapping (e.g., Kyiv)
ALTER TABLE `oc_dockercart_novapost_region_map`
	ADD COLUMN IF NOT EXISTS `city_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `novapost_region_name`,
	DROP INDEX IF EXISTS `uk_np_region`,
	ADD UNIQUE KEY `uk_np_region` (`novapost_region_id`, `country_code`, `city_name`);
