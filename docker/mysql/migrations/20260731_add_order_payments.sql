-- Migration: 20260731 - add order payment journal (payments, reversals, paid_amount)
CREATE TABLE IF NOT EXISTS `oc_order_payment` (
  `order_payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `payment_method` varchar(128) NOT NULL DEFAULT '',
  `payment_code` varchar(128) NOT NULL DEFAULT '',
  `reference` varchar(255) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`order_payment_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `oc_order` ADD COLUMN IF NOT EXISTS `paid_amount` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `total`;
