<?php
class ModelExtensionReportDockercartAnalytics extends Model {
	protected function getCompleteStatusCondition() {
		$implode = array();

		foreach ((array)$this->config->get('config_complete_status') as $order_status_id) {
			$implode[] = "'" . (int)$order_status_id . "'";
		}

		return $implode ? "IN(" . implode(",", $implode) . ")" : "IN (0)";
	}

	protected function getCompleteStatusList() {
		$statuses = array();

		foreach ((array)$this->config->get('config_complete_status') as $order_status_id) {
			$statuses[] = (int)$order_status_id;
		}

		return $statuses;
	}

	/**
	 * Cancelled status id used by the cancellation-rate report.
	 * Previously hardcoded as 130; now a store setting with a 130 fallback.
	 */
	protected function getCancelledStatusId() {
		$status = (int)$this->config->get('config_cancelled_status');

		return $status > 0 ? $status : 130;
	}

	/**
	 * Shared WHERE fragment for order-level filters (dates, status, utm).
	 */
	protected function getOrderWhere($data, $alias = 'o') {
		$where = array();

		if (!empty($data['filter_date_start'])) {
			$where[] = "DATE({$alias}.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$where[] = "DATE({$alias}.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_order_status_id'])) {
			$where[] = "{$alias}.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_utm_medium'])) {
			$where[] = "{$alias}.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$where[] = "{$alias}.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		return $where ? ' AND ' . implode(' AND ', $where) : '';
	}

	public function getTotals($data = array()) {
		$complete_cond = $this->getCompleteStatusCondition();
		$cancelled_id = $this->getCancelledStatusId();

		$sql = "SELECT
			COUNT(*) AS total_orders,
			SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed_orders,
			SUM(CASE WHEN o.order_status_id = {$cancelled_id} THEN 1 ELSE 0 END) AS cancelled_orders,
			COUNT(DISTINCT o.customer_id) AS unique_customers,
			COALESCE(SUM((SELECT SUM(op.quantity) FROM `" . DB_PREFIX . "order_product` op WHERE op.order_id = o.order_id)), 0) AS total_products,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 2), 0) AS conversion_rate,
			COALESCE(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN o.total / o.currency_value ELSE 0 END), 0) AS revenue,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN o.total / o.currency_value ELSE 0 END) / NULLIF(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END), 0), 2), 0) AS aov
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0'";

		$sql .= $this->getOrderWhere($data);

		$query = $this->db->query($sql);

		return $query->row;
	}

	public function getTopProducts($data = array()) {
		$status_cond = "o.order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$status_cond = "o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		$sql = "SELECT
			op.product_id,
			op.name,
			op.model,
			SUM(op.quantity) AS quantity,
			COALESCE(SUM(op.total / o.currency_value), 0) AS total
		FROM " . DB_PREFIX . "order_product op
		INNER JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id)
		WHERE {$status_cond}";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$sql .= " GROUP BY op.product_id ORDER BY total DESC";

		$limit = !empty($data['limit']) ? (int)$data['limit'] : 10;
		$sql .= " LIMIT {$limit}";

		if (isset($data['start'])) {
			$start = (int)$data['start'];
			$sql .= " OFFSET {$start}";
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalTopProducts($data = array()) {
		$status_cond = "o.order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$status_cond = "o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		$sql = "SELECT COUNT(DISTINCT op.product_id) AS total
		FROM " . DB_PREFIX . "order_product op
		INNER JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id)
		WHERE {$status_cond}";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getReport($data = array()) {
		$complete_cond = $this->getCompleteStatusCondition();

		$sql = "SELECT
			MIN(o.date_added) AS date_start,
			MAX(o.date_added) AS date_end,
			COUNT(*) AS total_orders,
			SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed_orders,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 2), 0) AS conversion_rate,
			COALESCE(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN o.total / o.currency_value ELSE 0 END), 0) AS revenue,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN o.total / o.currency_value ELSE 0 END) / NULLIF(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END), 0), 2), 0) AS aov,
			COALESCE(SUM(op_agg.quantity), 0) AS products,
			COALESCE(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END), 0) AS completed_count
		FROM `" . DB_PREFIX . "order` o
		LEFT JOIN (
			SELECT order_id, SUM(quantity) AS quantity FROM `" . DB_PREFIX . "order_product` GROUP BY order_id
		) op_agg ON (op_agg.order_id = o.order_id)
		WHERE o.order_status_id > '0'";

		$sql .= $this->getOrderWhere($data);

		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'week';
		}

		switch ($group) {
			case 'day':
				$sql .= " GROUP BY YEAR(o.date_added), MONTH(o.date_added), DAY(o.date_added)";
				break;
			default:
			case 'week':
				$sql .= " GROUP BY YEAR(o.date_added), WEEK(o.date_added)";
				break;
			case 'month':
				$sql .= " GROUP BY YEAR(o.date_added), MONTH(o.date_added)";
				break;
			case 'year':
				$sql .= " GROUP BY YEAR(o.date_added)";
				break;
		}

		$sql .= " ORDER BY o.date_added DESC";

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

	/**
	 * Time-series grouped by period: revenue, orders, products (quantity),
	 * average order value, conversion rate.
	 */
	public function getTimeSeries($data = array()) {
		$complete_cond = $this->getCompleteStatusCondition();

		$sql = "SELECT
			DATE(o.date_added) AS date_start,
			COUNT(*) AS total_orders,
			COALESCE(SUM(o.total / o.currency_value), 0) AS revenue,
			COALESCE(SUM(op_agg.quantity), 0) AS products,
			COALESCE(ROUND(SUM(o.total / o.currency_value) / NULLIF(COUNT(*), 0), 2), 0) AS aov,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 2), 0) AS conversion_rate,
			SUM(CASE WHEN o.order_status_id {$complete_cond} THEN 1 ELSE 0 END) AS completed_orders
		FROM `" . DB_PREFIX . "order` o
		LEFT JOIN (
			SELECT order_id, SUM(quantity) AS quantity FROM `" . DB_PREFIX . "order_product` GROUP BY order_id
		) op_agg ON (op_agg.order_id = o.order_id)
		WHERE o.order_status_id > '0'";

		$sql .= $this->getOrderWhere($data);

		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'day';
		}

		switch ($group) {
			case 'day':
				$sql .= " GROUP BY DATE(o.date_added)";
				break;
			case 'week':
				$sql .= " GROUP BY YEAR(o.date_added), WEEK(o.date_added)";
				break;
			case 'month':
				$sql .= " GROUP BY YEAR(o.date_added), MONTH(o.date_added)";
				break;
			case 'year':
				$sql .= " GROUP BY YEAR(o.date_added)";
				break;
		}

		$sql .= " ORDER BY date_start ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getRepeatPurchaseRate($data = array()) {
		$sql_from = "FROM `" . DB_PREFIX . "order` o";
		$sql_where = "WHERE o.order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$sql_where .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_date_start'])) {
			$sql_where .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql_where .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql_where .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql_where .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		if (!empty($data['filter_date_start'])) {
			$prev_cond = "AND DATE(o.date_added) < DATE('" . $this->db->escape($data['filter_date_start']) . "')";

			$sql = "SELECT
				COUNT(DISTINCT o.customer_id) AS total_customers,
				COUNT(DISTINCT CASE WHEN prev.customer_id IS NOT NULL THEN o.customer_id END) AS repeat_customers,
				COALESCE(ROUND(COUNT(DISTINCT CASE WHEN prev.customer_id IS NOT NULL THEN o.customer_id END) / NULLIF(COUNT(DISTINCT o.customer_id), 0) * 100, 2), 0) AS repeat_rate
			{$sql_from}
			LEFT JOIN (
				SELECT DISTINCT customer_id FROM `" . DB_PREFIX . "order` o
				WHERE order_status_id > '0' {$prev_cond}
			) prev ON (o.customer_id = prev.customer_id)
			{$sql_where}";
		} else {
			$sql = "SELECT
				COUNT(DISTINCT o.customer_id) AS total_customers,
				COUNT(DISTINCT CASE WHEN order_count >= 2 THEN o.customer_id END) AS repeat_customers,
				COALESCE(ROUND(COUNT(DISTINCT CASE WHEN order_count >= 2 THEN o.customer_id END) / NULLIF(COUNT(DISTINCT o.customer_id), 0) * 100, 2), 0) AS repeat_rate
			FROM (
				SELECT customer_id, COUNT(*) AS order_count
				{$sql_from} {$sql_where}
				GROUP BY customer_id
			) o";
		}

		$query = $this->db->query($sql);

		return $query->row;
	}

	public function getCancellationRate($data = array()) {
		$cancelled_id = $this->getCancelledStatusId();

		$sql = "SELECT
			COUNT(*) AS total_orders,
			SUM(CASE WHEN o.order_status_id = {$cancelled_id} THEN 1 ELSE 0 END) AS cancelled_orders,
			COALESCE(ROUND(SUM(CASE WHEN o.order_status_id = {$cancelled_id} THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100, 2), 0) AS cancellation_rate
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$query = $this->db->query($sql);

		return $query->row;
	}

	public function getOrdersByDayOfWeek($data = array()) {
		$sql = "SELECT DAYOFWEEK(o.date_added) AS day_of_week, COUNT(*) AS total
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$sql .= " GROUP BY DAYOFWEEK(o.date_added) ORDER BY day_of_week";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getOrdersByHour($data = array()) {
		$sql = "SELECT HOUR(o.date_added) AS hour, COUNT(*) AS total
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$sql .= " GROUP BY HOUR(o.date_added) ORDER BY hour";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getRevenueByCategory($data = array()) {
		$status_cond = "o.order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$status_cond = "o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		$sql = "SELECT
			cd.name AS category_name,
			COALESCE(SUM(op.quantity), 0) AS quantity_sold,
			COALESCE(SUM(op.total / o.currency_value), 0) AS revenue
		FROM " . DB_PREFIX . "order_product op
		INNER JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id)
		LEFT JOIN " . DB_PREFIX . "product p ON (op.product_id = p.product_id AND p.main_category_id > 0)
		LEFT JOIN " . DB_PREFIX . "category_description cd ON (p.main_category_id = cd.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "')
		WHERE {$status_cond}";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$sql .= " GROUP BY p.main_category_id ORDER BY revenue DESC LIMIT 10";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalReport($data = array()) {
		if (!empty($data['filter_group'])) {
			$group = $data['filter_group'];
		} else {
			$group = 'week';
		}

		switch ($group) {
			case 'day':
				$sql = "SELECT COUNT(DISTINCT YEAR(date_added), MONTH(date_added), DAY(date_added)) AS total FROM `" . DB_PREFIX . "order`";
				break;
			default:
			case 'week':
				$sql = "SELECT COUNT(DISTINCT YEAR(date_added), WEEK(date_added)) AS total FROM `" . DB_PREFIX . "order`";
				break;
			case 'month':
				$sql = "SELECT COUNT(DISTINCT YEAR(date_added), MONTH(date_added)) AS total FROM `" . DB_PREFIX . "order`";
				break;
			case 'year':
				$sql = "SELECT COUNT(DISTINCT YEAR(date_added)) AS total FROM `" . DB_PREFIX . "order`";
				break;
		}

		$sql .= " WHERE order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	public function getTrafficSources($data = array()) {
		$sql = "SELECT `source`, `medium`, COUNT(*) AS `visits` FROM `" . DB_PREFIX . "dockercart_traffic_source`";

		$conditions = array();

		if (!empty($data['filter_date_start'])) {
			$conditions[] = "DATE(`date_added`) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$conditions[] = "DATE(`date_added`) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$conditions[] = "`medium` = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if ($conditions) {
			$sql .= " WHERE " . implode(" AND ", $conditions);
		}

		$sql .= " GROUP BY `source`, `medium` ORDER BY `visits` DESC LIMIT 15";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Traffic conversion: sessions, orders and revenue per source/medium.
	 * Joins traffic sessions to orders via the session_id persisted on
	 * oc_order at checkout (order_claim is deleted after success, so it
	 * cannot be used as a join key here).
	 */
	public function getTrafficConversions($data = array()) {
		$conditions = array();

		if (!empty($data['filter_date_start'])) {
			$conditions[] = "DATE(t.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$conditions[] = "DATE(t.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$conditions[] = "t.medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		$sql = "SELECT
			t.source,
			t.medium,
			COUNT(DISTINCT t.session_id) AS sessions,
			COUNT(DISTINCT o.order_id) AS orders,
			COALESCE(SUM(o.total / o.currency_value), 0) AS revenue
		FROM `" . DB_PREFIX . "dockercart_traffic_source` t
		LEFT JOIN `" . DB_PREFIX . "order` o ON (o.session_id = t.session_id AND o.order_status_id > '0')";

		if ($conditions) {
			$sql .= " WHERE " . implode(" AND ", $conditions);
		}

		$sql .= " GROUP BY t.source, t.medium ORDER BY sessions DESC LIMIT 15";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Order distribution: total products, orders, avg items per order.
	 */
	public function getOrderItemsDistribution($data = array()) {
		$sql = "SELECT
			COUNT(DISTINCT o.order_id) AS total_orders,
			COALESCE(SUM(op_agg.quantity), 0) AS total_products,
			COALESCE(ROUND(SUM(op_agg.quantity) / NULLIF(COUNT(DISTINCT o.order_id), 0), 2), 0) AS avg_items
		FROM `" . DB_PREFIX . "order` o
		LEFT JOIN (
			SELECT order_id, SUM(quantity) AS quantity FROM `" . DB_PREFIX . "order_product` GROUP BY order_id
		) op_agg ON (op_agg.order_id = o.order_id)
		WHERE o.order_status_id > '0'";

		$sql .= $this->getOrderWhere($data);

		$query = $this->db->query($sql);

		return $query->row;
	}

	/**
	 * Top products by quantity sold.
	 */
	public function getProductsByQuantity($data = array()) {
		$status_cond = "o.order_status_id > '0'";

		if (!empty($data['filter_order_status_id'])) {
			$status_cond = "o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		$sql = "SELECT
			op.product_id,
			op.name,
			op.model,
			SUM(op.quantity) AS quantity,
			COUNT(DISTINCT o.order_id) AS orders,
			COALESCE(SUM(op.total / o.currency_value), 0) AS total
		FROM " . DB_PREFIX . "order_product op
		INNER JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id)
		WHERE {$status_cond}";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_utm_medium'])) {
			$sql .= " AND o.utm_medium = '" . $this->db->escape($data['filter_utm_medium']) . "'";
		}

		if (!empty($data['filter_source'])) {
			$sql .= " AND o.utm_source = '" . $this->db->escape($data['filter_source']) . "'";
		}

		$sql .= " GROUP BY op.product_id ORDER BY quantity DESC LIMIT 10";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Revenue/orders by traffic medium (channel).
	 */
	public function getTotalsByMedium($data = array()) {
		$sql = "SELECT
			o.utm_medium AS medium,
			COUNT(*) AS orders,
			COALESCE(SUM(o.total / o.currency_value), 0) AS revenue
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0' AND o.utm_medium <> ''";

		$sql .= $this->getOrderWhere($data);

		$sql .= " GROUP BY o.utm_medium ORDER BY revenue DESC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Sales from social networks: orders/revenue/customers by utm_source.
	 */
	public function getSocialSales($data = array()) {
		$sql = "SELECT
			o.utm_source AS source,
			COUNT(*) AS orders,
			COUNT(DISTINCT o.customer_id) AS customers,
			COALESCE(SUM(o.total / o.currency_value), 0) AS revenue
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0' AND o.utm_source <> '' AND o.utm_medium = 'social'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(o.date_added) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(o.date_added) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		if (!empty($data['filter_order_status_id'])) {
			$sql .= " AND o.order_status_id = '" . (int)$data['filter_order_status_id'] . "'";
		}

		$sql .= " GROUP BY o.utm_source ORDER BY revenue DESC LIMIT 15";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Traffic from social networks: visits by source.
	 */
	public function getSocialTraffic($data = array()) {
		$sql = "SELECT `source`, COUNT(*) AS visits FROM `" . DB_PREFIX . "dockercart_traffic_source`
		WHERE `medium` = 'social'";

		if (!empty($data['filter_date_start'])) {
			$sql .= " AND DATE(`date_added`) >= DATE('" . $this->db->escape($data['filter_date_start']) . "')";
		}

		if (!empty($data['filter_date_end'])) {
			$sql .= " AND DATE(`date_added`) <= DATE('" . $this->db->escape($data['filter_date_end']) . "')";
		}

		$sql .= " GROUP BY `source` ORDER BY `visits` DESC LIMIT 15";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Checkout funnel from order history: count of orders reaching each
	 * flow step (status), using config_order_flow_steps as the step chain.
	 */
	public function getCheckoutFunnel($data = array()) {
		$steps = array();

		foreach ((array)$this->config->get('config_order_flow_steps') as $step) {
			$steps[] = (int)$step;
		}

		$steps = array_values(array_unique(array_filter($steps)));

		if (!$steps) {
			$steps = array(1, 132, 133, 128, 129);
		}

		$step_columns = array();

		foreach ($steps as $i => $step) {
			$step_columns[] = "MAX(CASE WHEN o.order_status_id = '" . (int)$step . "' THEN 1 ELSE 0 END) AS step_" . $i;
		}

		$sql = "SELECT
			o.order_id,
			" . implode(",\n\t\t\t", $step_columns) . "
		FROM `" . DB_PREFIX . "order` o
		WHERE o.order_status_id > '0'";

		$sql .= $this->getOrderWhere($data);

		$sql .= " GROUP BY o.order_id";

		$query = $this->db->query($sql);

		$funnel = array();

		foreach ($steps as $i => $step) {
			$funnel[] = array(
				'step'  => $step,
				'count' => 0
			);
		}

		foreach ($query->rows as $row) {
			for ($i = 0; $i < count($steps); $i++) {
				if ((int)$row['step_' . $i] === 1) {
					$funnel[$i]['count']++;
				}
			}
		}

		return $funnel;
	}
}
