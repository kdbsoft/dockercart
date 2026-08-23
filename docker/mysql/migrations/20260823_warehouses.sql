-- Multi-warehouse + dropshipping foundation.
--
-- Adds a warehouse concept (physical / virtual / dropship), per-warehouse
-- stock, working schedules with holidays, transfers between warehouses and a
-- stock movement journal. oc_product.quantity / oc_product_variant.quantity
-- are kept as denormalised SUM caches across warehouses (rewritten on every
-- mutation) so all existing reads continue to work unchanged; the source of
-- truth for stock is always oc_warehouse_stock.
--
-- config_warehouse_enabled=0 => full legacy behaviour (nothing reads the new
-- tables and the cache columns stay in sync via the existing subtract flow).
--
-- Idempotent: make migrate re-runs every *.sql file.
--
-- 1. Warehouse registry + schedule windows + holidays.
-- 2. Stock (source of truth) + movement journal + transfers.
-- 3. ALTER oc_stock_reservation (add warehouse_id) and oc_order_product
--    (add warehouse snapshot + dropship supplier columns).
-- 4. Seed default warehouse, mirror current stock, settings, extensions,
--    permissions, scheduler task.

-- ---------------------------------------------------------------------------
-- 1. Warehouse registry, schedules, holidays
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oc_warehouse` (
	`warehouse_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`type` ENUM('physical','virtual','dropship') NOT NULL DEFAULT 'physical',
	`is_default` TINYINT(1) NOT NULL DEFAULT 0,
	`priority` INT(11) NOT NULL DEFAULT 0,
	`status` TINYINT(1) NOT NULL DEFAULT 1,
	`sort_order` INT(11) NOT NULL DEFAULT 0,
	`address_1` VARCHAR(255) NOT NULL DEFAULT '',
	`address_2` VARCHAR(255) NOT NULL DEFAULT '',
	`city` VARCHAR(128) NOT NULL DEFAULT '',
	`postcode` VARCHAR(10) NOT NULL DEFAULT '',
	`country_id` INT(11) NOT NULL DEFAULT 0,
	`zone_id` INT(11) NOT NULL DEFAULT 0,
	`latitude` DECIMAL(10,8) NOT NULL DEFAULT 0.00000000,
	`longitude` DECIMAL(11,8) NOT NULL DEFAULT 0.00000000,
	`phone` VARCHAR(32) NOT NULL DEFAULT '',
	`email` VARCHAR(96) NOT NULL DEFAULT '',
	`map_url` VARCHAR(255) NOT NULL DEFAULT '',
	`prepare_days` INT(11) NOT NULL DEFAULT 0,
	`low_stock` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	`allow_pickup` TINYINT(1) NOT NULL DEFAULT 0,
	`pickup_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	`pickup_note` TEXT,
	`supplier_name` VARCHAR(255) NOT NULL DEFAULT '',
	`supplier_phone` VARCHAR(32) NOT NULL DEFAULT '',
	`supplier_email` VARCHAR(96) NOT NULL DEFAULT '',
	`supplier_lead_time` INT(11) NOT NULL DEFAULT 0,
	`supplier_note` TEXT,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`warehouse_id`),
	KEY `idx_warehouse_status_priority` (`status`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_schedule` (
	`schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
	`warehouse_id` INT(11) NOT NULL,
	`day_of_week` TINYINT(1) NOT NULL,
	`is_open` TINYINT(1) NOT NULL DEFAULT 1,
	PRIMARY KEY (`schedule_id`),
	UNIQUE KEY `ux_warehouse_schedule` (`warehouse_id`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_schedule_window` (
	`window_id` INT(11) NOT NULL AUTO_INCREMENT,
	`schedule_id` INT(11) NOT NULL,
	`time_from` TIME NOT NULL,
	`time_to` TIME NOT NULL,
	PRIMARY KEY (`window_id`),
	KEY `idx_schedule` (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_holiday` (
	`holiday_id` INT(11) NOT NULL AUTO_INCREMENT,
	`warehouse_id` INT(11) NOT NULL DEFAULT 0,
	`date` DATE NOT NULL,
	`name` VARCHAR(255) NOT NULL DEFAULT '',
	`is_open` TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`holiday_id`),
	UNIQUE KEY `ux_warehouse_holiday` (`warehouse_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Stock, movement journal, transfers
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oc_warehouse_stock` (
	`stock_id` INT(11) NOT NULL AUTO_INCREMENT,
	`warehouse_id` INT(11) NOT NULL,
	`product_id` INT(11) NOT NULL,
	`variant_id` INT(11) NOT NULL DEFAULT 0,
	`quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	`unlimited` TINYINT(1) NOT NULL DEFAULT 0,
	`lead_time` INT(11) NOT NULL DEFAULT 0,
	PRIMARY KEY (`stock_id`),
	UNIQUE KEY `ux_warehouse_stock` (`warehouse_id`,`product_id`,`variant_id`),
	KEY `idx_stock_product` (`product_id`,`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_stock_movement` (
	`movement_id` INT(11) NOT NULL AUTO_INCREMENT,
	`warehouse_id` INT(11) NOT NULL,
	`product_id` INT(11) NOT NULL,
	`variant_id` INT(11) NOT NULL DEFAULT 0,
	`type` ENUM('inbound','outbound','adjustment','transfer_in','transfer_out','order_subtract','order_restock','return') NOT NULL,
	`quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	`reference` VARCHAR(255) NOT NULL DEFAULT '',
	`order_id` INT(11) NOT NULL DEFAULT 0,
	`transfer_id` INT(11) NOT NULL DEFAULT 0,
	`user_id` INT(11) NOT NULL DEFAULT 0,
	`comment` VARCHAR(255) NOT NULL DEFAULT '',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`movement_id`),
	KEY `idx_movement_warehouse` (`warehouse_id`),
	KEY `idx_movement_product` (`product_id`,`variant_id`),
	KEY `idx_movement_order` (`order_id`),
	KEY `idx_movement_date` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_transfer` (
	`transfer_id` INT(11) NOT NULL AUTO_INCREMENT,
	`transfer_no` VARCHAR(64) NOT NULL,
	`from_warehouse_id` INT(11) NOT NULL,
	`to_warehouse_id` INT(11) NOT NULL,
	`status` ENUM('pending','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
	`note` VARCHAR(255) NOT NULL DEFAULT '',
	`created_by` INT(11) NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	`date_completed` DATETIME DEFAULT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_warehouse_transfer_item` (
	`item_id` INT(11) NOT NULL AUTO_INCREMENT,
	`transfer_id` INT(11) NOT NULL,
	`product_id` INT(11) NOT NULL,
	`variant_id` INT(11) NOT NULL DEFAULT 0,
	`quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
	PRIMARY KEY (`item_id`),
	KEY `idx_transfer` (`transfer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. ALTER existing tables
-- ---------------------------------------------------------------------------

ALTER TABLE `oc_stock_reservation`
	ADD COLUMN IF NOT EXISTS `warehouse_id` INT(11) NOT NULL DEFAULT 0 AFTER `order_id`,
	ADD KEY IF NOT EXISTS `idx_reservation_warehouse` (`warehouse_id`);

ALTER TABLE `oc_order_product`
	ADD COLUMN IF NOT EXISTS `warehouse_id` INT(11) NOT NULL DEFAULT 0 AFTER `variant_sku`,
	ADD COLUMN IF NOT EXISTS `warehouse_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `warehouse_id`,
	ADD COLUMN IF NOT EXISTS `estimate_date` DATE DEFAULT NULL AFTER `warehouse_name`,
	ADD COLUMN IF NOT EXISTS `supplier_status` VARCHAR(24) NOT NULL DEFAULT '' AFTER `estimate_date`,
	ADD COLUMN IF NOT EXISTS `supplier_ordered_date` DATETIME DEFAULT NULL AFTER `supplier_status`,
	ADD COLUMN IF NOT EXISTS `supplier_tracking` VARCHAR(128) NOT NULL DEFAULT '' AFTER `supplier_ordered_date`;

-- ---------------------------------------------------------------------------
-- 4. Seeds
-- ---------------------------------------------------------------------------

-- Default warehouse: one physical "Main warehouse".
INSERT INTO `oc_warehouse`
	(`warehouse_id`, `name`, `type`, `is_default`, `priority`, `status`, `sort_order`,
	 `address_1`, `city`, `postcode`, `country_id`, `zone_id`, `phone`, `email`,
	 `prepare_days`, `low_stock`, `allow_pickup`, `pickup_cost`, `date_added`, `date_modified`)
SELECT 1, 'Основной склад', 'physical', 1, 100, 1, 0,
	   '', '', '', 0, 0, '', '',
	   0, 0.0000, 0, 0.0000, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `oc_warehouse` WHERE `warehouse_id` = 1);

-- Default full working-week schedule for the default warehouse.
INSERT INTO `oc_warehouse_schedule` (`warehouse_id`, `day_of_week`, `is_open`)
SELECT 1, d.day_of_week, 1
FROM (SELECT 1 AS day_of_week UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) d
WHERE NOT EXISTS (SELECT 1 FROM `oc_warehouse_schedule` WHERE `warehouse_id` = 1 AND `day_of_week` = d.day_of_week);

-- Mirror current simple-product stock into the default warehouse.
INSERT IGNORE INTO `oc_warehouse_stock` (`warehouse_id`, `product_id`, `variant_id`, `quantity`, `unlimited`, `lead_time`)
SELECT 1, `product_id`, 0, `quantity`, 0, 0
FROM `oc_product`
WHERE `subtract` = '1'
	AND NOT EXISTS (
		SELECT 1 FROM `oc_warehouse_stock` s
		WHERE s.`warehouse_id` = 1 AND s.`product_id` = `oc_product`.`product_id` AND s.`variant_id` = 0
	);

-- Mirror current variant stock into the default warehouse.
INSERT IGNORE INTO `oc_warehouse_stock` (`warehouse_id`, `product_id`, `variant_id`, `quantity`, `unlimited`, `lead_time`)
SELECT 1, `product_id`, `variant_id`, `quantity`, 0, 0
FROM `oc_product_variant`
WHERE `subtract` = '1'
	AND NOT EXISTS (
		SELECT 1 FROM `oc_warehouse_stock` s
		WHERE s.`warehouse_id` = 1 AND s.`product_id` = `oc_product_variant`.`product_id` AND s.`variant_id` = `oc_product_variant`.`variant_id`
	);

-- Global warehouse settings (existing installs keep their current values).
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_enabled', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_split_allowed', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_split_allowed');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_stock_display', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_stock_display');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_show_pickup', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_show_pickup');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_estimate_enabled', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_estimate_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_warehouse_dropship_checkout', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `key` = 'config_warehouse_dropship_checkout');

-- Register the pickup shipping extension (disabled by default).
INSERT IGNORE INTO `oc_extension` (`type`, `code`) VALUES ('shipping', 'dockercart_warehouse_pickup');

-- Grant the Administrator group access & modify to the warehouse routes
-- (MariaDB-compatible JSON merge, no-op when already present).
UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.access',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY(
		'warehouse/warehouse', 'warehouse/stock', 'warehouse/movement', 'warehouse/transfer', 'warehouse/supplier_orders'
	))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"warehouse/warehouse"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
	`permission`,
	'$.modify',
	JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY(
		'warehouse/warehouse', 'warehouse/stock', 'warehouse/movement', 'warehouse/transfer', 'warehouse/supplier_orders'
	))
)
WHERE `user_group_id` = 1 AND JSON_CONTAINS(`permission`, '"warehouse/warehouse"', '$.modify') = 0;

-- Scheduler: daily warehouse audit (recompute + drift detection).
INSERT INTO `oc_dockercart_scheduler_task`
	(`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
	('warehouse_audit', 'Warehouse Audit', 0, 'php /var/www/html/bin/dockercart_warehouse_audit.php', 1, 'daily', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
	`worker_command` = VALUES(`worker_command`),
	`is_system` = 1,
	`date_modified` = NOW();