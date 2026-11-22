-- Blog posts as landing pages: per-post page builder mode.
--
-- Design notes (mirrors 20261119_information_landing.sql):
--   * oc_blog_post.use_builder switches the storefront article between the classic
--     WYSIWYG content (0) and the landing section builder (1). The WYSIWYG
--     `content` stays untouched — it acts as the fallback when the builder is off
--     and seeds the first text block when the builder is enabled
--     (ModelExtensionModuleDockercartBlogPost::enableBuilder()).
--   * oc_landing.post_id is the "owner" link: a landing bound to a blog post is
--     that article's composed page, not a standalone page. It is hidden from the
--     landing list, never mirrored into oc_seo_url, is not reachable through
--     landing/page&landing_id=N or lp-<id> (404) and its own
--     status/schedule/is_home/slug are ignored — visibility is governed by
--     oc_blog_post.status (+ store + date_published) alone, and the URL/meta
--     come from oc_blog_seo_url / oc_blog_post_description.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_blog_post`
	ADD COLUMN IF NOT EXISTS `use_builder` TINYINT(1) NOT NULL DEFAULT 0 AFTER `background_image`;

ALTER TABLE `oc_landing`
	ADD COLUMN IF NOT EXISTS `post_id` INT(11) NULL DEFAULT NULL AFTER `information_id`,
	ADD INDEX IF NOT EXISTS `idx_landing_post` (`post_id`);
