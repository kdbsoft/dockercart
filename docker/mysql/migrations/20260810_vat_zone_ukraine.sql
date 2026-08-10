-- Link the demo "VAT Зона" geo zone (id 3) to all Ukraine zones.
-- The demo seed creates the tax rates/rules and the geo zone, but never
-- populates oc_zone_to_geo_zone, so no tax rates ever matched any address
-- and sales tax was silently missing from the storefront and admin orders.
-- Idempotent: only inserts rows that do not already exist.

INSERT IGNORE INTO `oc_zone_to_geo_zone` (`country_id`, `zone_id`, `geo_zone_id`, `date_added`, `date_modified`)
SELECT z.country_id, z.zone_id, '3', NOW(), NOW()
FROM `oc_zone` z
WHERE z.country_id = '220'
  AND NOT EXISTS (
    SELECT 1 FROM `oc_zone_to_geo_zone` z2gz
    WHERE z2gz.geo_zone_id = '3' AND z2gz.country_id = z.country_id AND z2gz.zone_id = z.zone_id
  );
