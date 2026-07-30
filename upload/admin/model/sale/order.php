<?php
class ModelSaleOrder extends Model {
	public function getOrder($order_id) {
		$order_query = $this->db->query("SELECT *, (SELECT CONCAT(c.firstname, ' ', c.lastname) FROM " . DB_PREFIX . "customer c WHERE c.customer_id = o.customer_id) AS customer, (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS order_status FROM `" . DB_PREFIX . "order` o WHERE o.order_id = '" . (int)$order_id . "'");

		if ($order_query->num_rows) {
			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['payment_country_id'] . "'");

			if ($country_query->num_rows) {
				$payment_iso_code_2 = $country_query->row['iso_code_2'];
				$payment_iso_code_3 = $country_query->row['iso_code_3'];
			} else {
				$payment_iso_code_2 = '';
				$payment_iso_code_3 = '';
			}

			$zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['payment_zone_id'] . "'");

			if ($zone_query->num_rows) {
				$payment_zone_code = $zone_query->row['code'];
			} else {
				$payment_zone_code = '';
			}

			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['shipping_country_id'] . "'");

			if ($country_query->num_rows) {
				$shipping_iso_code_2 = $country_query->row['iso_code_2'];
				$shipping_iso_code_3 = $country_query->row['iso_code_3'];
			} else {
				$shipping_iso_code_2 = '';
				$shipping_iso_code_3 = '';
			}

			$zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['shipping_zone_id'] . "'");

			if ($zone_query->num_rows) {
				$shipping_zone_code = $zone_query->row['code'];
			} else {
				$shipping_zone_code = '';
			}

			$reward = 0;

			$order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

			foreach ($order_product_query->rows as $product) {
				$reward += $product['reward'];
			}
			
			$this->load->model('customer/customer');

			$affiliate_info = $this->model_customer_customer->getCustomer($order_query->row['affiliate_id']);

			if ($affiliate_info) {
				$affiliate_firstname = $affiliate_info['firstname'];
				$affiliate_lastname = $affiliate_info['lastname'];
			} else {
				$affiliate_firstname = '';
				$affiliate_lastname = '';
			}

			$this->load->model('localisation/language');

			$language_info = $this->model_localisation_language->getLanguage($order_query->row['language_id']);

			if ($language_info) {
				$language_code = $language_info['code'];
			} else {
				$language_code = $this->config->get('config_language');
			}

			return array(
				'order_id'                => $order_query->row['order_id'],
				'invoice_no'              => $order_query->row['invoice_no'],
				'invoice_prefix'          => $order_query->row['invoice_prefix'],
				'store_id'                => $order_query->row['store_id'],
				'store_name'              => $order_query->row['store_name'],
				'store_url'               => $order_query->row['store_url'],
				'customer_id'             => $order_query->row['customer_id'],
				'customer'                => $order_query->row['customer'],
				'customer_group_id'       => $order_query->row['customer_group_id'],
				'firstname'               => $order_query->row['firstname'],
				'lastname'                => $order_query->row['lastname'],
				'email'                   => $order_query->row['email'],
				'telephone'               => $order_query->row['telephone'],
				'tax_number'              => $order_query->row['tax_number'],
				'custom_field'            => json_decode($order_query->row['custom_field'], true),
				'payment_firstname'       => $order_query->row['payment_firstname'],
				'payment_lastname'        => $order_query->row['payment_lastname'],
				'payment_company'         => $order_query->row['payment_company'],
				'payment_address_1'       => $order_query->row['payment_address_1'],
				'payment_address_2'       => $order_query->row['payment_address_2'],
				'payment_postcode'        => $order_query->row['payment_postcode'],
				'payment_city'            => $order_query->row['payment_city'],
				'payment_zone_id'         => $order_query->row['payment_zone_id'],
				'payment_zone'            => $order_query->row['payment_zone'],
				'payment_zone_code'       => $payment_zone_code,
				'payment_country_id'      => $order_query->row['payment_country_id'],
				'payment_country'         => $order_query->row['payment_country'],
				'payment_iso_code_2'      => $payment_iso_code_2,
				'payment_iso_code_3'      => $payment_iso_code_3,
				'payment_address_format'  => $order_query->row['payment_address_format'],
				'payment_custom_field'    => json_decode($order_query->row['payment_custom_field'], true),
				'payment_method'          => $order_query->row['payment_method'],
				'payment_code'            => $order_query->row['payment_code'],
				'shipping_firstname'      => $order_query->row['shipping_firstname'],
				'shipping_lastname'       => $order_query->row['shipping_lastname'],
				'shipping_company'        => $order_query->row['shipping_company'],
				'shipping_address_1'      => $order_query->row['shipping_address_1'],
				'shipping_address_2'      => $order_query->row['shipping_address_2'],
				'shipping_postcode'       => $order_query->row['shipping_postcode'],
				'shipping_city'           => $order_query->row['shipping_city'],
				'shipping_zone_id'        => $order_query->row['shipping_zone_id'],
				'shipping_zone'           => $order_query->row['shipping_zone'],
				'shipping_zone_code'      => $shipping_zone_code,
				'shipping_country_id'     => $order_query->row['shipping_country_id'],
				'shipping_country'        => $order_query->row['shipping_country'],
				'shipping_iso_code_2'     => $shipping_iso_code_2,
				'shipping_iso_code_3'     => $shipping_iso_code_3,
				'shipping_address_format' => $order_query->row['shipping_address_format'],
				'shipping_custom_field'   => json_decode($order_query->row['shipping_custom_field'], true),
				'shipping_method'         => $order_query->row['shipping_method'],
				'shipping_code'           => $order_query->row['shipping_code'],
				'tracking_number'         => $order_query->row['tracking_number'],
				'comment'                 => $order_query->row['comment'],
				'total'                   => $order_query->row['total'],
				'reward'                  => $reward,
				'order_status_id'         => $order_query->row['order_status_id'],
				'order_status'            => $order_query->row['order_status'],
				'affiliate_id'            => $order_query->row['affiliate_id'],
				'affiliate_firstname'     => $affiliate_firstname,
				'affiliate_lastname'      => $affiliate_lastname,
				'commission'              => $order_query->row['commission'],
				'language_id'             => $order_query->row['language_id'],
				'language_code'           => $language_code,
				'currency_id'             => $order_query->row['currency_id'],
				'currency_code'           => $order_query->row['currency_code'],
				'currency_value'          => $order_query->row['currency_value'],
				'ip'                      => $order_query->row['ip'],
				'forwarded_ip'            => $order_query->row['forwarded_ip'],
				'user_agent'              => $order_query->row['user_agent'],
				'accept_language'         => $order_query->row['accept_language'],
				'date_added'              => $order_query->row['date_added'],
				'date_modified'           => $order_query->row['date_modified']
			);
		} else {
			return;
		}
	}

	public function getOrders($data = array()) {
		$sql = "SELECT o.order_id, o.customer_id, o.payment_code, o.payment_method, o.shipping_method, CONCAT(o.firstname, ' ', o.lastname) AS customer, o.order_status_id, (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS order_status, o.shipping_code, o.tracking_number, o.total, o.currency_code, o.currency_value, o.date_added, o.date_modified FROM `" . DB_PREFIX . "order` o";

		if (!empty($data['filter_order_status'])) {
			$implode = array();

			$order_statuses = explode(',', $data['filter_order_status']);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "o.order_status_id = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE o.order_status_id > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND o.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(o.firstname, ' ', o.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(o.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(o.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND o.total = '" . (float)$data['filter_total'] . "'";
		}

		$sort_data = array(
			'o.order_id',
			'customer',
			'order_status',
			'o.date_added',
			'o.date_modified',
			'o.total'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY o.order_id";
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

	public function getOrderProducts($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

		return $query->rows;
	}

	public function getOrderOptions($order_id, $order_product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "' ORDER BY order_option_id ASC");

		return $query->rows;
	}

	public function getOrderVouchers($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_voucher WHERE order_id = '" . (int)$order_id . "'");

		return $query->rows;
	}

	public function getOrderVoucherByVoucherId($voucher_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_voucher` WHERE voucher_id = '" . (int)$voucher_id . "'");

		return $query->row;
	}

	public function getOrderTotals($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order");

		return $query->rows;
	}
	
	public function getTotalOrders($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order`";

		if (!empty($data['filter_order_status'])) {
			$implode = array();

			$order_statuses = explode(',', $data['filter_order_status']);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE order_status_id > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND total = '" . (float)$data['filter_total'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalOrdersByStoreId($store_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE store_id = '" . (int)$store_id . "'");

		return $query->row['total'];
	}

	public function getTotalOrdersByOrderStatusId($order_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE order_status_id = '" . (int)$order_status_id . "' AND order_status_id > '0'");

		return $query->row['total'];
	}

	public function getTotalOrdersByProcessingStatus() {
		$implode = array();

		$order_statuses = $this->config->get('config_processing_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE " . implode(" OR ", $implode));

			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function getTotalOrdersByCompleteStatus() {
		$implode = array();

		$order_statuses = $this->config->get('config_complete_status');

		foreach ($order_statuses as $order_status_id) {
			$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
		}

		if ($implode) {
			$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE " . implode(" OR ", $implode) . "");

			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function getTotalOrdersByLanguageId($language_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE language_id = '" . (int)$language_id . "' AND order_status_id > '0'");

		return $query->row['total'];
	}

	public function getTotalOrdersByCurrencyId($currency_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE currency_id = '" . (int)$currency_id . "' AND order_status_id > '0'");

		return $query->row['total'];
	}
	
	public function getTotalSales($data = array()) {
		$sql = "SELECT SUM(total) AS total FROM `" . DB_PREFIX . "order`";

		if (!empty($data['filter_order_status'])) {
			$implode = array();

			$order_statuses = explode(',', $data['filter_order_status']);

			foreach ($order_statuses as $order_status_id) {
				$implode[] = "order_status_id = '" . (int)$order_status_id . "'";
			}

			if ($implode) {
				$sql .= " WHERE (" . implode(" OR ", $implode) . ")";
			}
		} elseif (isset($data['filter_order_status_id']) && $data['filter_order_status_id'] !== '') {
			$sql .= " WHERE order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		} else {
			$sql .= " WHERE order_status_id > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$sql .= " AND CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$sql .= " AND total = '" . (float)$data['filter_total'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
	
	public function getOrderHistories($order_id, $start = 0, $limit = 10) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $this->db->query("SELECT oh.date_added, os.name AS status, oh.comment, oh.notify FROM " . DB_PREFIX . "order_history oh LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id WHERE oh.order_id = '" . (int)$order_id . "' AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY oh.date_added DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getTotalOrderHistories($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['total'];
	}

	public function getTotalOrderHistoriesByOrderStatusId($order_status_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order_history WHERE order_status_id = '" . (int)$order_status_id . "'");

		return $query->row['total'];
	}
	
	public function getEmailsByProductsOrdered($products, $start, $end) {
		$implode = array();

		foreach ($products as $product_id) {
			$implode[] = "op.product_id = '" . (int)$product_id . "'";
		}

		$query = $this->db->query("SELECT DISTINCT email FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_product op ON (o.order_id = op.order_id) WHERE (" . implode(" OR ", $implode) . ") AND o.order_status_id <> '0' LIMIT " . (int)$start . "," . (int)$end);

		return $query->rows;
	}

	public function getTotalEmailsByProductsOrdered($products) {
		$implode = array();

		foreach ($products as $product_id) {
			$implode[] = "op.product_id = '" . (int)$product_id . "'";
		}

		$query = $this->db->query("SELECT COUNT(DISTINCT email) AS total FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_product op ON (o.order_id = op.order_id) WHERE (" . implode(" OR ", $implode) . ") AND o.order_status_id <> '0'");

		return $query->row['total'];
	}

	public function updateOrderQuick($order_id, $data = array()) {
		$allowed = array(
			'firstname',
			'lastname',
			'email',
			'telephone',
			'tax_number',
			'payment_method',
			'payment_code',
			'shipping_method',
			'shipping_code',
			'payment_firstname',
			'payment_lastname',
			'payment_company',
			'payment_address_1',
			'payment_address_2',
			'payment_city',
			'payment_postcode',
			'payment_zone',
			'payment_zone_id',
			'payment_country',
			'payment_country_id',
			'shipping_firstname',
			'shipping_lastname',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_postcode',
			'shipping_zone',
			'shipping_zone_id',
			'shipping_country',
			'shipping_country_id',
			'tracking_number',
			'comment'
		);

		$set = array();

		foreach ($allowed as $field) {
			if (array_key_exists($field, $data)) {
				$set[] = "`" . $field . "` = '" . $this->db->escape($data[$field]) . "'";
			}
		}

		if (!$set) {
			return false;
		}

		$set[] = "`date_modified` = NOW()";

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET " . implode(', ', $set) . " WHERE order_id = '" . (int)$order_id . "'");

		return true;
	}

	public function applyLineDiscounts($order_id, $discounts = array()) {
		$this->ensureOrderProductDiscountTable();

		$order_products_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "' ORDER BY order_product_id ASC");

		if (!$order_products_query->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product_discount` WHERE order_id = '" . (int)$order_id . "'");
			return;
		}

		$stored_discounts = $this->getOrderProductDiscounts($order_id);

		$delta_subtotal = 0.0;
		$delta_tax = 0.0;
		$processed_order_product_ids = array();

		foreach ($order_products_query->rows as $index => $product) {
			$order_product_id = (int)$product['order_product_id'];
			$processed_order_product_ids[] = $order_product_id;

			$old_percent = isset($stored_discounts[$order_product_id]) ? (float)$stored_discounts[$order_product_id] : 0.0;
			$new_percent = isset($discounts[$index]) ? (float)$discounts[$index] : 0.0;

			if ($new_percent < 0) {
				$new_percent = 0;
			}

			if ($new_percent > 100) {
				$new_percent = 100;
			}

			if ($old_percent < 0) {
				$old_percent = 0;
			}

			if ($old_percent > 100) {
				$old_percent = 100;
			}

			$qty = (int)$product['quantity'];
			$old_price = (float)$product['price'];
			$old_tax = (float)$product['tax'];

			$base_price = $old_price;
			$base_tax = $old_tax;

			if ($old_percent > 0 && $old_percent < 100) {
				$factor = 1 - ($old_percent / 100);

				if ($factor > 0) {
					$base_price = round($old_price / $factor, 4);
					$base_tax = round($old_tax / $factor, 4);
				}
			}

			$new_price = round($base_price * (1 - ($new_percent / 100)), 4);
			$new_tax = round($base_tax * (1 - ($new_percent / 100)), 4);
			$new_total = round($new_price * $qty, 4);

			$delta_subtotal += ($new_price - $old_price) * $qty;
			$delta_tax += ($new_tax - $old_tax) * $qty;

			$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET price = '" . (float)$new_price . "', tax = '" . (float)$new_tax . "', total = '" . (float)$new_total . "' WHERE order_product_id = '" . $order_product_id . "'");

			if ($new_percent > 0) {
				$this->db->query("REPLACE INTO `" . DB_PREFIX . "order_product_discount` SET order_product_id = '" . $order_product_id . "', order_id = '" . (int)$order_id . "', discount_percent = '" . (float)$new_percent . "', date_modified = NOW()");
			} else {
				$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product_discount` WHERE order_product_id = '" . $order_product_id . "'");
			}
		}

		if ($processed_order_product_ids) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product_discount` WHERE order_id = '" . (int)$order_id . "' AND order_product_id NOT IN (" . implode(',', array_map('intval', $processed_order_product_ids)) . ")");
		} else {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product_discount` WHERE order_id = '" . (int)$order_id . "'");
		}

		$delta_subtotal = round($delta_subtotal, 4);
		$delta_tax = round($delta_tax, 4);

		if (abs($delta_subtotal) < 0.0001 && abs($delta_tax) < 0.0001) {
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
			return;
		}

		$sub_total_query = $this->db->query("SELECT order_total_id, value FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'sub_total' ORDER BY sort_order ASC LIMIT 1");

		if ($sub_total_query->num_rows) {
			$new_subtotal = (float)$sub_total_query->row['value'] + $delta_subtotal;

			if ($new_subtotal < 0) {
				$new_subtotal = 0;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_subtotal . "' WHERE order_total_id = '" . (int)$sub_total_query->row['order_total_id'] . "'");
		}

		$tax_query = $this->db->query("SELECT order_total_id, value FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'tax' ORDER BY sort_order ASC LIMIT 1");

		if ($tax_query->num_rows) {
			$new_tax_total = (float)$tax_query->row['value'] + $delta_tax;

			if ($new_tax_total < 0) {
				$new_tax_total = 0;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_tax_total . "' WHERE order_total_id = '" . (int)$tax_query->row['order_total_id'] . "'");
		}

		$total_query = $this->db->query("SELECT order_total_id, value FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'total' ORDER BY sort_order DESC LIMIT 1");

		if ($total_query->num_rows) {
			$new_order_total = (float)$total_query->row['value'] + $delta_subtotal + $delta_tax;

			if ($new_order_total < 0) {
				$new_order_total = 0;
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_order_total . "' WHERE order_total_id = '" . (int)$total_query->row['order_total_id'] . "'");
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . (float)$new_order_total . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
		} else {
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
		}
	}

	public function getOrderProductDiscounts($order_id) {
		$this->ensureOrderProductDiscountTable();

		$discounts = array();

		$query = $this->db->query("SELECT order_product_id, discount_percent FROM `" . DB_PREFIX . "order_product_discount` WHERE order_id = '" . (int)$order_id . "'");

		foreach ($query->rows as $row) {
			$discounts[(int)$row['order_product_id']] = (float)$row['discount_percent'];
		}

		return $discounts;
	}

	private function ensureOrderProductDiscountTable() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_product_discount` (
			`order_product_id` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
			`discount_percent` decimal(15,4) NOT NULL DEFAULT '0.0000',
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`order_product_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8");
	}

	public function updateOrderField($order_id, $field, $value) {
		$allowed = array(
			'firstname', 'lastname', 'email', 'telephone', 'tax_number',
			'payment_method', 'payment_code', 'shipping_method', 'shipping_code',
			'payment_firstname', 'payment_lastname', 'payment_company',
			'payment_address_1', 'payment_address_2', 'payment_city',
			'payment_postcode', 'payment_zone', 'payment_zone_id',
			'payment_country', 'payment_country_id',
			'shipping_firstname', 'shipping_lastname', 'shipping_company',
			'shipping_address_1', 'shipping_address_2', 'shipping_city',
			'shipping_postcode', 'shipping_zone', 'shipping_zone_id',
			'shipping_country', 'shipping_country_id',
			'tracking_number', 'comment'
		);

		if (!in_array($field, $allowed)) {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `" . $field . "` = '" . $this->db->escape((string)$value) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

		return true;
	}

	public function updateOrderProductQuantity($order_product_id, $order_id, $quantity) {
		$product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "'");

		if (!$product_query->num_rows) {
			return false;
		}

		$product = $product_query->row;
		$quantity = (float)$quantity;

		if ($quantity < 0) {
			$quantity = 0;
		}

		$unit_price = (float)$product['price'];
		$unit_tax = (float)$product['tax'];

		$new_total = round($unit_price * $quantity, 4);
		$new_tax = round($unit_tax * $quantity, 4);

		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET quantity = '" . (float)$quantity . "', total = '" . (float)$new_total . "', tax = '" . (float)$new_tax . "' WHERE order_product_id = '" . (int)$order_product_id . "'");

		return true;
	}

	public function updateOrderProductPrice($order_product_id, $order_id, $price) {
		$product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "'");

		if (!$product_query->num_rows) {
			return false;
		}

		$product = $product_query->row;
		$quantity = (float)$product['quantity'];
		$price = (float)$price;

		if ($price < 0) {
			$price = 0;
		}

		$new_total = round($price * $quantity, 4);

		$this->db->query("UPDATE `" . DB_PREFIX . "order_product` SET price = '" . (float)$price . "', total = '" . (float)$new_total . "' WHERE order_product_id = '" . (int)$order_product_id . "'");

		return true;
	}

	public function addProductToOrder($order_id, $product_id, $quantity = 1, $options = array()) {
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return false;
		}

		$quantity = max(1, (int)$quantity);

		$price = (float)$product_info['price'];
		$tax = 0.0;

		if ($product_info['tax_class_id']) {
			$tax_rates = $this->tax->getRates($price, $product_info['tax_class_id']);
			foreach ($tax_rates as $tax_rate) {
				$tax += $tax_rate['amount'];
			}
		}

		$total = round($price * $quantity, 4);
		$tax_total = round($tax * $quantity, 4);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET
			order_id = '" . (int)$order_id . "',
			product_id = '" . (int)$product_id . "',
			name = '" . $this->db->escape($product_info['name']) . "',
			model = '" . $this->db->escape($product_info['model']) . "',
			quantity = '" . (float)$quantity . "',
			price = '" . (float)$price . "',
			total = '" . (float)$total . "',
			tax = '" . (float)$tax_total . "',
			reward = '" . (int)($product_info['reward'] ?? 0) . "',
			variant_id = '0',
			variant_sku = ''
		");

		$order_product_id = $this->db->getLastId();

		if ($options) {
			foreach ($options as $option) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "order_option` SET
					order_id = '" . (int)$order_id . "',
					order_product_id = '" . (int)$order_product_id . "',
					product_option_id = '" . (int)$option['product_option_id'] . "',
					product_option_value_id = '" . (int)$option['product_option_value_id'] . "',
					name = '" . $this->db->escape($option['name']) . "',
					value = '" . $this->db->escape($option['value']) . "',
					type = '" . $this->db->escape($option['type']) . "'
				");
			}
		}

		return $order_product_id;
	}

	public function removeProductFromOrder($order_product_id, $order_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "'");

		return true;
	}

	public function recalculateOrderTotals($order_id) {
		$products_query = $this->db->query("SELECT SUM(total) AS subtotal, SUM(tax) AS total_tax FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");

		$new_subtotal = (float)$products_query->row['subtotal'];
		$new_tax = (float)$products_query->row['total_tax'];

		if ($new_subtotal < 0) {
			$new_subtotal = 0;
		}

		if ($new_tax < 0) {
			$new_tax = 0;
		}

		$totals = $this->getOrderTotals($order_id);

		$non_dynamic_total = 0.0;

		foreach ($totals as $total) {
			if ($total['code'] == 'sub_total') {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_subtotal . "' WHERE order_total_id = '" . (int)$total['order_total_id'] . "'");
			} elseif ($total['code'] == 'tax') {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_tax . "' WHERE order_total_id = '" . (int)$total['order_total_id'] . "'");
			} elseif ($total['code'] != 'total') {
				$non_dynamic_total += (float)$total['value'];
			}
		}

		$new_total = round($new_subtotal + $non_dynamic_total, 4);

		foreach ($totals as $total) {
			if ($total['code'] == 'total') {
				$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET value = '" . (float)$new_total . "' WHERE order_total_id = '" . (int)$total['order_total_id'] . "'");
				break;
			}
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . (float)$new_total . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

		return true;
	}

	public function getOrderTimeline($order_id, $start = 0, $limit = 20) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$query = $this->db->query("SELECT oh.order_history_id, oh.order_status_id, oh.notify, oh.comment, oh.date_added,
			os.name AS status_name
			FROM " . DB_PREFIX . "order_history oh
			LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "'
			WHERE oh.order_id = '" . (int)$order_id . "'
			ORDER BY oh.date_added DESC, oh.order_history_id DESC
			LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function countOrderTimeline($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$order_id . "'");

		return (int)$query->row['total'];
	}

	public function deleteOrder($order_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_voucher` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_history` WHERE order_id = '" . (int)$order_id . "'");
		$this->db->query("DELETE `or`, ort FROM `" . DB_PREFIX . "order_recurring` `or`, `" . DB_PREFIX . "order_recurring_transaction` `ort` WHERE order_id = '" . (int)$order_id . "' AND ort.order_recurring_id = `or`.order_recurring_id");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE order_id = '" . (int)$order_id . "'");

		$order_vouchers = $this->getOrderVouchers($order_id);

		foreach ($order_vouchers as $order_voucher) {
			$this->db->query("UPDATE `" . DB_PREFIX . "voucher` SET `status` = '0' WHERE voucher_id = '" . (int)$order_voucher['voucher_id'] . "'");
		}
	}

	public function addOrderHistory($order_id, $order_status_id, $comment = '', $notify = false, $override = false) {
		$order_info = $this->getOrder($order_id);

		if ($order_info) {
			$processing_statuses = (array)$this->config->get('config_processing_status');
			$complete_statuses = (array)$this->config->get('config_complete_status');

			// Transition from non-proc/complete to proc/complete → subtract stock, add affiliate commission
			if (!in_array($order_info['order_status_id'], array_merge($processing_statuses, $complete_statuses)) && in_array($order_status_id, array_merge($processing_statuses, $complete_statuses))) {
				$order_products = $this->getOrderProducts($order_id);

				foreach ($order_products as $order_product) {
					$this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (float)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

					if ((int)$order_product['variant_id'] > 0) {
						$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = (quantity - " . (float)$order_product['quantity'] . ") WHERE variant_id = '" . (int)$order_product['variant_id'] . "' AND subtract = '1'");
					}
				}

				if ($order_info['affiliate_id'] && $this->config->get('config_affiliate_auto')) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_transaction` SET customer_id = '" . (int)$order_info['affiliate_id'] . "', order_id = '" . (int)$order_id . "', description = '" . $this->db->escape('Order #' . $order_id) . "', amount = '" . (float)$order_info['commission'] . "', date_added = NOW()");
				}
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");

			// Reversal from proc/complete to non-proc/complete → restock, remove affiliate commission
			if (in_array($order_info['order_status_id'], array_merge($processing_statuses, $complete_statuses)) && !in_array($order_status_id, array_merge($processing_statuses, $complete_statuses))) {
				$order_products = $this->getOrderProducts($order_id);

				foreach ($order_products as $order_product) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = (quantity + " . (float)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

					if ((int)$order_product['variant_id'] > 0) {
						$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = (quantity + " . (float)$order_product['quantity'] . ") WHERE variant_id = '" . (int)$order_product['variant_id'] . "' AND subtract = '1'");
					}
				}

				if ($order_info['affiliate_id']) {
					$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE order_id = '" . (int)$order_id . "'");
				}
			}

			$this->cache->delete('product');
		}
	}

	public function addOrderNote($order_id, $comment, $notify = false) {
		$query = $this->db->query("SELECT order_status_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		if (!$query->num_rows) {
			return false;
		}

		$current_status_id = (int)$query->row['order_status_id'];

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET
			order_id = '" . (int)$order_id . "',
			order_status_id = '0',
			notify = '" . (int)$notify . "',
			comment = '" . $this->db->escape($comment) . "',
			date_added = NOW()
		");

		return true;
	}
}
