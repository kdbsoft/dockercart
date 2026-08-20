-- Migration: 20260820 - Product multi-currency (currency_id)
-- -------------------------------------------------------------
-- The `oc_product.currency_id` column (NULL = store default currency) already
-- exists in the generated `docker/mysql/init.sql` dump for fresh installs.
-- This idempotent migration guarantees it also exists on upgraded environments
-- that predate the column, so both `make migrate` (re-runs every file) and
-- `make update` (records applied files) work safely.

ALTER TABLE `oc_product`
  ADD COLUMN IF NOT EXISTS `currency_id` int(11) DEFAULT NULL;