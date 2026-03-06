<?php
/**
 * DockerCart Checkout - Admin Model
 * Database operations for checkout module
 * 
 * @package    DockerCart Checkout
 * @author     mathflow-bit
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
        
        $sql = "SELECT step, COUNT(DISTINCT session_id) as count
                FROM `" . DB_PREFIX . "dockercart_checkout_analytics`
                WHERE 1=1";
        
        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }
        
        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }
        
        $sql .= " GROUP BY step";
        
        $query = $this->db->query($sql);
        
        foreach ($query->rows as $row) {
            if (isset($steps[$row['step']])) {
                $steps[$row['step']] = (int)$row['count'];
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
     * @param array $filter Filter options
     * @return int Seconds
     */
    public function getAverageCheckoutTime($filter = array()) {
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, 
                    (SELECT MIN(a2.date_added) 
                     FROM `" . DB_PREFIX . "dockercart_checkout_analytics` a2 
                     WHERE a2.session_id = a.session_id AND a2.step = 'cart'),
                    a.date_added
                )) as avg_time
                FROM `" . DB_PREFIX . "dockercart_checkout_analytics` a
                WHERE a.step = 'completed'";
        
        if (!empty($filter['date_start'])) {
            $sql .= " AND DATE(a.date_added) >= '" . $this->db->escape($filter['date_start']) . "'";
        }
        
        if (!empty($filter['date_end'])) {
            $sql .= " AND DATE(a.date_added) <= '" . $this->db->escape($filter['date_end']) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['avg_time'];
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
