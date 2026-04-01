<?php
/**
 * DockerCart Universal Payment Model (Admin)
 * Handles CRUD operations for payment methods with database storage.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ModelExtensionPaymentDockercartUniversal extends Model {
    /**
     * Install database tables
     */
    public function install() {
        // Main payment methods table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_universal_payment` (
                `method_id` INT(11) NOT NULL AUTO_INCREMENT,
                `geo_zone_id` INT(11) NOT NULL DEFAULT '0',
                `min_total` DECIMAL(15,4) DEFAULT NULL,
                `max_total` DECIMAL(15,4) DEFAULT NULL,
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
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_universal_payment_description` (
                `method_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT,
                PRIMARY KEY (`method_id`, `language_id`),
                KEY `idx_language` (`language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Uninstall database tables
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_universal_payment_description`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_universal_payment`");
    }

    /**
     * Add a new payment method
     */
    public function addMethod(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "dockercart_universal_payment` SET
            `geo_zone_id` = '" . (int)($data['geo_zone_id'] ?? 0) . "',
            `min_total` = " . ($data['min_total'] !== '' ? "'" . (float)$data['min_total'] . "'" : "NULL") . ",
            `max_total` = " . ($data['max_total'] !== '' ? "'" . (float)$data['max_total'] . "'" : "NULL") . ",
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
                    INSERT INTO `" . DB_PREFIX . "dockercart_universal_payment_description` SET
                    `method_id` = '" . (int)$method_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name` = '" . $this->db->escape($value['name'] ?? '') . "',
                    `description` = '" . $this->db->escape($value['description'] ?? '') . "'
                ");
            }
        }

        return $method_id;
    }

    /**
     * Edit existing payment method
     */
    public function editMethod(int $method_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "dockercart_universal_payment` SET
            `geo_zone_id` = '" . (int)($data['geo_zone_id'] ?? 0) . "',
            `min_total` = " . ($data['min_total'] !== '' ? "'" . (float)$data['min_total'] . "'" : "NULL") . ",
            `max_total` = " . ($data['max_total'] !== '' ? "'" . (float)$data['max_total'] . "'" : "NULL") . ",
            `status` = '" . (int)($data['status'] ?? 1) . "',
            `sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
            `date_modified` = NOW()
            WHERE `method_id` = '" . (int)$method_id . "'
        ");

        // Update descriptions
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_payment_description` WHERE `method_id` = '" . (int)$method_id . "'");

        if (!empty($data['method_description'])) {
            foreach ($data['method_description'] as $language_id => $value) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "dockercart_universal_payment_description` SET
                    `method_id` = '" . (int)$method_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `name` = '" . $this->db->escape($value['name'] ?? '') . "',
                    `description` = '" . $this->db->escape($value['description'] ?? '') . "'
                ");
            }
        }
    }

    /**
     * Delete payment method
     */
    public function deleteMethod(int $method_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_payment` WHERE `method_id` = '" . (int)$method_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_universal_payment_description` WHERE `method_id` = '" . (int)$method_id . "'");
    }

    /**
     * Get single payment method
     */
    public function getMethod(int $method_id): ?array {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_universal_payment`
            WHERE `method_id` = '" . (int)$method_id . "'
        ");

        return $query->num_rows ? $query->row : null;
    }

    /**
     * Get all payment methods with descriptions for current language
     */
    public function getMethods(array $data = []): array {
        $sql = "
            SELECT m.*, md.name, md.description
            FROM `" . DB_PREFIX . "dockercart_universal_payment` m
            LEFT JOIN `" . DB_PREFIX . "dockercart_universal_payment_description` md
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
            SELECT * FROM `" . DB_PREFIX . "dockercart_universal_payment_description`
            WHERE `method_id` = '" . (int)$method_id . "'
        ");

        foreach ($query->rows as $result) {
            $method_description_data[$result['language_id']] = [
                'name'        => $result['name'],
                'description' => $result['description']
            ];
        }

        return $method_description_data;
    }

    /**
     * Get total count of payment methods
     */
    public function getTotalMethods(): int {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_universal_payment`");

        return (int)$query->row['total'];
    }
}
