-- Migration: 20260801 - structured i18n keys for order history auto-notes
-- New timeline notes store comment_key + JSON comment_params so they can be
-- re-rendered in the viewer's language; legacy rows keep comment as-is.
ALTER TABLE `oc_order_history`
  ADD COLUMN IF NOT EXISTS `comment_key` varchar(255) NOT NULL DEFAULT '' AFTER `comment`,
  ADD COLUMN IF NOT EXISTS `comment_params` mediumtext NULL DEFAULT NULL AFTER `comment_key`;
