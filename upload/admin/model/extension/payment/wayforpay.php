<?php
class ModelExtensionPaymentWayforpay extends Model {
	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wayforpay_order` (
			  `wayforpay_order_id` INT NOT NULL AUTO_INCREMENT,
			  `order_id` INT NOT NULL,
			  `payment_status` VARCHAR(32) DEFAULT 'pending',
			  `callback_data` TEXT DEFAULT NULL,
			  `date_added` DATETIME NOT NULL,
			  `date_modified` DATETIME NOT NULL,
			  PRIMARY KEY (`wayforpay_order_id`),
			  UNIQUE KEY `order_id` (`order_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
		");
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wayforpay_order`");
	}

	public function getOrderByOrderId($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "wayforpay_order` WHERE order_id = '" . (int)$order_id . "'");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}
}
