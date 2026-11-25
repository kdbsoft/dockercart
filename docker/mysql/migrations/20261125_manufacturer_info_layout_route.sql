-- Brand info pages must use the same Layout Builder layout as the brand list.
--
-- Only `product/manufacturer` (the A–Z list) was seeded in oc_layout_route.
-- `product/manufacturer/info` (an individual brand page) fell through to
-- config_layout_id (Default), so the modules assigned to the Manufacturer
-- layout — including content_bottom — never rendered on brand pages.
--
-- Mirror the route per store instead of hardcoding a layout id: shops may
-- have moved the list route to a different layout. An existing wildcard route
-- that already matches the info page (e.g. `product/manufacturer/%`) wins, so
-- no duplicate row is inserted. Idempotent: `make migrate` re-runs every SQL.

INSERT INTO `oc_layout_route` (`layout_id`, `store_id`, `route`)
SELECT DISTINCT src.layout_id, src.store_id, 'product/manufacturer/info'
FROM `oc_layout_route` src
WHERE src.route = 'product/manufacturer'
  AND NOT EXISTS (
    SELECT 1
    FROM `oc_layout_route` existing
    WHERE existing.store_id = src.store_id
      AND 'product/manufacturer/info' LIKE existing.route
  );
