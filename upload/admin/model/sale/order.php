<?php
class ModelSaleOrder extends Model {
	public function updateInvoiceNo($order_id) {
		$query = $this->db->query("SELECT invoice_prefix, invoice_no FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		if (!$query->num_rows) {
			return 0;
		}

		if ((int)$query->row['invoice_no'] > 0) {
			return (int)$query->row['invoice_no'];
		}

		$invoice_prefix = $query->row['invoice_prefix'];
		if ($invoice_prefix === '') {
			$invoice_prefix = 'INV-';
		}

		$lock_name = 'oc_invoice_' . md5($invoice_prefix);
		$lock_query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lock_name) . "', 10) AS acquired");

		if (!$lock_query->num_rows || !$lock_query->row['acquired']) {
			return 0;
		}

		try {
			$next_query = $this->db->query("SELECT MAX(invoice_no) AS next FROM `" . DB_PREFIX . "order` WHERE invoice_prefix = '" . $this->db->escape($invoice_prefix) . "' AND invoice_no > 0");

			$invoice_no = (int)$next_query->row['next'] + 1;

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET invoice_no = '" . $invoice_no . "' WHERE order_id = '" . (int)$order_id . "'");
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lock_name) . "')");
		}

		return $invoice_no;
	}

	public function createOrder() {
		$store_id = 0;
		$store_name = $this->config->get('config_name') ? $this->config->get('config_name') : 'DockerCart';
		$store_url = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;

		$currency_code = $this->config->get('config_currency');
		$currency_id = $this->currency->getId($currency_code);
		$currency_value = 1.0;

		$flow_steps = (array)$this->config->get('config_order_flow_steps');

		if (!empty($flow_steps)) {
			$order_status_id = (int)reset($flow_steps);
		} else {
			$processing_statuses = (array)$this->config->get('config_processing_status');
			$order_status_id = !empty($processing_statuses) ? (int)$processing_statuses[0] : 0;
		}

		$language_id = (int)$this->config->get('config_language_id');
		if (!$language_id) {
			$language_id = 1;
		}

		$invoice_prefix = $this->config->get('config_invoice_prefix');
		if (!$invoice_prefix) {
			$invoice_prefix = 'INV-';
		}

		$ip = isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : '';
		$user_agent = isset($this->request->server['HTTP_USER_AGENT']) ? substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '';
		$accept_language = isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? substr($this->request->server['HTTP_ACCEPT_LANGUAGE'], 0, 255) : '';

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET
			invoice_prefix = '" . $this->db->escape($invoice_prefix) . "',
			store_id = '" . (int)$store_id . "',
			store_name = '" . $this->db->escape($store_name) . "',
			store_url = '" . $this->db->escape($store_url) . "',
			customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "',
			custom_field = '[]',
			payment_custom_field = '[]',
			shipping_custom_field = '[]',
			order_status_id = '" . (int)$order_status_id . "',
			affiliate_id = '0',
			commission = '0',
			marketing_id = '0',
			language_id = '" . (int)$language_id . "',
			currency_id = '" . (int)$currency_id . "',
			currency_code = '" . $this->db->escape($currency_code) . "',
			currency_value = '" . (float)$currency_value . "',
			ip = '" . $this->db->escape($ip) . "',
			forwarded_ip = '',
			user_agent = '" . $this->db->escape($user_agent) . "',
			accept_language = '" . $this->db->escape($accept_language) . "',
			date_added = NOW(),
			date_modified = NOW()
		");

		$order_id = $this->db->getLastId();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET order_id = '" . (int)$order_id . "', code = 'sub_total', title = '" . $this->db->escape($this->language->get('text_sub_total')) . "', `value` = '0', sort_order = '1'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET order_id = '" . (int)$order_id . "', code = 'shipping', title = '" . $this->db->escape($this->language->get('text_shipping')) . "', `value` = '0', sort_order = '3'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET order_id = '" . (int)$order_id . "', code = 'total', title = '" . $this->db->escape($this->language->get('text_total')) . "', `value` = '0', sort_order = '9'");

		$this->addOrderHistory($order_id, $order_status_id, '', false);

		return $order_id;
	}

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
				'paid_amount'             => $order_query->row['paid_amount'],
				'reward'                  => $reward,
				'reward_awarded'          => (int)$order_query->row['reward_awarded'],
				'reward_revoked_points'   => (int)$order_query->row['reward_revoked_points'],
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
		$sql = "SELECT o.order_id, o.customer_id, o.payment_code, o.payment_method, o.shipping_method, CONCAT(o.firstname, ' ', o.lastname) AS customer, o.order_status_id, (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS order_status, o.shipping_code, o.tracking_number, o.total, o.paid_amount, o.currency_code, o.currency_value, o.date_added, o.date_modified FROM `" . DB_PREFIX . "order` o";

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
		} elseif (!empty($data['filter_order_status_exclude'])) {
			$exclude = array_map('intval', (array)$data['filter_order_status_exclude']);

			if ($exclude) {
				$sql .= " WHERE o.order_status_id NOT IN (" . implode(',', $exclude) . ")";
			} else {
				$sql .= " WHERE o.order_status_id > '0'";
			}
		} else {
			$sql .= " WHERE o.order_status_id > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND o.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$filter_customer = (string)$data['filter_customer'];
			$phone_digits = preg_replace('/\D+/', '', $filter_customer);

			$customer_conditions = array(
				"CONCAT(o.firstname, ' ', o.lastname) LIKE '%" . $this->db->escape($filter_customer) . "%'",
				"o.email LIKE '%" . $this->db->escape($filter_customer) . "%'"
			);

			if ($phone_digits !== '') {
				$customer_conditions[] = "REGEXP_REPLACE(o.telephone, '[^0-9]', '') LIKE '%" . $this->db->escape($phone_digits) . "%'";
			}

			$sql .= " AND (" . implode(' OR ', $customer_conditions) . ")";
		}

		if (!empty($data['filter_date_added'])) {
			if (!empty($data['filter_date_added_operator'])) {
				$operator = $this->getComparisonOperator((string)$data['filter_date_added_operator']);
				$sql .= " AND DATE(o.date_added) " . $operator . " DATE('" . $this->db->escape($data['filter_date_added']) . "')";
			} else {
				$sql .= " AND DATE(o.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
			}
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(o.date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$operator = !empty($data['filter_total_operator']) ? $this->getComparisonOperator((string)$data['filter_total_operator']) : '=';
			$sql .= " AND o.total " . $operator . " '" . (float)$data['filter_total'] . "'";
		}

		if (isset($data['filter_total_min']) && $data['filter_total_min'] !== '') {
			$sql .= " AND o.total >= '" . (float)$data['filter_total_min'] . "'";
		}

		if (isset($data['filter_total_max']) && $data['filter_total_max'] !== '') {
			$sql .= " AND o.total <= '" . (float)$data['filter_total_max'] . "'";
		}

		if (!empty($data['filter_payment_method'])) {
			$sql .= " AND o.payment_method LIKE '%" . $this->db->escape((string)$data['filter_payment_method']) . "%'";
		}

		if (!empty($data['filter_shipping_method'])) {
			$sql .= " AND o.shipping_method LIKE '%" . $this->db->escape((string)$data['filter_shipping_method']) . "%'";
		}

		if (!empty($data['filter_date_preset'])) {
			$sql .= $this->getDatePresetSql((string)$data['filter_date_preset'], 'o.date_added');
		}

		if (!empty($data['filter_payment_status'])) {
			$sql .= $this->getPaymentStatusFilterSql((string)$data['filter_payment_status'], 'o');
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

	private function getPaymentStatusFilterSql(string $status, string $alias = ''): string {
		$prefix = $alias ? $alias . '.' : '';

		switch ($status) {
			case 'unpaid':
				return " AND " . $prefix . "paid_amount <= 0";
			case 'partial':
				return " AND " . $prefix . "paid_amount > 0 AND " . $prefix . "paid_amount < " . $prefix . "total";
			case 'paid':
				return " AND " . $prefix . "paid_amount >= " . $prefix . "total";
			case 'overpaid':
				return " AND " . $prefix . "paid_amount > " . $prefix . "total AND " . $prefix . "total > 0";
			default:
				return "";
		}
	}

	private function getComparisonOperator(string $operator): string {
		switch ($operator) {
			case 'gt':
				return '>';
			case 'gte':
				return '>=';
			case 'lt':
				return '<';
			case 'lte':
				return '<=';
			case 'ne':
				return '<>';
			default:
				return '=';
		}
	}

	/**
	 * Build SQL for a date range preset (today, yesterday, this week, ...).
	 */
	private function getDatePresetSql(string $preset, string $column): string {
		switch ($preset) {
			case 'today':
				return " AND DATE(" . $column . ") = CURDATE()";
			case 'yesterday':
				return " AND DATE(" . $column . ") = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
			case 'this_week':
				return " AND YEARWEEK(" . $column . ", 1) = YEARWEEK(CURDATE(), 1)";
			case 'this_month':
				return " AND YEAR(" . $column . ") = YEAR(CURDATE()) AND MONTH(" . $column . ") = MONTH(CURDATE())";
			case 'this_year':
				return " AND YEAR(" . $column . ") = YEAR(CURDATE())";
			default:
				return "";
		}
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
		} elseif (!empty($data['filter_order_status_exclude'])) {
			$exclude = array_map('intval', (array)$data['filter_order_status_exclude']);

			if ($exclude) {
				$sql .= " WHERE order_status_id NOT IN (" . implode(',', $exclude) . ")";
			} else {
				$sql .= " WHERE order_status_id > '0'";
			}
		} else {
			$sql .= " WHERE order_status_id > '0'";
		}

		if (!empty($data['filter_order_id'])) {
			$sql .= " AND order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$filter_customer = (string)$data['filter_customer'];
			$phone_digits = preg_replace('/\D+/', '', $filter_customer);

			$customer_conditions = array(
				"CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($filter_customer) . "%'",
				"email LIKE '%" . $this->db->escape($filter_customer) . "%'"
			);

			if ($phone_digits !== '') {
				$customer_conditions[] = "REGEXP_REPLACE(telephone, '[^0-9]', '') LIKE '%" . $this->db->escape($phone_digits) . "%'";
			}

			$sql .= " AND (" . implode(' OR ', $customer_conditions) . ")";
		}

		if (!empty($data['filter_date_added'])) {
			if (!empty($data['filter_date_added_operator'])) {
				$operator = $this->getComparisonOperator((string)$data['filter_date_added_operator']);
				$sql .= " AND DATE(date_added) " . $operator . " DATE('" . $this->db->escape($data['filter_date_added']) . "')";
			} else {
				$sql .= " AND DATE(date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
			}
		}

		if (!empty($data['filter_date_modified'])) {
			$sql .= " AND DATE(date_modified) = DATE('" . $this->db->escape($data['filter_date_modified']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$operator = !empty($data['filter_total_operator']) ? $this->getComparisonOperator((string)$data['filter_total_operator']) : '=';
			$sql .= " AND total " . $operator . " '" . (float)$data['filter_total'] . "'";
		}

		if (isset($data['filter_total_min']) && $data['filter_total_min'] !== '') {
			$sql .= " AND total >= '" . (float)$data['filter_total_min'] . "'";
		}

		if (isset($data['filter_total_max']) && $data['filter_total_max'] !== '') {
			$sql .= " AND total <= '" . (float)$data['filter_total_max'] . "'";
		}

		if (!empty($data['filter_payment_method'])) {
			$sql .= " AND payment_method LIKE '%" . $this->db->escape((string)$data['filter_payment_method']) . "%'";
		}

		if (!empty($data['filter_shipping_method'])) {
			$sql .= " AND shipping_method LIKE '%" . $this->db->escape((string)$data['filter_shipping_method']) . "%'";
		}

		if (!empty($data['filter_date_preset'])) {
			$sql .= $this->getDatePresetSql((string)$data['filter_date_preset'], 'date_added');
		}

		if (!empty($data['filter_payment_status'])) {
			$sql .= $this->getPaymentStatusFilterSql((string)$data['filter_payment_status']);
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

	public function getTotalOrdersExcludingStatuses($exclude_status_ids = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` WHERE order_status_id > '0'";

		if ($exclude_status_ids) {
			$implode = array();
			foreach ($exclude_status_ids as $status_id) {
				$implode[] = (int)$status_id;
			}
			$sql .= " AND order_status_id NOT IN (" . implode(',', $implode) . ")";
		}

		$query = $this->db->query($sql);

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
			$filter_customer = (string)$data['filter_customer'];
			$phone_digits = preg_replace('/\D+/', '', $filter_customer);

			$customer_conditions = array(
				"CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($filter_customer) . "%'",
				"email LIKE '%" . $this->db->escape($filter_customer) . "%'"
			);

			if ($phone_digits !== '') {
				$customer_conditions[] = "REGEXP_REPLACE(telephone, '[^0-9]', '') LIKE '%" . $this->db->escape($phone_digits) . "%'";
			}

			$sql .= " AND (" . implode(' OR ', $customer_conditions) . ")";
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
				$value = ($field === 'tracking_number') ? $this->normalizeTrackingNumber($data[$field]) : $data[$field];
				$set[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
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

		$order_query = $this->db->query("SELECT total FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		$old_total = (float)($order_query->num_rows ? $order_query->row['total'] : 0);

		$order_products_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "' ORDER BY order_product_id ASC");

		if (!$order_products_query->num_rows) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product_discount` WHERE order_id = '" . (int)$order_id . "'");
			return array(
				'old_total' => $old_total,
				'new_total' => $old_total
			);
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
			$delta_tax += $new_tax - $old_tax;

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
			return array(
				'old_total' => $old_total,
				'new_total' => $old_total
			);
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
			$new_order_total = $old_total;
		}

		return array(
			'old_total' => $old_total,
			'new_total' => $new_order_total
		);
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

		if ($field === 'tracking_number') {
			$value = $this->normalizeTrackingNumber($value);
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET `" . $field . "` = '" . $this->db->escape((string)$value) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

		return true;
	}

	private function normalizeTrackingNumber($value) {
		$numbers = array_map('trim', explode('|', (string)$value));
		$numbers = array_values(array_filter($numbers, function($number) {
			return $number !== '';
		}));

		return implode('|', array_slice($numbers, 0, 10));
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
		$old_quantity = (float)$product['quantity'];
		$unit_tax = $old_quantity > 0 ? (float)$product['tax'] / $old_quantity : 0;

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

	public function calculateProductPricing($order_id, $product_id, $quantity = 1, $options = array()) {
		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return false;
		}

		$quantity = max(1, (int)$quantity);

		$order_query = $this->db->query("SELECT customer_group_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
		$customer_group_id = $order_query->num_rows ? (int)$order_query->row['customer_group_id'] : 0;

		$variant_id = 0;
		$variant_sku = '';
		$model = $product_info['model'];
		$price = (float)$product_info['price'];
		$option_price = 0.0;
		$stock = (float)$product_info['quantity'];
		$subtract = (bool)$product_info['subtract'];

		$pc = new \ProductConfigurable($this->registry);

		$axis_ids = array();

		if ($pc->isConfigurable($product_id)) {
			$axis_query = $this->db->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

			foreach ($axis_query->rows as $row) {
				$axis_ids[] = (int)$row['option_id'];
			}
		}

		if ($options || $axis_ids) {
			$pov_ids = array();

			foreach ($options as $option) {
				if (!empty($option['product_option_value_id'])) {
					$pov_ids[] = (int)$option['product_option_value_id'];
				}
			}

			$axis_selection = array();

			if ($pov_ids) {
				$pov_query = $this->db->query("SELECT pov.product_option_value_id, po.option_id, pov.option_value_id, COALESCE(cgp.price, pov.price) AS price, COALESCE(cgp.price_prefix, pov.price_prefix) AS price_prefix FROM " . DB_PREFIX . "product_option_value pov INNER JOIN " . DB_PREFIX . "product_option po ON (pov.product_option_id = po.product_option_id) LEFT JOIN " . DB_PREFIX . "dockercart_product_option_value_customer_group_price cgp ON (cgp.product_option_value_id = pov.product_option_value_id AND cgp.customer_group_id = '" . (int)$customer_group_id . "') WHERE pov.product_option_value_id IN (" . implode(',', array_unique($pov_ids)) . ") AND po.product_id = '" . (int)$product_id . "'");

				foreach ($pov_query->rows as $row) {
					if (in_array((int)$row['option_id'], $axis_ids)) {
						$axis_selection[(int)$row['option_id']] = (int)$row['option_value_id'];
					} elseif ($row['price_prefix'] == '+') {
						$option_price += (float)$row['price'];
					} elseif ($row['price_prefix'] == '-') {
						$option_price -= (float)$row['price'];
					}
				}
			}

			if ($axis_ids) {
				$variant = $pc->resolveVariant($product_id, $axis_selection);

				if (empty($variant)) {
					throw new \RuntimeException('error_variant_not_found');
				}

				$variant_id = (int)$variant['variant_id'];
				$variant_sku = $variant['sku'] ?? '';
				$variant_model = $variant['model'] ?? '';
				$price = (float)$variant['price'];
				$stock = (float)($variant['quantity'] ?? 0);
				$subtract = (bool)($variant['subtract'] ?? true);

				if (!empty($variant_model)) {
					$model = $variant_model;
				} elseif (!empty($variant_sku)) {
					$model = $variant_sku;
				}

				if ($customer_group_id) {
					$cg_price = $pc->getVariantCustomerGroupPrice($variant_id, $customer_group_id);

					if ($cg_price !== null && $cg_price > 0) {
						$price = $cg_price;
					}

					$special_price = $pc->getVariantSpecialPrice($variant_id, $customer_group_id);

					if ($special_price !== null && $special_price < $price) {
						$price = $special_price;
					}

					$discount_price = $pc->getVariantDiscountPrice($variant_id, $customer_group_id, $quantity);

					if ($discount_price !== null && $discount_price < $price) {
						$price = $discount_price;
					}
				}
			}
		}

		$price += $option_price;

		$tax = 0.0;

		if ($product_info['tax_class_id']) {
			$tax_rates = $this->tax->getRates($price, $product_info['tax_class_id']);
			foreach ($tax_rates as $tax_rate) {
				$tax += $tax_rate['amount'];
			}
		}

		return array(
			'product_info'      => $product_info,
			'quantity'          => $quantity,
			'price'             => round($price, 4),
			'option_price'      => round($option_price, 4),
			'tax'               => round($tax, 4),
			'total'             => round($price * $quantity, 4),
			'tax_total'         => round($tax * $quantity, 4),
			'variant_id'        => $variant_id,
			'variant_sku'       => $variant_sku,
			'model'             => $model,
			'is_configurable'   => (bool)$axis_ids,
			'stock'             => $stock,
			'subtract'          => $subtract,
			'customer_group_id' => $customer_group_id,
		);
	}

	public function addProductToOrder($order_id, $product_id, $quantity = 1, $options = array()) {
		$pricing = $this->calculateProductPricing($order_id, $product_id, $quantity, $options);

		if (!$pricing) {
			return false;
		}

		$order_id = (int)$order_id;
		$product_info = $pricing['product_info'];

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET
			order_id = '" . $order_id . "',
			product_id = '" . (int)$product_id . "',
			name = '" . $this->db->escape($product_info['name']) . "',
			model = '" . $this->db->escape($pricing['model']) . "',
			quantity = '" . (float)$pricing['quantity'] . "',
			price = '" . (float)$pricing['price'] . "',
			total = '" . (float)$pricing['total'] . "',
			tax = '" . (float)$pricing['tax_total'] . "',
			reward = '" . (int)($product_info['reward'] ?? 0) . "',
			variant_id = '" . (int)$pricing['variant_id'] . "',
			variant_sku = '" . $this->db->escape($pricing['variant_sku']) . "'
		");

		$order_product_id = $this->db->getLastId();

		if ($options) {
			foreach ($options as $option) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "order_option` SET
					order_id = '" . $order_id . "',
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
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_shipment_item` WHERE order_product_id = '" . (int)$order_product_id . "'");
		$this->db->query("DELETE s FROM `" . DB_PREFIX . "order_shipment` s LEFT JOIN `" . DB_PREFIX . "order_shipment_item` si ON si.shipment_id = s.shipment_id WHERE s.order_id = '" . (int)$order_id . "' AND si.shipment_item_id IS NULL");

		$this->updateOrderTrackingFromShipments($order_id);

		return true;
	}

	public function recalculateOrderTotals($order_id) {
		$order_query = $this->db->query("SELECT total FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		$old_total = (float)($order_query->num_rows ? $order_query->row['total'] : 0);

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

		return array(
			'old_total' => $old_total,
			'new_total' => $new_total
		);
	}

	public function applyCoupon($order_id, $code) {
		$code = trim((string)$code);

		if ($code === '') {
			return false;
		}

		$this->db->query("START TRANSACTION");

		try {
			$order_query = $this->db->query("SELECT order_id, customer_id, total FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

			if (!$order_query->num_rows) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$order_row = $order_query->row;

			$coupon_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon` WHERE code = '" . $this->db->escape($code) . "' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) AND status = '1' FOR UPDATE");

			if (!$coupon_query->num_rows) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$coupon = $coupon_query->row;

			$subtotal_query = $this->db->query("SELECT value FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'sub_total'");

			$sub_total = $subtotal_query->num_rows ? (float)$subtotal_query->row['value'] : 0.0;

			if ((float)$coupon['total'] > 0 && (float)$coupon['total'] > $sub_total) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$coupon_uses_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '" . $this->db->escape($code) . "'");

			if ((int)$coupon['uses_total'] > 0 && (int)$coupon_uses_query->row['total'] >= (int)$coupon['uses_total']) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$customer_id = (int)$order_row['customer_id'];

			if ($coupon['logged'] && !$customer_id) {
				$this->db->query("ROLLBACK");
				return false;
			}

			if ($customer_id) {
				$customer_uses_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "coupon_history` ch LEFT JOIN `" . DB_PREFIX . "coupon` c ON (ch.coupon_id = c.coupon_id) WHERE c.code = '" . $this->db->escape($code) . "' AND ch.customer_id = '" . (int)$customer_id . "'");

				if ((int)$coupon['uses_customer'] > 0 && (int)$customer_uses_query->row['total'] >= (int)$coupon['uses_customer']) {
					$this->db->query("ROLLBACK");
					return false;
				}
			}

			$coupon_product_data = array();

			$coupon_product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_product` WHERE coupon_id = '" . (int)$coupon['coupon_id'] . "'");

			foreach ($coupon_product_query->rows as $product) {
				$coupon_product_data[] = (int)$product['product_id'];
			}

			$coupon_category_data = array();

			$coupon_category_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "coupon_category` cc LEFT JOIN `" . DB_PREFIX . "category_path` cp ON (cc.category_id = cp.path_id) WHERE cc.coupon_id = '" . (int)$coupon['coupon_id'] . "'");

			foreach ($coupon_category_query->rows as $category) {
				$coupon_category_data[] = (int)$category['category_id'];
			}

			$order_products = $this->getOrderProducts($order_id);

			$product_data = array();

			if ($coupon_product_data || $coupon_category_data) {
				foreach ($order_products as $product) {
					if (in_array((int)$product['product_id'], $coupon_product_data)) {
						$product_data[] = (int)$product['product_id'];
						continue;
					}

					foreach ($coupon_category_data as $category_id) {
						$category_query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product['product_id'] . "' AND category_id = '" . (int)$category_id . "'");

						if ($category_query->row['total']) {
							$product_data[] = (int)$product['product_id'];
							continue;
						}
					}
				}

				if (!$product_data) {
					$this->db->query("ROLLBACK");
					return false;
				}
			}

			$qualifying_sub_total = 0.0;

			foreach ($order_products as $product) {
				$status = !$coupon_product_data && !$coupon_category_data ? true : in_array((int)$product['product_id'], $product_data);

				if ($status) {
					$qualifying_sub_total += (float)$product['total'];
				}
			}

			if ((float)$coupon['discount'] < 0) {
				$coupon['discount'] = 0;
			}

			if ($coupon['type'] == 'F' && (float)$coupon['discount'] > $qualifying_sub_total) {
				$coupon['discount'] = $qualifying_sub_total;
			}

			$discount_total = 0.0;

			foreach ($order_products as $product) {
				$status = !$coupon_product_data && !$coupon_category_data ? true : in_array((int)$product['product_id'], $product_data);

				if (!$status) {
					continue;
				}

				if ($coupon['type'] == 'F') {
					$discount = $qualifying_sub_total > 0 ? (float)$coupon['discount'] * ((float)$product['total'] / $qualifying_sub_total) : 0;
				} elseif ($coupon['type'] == 'P') {
					$discount = (float)$product['total'] / 100 * (float)$coupon['discount'];
				} else {
					$discount = 0;
				}

				$discount_total += $discount;
			}

			if ($coupon['shipping']) {
				$shipping_query = $this->db->query("SELECT value FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'shipping'");

				if ($shipping_query->num_rows) {
					$discount_total += (float)$shipping_query->row['value'];
				}
			}

			if ($discount_total > (float)$order_row['total']) {
				$discount_total = (float)$order_row['total'];
			}

			if ($discount_total <= 0) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'coupon'");

			$sort_order = (int)$this->config->get('total_coupon_sort_order');

			if ($sort_order <= 0) {
				$sort_order = 2;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET
				order_id = '" . (int)$order_id . "',
				code = 'coupon',
				title = '" . $this->db->escape($this->language->get('entry_coupon') . ' (' . $code . ')') . "',
				value = '" . (float)(-$discount_total) . "',
				sort_order = '" . (int)$sort_order . "'
			");

			$this->db->query("INSERT INTO `" . DB_PREFIX . "coupon_history` SET
				coupon_id = '" . (int)$coupon['coupon_id'] . "',
				order_id = '" . (int)$order_id . "',
				customer_id = '" . (int)$customer_id . "',
				amount = '" . (float)(-$discount_total) . "',
				date_added = NOW()
			");

			$this->recalculateOrderTotals($order_id);

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return array(
			'code'   => $code,
			'amount' => $discount_total
		);
	}

	public function removeCoupon($order_id) {
		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'coupon'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "coupon_history` WHERE order_id = '" . (int)$order_id . "'");

			$this->recalculateOrderTotals($order_id);

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function hasCoupon($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' AND code = 'coupon'");

		return $query->num_rows ? $query->row : false;
	}

	public function recalculateShipping($order_id) {
		$order_info = $this->getOrder($order_id);

		if (!$order_info || empty($order_info['shipping_code'])) {
			return null;
		}

		$parts = explode('.', (string)$order_info['shipping_code']);

		if (($parts[0] ?? '') !== 'dockercart_universal') {
			return null;
		}

		$totals = $this->getOrderTotals($order_id);

		$subtotal = 0.0;
		$shipping_total_id = 0;

		foreach ($totals as $total) {
			if ($total['code'] == 'sub_total') {
				$subtotal = (float)$total['value'];
			} elseif ($total['code'] == 'shipping') {
				$shipping_total_id = (int)$total['order_total_id'];
			}
		}

		if (!$shipping_total_id) {
			return null;
		}

		$currency_value = (float)$order_info['currency_value'];

		if ($currency_value <= 0) {
			$currency_value = 1.0;
		}

		$this->load->model('extension/shipping/dockercart_universal');

		$quote = $this->model_extension_shipping_dockercart_universal->getQuoteForOrder(
			$order_info,
			$subtotal / $currency_value,
			$this->getOrderShippingWeight($order_id)
		);

		if ($quote === null) {
			return null;
		}

		$cost = $quote['cost'] === null ? 0.0 : (float)$quote['cost'];
		$cost_order = round($cost * $currency_value, 4);

		$this->db->query("UPDATE `" . DB_PREFIX . "order_total` SET
			value = '" . (float)$cost_order . "',
			title = '" . $this->db->escape($quote['title']) . "'
			WHERE order_total_id = '" . (int)$shipping_total_id . "'
		");

		return $this->recalculateOrderTotals($order_id);
	}

	public function previewShippingQuote($order_id, $shipping_code, $country_id = 0, $zone_id = 0, $subtotal = null) {
		$order_info = $this->getOrder($order_id);

		if (!$order_info || $shipping_code === '') {
			return null;
		}

		$parts = explode('.', (string)$shipping_code);

		if (($parts[0] ?? '') !== 'dockercart_universal') {
			return null;
		}

		if ($country_id) {
			$order_info['shipping_country_id'] = (int)$country_id;
		}

		if ($zone_id) {
			$order_info['shipping_zone_id'] = (int)$zone_id;
		}

		$order_info['shipping_code'] = $shipping_code;

		if ($subtotal === null) {
			$subtotal = 0.0;

			foreach ($this->getOrderTotals($order_id) as $total) {
				if ($total['code'] == 'sub_total') {
					$subtotal = (float)$total['value'];
					break;
				}
			}
		}

		$currency_value = (float)$order_info['currency_value'];

		if ($currency_value <= 0) {
			$currency_value = 1.0;
		}

		$this->load->model('extension/shipping/dockercart_universal');

		$quote = $this->model_extension_shipping_dockercart_universal->getQuoteForOrder(
			$order_info,
			(float)$subtotal / $currency_value,
			$this->getOrderShippingWeight($order_id)
		);

		if ($quote === null) {
			return null;
		}

		$cost = $quote['cost'] === null ? 0.0 : (float)$quote['cost'];

		return array(
			'cost'  => round($cost * $currency_value, 4),
			'title' => $quote['title']
		);
	}

	private function getOrderShippingWeight($order_id) {
		$query = $this->db->query("SELECT op.product_id, op.quantity, p.weight, p.weight_class_id, p.shipping
			FROM `" . DB_PREFIX . "order_product` op
			LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id)
			WHERE op.order_id = '" . (int)$order_id . "'
		");

		$weight = 0.0;
		$default_class = (int)$this->config->get('config_weight_class_id');

		foreach ($query->rows as $row) {
			if (!$row['shipping']) {
				continue;
			}

			$line_weight = (float)$row['weight'] * (float)$row['quantity'];

			if ((int)$row['weight_class_id']) {
				$line_weight = $this->weight->convert($line_weight, (int)$row['weight_class_id'], $default_class);
			}

			$weight += $line_weight;
		}

		return $weight;
	}

	public function getOrderTimeline($order_id, $start = 0, $limit = 20) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$query = $this->db->query("SELECT oh.order_history_id, oh.order_status_id, oh.notify, oh.comment, oh.comment_key, oh.comment_params, oh.date_added,
			os.name AS status_name, 'history' AS type, 0 AS amount, '' AS payment_method, '' AS payment_code
			FROM " . DB_PREFIX . "order_history oh
			LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "'
			WHERE oh.order_id = '" . (int)$order_id . "'
			UNION ALL
			SELECT op.order_payment_id, 0, 0, CONVERT(op.comment USING utf8mb4) COLLATE utf8mb4_general_ci, '', NULL, op.date_added,
			op.payment_method COLLATE utf8mb4_general_ci AS status_name, 'payment' AS type, op.amount, op.payment_method COLLATE utf8mb4_general_ci, op.payment_code
			FROM " . DB_PREFIX . "order_payment op
			WHERE op.order_id = '" . (int)$order_id . "'
			ORDER BY date_added DESC, order_history_id DESC
			LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function countOrderTimeline($order_id) {
		$query = $this->db->query("SELECT (SELECT COUNT(*) FROM " . DB_PREFIX . "order_history WHERE order_id = '" . (int)$order_id . "') + (SELECT COUNT(*) FROM " . DB_PREFIX . "order_payment WHERE order_id = '" . (int)$order_id . "') AS total");

		return (int)$query->row['total'];
	}

	public function getPaymentStatus($total, $paid_amount) {
		$total = (float)$total;
		$paid_amount = (float)$paid_amount;

		if ($total <= 0) {
			return 'paid';
		}

		if ($paid_amount <= 0) {
			return 'unpaid';
		}

		if ($paid_amount > $total) {
			return 'overpaid';
		}

		if ($paid_amount >= $total) {
			return 'paid';
		}

		return 'partial';
	}

	public function getOrderPayment($order_payment_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_payment` WHERE order_payment_id = '" . (int)$order_payment_id . "'");

		return $query->num_rows ? $query->row : false;
	}

	public function getOrderPayments($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_payment` WHERE order_id = '" . (int)$order_id . "' ORDER BY date_added ASC, order_payment_id ASC");

		return $query->rows;
	}

	public function addOrderPayment($order_id, $amount, $reference = '', $comment = '', $payment_method = '', $payment_code = '') {
		$order_info = $this->getOrder($order_id);

		if (!$order_info) {
			return false;
		}

		$amount = (float)$amount;

		if ($amount <= 0) {
			return false;
		}

		if ($payment_method === '') {
			$payment_method = $order_info['payment_method'];
		}

		if ($payment_code === '') {
			$payment_code = $order_info['payment_code'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_payment` SET
			order_id = '" . (int)$order_id . "',
			amount = '" . (float)$amount . "',
			payment_method = '" . $this->db->escape($payment_method) . "',
			payment_code = '" . $this->db->escape($payment_code) . "',
			reference = '" . $this->db->escape($reference) . "',
			comment = '" . $this->db->escape($comment) . "',
			created_by = '" . (int)$this->user->getId() . "',
			date_added = NOW()");

		$order_payment_id = $this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET paid_amount = paid_amount + '" . (float)$amount . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

		return $order_payment_id;
	}

	public function removeOrderPayment($order_payment_id, $comment = '') {
		$payment = $this->getOrderPayment($order_payment_id);

		if (!$payment) {
			return false;
		}

		$amount = (float)$payment['amount'];

		if ($amount <= 0) {
			return false;
		}

		if ($comment === '') {
			$comment = 'Reversal of #' . (int)$order_payment_id;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_payment` SET
			order_id = '" . (int)$payment['order_id'] . "',
			amount = '" . (float)-$amount . "',
			payment_method = '" . $this->db->escape($payment['payment_method']) . "',
			payment_code = '" . $this->db->escape($payment['payment_code']) . "',
			reference = '" . $this->db->escape($payment['reference']) . "',
			comment = '" . $this->db->escape($comment) . "',
			created_by = '" . (int)$this->user->getId() . "',
			date_added = NOW()");

		$reversal_id = $this->db->getLastId();

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET paid_amount = paid_amount - '" . (float)$amount . "', date_modified = NOW() WHERE order_id = '" . (int)$payment['order_id'] . "'");

		return $reversal_id;
	}

	public function removeOrderOverpayment($order_id, $comment = '') {
		$this->db->query("START TRANSACTION");

		try {
			$lock_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

			if (!$lock_query->num_rows) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$order_info = $lock_query->row;

			$total = (float)$order_info['total'];
			$paid_amount = (float)$order_info['paid_amount'];
			$overpaid = $paid_amount - $total;

			if ($overpaid <= 0) {
				$this->db->query("ROLLBACK");
				return false;
			}

			if ($comment === '') {
				$comment = 'Overpayment reversal';
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_payment` SET
				order_id = '" . (int)$order_id . "',
				amount = '" . (float)-$overpaid . "',
				payment_method = '" . $this->db->escape($order_info['payment_method']) . "',
				payment_code = '" . $this->db->escape($order_info['payment_code']) . "',
				reference = '',
				comment = '" . $this->db->escape($comment) . "',
				created_by = '" . (int)$this->user->getId() . "',
				date_added = NOW()");

			$reversal_id = $this->db->getLastId();

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET paid_amount = paid_amount - '" . (float)$overpaid . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return $reversal_id;
	}

	public function addOrderRefund($order_id, $amount, $comment, $return_id = 0, $note_key = '', $note_params = array()) {
		$this->db->query("START TRANSACTION");

		try {
			$lock_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

			if (!$lock_query->num_rows || (float)$amount <= 0) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$order_info = $lock_query->row;

			$refund = min((float)$amount, (float)$order_info['paid_amount']);

			if ($refund <= 0) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_payment` SET
				order_id = '" . (int)$order_id . "',
				amount = '" . (float)-$refund . "',
				payment_method = '" . $this->db->escape($order_info['payment_method']) . "',
				payment_code = '" . $this->db->escape($order_info['payment_code']) . "',
				reference = '',
				comment = '" . $this->db->escape($comment) . "',
				created_by = '" . (int)$this->user->getId() . "',
				date_added = NOW()");

			$reversal_id = $this->db->getLastId();

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET paid_amount = paid_amount - '" . (float)$refund . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

			// Partial refund → revoke reward points proportionally to the
			// refunded share of what was paid before this refund. Idempotent:
			// reward_revoked_points already includes previous revocations, so
			// repeated partial refunds converge to zero.
			$paid_before_refund = (float)$order_info['paid_amount'];

			if ($paid_before_refund > 0) {
				$dockercart_reward = new \DockercartReward($this->registry);
				$dockercart_reward->revokeOrderReward((int)$order_id, $refund / $paid_before_refund);
			}

			if ($note_key === '') {
				$note_key = 'text_return_refund_note';
				$note_params = array($return_id);
			}

			$this->addOrderNote($order_id, $comment, false, $note_key, $note_params);

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return $reversal_id;
	}

	public function addOrderShipment($order_id, $tracking_number, $items = array(), $comment = '') {
		$tracking_number = trim((string)$tracking_number);

		if ($tracking_number === '' || !$items) {
			return false;
		}

		$this->db->query("START TRANSACTION");

		try {
			$order_query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

			if (!$order_query->num_rows) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$progress = $this->getOrderShipmentProgress($order_id);

			$valid_items = array();

			foreach ($items as $item) {
				$order_product_id = (int)($item['order_product_id'] ?? 0);
				$quantity = (int)($item['quantity'] ?? 0);

				if (!$order_product_id || $quantity < 1) {
					continue;
				}

				$product_query = $this->db->query("SELECT order_product_id, quantity FROM `" . DB_PREFIX . "order_product` WHERE order_product_id = '" . (int)$order_product_id . "' AND order_id = '" . (int)$order_id . "' FOR UPDATE");

				if (!$product_query->num_rows) {
					continue;
				}

				$ordered = (float)$product_query->row['quantity'];
				$shipped = (float)($progress[$order_product_id]['shipped'] ?? 0);
				$remaining = max(0, $ordered - $shipped);

				if ($quantity > $remaining) {
					$quantity = (int)$remaining;
				}

				if ($quantity > 0) {
					$valid_items[] = array(
						'order_product_id' => $order_product_id,
						'quantity'         => $quantity,
					);
				}
			}

			if (!$valid_items) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "order_shipment` SET
				order_id = '" . (int)$order_id . "',
				tracking_number = '" . $this->db->escape($tracking_number) . "',
				comment = '" . $this->db->escape($comment) . "',
				created_by = '" . (int)$this->user->getId() . "',
				date_added = NOW()");

			$shipment_id = $this->db->getLastId();

			foreach ($valid_items as $item) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "order_shipment_item` SET
					shipment_id = '" . (int)$shipment_id . "',
					order_product_id = '" . (int)$item['order_product_id'] . "',
					quantity = '" . (int)$item['quantity'] . "'");
			}

			$this->updateOrderTrackingFromShipments($order_id);

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return $shipment_id;
	}

	public function getOrderShipment($shipment_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_shipment` WHERE shipment_id = '" . (int)$shipment_id . "'");

		return $query->num_rows ? $query->row : false;
	}

	public function getOrderShipments($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_shipment` WHERE order_id = '" . (int)$order_id . "' ORDER BY date_added ASC, shipment_id ASC");

		$shipments = array();

		foreach ($query->rows as $row) {
			$row['items'] = $this->getOrderShipmentItems($row['shipment_id']);
			$shipments[] = $row;
		}

		return $shipments;
	}

	public function getOrderShipmentItems($shipment_id) {
		$query = $this->db->query("SELECT si.*, op.name, op.quantity AS ordered_quantity FROM `" . DB_PREFIX . "order_shipment_item` si LEFT JOIN `" . DB_PREFIX . "order_product` op ON op.order_product_id = si.order_product_id WHERE si.shipment_id = '" . (int)$shipment_id . "'");

		return $query->rows;
	}

	public function deleteOrderShipment($shipment_id) {
		$query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order_shipment` WHERE shipment_id = '" . (int)$shipment_id . "'");

		if (!$query->num_rows) {
			return false;
		}

		$order_id = (int)$query->row['order_id'];

		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_shipment_item` WHERE shipment_id = '" . (int)$shipment_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "order_shipment` WHERE shipment_id = '" . (int)$shipment_id . "'");

		$this->updateOrderTrackingFromShipments($order_id);

		return true;
	}

	public function updateOrderTrackingFromShipments($order_id) {
		$query = $this->db->query("SELECT tracking_number FROM `" . DB_PREFIX . "order_shipment` WHERE order_id = '" . (int)$order_id . "' AND tracking_number <> '' ORDER BY date_added ASC, shipment_id ASC");

		$tracking = array();

		foreach ($query->rows as $row) {
			$tracking[] = $row['tracking_number'];
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "order` SET tracking_number = '" . $this->db->escape(implode('|', $tracking)) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
	}

	public function getOrderShipmentProgress($order_id) {
		$query = $this->db->query("SELECT op.order_product_id, op.quantity AS ordered, COALESCE(SUM(si.quantity), 0) AS shipped FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "order_shipment_item` si ON si.order_product_id = op.order_product_id WHERE op.order_id = '" . (int)$order_id . "' GROUP BY op.order_product_id");

		$progress = array();

		foreach ($query->rows as $row) {
			$progress[(int)$row['order_product_id']] = array(
				'ordered' => (float)$row['ordered'],
				'shipped' => (float)$row['shipped'],
			);
		}

		return $progress;
	}

	public function deleteOrder($order_id) {
		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_voucher` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_history` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_payment` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE si FROM `" . DB_PREFIX . "order_shipment_item` si LEFT JOIN `" . DB_PREFIX . "order_shipment` s ON s.shipment_id = si.shipment_id WHERE s.order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "order_shipment` WHERE order_id = '" . (int)$order_id . "'");
			$this->db->query("DELETE `or`, ort FROM `" . DB_PREFIX . "order_recurring` `or`, `" . DB_PREFIX . "order_recurring_transaction` `ort` WHERE order_id = '" . (int)$order_id . "' AND ort.order_recurring_id = `or`.order_recurring_id");
			$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE order_id = '" . (int)$order_id . "'");

			$order_vouchers = $this->getOrderVouchers($order_id);

			foreach ($order_vouchers as $order_voucher) {
				$this->db->query("UPDATE `" . DB_PREFIX . "voucher` SET `status` = '0' WHERE voucher_id = '" . (int)$order_voucher['voucher_id'] . "'");
			}

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}
	}

	public function duplicateOrder($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");

		if (!$query->num_rows) {
			return false;
		}

		$order = $query->row;

		$flow_steps = (array)$this->config->get('config_order_flow_steps');

		if (!empty($flow_steps)) {
			$order_status_id = (int)reset($flow_steps);
		} else {
			$order_status_id = (int)$order['order_status_id'];
		}

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET
				invoice_prefix = '" . $this->db->escape($order['invoice_prefix']) . "',
				store_id = '" . (int)$order['store_id'] . "',
				store_name = '" . $this->db->escape($order['store_name']) . "',
				store_url = '" . $this->db->escape($order['store_url']) . "',
				customer_id = '" . (int)$order['customer_id'] . "',
				customer_group_id = '" . (int)$order['customer_group_id'] . "',
				firstname = '" . $this->db->escape($order['firstname']) . "',
				lastname = '" . $this->db->escape($order['lastname']) . "',
				email = '" . $this->db->escape($order['email']) . "',
				telephone = '" . $this->db->escape($order['telephone']) . "',
				tax_number = '" . $this->db->escape($order['tax_number']) . "',
				custom_field = '" . $this->db->escape($order['custom_field']) . "',
				payment_firstname = '" . $this->db->escape($order['payment_firstname']) . "',
				payment_lastname = '" . $this->db->escape($order['payment_lastname']) . "',
				payment_company = '" . $this->db->escape($order['payment_company']) . "',
				payment_address_1 = '" . $this->db->escape($order['payment_address_1']) . "',
				payment_address_2 = '" . $this->db->escape($order['payment_address_2']) . "',
				payment_city = '" . $this->db->escape($order['payment_city']) . "',
				payment_postcode = '" . $this->db->escape($order['payment_postcode']) . "',
				payment_zone = '" . $this->db->escape($order['payment_zone']) . "',
				payment_zone_id = '" . (int)$order['payment_zone_id'] . "',
				payment_country = '" . $this->db->escape($order['payment_country']) . "',
				payment_country_id = '" . (int)$order['payment_country_id'] . "',
				payment_address_format = '" . $this->db->escape($order['payment_address_format']) . "',
				payment_custom_field = '" . $this->db->escape($order['payment_custom_field']) . "',
				payment_method = '" . $this->db->escape($order['payment_method']) . "',
				payment_code = '" . $this->db->escape($order['payment_code']) . "',
				shipping_firstname = '" . $this->db->escape($order['shipping_firstname']) . "',
				shipping_lastname = '" . $this->db->escape($order['shipping_lastname']) . "',
				shipping_company = '" . $this->db->escape($order['shipping_company']) . "',
				shipping_address_1 = '" . $this->db->escape($order['shipping_address_1']) . "',
				shipping_address_2 = '" . $this->db->escape($order['shipping_address_2']) . "',
				shipping_city = '" . $this->db->escape($order['shipping_city']) . "',
				shipping_postcode = '" . $this->db->escape($order['shipping_postcode']) . "',
				shipping_zone = '" . $this->db->escape($order['shipping_zone']) . "',
				shipping_zone_id = '" . (int)$order['shipping_zone_id'] . "',
				shipping_country = '" . $this->db->escape($order['shipping_country']) . "',
				shipping_country_id = '" . (int)$order['shipping_country_id'] . "',
				shipping_address_format = '" . $this->db->escape($order['shipping_address_format']) . "',
				shipping_custom_field = '" . $this->db->escape($order['shipping_custom_field']) . "',
				shipping_method = '" . $this->db->escape($order['shipping_method']) . "',
				shipping_code = '" . $this->db->escape($order['shipping_code']) . "',
				comment = '" . $this->db->escape($order['comment']) . "',
				total = '" . (float)$order['total'] . "',
				order_status_id = '" . (int)$order_status_id . "',
				affiliate_id = '" . (int)$order['affiliate_id'] . "',
				commission = '" . (float)$order['commission'] . "',
				marketing_id = '" . (int)$order['marketing_id'] . "',
				language_id = '" . (int)$order['language_id'] . "',
				currency_id = '" . (int)$order['currency_id'] . "',
				currency_code = '" . $this->db->escape($order['currency_code']) . "',
				currency_value = '" . (float)$order['currency_value'] . "',
				ip = '" . $this->db->escape($order['ip']) . "',
				forwarded_ip = '" . $this->db->escape($order['forwarded_ip']) . "',
				user_agent = '" . $this->db->escape($order['user_agent']) . "',
				accept_language = '" . $this->db->escape($order['accept_language']) . "',
				date_added = NOW(),
				date_modified = NOW()
			");

			$new_order_id = $this->db->getLastId();

			$product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");

			$product_map = array();

			foreach ($product_query->rows as $product) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` SET
					order_id = '" . (int)$new_order_id . "',
					product_id = '" . (int)$product['product_id'] . "',
					name = '" . $this->db->escape($product['name']) . "',
					model = '" . $this->db->escape($product['model']) . "',
					quantity = '" . (int)$product['quantity'] . "',
					price = '" . (float)$product['price'] . "',
					total = '" . (float)$product['total'] . "',
					tax = '" . (float)$product['tax'] . "',
					reward = '" . (int)$product['reward'] . "',
					variant_id = '" . (int)$product['variant_id'] . "',
					variant_sku = '" . $this->db->escape($product['variant_sku']) . "'
				");

				$product_map[(int)$product['order_product_id']] = $this->db->getLastId();
			}

			foreach ($product_query->rows as $product) {
				$option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$product['order_product_id'] . "'");

				foreach ($option_query->rows as $option) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "order_option` SET
						order_id = '" . (int)$new_order_id . "',
						order_product_id = '" . (int)$product_map[(int)$option['order_product_id']] . "',
						product_option_id = '" . (int)$option['product_option_id'] . "',
						product_option_value_id = '" . (int)$option['product_option_value_id'] . "',
						name = '" . $this->db->escape($option['name']) . "',
						value = '" . $this->db->escape($option['value']) . "',
						type = '" . $this->db->escape($option['type']) . "'
					");
				}
			}

			$total_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order");

			foreach ($total_query->rows as $total) {
				if ($total['code'] == 'coupon') {
					continue;
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "order_total` SET
					order_id = '" . (int)$new_order_id . "',
					code = '" . $this->db->escape($total['code']) . "',
					title = '" . $this->db->escape($total['title']) . "',
					value = '" . (float)$total['value'] . "',
					sort_order = '" . (int)$total['sort_order'] . "'
				");
			}

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		return $new_order_id;
	}

	public function addOrderHistory($order_id, $order_status_id, $comment = '', $notify = false, $override = false) {
		$this->db->query("START TRANSACTION");

		try {
			$lock_query = $this->db->query("SELECT order_status_id, affiliate_id, commission FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' FOR UPDATE");

			if (!$lock_query->num_rows) {
				$this->db->query("ROLLBACK");
				return false;
			}

			$old_status_id = (int)$lock_query->row['order_status_id'];

			if (!$override) {
				$order_flow = new \OrderFlow([
					'steps'       => (array)$this->config->get('config_order_flow_steps'),
					'transitions' => (array)$this->config->get('config_order_flow_transitions'),
				]);

				if (!$order_flow->validateTransition($old_status_id, (int)$order_status_id)) {
					$this->db->query("ROLLBACK");
					return false;
				}
			}

			$processing_statuses = (array)$this->config->get('config_processing_status');
			$complete_statuses = (array)$this->config->get('config_complete_status');

			$was_processing = in_array($old_status_id, array_merge($processing_statuses, $complete_statuses));
			$is_processing = in_array((int)$order_status_id, array_merge($processing_statuses, $complete_statuses));

			$was_complete = in_array($old_status_id, $complete_statuses);
			$is_complete = in_array((int)$order_status_id, $complete_statuses);

			// Transition from non-proc/complete to proc/complete → subtract stock, add affiliate commission
			if (!$was_processing && $is_processing) {
				$order_products = $this->getOrderProducts($order_id);

				foreach ($order_products as $order_product) {
					$this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (float)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

					if ((int)$order_product['variant_id'] > 0) {
						$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = (quantity - " . (float)$order_product['quantity'] . ") WHERE variant_id = '" . (int)$order_product['variant_id'] . "' AND subtract = '1'");
					}
				}

				// Release checkout holds bound to this order: stock was just subtracted.
				$stock_reservation = new \DockercartStockReservation($this->registry);
				$stock_reservation->releaseOrder((int)$order_id);

				if ((int)$lock_query->row['affiliate_id'] && $this->config->get('config_affiliate_auto')) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_transaction` SET customer_id = '" . (int)$lock_query->row['affiliate_id'] . "', order_id = '" . (int)$order_id . "', description = '" . $this->db->escape('Order #' . $order_id) . "', amount = '" . (float)$lock_query->row['commission'] . "', date_added = NOW()");
				}
			}

			// Entering a complete status → auto-award the order's reward points
			// (idempotent: oc_order.reward_awarded flips once, never resets).
			if (!$was_complete && $is_complete) {
				$dockercart_reward = new \DockercartReward($this->registry);
				$dockercart_reward->awardOrderReward((int)$order_id);
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

			$this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");

			// Reversal from proc/complete to non-proc/complete → restock, remove affiliate commission
			if ($was_processing && !$is_processing) {
				$order_products = $this->getOrderProducts($order_id);

				foreach ($order_products as $order_product) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = (quantity + " . (float)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

					if ((int)$order_product['variant_id'] > 0) {
						$this->db->query("UPDATE " . DB_PREFIX . "product_variant SET quantity = (quantity + " . (float)$order_product['quantity'] . ") WHERE variant_id = '" . (int)$order_product['variant_id'] . "' AND subtract = '1'");
					}
				}

				// Release any remaining checkout holds bound to this order (restock).
				$stock_reservation = new \DockercartStockReservation($this->registry);
				$stock_reservation->releaseOrder((int)$order_id);

				if ((int)$lock_query->row['affiliate_id']) {
					$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE order_id = '" . (int)$order_id . "'");
				}
			}

			// Leaving a complete status → revoke the awarded reward points.
			// Runs after the restock/commission block; the admin flow has no
			// reward unconfirm() cycle to interfere with.
			if ($was_complete && !$is_complete) {
				$dockercart_reward = new \DockercartReward($this->registry);
				$dockercart_reward->revokeOrderReward((int)$order_id, 1.0);
			}

			$this->db->query("COMMIT");
		} catch (\Exception $e) {
			$this->db->query("ROLLBACK");
			throw $e;
		}

		$this->cache->delete('product');

		return true;
	}

	public function addOrderNote($order_id, $comment, $notify = false, $comment_key = '', $comment_params = array()) {
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
			comment_key = '" . $this->db->escape($comment_key) . "',
			comment_params = '" . $this->db->escape(is_array($comment_params) ? json_encode($comment_params) : '') . "',
			date_added = NOW()
		");

		return true;
	}
}
