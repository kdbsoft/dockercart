-- Category banner: select an existing system banner (banner_id) instead of
-- uploading a standalone image per language.
-- oc_category gains a single banner_id column; the per-language table
-- oc_category_banner (banner_image + banner_link) is dropped entirely.
--
-- Idempotent: make migrate re-runs every migration file, so this script must be safe
-- to run repeatedly against an already-migrated database.

ALTER TABLE `oc_category` ADD COLUMN IF NOT EXISTS `banner_id` int(11) DEFAULT NULL AFTER `background_image`;

-- Full replacement: legacy uploaded images/links are intentionally discarded
-- (the admin UI no longer has fields for them).
DELETE FROM `oc_category_banner`;

DROP TABLE IF EXISTS `oc_category_banner`;
