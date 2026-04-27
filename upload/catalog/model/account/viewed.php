<?php
class ModelAccountViewed extends Model {
	const LIMIT_GUEST = 10;
	const LIMIT_CUSTOMER = 100;

	public function addViewedProduct($product_id) {
		$product_id = (int)$product_id;

		if ($product_id <= 0) {
			return;
		}

		if ($this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();

			$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_viewed_product WHERE customer_id = '" . $customer_id . "' AND product_id = '" . $product_id . "'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_viewed_product SET customer_id = '" . $customer_id . "', session_id = NULL, product_id = '" . $product_id . "', date_added = NOW(), date_modified = NOW()");

			$this->trimCustomerViews($customer_id, self::LIMIT_CUSTOMER);

			return;
		}

		$session_id = $this->getSessionId();

		if ($session_id === '') {
			return;
		}

		$session_id_sql = $this->db->escape($session_id);

		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_viewed_product WHERE customer_id IS NULL AND session_id = '" . $session_id_sql . "' AND product_id = '" . $product_id . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "dockercart_viewed_product SET customer_id = NULL, session_id = '" . $session_id_sql . "', product_id = '" . $product_id . "', date_added = NOW(), date_modified = NOW()");

		$this->trimGuestViews($session_id_sql, self::LIMIT_GUEST);
	}

	public function getViewedProductIds($limit = 0) {
		$limit = (int)$limit;

		if ($this->customer->isLogged()) {
			$customer_id = (int)$this->customer->getId();
			$sql = "SELECT product_id FROM " . DB_PREFIX . "dockercart_viewed_product WHERE customer_id = '" . $customer_id . "' ORDER BY date_modified DESC, viewed_id DESC";
		} else {
			$session_id = $this->getSessionId();

			if ($session_id === '') {
				return array();
			}

			$session_id_sql = $this->db->escape($session_id);
			$sql = "SELECT product_id FROM " . DB_PREFIX . "dockercart_viewed_product WHERE customer_id IS NULL AND session_id = '" . $session_id_sql . "' ORDER BY date_modified DESC, viewed_id DESC";
		}

		if ($limit > 0) {
			$sql .= " LIMIT " . $limit;
		}

		$query = $this->db->query($sql);

		$product_ids = array();

		foreach ($query->rows as $row) {
			$product_ids[] = (int)$row['product_id'];
		}

		return $product_ids;
	}

	private function trimCustomerViews($customer_id, $limit) {
		$customer_id = (int)$customer_id;
		$limit = (int)$limit;

		if ($customer_id <= 0 || $limit <= 0) {
			return;
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_viewed_product
			WHERE customer_id = '" . $customer_id . "'
			AND viewed_id NOT IN (
				SELECT viewed_id FROM (
					SELECT viewed_id
					FROM " . DB_PREFIX . "dockercart_viewed_product
					WHERE customer_id = '" . $customer_id . "'
					ORDER BY date_modified DESC, viewed_id DESC
					LIMIT " . $limit . "
				) AS keep_rows
			)");
	}

	private function trimGuestViews($session_id_sql, $limit) {
		$limit = (int)$limit;

		if ($session_id_sql === '' || $limit <= 0) {
			return;
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "dockercart_viewed_product
			WHERE customer_id IS NULL
			AND session_id = '" . $session_id_sql . "'
			AND viewed_id NOT IN (
				SELECT viewed_id FROM (
					SELECT viewed_id
					FROM " . DB_PREFIX . "dockercart_viewed_product
					WHERE customer_id IS NULL
					AND session_id = '" . $session_id_sql . "'
					ORDER BY date_modified DESC, viewed_id DESC
					LIMIT " . $limit . "
				) AS keep_rows
			)");
	}

	private function getSessionId() {
		if (method_exists($this->session, 'getId')) {
			return (string)$this->session->getId();
		}

		if (isset($this->session->data['session_id'])) {
			return (string)$this->session->data['session_id'];
		}

		return '';
	}
}
