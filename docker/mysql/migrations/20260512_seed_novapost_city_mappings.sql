-- Seed city-level region map entries for cities with empty parent_region_id
-- These cities (e.g. Kyiv) need direct zone mapping since they have no parent region.
INSERT IGNORE INTO `oc_dockercart_novapost_region_map`
	(`novapost_region_id`, `country_code`, `novapost_region_name`, `city_name`)
SELECT DISTINCT '', m.country_code, 'City-level mapping', m.city_name
FROM `oc_dockercart_novapost_division` m
WHERE m.parent_region_id = ''
	AND m.enabled = '1'
	AND NOT EXISTS (
		SELECT 1 FROM `oc_dockercart_novapost_region_map` rm
		WHERE rm.city_name != ''
		AND rm.city_name = m.city_name
		AND rm.country_code = m.country_code
	);
