-- Add missing indexes to oc_seo_url for cache load and lookup performance

ALTER TABLE `oc_seo_url`
  ADD INDEX IF NOT EXISTS `idx_store_id` (`store_id`);

ALTER TABLE `oc_seo_url`
  ADD INDEX IF NOT EXISTS `idx_store_language_query` (`store_id`, `language_id`, `query`(191));

ALTER TABLE `oc_seo_url`
  ADD INDEX IF NOT EXISTS `idx_store_keyword` (`store_id`, `keyword`(191));
