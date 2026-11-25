-- Advanced Description for blog posts.
--
-- The article keeps its own page chrome, URL, meta tags, recommendations and
-- comments. Only the description slot changes:
--   * use_advanced_description = 0 -> classic WYSIWYG content;
--   * use_advanced_description = 1 -> Dockercart\Description sections.
--
-- The classic content stays stored as the search/snippet source and fallback.
-- The shared section structure lives on the post; translated section texts live
-- on each blog_post_description row, exactly like products/categories/brands.
--
-- The companion PHP data migration converts existing Builder posts before the
-- following migration removes the legacy Builder columns.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_blog_post`
	ADD COLUMN IF NOT EXISTS `use_advanced_description` TINYINT(1) NOT NULL DEFAULT 0 AFTER `background_image`,
	ADD COLUMN IF NOT EXISTS `advanced_description` MEDIUMTEXT NULL AFTER `use_advanced_description`;

ALTER TABLE `oc_blog_post_description`
	ADD COLUMN IF NOT EXISTS `advanced_content` MEDIUMTEXT NULL AFTER `content`;
