-- Customer (storefront) REST API — payment initialisation.
--
-- The API order is created by POST /v1/checkout/{id}/confirm; the payment of the
-- chosen method is started afterwards by POST /v1/checkout/{id}/payment, which
-- runs the payment extension's own confirm() step (see
-- upload/system/library/dockercart/api/store_payment.php).
--
-- That second request needs the checkout row to survive confirm(), so the row
-- becomes a small state machine instead of being deleted:
--
--   * `status`            open | completed (a completed row is kept so the
--                         payment can be initialised, replayed and diagnosed),
--   * `order_id`          the order confirm() created,
--   * `order_status_id`   the order status right after the order write — the
--                         "nothing has been paid yet" guard for the first init,
--   * `payment_init`      the stored payment-init answer (JSON). A replay returns
--                         it verbatim, so a retried request never opens a second
--                         gateway session,
--   * `payment_init_at`   when it was produced (also bumped by `refresh=1`).
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_api_checkout`
	ADD COLUMN IF NOT EXISTS `status` VARCHAR(16) NOT NULL DEFAULT 'open' AFTER `agree`;

ALTER TABLE `oc_api_checkout`
	ADD COLUMN IF NOT EXISTS `order_id` INT(11) NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `oc_api_checkout`
	ADD COLUMN IF NOT EXISTS `order_status_id` INT(11) NOT NULL DEFAULT 0 AFTER `order_id`;

ALTER TABLE `oc_api_checkout`
	ADD COLUMN IF NOT EXISTS `payment_init` MEDIUMTEXT NULL DEFAULT NULL AFTER `order_status_id`;

ALTER TABLE `oc_api_checkout`
	ADD COLUMN IF NOT EXISTS `payment_init_at` DATETIME NULL DEFAULT NULL AFTER `payment_init`;

ALTER TABLE `oc_api_checkout`
	ADD INDEX IF NOT EXISTS `idx_api_checkout_status` (`customer_id`, `status`);

ALTER TABLE `oc_api_checkout`
	ADD INDEX IF NOT EXISTS `idx_api_checkout_order` (`order_id`);
