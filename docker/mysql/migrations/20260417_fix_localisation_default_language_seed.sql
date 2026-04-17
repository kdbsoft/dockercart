-- Ensure default language rows exist for country/zone/geo_zone descriptions.
-- Safe to run multiple times: uses NOT EXISTS checks.

SET @default_language_id = (
  SELECT CAST(`value` AS UNSIGNED)
  FROM `oc_setting`
  WHERE `store_id` = 0 AND `key` = 'config_language_id'
  ORDER BY `setting_id` DESC
  LIMIT 1
);

SET @default_language_id = COALESCE(
  @default_language_id,
  (
    SELECT `language_id`
    FROM `oc_language`
    WHERE `status` = '1'
    ORDER BY `sort_order` ASC, `language_id` ASC
    LIMIT 1
  )
);

INSERT INTO `oc_country_description` (`country_id`, `language_id`, `name`, `address_format`)
SELECT c.`country_id`, @default_language_id, c.`name`, c.`address_format`
FROM `oc_country` c
WHERE @default_language_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `oc_country_description` cd
    WHERE cd.`country_id` = c.`country_id`
      AND cd.`language_id` = @default_language_id
  );

INSERT INTO `oc_zone_description` (`zone_id`, `language_id`, `name`)
SELECT z.`zone_id`, @default_language_id, z.`name`
FROM `oc_zone` z
WHERE @default_language_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `oc_zone_description` zd
    WHERE zd.`zone_id` = z.`zone_id`
      AND zd.`language_id` = @default_language_id
  );

INSERT INTO `oc_geo_zone_description` (`geo_zone_id`, `language_id`, `name`, `description`)
SELECT gz.`geo_zone_id`, @default_language_id, gz.`name`, gz.`description`
FROM `oc_geo_zone` gz
WHERE @default_language_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `oc_geo_zone_description` gzd
    WHERE gzd.`geo_zone_id` = gz.`geo_zone_id`
      AND gzd.`language_id` = @default_language_id
  );
