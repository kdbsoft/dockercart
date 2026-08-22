-- Persist the catalog session id on orders so Traffic Conversions reporting can
-- join orders back to their traffic source (oc_dockercart_traffic_source) without
-- relying on the ephemeral oc_order_claim row, which is deleted on checkout
-- success and released on abandoned-checkout flows.
-- The collation must match oc_dockercart_traffic_source.session_id /
-- oc_order_claim.session_id (utf8mb4_unicode_ci); oc_order's table default is
-- utf8mb4_general_ci, which would make the JOIN fail with "Illegal mix of collations".
ALTER TABLE `oc_order` ADD COLUMN IF NOT EXISTS `session_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `customer_id`;
ALTER TABLE `oc_order` MODIFY `session_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `oc_order` ADD INDEX IF NOT EXISTS `idx_order_session` (`session_id`);
