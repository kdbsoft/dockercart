<?php
/**
 * DockerCart Universal Shipping Model (Admin)
 * Handles CRUD operations for shipping methods with database storage.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ModelExtensionShippingDockercartUniversal extends Model {
    /**
     * Ensure DB schema is compatible (cost column nullable).
     * Run on model construction so existing installs are migrated transparently.
     */
    public function __construct($registry) {
        parent::__construct($registry);

        $this->ensureCostNullable();
    }
    
    /**
     * Install database tables
     */
    public function install() {
        // Main shipping methods table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_universal_shipping` (
                `method_id` INT(11) NOT NULL AUTO_INCREMENT,
                `cost` DECIMAL(15,4) NULL DEFAULT NULL,
                `cost_type` VARCHAR(32) NOT NULL DEFAULT 'fixed',
                `weight_rates` TEXT,
                `geo_zone_id` INT(11) NOT NULL DEFAULT '0',
                `tax_class_id` INT(11) NOT NULL DEFAULT '0',
                `min_total` DECIMAL(15,4) DEFAULT NULL,
                `max_total` DECIMAL(15,4) DEFAULT NULL,
                `min_weight` DECIMAL(15,8) DEFAULT NULL,
                `max_weight` DECIMAL(15,8) DEFAULT NULL,
                `free_shipping_threshold` DECIMAL(15,4) DEFAULT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT '1',
                `sort_order` INT(3) NOT NULL DEFAULT '0',
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME NOT NULL,
                PRIMARY KEY (`method_id`),
                KEY `idx_status` (`status`),
                KEY `idx_geo_zone` (`geo_zone_id`),
                KEY `idx_sort_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Method descriptions (multilingual)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_universal_shipping_description` (
                `method_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `delivery_time` VARCHAR(128) DEFAULT NULL,
                PRIMARY KEY (`method_id`, `language_id`),
                KEY `idx_language` (`language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    /**
     * Uninstall database tables
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_universal_shipping_description`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_universal_shipping`");
    }
    
    /**
     * Add a new shipping method
     */
    public function addMethod(array $data): int {
        // Build parts safely to avoid implicit 0.0000 when DB column is not nullable
        $cost_sql = (isset($data['cost']) && $data['cost'] !== '' ? "'" . (float)$data['cost'] . "'" : "NULL");

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "dockercart_universal_shipping` SET
            `cost` = " . $cost_sql . ",
            `cost_type` = '" . $this->db->escape($data['cost_type'] ?? 'fixed') . "',
            `weight_rates` = '" . $this->db->escape($data['weight_rates'] ?? '') . "',
            `geo_zone_id` = '" . (int)($data['geo_zone_id'] ?? 0) . "',
            `tax_class_id` = '" . (int)($data['tax_class_id'] ?? 0) . "',
            `min_total` = " . ($data['min_total'] !== '' ? "'" . (float)$data['min_total'] . "'" : "NULL") . ",
            `max_total` = " . ($data['max_total'] !== '' ? "'" . (float)$data['max_total'] . "'" : "NULL") . ",
            `min_weight` = " . ($data['min_weight'] !== '' ? "'" . (float)$data['min_weight'] . "'" : "NULL") . ",
            `max_weight` = " . ($data['max_weight'] !== '' ? "'" . (float)$data['max_weight'] . "'" : "NULL") . ",
            `free_shipping_threshold` = " . ($data['free_shipping_threshold'] !== '' ? "'" . (float)$data['free_shipping_threshold'] . "'" : "NULL") . ",
            `status` = '" . (int)($data['status'] ?? 1) . "',
            `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
            `date_added` = NOW(),
            `date_modified` = NOW()
        ");
        
        $method_id = $this->db->getLastId();
        
        // Add descriptions for each language
        if (!empty($data['method_description'])) {
            foreach ($data['method_description'] as $language_id => $value) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "dockercart_universal_shipping_description` SET
                    `method_id` = '" . (int)$method_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name` = '" . $this->db->escape($value['name'] ?? '') . "',
                    `description` = '" . $this->db->escape($value['description'] ?? '') . "',
                    `delivery_time` = '" . $this->db->escape($value['delivery_time'] ?? '') . "'
                ");
            }
        }
        
        return $method_id;
    }
    
    /**
     * Edit existing shipping method
     */
    public function editMethod(int $method_id, array $data): void {
        $cost_sql = (isset($data['cost']) && $data['cost'] !== '' ? "'" . (float)$data['cost'] . "'" : "NULL");

        $this->db->query("
            UPDATE `" . DB_PREFIX . "dockercart_universal_shipping` SET
            `cost` = " . $cost_sql . ",
            `cost_type` = '" . $this->db->escape($data['cost_type'] ?? 'fixed') . "',
            `weight_rates` = '" . $this->db->escape($data['weight_rates'] ?? '') . "',
            `geo_zone_id` = '" . (int)($data['geo_zone_id'] ?? 0) . "',
            `tax_class_id` = '" . (int)($data['tax_class_id'] ?? 0) . "',
            `min_total` = " . ($data['min_total'] !== '' ? "'" . (float)$data['min_total'] . "'" : "NULL") . ",
            `max_total` = " . ($data['max_total'] !== '' ? "'" . (float)$data['max_total'] . "'" : "NULL") . ",
            `min_weight` = " . ($data['min_weight'] !== '' ? "'" . (float)$data['min_weight'] . "'" : "NULL") . ",
            `max_weight` = " . ($data['max_weight'] !== '' ? "'" . (float)$data['max_weight'] . "'" : "NULL") . ",
            `free_shipping_threshold` = " . ($data['free_shipping_threshold'] !== '' ? "'" . (float)$data['free_shipping_threshold'] . "'" : "NULL") . ",
            `status` = '" . (int)($data['status'] ?? 1) . "',
            `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
            `date_modified` = NOW()
            WHERE `method_id` = '" . (int)$method_id . "'
        ");
        
        // Update descriptions
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_shipping_description` WHERE `method_id` = '" . (int)$method_id . "'");
        
        if (!empty($data['method_description'])) {
            foreach ($data['method_description'] as $language_id => $value) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "dockercart_universal_shipping_description` SET
                    `method_id` = '" . (int)$method_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name` = '" . $this->db->escape($value['name'] ?? '') . "',
                    `description` = '" . $this->db->escape($value['description'] ?? '') . "',
                    `delivery_time` = '" . $this->db->escape($value['delivery_time'] ?? '') . "'
                ");
            }
        }
    }
    
    /**
     * Delete shipping method
     */
    public function deleteMethod(int $method_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_shipping` WHERE `method_id` = '" . (int)$method_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_shipping_description` WHERE `method_id` = '" . (int)$method_id . "'");
    }

    /**
     * Ensure the cost column is nullable on existing installations.
     */
    protected function ensureCostNullable(): void {
        // Check if table exists first
        $table = DB_PREFIX . 'dockercart_universal_shipping';

        $check = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table) . "'");

        if (!$check->num_rows) {
            return;
        }

        $col = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table) . "` LIKE 'cost'");

        if ($col->num_rows) {
            $null = $col->row['Null'] ?? $col->row['Null'];

            if (isset($null) && strtoupper($null) === 'NO') {
                // Alter column to allow NULL (idempotent)
                $this->db->query("ALTER TABLE `" . $this->db->escape($table) . "` MODIFY `cost` DECIMAL(15,4) NULL DEFAULT NULL");
            }
        }
    }
    
    /**
     * Get single shipping method
     */
    public function getMethod(int $method_id): ?array {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_universal_shipping`
            WHERE `method_id` = '" . (int)$method_id . "'
        ");
        
        return $query->num_rows ? $query->row : null;
    }
    
    /**
     * Get all shipping methods with descriptions for current language
     */
    public function getMethods(array $data = []): array {
        $sql = "
            SELECT m.*, md.name, md.description, md.delivery_time
            FROM `" . DB_PREFIX . "dockercart_universal_shipping` m
            LEFT JOIN `" . DB_PREFIX . "dockercart_universal_shipping_description` md
                ON (m.method_id = md.method_id AND md.language_id = '" . (int)$this->config->get('config_language_id') . "')
        ";
        
        $sql .= " ORDER BY m.sort_order ASC, m.method_id ASC";
        
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
     * Get method descriptions for all languages
     */
    public function getMethodDescriptions(int $method_id): array {
        $method_description_data = [];
        
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_universal_shipping_description`
            WHERE `method_id` = '" . (int)$method_id . "'
        ");
        
        foreach ($query->rows as $result) {
            $method_description_data[$result['language_id']] = [
                'name'          => $result['name'],
                'description'   => $result['description'],
                'delivery_time' => $result['delivery_time']
            ];
        }
        
        return $method_description_data;
    }
    
    /**
     * Get total count of shipping methods
     */
    public function getTotalMethods(): int {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_universal_shipping`");
        
        return (int)$query->row['total'];
    }
}
