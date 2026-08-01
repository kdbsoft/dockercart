-- Stock reservation at checkout: holds product quantities for a configurable
-- window when a customer enters the checkout flow, preventing overselling of
-- the last item(s) under high traffic.
--
-- 1. oc_stock_reservation — active holds keyed by session. Unbound rows
--    (order_id IS NULL) expire via expires_at and are swept by the scheduler
--    task registered below. Rows bound to an order survive until stock is
--    subtracted (processing/complete) or the order is cancelled/refunded.
-- 2. reserve_minutes on oc_dockercart_universal_payment — per-payment-method
--    override (NULL = global setting, 0 = no reservation for this method).
-- 3. Global settings config_stock_reserve_enabled / config_stock_reserve_minutes.
-- (Idempotent: make migrate re-runs every file.)

CREATE TABLE IF NOT EXISTS `oc_stock_reservation` (
	`reservation_id` INT(11) NOT NULL AUTO_INCREMENT,
	`session_id` VARCHAR(128) NOT NULL,
	`product_id` INT(11) NOT NULL,
	`variant_id` INT(11) NOT NULL DEFAULT 0,
	`quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	`order_id` INT(11) DEFAULT NULL,
	`expires_at` DATETIME NOT NULL,
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`reservation_id`),
	KEY `idx_reservation_session` (`session_id`),
	KEY `idx_reservation_product` (`product_id`, `variant_id`),
	KEY `idx_reservation_expiry` (`expires_at`),
	KEY `idx_reservation_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-payment-method reserve override (module-created table, guard existence).
SET @sql := IF(
	EXISTS (
		SELECT 1 FROM information_schema.tables
		WHERE table_schema = DATABASE() AND table_name = 'oc_dockercart_universal_payment'
	),
	'ALTER TABLE `oc_dockercart_universal_payment` ADD COLUMN IF NOT EXISTS `reserve_minutes` INT(11) DEFAULT NULL AFTER `sort_order`',
	'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Global settings defaults (existing installs keep their current values).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_stock_reserve_enabled', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_stock_reserve_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_stock_reserve_minutes', '30', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_stock_reserve_minutes');

-- Scheduler task sweeping expired unbound reservations (hidden from admin UI).
INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('reservation_cleanup', 'Reservation Cleanup', 0, 'php /var/www/html/bin/dockercart_reservation_cleanup.php', 1, 'every_15m', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `date_modified` = NOW();
