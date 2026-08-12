-- Migration: 20260811 - review replies (one level deep)
-- Admins and registered customers can reply to reviews (mini-blog).
-- Replies are flat: parent_id points to a top-level review only.
-- Replies never affect rating cache, counts, schema.org or votes.
-- All statements are idempotent: `make migrate` re-runs every migration file.

ALTER TABLE `oc_review`
  ADD COLUMN IF NOT EXISTS `parent_id` INT(11) DEFAULT NULL AFTER `criteria_group_id`,
  ADD COLUMN IF NOT EXISTS `author_is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `parent_id`,
  ADD KEY IF NOT EXISTS `parent_id` (`parent_id`);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_replies_enabled', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_replies_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_reply_auto_approve', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_reply_auto_approve');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_reply_min_length', '2', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_reply_min_length');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_reply_max_length', '1000', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_reply_max_length');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_reply_author_name', '', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_reply_author_name');
