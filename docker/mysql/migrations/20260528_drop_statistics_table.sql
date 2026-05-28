-- Remove unused oc_statistics table (stale snapshot data, never auto-updated)
DROP TABLE IF EXISTS `oc_statistics`;
