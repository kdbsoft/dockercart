-- Migration: 20260802 - order shipments (tracking numbers with partial quantities)
-- A shipment groups one or more tracking numbers... no: one row = one tracking
-- number; oc_order_shipment_item holds how much of each order product goes
-- with that tracking number. Partial shipments = several rows for the same
-- order. oc_order.tracking_number is kept in sync as a '|'-joined aggregate
-- (used by order list/print views).

CREATE TABLE IF NOT EXISTS `oc_order_shipment` (
  `shipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `tracking_number` varchar(255) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`shipment_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_order_shipment_item` (
  `shipment_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `order_product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`shipment_item_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `order_product_id` (`order_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping status: the flow status that requires a tracking number when the
-- order moves into it (default: 128 Shipped). NULL/0 disables the requirement.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_order_flow_shipping_status', '128', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_order_flow_shipping_status');
