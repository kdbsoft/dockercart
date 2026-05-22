-- Migration: add `full_width` column to information table
-- Allows marking information pages for full-width display (no max-width constraint).
ALTER TABLE `oc_information`
  ADD COLUMN IF NOT EXISTS `full_width` tinyint(1) NOT NULL DEFAULT 0 AFTER `top`;
