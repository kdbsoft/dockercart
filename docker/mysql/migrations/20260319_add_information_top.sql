-- Migration: add `top` column to information table
-- Allows marking information pages for display in the header top navigation bar.
ALTER TABLE `oc_information`
  ADD COLUMN `top` tinyint(1) NOT NULL DEFAULT 0 AFTER `bottom`;
