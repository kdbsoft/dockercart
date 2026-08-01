<?php
class ModelExtensionTotalCredit extends Model {
	public function getTotal($total) {
		$this->load->language('extension/total/credit');

		$balance = $this->customer->getBalance();

		if ((float)$balance) {
			$credit = min($balance, $total['total']);

			if ((float)$credit > 0) {
				$total['totals'][] = array(
					'code'       => 'credit',
					'title'      => $this->language->get('text_credit'),
					'value'      => -$credit,
					'sort_order' => $this->config->get('total_credit_sort_order')
				);

				$total['total'] -= $credit;
			}
		}
	}

	public function confirm($order_info, $order_total) {
		$this->load->language('extension/total/credit');

		if ($order_info['customer_id']) {
			// Serialize concurrent store-credit spending for this customer.
			// Locking reads see the latest committed state even under
			// REPEATABLE READ; this method runs inside the caller's transaction
			// (catalog model checkout/order addOrderHistory).
			$this->db->query("SELECT customer_id FROM `" . DB_PREFIX . "customer` WHERE customer_id = '" . (int)$order_info['customer_id'] . "' FOR UPDATE");

			$balance_query = $this->db->query("SELECT SUM(amount) AS total FROM `" . DB_PREFIX . "customer_transaction` WHERE customer_id = '" . (int)$order_info['customer_id'] . "' FOR UPDATE");

			$balance = (float)$balance_query->row['total'];
			$credit = (float)$order_total['value'];

			if (($balance + $credit) < 0) {
				return $this->config->get('config_fraud_status_id');
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_transaction SET customer_id = '" . (int)$order_info['customer_id'] . "', order_id = '" . (int)$order_info['order_id'] . "', description = '" . $this->db->escape(sprintf($this->language->get('text_order_id'), (int)$order_info['order_id'])) . "', amount = '" . (float)$order_total['value'] . "', date_added = NOW()");
		}
	}

	public function unconfirm($order_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "customer_transaction WHERE order_id = '" . (int)$order_id . "'");
	}
}
