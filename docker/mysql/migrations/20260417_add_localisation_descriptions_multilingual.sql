-- Add multilingual descriptions for countries, zones and geo zones

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

CREATE TABLE IF NOT EXISTS `oc_country_description` (
  `country_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `address_format` mediumtext NOT NULL,
  PRIMARY KEY (`country_id`,`language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `oc_country_description`
  ADD COLUMN IF NOT EXISTS `address_format` mediumtext NOT NULL AFTER `name`;

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

CREATE TABLE IF NOT EXISTS `oc_zone_description` (
  `zone_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`zone_id`,`language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `oc_geo_zone_description` (
  `geo_zone_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`geo_zone_id`,`language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
