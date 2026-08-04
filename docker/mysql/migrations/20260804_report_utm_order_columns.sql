-- Migration: 20260804 - UTM columns on orders + cancelled status setting
-- Adds utm_source/utm_medium/utm_campaign to oc_order so the analytics report
-- can attribute orders to traffic sources (social networks, campaigns).
-- Also seeds config_cancelled_status (previously hardcoded as order_status 130).
-- All statements are idempotent: `make migrate` re-runs every migration file.

-- 1. UTM columns on oc_order
ALTER TABLE `oc_order`
  ADD COLUMN IF NOT EXISTS `utm_source` varchar(128) NOT NULL DEFAULT '' AFTER `shipping_code`,
  ADD COLUMN IF NOT EXISTS `utm_medium` varchar(64) NOT NULL DEFAULT '' AFTER `utm_source`,
  ADD COLUMN IF NOT EXISTS `utm_campaign` varchar(128) NOT NULL DEFAULT '' AFTER `utm_medium`;

-- 2. Indexes for analytics grouping (source/medium + date)
ALTER TABLE `oc_order`
  ADD KEY IF NOT EXISTS `idx_order_utm_source` (`utm_source`, `date_added`),
  ADD KEY IF NOT EXISTS `idx_order_utm_medium` (`utm_medium`, `date_added`);

-- 3. Cancelled order status used by the analytics cancellation-rate report
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_cancelled_status', '130', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_cancelled_status');

-- 4. Deduplicate oc_setting (kept idempotent; oc_setting has no unique key)
DELETE s1 FROM `oc_setting` s1
INNER JOIN `oc_setting` s2
  ON s1.store_id = s2.store_id AND s1.code = s2.code AND s1.`key` = s2.`key`
WHERE s1.setting_id > s2.setting_id;
