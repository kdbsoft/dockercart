<?php
class ModelSaleReturn extends Model {
	public function addReturn($data) {
		$products = $this->normalizeReturnProducts($data['products'] ?? array());

		$first = $products ? reset($products) : array();
		$quantity = 0;

		foreach ($products as $product) {
			$quantity += (int)$product['quantity'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "return` SET
			order_id = '" . (int)$data['order_id'] . "',
			type = '" . $this->db->escape($data['type'] ?? 'full') . "',
			product_id = '" . (int)($first['product_id'] ?? 0) . "',
			customer_id = '" . (int)$data['customer_id'] . "',
			firstname = '" . $this->db->escape($data['firstname']) . "',
			lastname = '" . $this->db->escape($data['lastname']) . "',
			email = '" . $this->db->escape($data['email']) . "',
			telephone = '" . $this->db->escape($data['telephone']) . "',
			product = '" . $this->db->escape($first['name'] ?? '') . "',
			model = '" . $this->db->escape($first['model'] ?? '') . "',
			quantity = '" . (int)$quantity . "',
			amount = '" . (float)($data['amount'] ?? 0) . "',
			opened = '" . (int)($data['opened'] ?? 0) . "',
			return_reason_id = '" . (int)$data['return_reason_id'] . "',
			return_action_id = '" . (int)($data['return_action_id'] ?? 0) . "',
			return_status_id = '" . (int)$data['return_status_id'] . "',
			comment = '" . $this->db->escape($data['comment']) . "',
			date_ordered = '" . $this->db->escape($data['date_ordered']) . "',
			date_added = NOW(),
			date_modified = NOW()");

		$return_id = $this->db->getLastId();

		$this->addReturnProducts($return_id, $products);

		return $return_id;
	}

	public function editReturn($return_id, $data) {
		$products = $this->normalizeReturnProducts($data['products'] ?? array());

		$first = $products ? reset($products) : array();
		$quantity = 0;

		foreach ($products as $product) {
			$quantity += (int)$product['quantity'];
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "return` SET
			order_id = '" . (int)$data['order_id'] . "',
			type = '" . $this->db->escape($data['type'] ?? 'full') . "',
			product_id = '" . (int)($first['product_id'] ?? 0) . "',
			customer_id = '" . (int)$data['customer_id'] . "',
			firstname = '" . $this->db->escape($data['firstname']) . "',
			lastname = '" . $this->db->escape($data['lastname']) . "',
			email = '" . $this->db->escape($data['email']) . "',
			telephone = '" . $this->db->escape($data['telephone']) . "',
			product = '" . $this->db->escape($first['name'] ?? '') . "',
			model = '" . $this->db->escape($first['model'] ?? '') . "',
			quantity = '" . (int)$quantity . "',
			amount = '" . (float)($data['amount'] ?? 0) . "',
			opened = '" . (int)($data['opened'] ?? 0) . "',
			return_reason_id = '" . (int)$data['return_reason_id'] . "',
			return_action_id = '" . (int)($data['return_action_id'] ?? 0) . "',
			comment = '" . $this->db->escape($data['comment']) . "',
			date_ordered = '" . $this->db->escape($data['date_ordered']) . "',
			date_modified = NOW()
			WHERE return_id = '" . (int)$return_id . "'");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "return_product` WHERE return_id = '" . (int)$return_id . "'");

		$this->addReturnProducts($return_id, $products);
	}

	protected function normalizeReturnProducts($products) {
		$normalized = array();

		foreach ((array)$products as $product) {
			$quantity = (int)($product['quantity'] ?? 0);

			if ($quantity < 1) {
				continue;
			}

			$price = (float)($product['price'] ?? 0);
			$total = (float)($product['total'] ?? 0);

			if ($total <= 0 && $price > 0) {
				$total = $price * $quantity;
			}

			$normalized[] = array(
				'order_product_id' => (int)($product['order_product_id'] ?? 0),
				'product_id'       => (int)($product['product_id'] ?? 0),
				'variant_id'       => (int)($product['variant_id'] ?? 0),
				'name'             => (string)($product['name'] ?? ''),
				'model'            => (string)($product['model'] ?? ''),
				'quantity'         => $quantity,
				'price'            => $price,
				'total'            => $total,
			);
		}

		return $normalized;
	}

	protected function addReturnProducts($return_id, $products) {
		foreach ($products as $product) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "return_product` SET
				return_id = '" . (int)$return_id . "',
				order_product_id = '" . (int)$product['order_product_id'] . "',
				product_id = '" . (int)$product['product_id'] . "',
				variant_id = '" . (int)$product['variant_id'] . "',
				name = '" . $this->db->escape($product['name']) . "',
				model = '" . $this->db->escape($product['model']) . "',
				quantity = '" . (int)$product['quantity'] . "',
				price = '" . (float)$product['price'] . "',
				total = '" . (float)$product['total'] . "'");
		}
	}

	public function deleteReturn($return_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "return` WHERE `return_id` = '" . (int)$return_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "return_product` WHERE `return_id` = '" . (int)$return_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "return_history` WHERE `return_id` = '" . (int)$return_id . "'");
	}

	public function getReturn($return_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT CONCAT(c.firstname, ' ', c.lastname) FROM " . DB_PREFIX . "customer c WHERE c.customer_id = r.customer_id) AS customer, (SELECT rs.name FROM " . DB_PREFIX . "return_status rs WHERE rs.return_status_id = r.return_status_id AND rs.language_id = '" . (int)$this->config->get('config_language_id') . "') AS return_status FROM `" . DB_PREFIX . "return` r WHERE r.return_id = '" . (int)$return_id . "'");

		return $query->row;
	}

	public function getReturnProducts($return_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "return_product` WHERE return_id = '" . (int)$return_id . "' ORDER BY return_product_id ASC");

		return $query->rows;
	}

	public function getReturns($data = array()) {
		$sql = "SELECT *, CONCAT(r.firstname, ' ', r.lastname) AS customer, (SELECT rs.name FROM " . DB_PREFIX . "return_status rs WHERE rs.return_status_id = r.return_status_id AND rs.language_id = '" . (int)$this->config->get('config_language_id') . "') AS return_status FROM `" . DB_PREFIX . "return` r";

		$implode = array();

		if (!empty($data['filter_return_id'])) {
			$implode[] = "r.return_id = '" . (int)$data['filter_return_id'] . "'";
		}

		if (!empty($data['filter_order_id'])) {
			$implode[] = "r.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(r.firstname, ' ', r.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_product'])) {
			$implode[] = "r.product = '" . $this->db->escape($data['filter_product']) . "'";
		}

		if (!empty($data['filter_model'])) {
			$implode[] = "r.model = '" . $this->db->escape($data['filter_model']) . "'";
		}

		if (!empty($data['filter_type'])) {
			$implode[] = "r.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (!empty($data['filter_return_status_id'])) {
			$implode[] = "r.return_status_id = '" . (int)$data['filter_return_status_id'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$implode[] = "DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$implode[] = "DATE(r.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'r.return_id',
			'r.order_id',
			'customer',
			'r.product',
			'r.model',
			'r.type',
			'status',
			'r.date_added',
			'r.date_modified'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY r.return_id";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalReturns($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "return` r";

		$implode = array();

		if (!empty($data['filter_return_id'])) {
			$implode[] = "r.return_id = '" . (int)$data['filter_return_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$implode[] = "CONCAT(r.firstname, ' ', r.lastname) LIKE '" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_order_id'])) {
			$implode[] = "r.order_id = '" . $this->db->escape($data['filter_order_id']) . "'";
		}

		if (!empty($data['filter_product'])) {
			$implode[] = "r.product = '" . $this->db->escape($data['filter_product']) . "'";
		}

		if (!empty($data['filter_model'])) {
			$implode[] = "r.model = '" . $this->db->escape($data['filter_model']) . "'";
		}

		if (!empty($data['filter_type'])) {
			$implode[] = "r.type = '" . $this->db->escape($data['filter_type']) . "'";
		}

		if (!empty($data['filter_return_status_id'])) {
			$implode[] = "r.return_status_id = '" . (int)$data['filter_return_status_id'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$implode[] = "DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$implode[] = "DATE(r.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if ($implode) {
			$sql .= " WHERE " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalReturnsByReturnStatusId($return_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "return` WHERE return_status_id = '" . (int)$return_status_id . "'");

		return $query->row['total'];
	}

	public function getTotalReturnsExcludingStatuses($exclude_status_ids = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "return`";

		if ($exclude_status_ids) {
			$implode = array();
			foreach ($exclude_status_ids as $status_id) {
				$implode[] = (int)$status_id;
			}
			$sql .= " WHERE return_status_id NOT IN (" . implode(',', $implode) . ")";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalReturnsByReturnReasonId($return_reason_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "return` WHERE return_reason_id = '" . (int)$return_reason_id . "'");

		return $query->row['total'];
	}

	public function getTotalReturnsByReturnActionId($return_action_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "return` WHERE return_action_id = '" . (int)$return_action_id . "'");

		return $query->row['total'];
	}

	public function addReturnHistory($return_id, $return_status_id, $comment, $notify, $restock = true) {
		$return_info = $this->getReturn($return_id);
		$previous_status = $return_info ? (int)$return_info['return_status_id'] : 0;

		$this->db->query("UPDATE `" . DB_PREFIX . "return` SET `return_status_id` = '" . (int)$return_status_id . "', date_modified = NOW() WHERE return_id = '" . (int)$return_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "return_history` SET `return_id` = '" . (int)$return_id . "', return_status_id = '" . (int)$return_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape(strip_tags($comment)) . "', date_added = NOW()");

		// Completion of the return (status 3 = Complete): restock the returned
		// items, refund the money when requested, and move a full return's
		// order to the Refunded order status.
		if ((int)$return_status_id == 3 && $previous_status != 3 && $return_info) {
			$this->completeReturn($return_info, $restock);
		}
	}

	protected function completeReturn($return_info, $restock = true) {
		$return_id = (int)$return_info['return_id'];
		$order_id = (int)$return_info['order_id'];
		$products = $this->getReturnProducts($return_id);

		// Restock
		if ($restock) {
			if ($this->config->get('config_warehouse_enabled')) {
				$warehouse = new \DockercartWarehouse($this->registry);
				$default_warehouse_id = $warehouse->getDefaultWarehouseId();

				foreach ($products as $product) {
					$warehouse_id = $default_warehouse_id;

					// Return to the warehouse the original order line came from.
					if (!empty($product['order_product_id'])) {
						$op = $this->db->query("SELECT `warehouse_id` FROM `" . DB_PREFIX . "order_product` WHERE `order_product_id` = '" . (int)$product['order_product_id'] . "'");

						if ($op->num_rows && (int)$op->row['warehouse_id'] > 0) {
							$warehouse_id = (int)$op->row['warehouse_id'];
						}
					}

					$warehouse->adjustStock(
						$warehouse_id,
						(int)$product['product_id'],
						(int)$product['variant_id'],
						(float)$product['quantity'],
						'return',
						['order_id' => (int)$order_id, 'reference' => 'return-' . (int)$return_id]
					);
				}
			} else {
				foreach ($products as $product) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = (quantity + " . (float)$product['quantity'] . ") WHERE product_id = '" . (int)$product['product_id'] . "' AND subtract = '1'");

					if ((int)$product['variant_id'] > 0) {
						$this->db->query("UPDATE `" . DB_PREFIX . "product_variant` SET quantity = (quantity + " . (float)$product['quantity'] . ") WHERE variant_id = '" . (int)$product['variant_id'] . "' AND subtract = '1'");
					}
				}
			}
		}

		$this->cache->delete('product');

		// Refund (once)
		if (!$return_info['refunded'] && (float)$return_info['amount'] > 0) {
			$this->load->model('sale/order');

			$refund_id = $this->model_sale_order->addOrderRefund($order_id, (float)$return_info['amount'], sprintf($this->language->get('text_return_refund_note'), $return_id), $return_id);

			if ($refund_id) {
				$this->db->query("UPDATE `" . DB_PREFIX . "return` SET refunded = '1' WHERE return_id = '" . (int)$return_id . "'");
			}
		}

		// A full return completes the order flow at the Refunded status
		if ($return_info['type'] == 'full' && $order_id) {
			$this->load->model('sale/order');

			$order_info = $this->model_sale_order->getOrder($order_id);

			// 134 = Refunded order status (see migration 20260801_add_order_flow.sql)
			if ($order_info && (int)$order_info['order_status_id'] != 134) {
				$this->model_sale_order->addOrderHistory($order_id, 134, sprintf($this->language->get('text_return_complete_note'), $return_id), false, true);
			}
		}
	}

	public function getReturnHistories($return_id, $start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT rh.date_added, rs.name AS status, rh.comment, rh.notify FROM " . DB_PREFIX . "return_history rh LEFT JOIN " . DB_PREFIX . "return_status rs ON rh.return_status_id = rs.return_status_id WHERE rh.return_id = '" . (int)$return_id . "' AND rs.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY rh.date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getTotalReturnHistories($return_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "return_history WHERE return_id = '" . (int)$return_id . "'");

		return $query->row['total'];
	}

	public function getTotalReturnHistoriesByReturnStatusId($return_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "return_history WHERE return_status_id = '" . (int)$return_status_id . "'");

		return $query->row['total'];
	}
}
