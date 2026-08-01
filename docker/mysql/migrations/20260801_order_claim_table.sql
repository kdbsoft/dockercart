-- Order-creation claim table: exactly-once guard against duplicate orders
-- from double-submits / concurrent checkout POSTs (fix for order addOrder idempotency).
-- One row per session; addOrder() claims the row inside its transaction and
-- reuses a fresh order_id on repeat submissions.
-- (Idempotent.)

CREATE TABLE IF NOT EXISTS `oc_order_claim` (
	`session_id` VARCHAR(128) NOT NULL,
	`order_id` INT NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`session_id`),
	KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
