-- Remove obsolete wishlist image size settings from the dockercart theme.
-- The wishlist page now resizes product images with theme_dockercart_image_product_width/height
-- (the same size as listing cards); the old image_wishlist_* settings are no longer read anywhere.
--
-- Idempotent: make migrate re-runs every migration file, so this script must be safe
-- to run repeatedly against an already-migrated database.

DELETE FROM `oc_setting`
WHERE `code` = 'theme_dockercart'
  AND `key` IN ('theme_dockercart_image_wishlist_width', 'theme_dockercart_image_wishlist_height');
