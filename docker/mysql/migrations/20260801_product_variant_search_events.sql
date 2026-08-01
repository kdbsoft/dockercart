-- Migration: 20260801 - product variant search indexing events
-- Configurable product variants carry their own article codes (model/sku/upc/ean/mpn)
-- which are indexed into the Manticore `products` document. These events re-index the
-- parent product whenever variants change, keeping the full-text index in sync.
-- Idempotent: `make migrate` re-runs every file, so inserts guard on NOT EXISTS.
INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_variant_add', 'admin/model/catalog/product_configurable/addVariant/after', 'extension/module/dockercart_search/eventVariantAdd', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_variant_add');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_variant_edit', 'admin/model/catalog/product_configurable/updateVariant/after', 'extension/module/dockercart_search/eventVariantEdit', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_variant_edit');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_variant_delete', 'admin/model/catalog/product_configurable/deleteVariant/after', 'extension/module/dockercart_search/eventVariantDelete', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_variant_delete');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_search_variant_delete_all', 'admin/model/catalog/product_configurable/deleteAllVariants/after', 'extension/module/dockercart_search/eventVariantDeleteAll', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_search_variant_delete_all');
