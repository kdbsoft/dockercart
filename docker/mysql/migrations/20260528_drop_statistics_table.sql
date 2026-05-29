-- Remove unused oc_statistics table (stale snapshot data, never auto-updated)
DROP TABLE IF EXISTS `oc_statistics`;

-- Remove event hooks that referenced the statistics model
DELETE FROM `oc_event` WHERE `code` IN ('statistics_review_add', 'statistics_return_add', 'statistics_order_history');
