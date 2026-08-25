-- Supplier Orders: fix default for oc_order_product.supplier_status.
--
-- Root cause: the 20260823_warehouses migration created the column with
-- DEFAULT '' while every consumer (controller, model, Twig, KPI) expects
-- 'pending' as the initial state. Checkout never writes supplier_status so
-- new dropship rows silently landed in '' and vanished from the Pending
-- tab/KPI while rendering a raw language key `text_line_`. See
-- upload/admin/controller/warehouse/supplier_orders.php:352 and
-- upload/admin/model/warehouse/supplier_orders.php:58.
--
-- Also gates overdue on having a real lead time so that a dropship supplier
-- with no lead_time configured does not make every line overdue the next
-- day (the PHP mirror is in computeDeadline()).
--
-- Idempotent: MODIFY with the same definition is a no-op and the UPDATE
-- matches zero rows once backfilled; safe for `make migrate` re-runs.

ALTER TABLE `oc_order_product`
	MODIFY `supplier_status` VARCHAR(24) NOT NULL DEFAULT 'pending' AFTER `estimate_date`;

UPDATE `oc_order_product`
SET `supplier_status` = 'pending'
WHERE `supplier_status` = '';
