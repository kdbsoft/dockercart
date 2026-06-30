<?php
class ModelExtensionPaymentDockercartWayforpay extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/dockercart_wayforpay');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_dockercart_wayforpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get('payment_dockercart_wayforpay_total') > 0 && $this->config->get('payment_dockercart_wayforpay_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_dockercart_wayforpay_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'       => 'dockercart_wayforpay',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_dockercart_wayforpay_sort_order')
			);
		}

		return $method_data;
	}

	public function addOrder($order_id) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_wayforpay_order` SET order_id = '" . (int)$order_id . "', payment_status = 'pending', date_added = NOW(), date_modified = NOW()");

		return $this->db->getLastId();
	}

	public function getOrder($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_wayforpay_order` WHERE order_id = '" . (int)$order_id . "'");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}

	public function updateOrder($order_id, $data) {
		$sets = array();

		if (isset($data['payment_status'])) {
			$sets[] = "payment_status = '" . $this->db->escape($data['payment_status']) . "'";
		}

		if (isset($data['callback_data'])) {
			$sets[] = "callback_data = '" . $this->db->escape($data['callback_data']) . "'";
		}

		if (!empty($sets)) {
			$sets[] = "date_modified = NOW()";
			$this->db->query("UPDATE `" . DB_PREFIX . "dockercart_wayforpay_order` SET " . implode(', ', $sets) . " WHERE order_id = '" . (int)$order_id . "'");
		}
	}
}
