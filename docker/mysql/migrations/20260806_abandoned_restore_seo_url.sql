-- SEO keyword for the abandoned cart restore endpoint so generated restore
-- links are clean URLs (/restore-cart?token=...) instead of
-- /index.php?route=checkout/dockercart_checkout/restore&token=...
-- oc_seo_url has no unique key, so guard each insert with WHERE NOT EXISTS.

INSERT INTO `oc_seo_url` (`store_id`, `language_id`, `query`, `keyword`)
SELECT 0, 1, 'checkout/dockercart_checkout/restore', 'restore-cart'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_seo_url` WHERE `store_id` = 0 AND `language_id` = 1 AND `query` = 'checkout/dockercart_checkout/restore');

INSERT INTO `oc_seo_url` (`store_id`, `language_id`, `query`, `keyword`)
SELECT 0, 2, 'checkout/dockercart_checkout/restore', 'restore-cart'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_seo_url` WHERE `store_id` = 0 AND `language_id` = 2 AND `query` = 'checkout/dockercart_checkout/restore');

INSERT INTO `oc_seo_url` (`store_id`, `language_id`, `query`, `keyword`)
SELECT 0, 3, 'checkout/dockercart_checkout/restore', 'restore-cart'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_seo_url` WHERE `store_id` = 0 AND `language_id` = 3 AND `query` = 'checkout/dockercart_checkout/restore');
