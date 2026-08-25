<?php
/**
 * DockerCart Checkout - Admin Model
 * Database operations for checkout module
 *
 * @package    DockerCart Checkout
 * @author     kdbsoft
 * @license    Commercial License
 */

class ModelExtensionModuleDockerCartCheckout extends Model {

    // Constants
    const DEFAULT_CLEANUP_DAYS = 90;
    const STATUS_RECOVERED = 1;
    const STATUS_ABANDONED = 0;

    /**
     * Install module - create tables and default settings
     *
     * @return void
     */
    public function install() {
        // Create analytics table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_checkout_analytics` (
                `analytics_id` int(11) NOT NULL AUTO_INCREMENT,
                `session_id` varchar(255) NOT NULL,
                `customer_id` int(11) DEFAULT 0,
                `step` varchar(50) NOT NULL,
                `data` text,
                `date_added` datetime NOT NULL,
                PRIMARY KEY (`analytics_id`),
                KEY `session_id` (`session_id`),
                KEY `step` (`step`),
                KEY `date_added` (`date_added`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create abandoned cart table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_checkout_abandoned` (
                `abandoned_id` int(11) NOT NULL AUTO_INCREMENT,
                `session_id` varchar(255) NOT NULL,
                `customer_id` int(11) DEFAULT 0,
                `email` varchar(255) DEFAULT NULL,
                `phone` varchar(50) DEFAULT NULL,
                `cart_data` text,
                `address_data` text,
                `last_step` varchar(50) NOT NULL,
                `recovered` tinyint(1) DEFAULT 0,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`abandoned_id`),
                KEY `session_id` (`session_id`),
                KEY `customer_id` (`customer_id`),
                KEY `email` (`email`),
                KEY `recovered` (`recovered`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Uninstall module - clean up tables
     *
     * @return void
     */
    public function uninstall() {
        // Optionally drop tables (commented out to preserve data)
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_checkout_analytics`");
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_checkout_abandoned`");
    }

    /**
     * Get analytics data
     *
     * @param array $filter Filter options
     * @return array
     */
    public function getAnalytics($filter = array()) {
        $sql = "SELECT
                    step,
                    COUNT(*) as total,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    DATE(date_added) as date
                FROM `" . DB_PREFIX . "dockercart_checkout_analytics`
                WHERE 1=1";

        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }

        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }

        $sql .= " GROUP BY step, DATE(date_added)
                  ORDER BY date_added DESC";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Get checkout funnel statistics
     *
     * A session's funnel position is the deepest step it reached and each
     * step counts sessions that got at least that far, so the counts stay
     * monotonic even when steps are skipped or hit without loading the
     * checkout page first.
     *
     * @param array $filter Filter options
     * @return array
     */
    public function getCheckoutFunnel($filter = array()) {
        $steps = array(
            'cart' => 0,
            'customer' => 0,
            'shipping_address' => 0,
            'shipping_method' => 0,
            'payment_method' => 0,
            'confirm' => 0,
            'completed' => 0
        );

        // Rank per event; payment_address shares shipping_address's rank so
        // sessions that only saved a payment address stay in funnel.
        $sql = "SELECT SUM(t.max_ord >= 1) AS cart,
                       SUM(t.max_ord >= 2) AS customer,
                       SUM(t.max_ord >= 3) AS shipping_address,
                       SUM(t.max_ord >= 4) AS shipping_method,
                       SUM(t.max_ord >= 5) AS payment_method,
                       SUM(t.max_ord >= 6) AS confirm,
                       SUM(t.max_ord >= 7) AS completed
                FROM (
                    SELECT session_id,
                           MAX(CASE step
                               WHEN 'cart' THEN 1
                               WHEN 'customer' THEN 2
                               WHEN 'shipping_address' THEN 3
                               WHEN 'payment_address' THEN 3
                               WHEN 'shipping_method' THEN 4
                               WHEN 'payment_method' THEN 5
                               WHEN 'confirm' THEN 6
                               WHEN 'completed' THEN 7
                               ELSE 0 END) AS max_ord
                    FROM `" . DB_PREFIX . "dockercart_checkout_analytics`
                    WHERE 1=1";

        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }

        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }

        $sql .= " GROUP BY session_id
                ) t";

        $query = $this->db->query($sql);

        if ($query->num_rows) {
            foreach ($steps as $step => $count) {
                $steps[$step] = (int)$query->row[$step];
            }
        }

        return $steps;
    }

    /**
     * Get abandoned carts
     *
     * @param array $filter Filter options
     * @return array
     */
    public function getAbandonedCarts($filter = array()) {
        $sql = "SELECT *
                FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
                WHERE recovered = " . self::STATUS_ABANDONED;

        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }

        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }

        if (!empty($filter['email'])) {
            $sql .= " AND email LIKE '%" . $this->db->escape($filter['email']) . "%'";
        }

        $sql .= " ORDER BY date_added DESC";

        if (!empty($filter['limit'])) {
            $sql .= " LIMIT " . (int)$filter['start'] . ", " . (int)$filter['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Count abandoned carts
     *
     * @param array $filter Filter options
     * @return int
     */
    public function getTotalAbandonedCarts($filter = array()) {
        $sql = "SELECT COUNT(*) as total
                FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
                WHERE recovered = " . self::STATUS_ABANDONED;

        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }

        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    /**
     * Mark abandoned cart as recovered
     *
     * @param int $abandoned_id
     * @return void
     */
    public function markRecovered($abandoned_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
                          SET recovered = " . self::STATUS_RECOVERED . ",
                              restore_token = NULL,
                              restore_expires = NULL,
                              date_modified = NOW()
                          WHERE abandoned_id = " . (int)$abandoned_id);
    }

    /**
     * Generate a one-time restore token for an abandoned cart
     *
     * @param int $abandoned_id
     * @param int $ttl_days Token lifetime in days
     * @return string The generated token
     */
    public function createRestoreToken($abandoned_id, $ttl_days = 7) {
        $token = bin2hex(random_bytes(24));

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
                          SET restore_token = '" . $this->db->escape($token) . "',
                              restore_expires = DATE_ADD(NOW(), INTERVAL " . (int)$ttl_days . " DAY),
                              date_modified = NOW()
                          WHERE abandoned_id = " . (int)$abandoned_id);

        return $token;
    }

    /**
     * Invalidate the restore token of an abandoned cart
     *
     * @param int $abandoned_id
     * @return void
     */
    public function clearRestoreToken($abandoned_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
                          SET restore_token = NULL,
                              restore_expires = NULL,
                              date_modified = NOW()
                          WHERE abandoned_id = " . (int)$abandoned_id);
    }

    /**
     * Delete old analytics data
     *
     * @param int $days Days to keep
     * @return int Rows deleted
     */
    public function cleanupAnalytics($days = self::DEFAULT_CLEANUP_DAYS) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_checkout_analytics`
                          WHERE date_added < DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)");

        return $this->db->countAffected();
    }

    /**
     * Get conversion rate
     *
     * @param array $filter Filter options
     * @return float
     */
    public function getConversionRate($filter = array()) {
        $funnel = $this->getCheckoutFunnel($filter);

        $started = $funnel['cart'];
        $completed = $funnel['completed'];

        if ($started > 0) {
            return round(($completed / $started) * 100, 2);
        }

        return 0;
    }

    /**
     * Get average checkout time
     *
     * Measured per session from its first cart step to its completed step;
     * sessions missing either event are ignored.
     *
     * @param array $filter Filter options
     * @return int Seconds
     */
    public function getAverageCheckoutTime($filter = array()) {
        $sql = "SELECT AVG(t.duration) AS avg_time
                FROM (
                    SELECT TIMESTAMPDIFF(SECOND,
                        MIN(CASE WHEN step = 'cart' THEN date_added END),
                        MIN(CASE WHEN step = 'completed' THEN date_added END)
                    ) AS duration
                    FROM `" . DB_PREFIX . "dockercart_checkout_analytics`
                    WHERE 1=1";

        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }

        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }

        $sql .= " GROUP BY session_id
                ) t";

        $query = $this->db->query($sql);

        return (int)($query->row['avg_time'] ?? 0);
    }

    /**
     * Get top drop-off step
     *
     * @param array $filter Filter options
     * @return array
     */
    public function getTopDropOffStep($filter = array()) {
        $funnel = $this->getCheckoutFunnel($filter);

        $steps = array_keys($funnel);
        $maxDrop = 0;
        $dropStep = '';

        for ($i = 0; $i < count($steps) - 1; $i++) {
            $current = $funnel[$steps[$i]];
            $next = $funnel[$steps[$i + 1]];

            if ($current > 0) {
                $dropRate = (($current - $next) / $current) * 100;

                if ($dropRate > $maxDrop) {
                    $maxDrop = $dropRate;
                    $dropStep = $steps[$i];
                }
            }
        }

        return array(
            'step' => $dropStep,
            'drop_rate' => round($maxDrop, 2)
        );
    }
}
