-- Information pages as landing pages: per-page page builder mode.
--
-- Design notes:
--   * oc_information.use_builder switches the storefront page between the classic
--     WYSIWYG description (0) and the landing section builder (1). The WYSIWYG
--     description stays untouched — it is still rendered by information/agree()
--     (the checkout terms checkbox) and acts as the fallback when the builder is off.
--   * oc_landing.information_id is the "owner" link: a landing page bound to an
--     information page is that page's composed body, not a standalone page. It is
--     hidden from the landing list, never mirrored into oc_seo_url, is not reachable
--     through landing/page&landing_id=N or lp-<id> (404) and its own
--     status/schedule/is_home/slug are ignored — visibility is governed by
--     oc_information.status (+ store) alone.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_information`
	ADD COLUMN IF NOT EXISTS `use_builder` TINYINT(1) NOT NULL DEFAULT 0 AFTER `full_width`;

ALTER TABLE `oc_landing`
	ADD COLUMN IF NOT EXISTS `information_id` INT(11) NULL DEFAULT NULL AFTER `landing_id`,
	ADD INDEX IF NOT EXISTS `idx_landing_information` (`information_id`);
