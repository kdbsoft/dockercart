<?php
/**
 * DockerCart Export YML Model
 * 
 * Handles database operations for YML export profiles
 * 
 * @package DockerCart
 * @subpackage Export YML
 * @version 1.0.0
 */

class ModelExtensionFeedDockercartExportYml extends Model {

    /**
     * Install module - create database tables
     */
    public function install() {
        // Create profiles table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_export_yml_profile` (
                `profile_id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `store_id` int(11) NOT NULL DEFAULT '0',
                `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
                `language_id` int(11) NOT NULL DEFAULT '1',
                `shop_name` varchar(255) NOT NULL DEFAULT '',
                `company_name` varchar(255) NOT NULL DEFAULT '',
                `status` tinyint(1) NOT NULL DEFAULT '1',
                `max_products` int(11) NOT NULL DEFAULT '50000',
                `cache_ttl` int(11) NOT NULL DEFAULT '3600',
                `split_files` tinyint(1) NOT NULL DEFAULT '0',
                `products_per_file` int(11) NOT NULL DEFAULT '10000',
                `settings` text,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`profile_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create profile filter table (for categories, manufacturers, etc.)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_export_yml_profile_filter` (
                `filter_id` int(11) NOT NULL AUTO_INCREMENT,
                `profile_id` int(11) NOT NULL,
                `filter_type` varchar(50) NOT NULL,
                `filter_value` varchar(255) NOT NULL,
                PRIMARY KEY (`filter_id`),
                KEY `profile_id` (`profile_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create default profile
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "dockercart_export_yml_profile` 
            (`name`, `store_id`, `currency_code`, `language_id`, `shop_name`, `company_name`, `status`, `max_products`, `cache_ttl`, `date_added`, `date_modified`)
            VALUES 
            ('Default YML Export', 0, 'USD', 1, 'My Shop', 'My Company', 1, 50000, 3600, NOW(), NOW())
        ");
    }

    /**
     * Uninstall module - drop database tables
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_export_yml_profile_filter`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_export_yml_profile`");
    }

    /**
     * Get all profiles
     * 
     * @return array
     */
    public function getProfiles() {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_export_yml_profile`
            ORDER BY `name` ASC
        ");

        $profiles = array();
        foreach ($query->rows as $row) {
            $row['settings'] = !empty($row['settings']) ? json_decode($row['settings'], true) : array();
            $row['filters'] = $this->getProfileFilters($row['profile_id']);
            $profiles[] = $row;
        }

        return $profiles;
    }

    /**
     * Get profile by ID
     * 
     * @param int $profile_id
     * @return array|null
     */
    public function getProfile($profile_id) {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_export_yml_profile`
            WHERE `profile_id` = '" . (int)$profile_id . "'
        ");

        if ($query->num_rows) {
            $profile = $query->row;
            $profile['settings'] = !empty($profile['settings']) ? json_decode($profile['settings'], true) : array();
            $profile['filters'] = $this->getProfileFilters($profile_id);
            return $profile;
        }

        return null;
    }

    /**
     * Get profile filters
     * 
     * @param int $profile_id
     * @return array
     */
    public function getProfileFilters($profile_id) {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "dockercart_export_yml_profile_filter`
            WHERE `profile_id` = '" . (int)$profile_id . "'
        ");

        $filters = array();
        foreach ($query->rows as $row) {
            $filters[$row['filter_type']][] = $row['filter_value'];
        }

        return $filters;
    }

    /**
     * Add new profile
     * 
     * @param array $data
     * @return int Profile ID
     */
    public function addProfile($data) {
        $settings = array();
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = $data['settings'];
        }

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "dockercart_export_yml_profile`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `store_id` = '" . (int)(isset($data['store_id']) ? $data['store_id'] : 0) . "',
                `currency_code` = '" . $this->db->escape(isset($data['currency_code']) ? $data['currency_code'] : 'USD') . "',
                `language_id` = '" . (int)(isset($data['language_id']) ? $data['language_id'] : 1) . "',
                `shop_name` = '" . $this->db->escape(isset($data['shop_name']) ? $data['shop_name'] : '') . "',
                `company_name` = '" . $this->db->escape(isset($data['company_name']) ? $data['company_name'] : '') . "',
                `status` = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
                `max_products` = '" . (int)(isset($data['max_products']) ? $data['max_products'] : 50000) . "',
                `cache_ttl` = '" . (int)(isset($data['cache_ttl']) ? $data['cache_ttl'] : 3600) . "',
                `split_files` = '" . (int)(isset($data['split_files']) ? $data['split_files'] : 0) . "',
                `products_per_file` = '" . (int)(isset($data['products_per_file']) ? $data['products_per_file'] : 10000) . "',
                `settings` = '" . $this->db->escape(json_encode($settings)) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()
        ");

        $profile_id = $this->db->getLastId();

        // Add filters
        if (isset($data['filters']) && is_array($data['filters'])) {
            $this->setProfileFilters($profile_id, $data['filters']);
        }

        return $profile_id;
    }

    /**
     * Update profile
     * 
     * @param int $profile_id
     * @param array $data
     */
    public function updateProfile($profile_id, $data) {
        $settings = array();
        if (isset($data['settings']) && is_array($data['settings'])) {
            $settings = $data['settings'];
        }

        $this->db->query("
            UPDATE `" . DB_PREFIX . "dockercart_export_yml_profile`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `store_id` = '" . (int)(isset($data['store_id']) ? $data['store_id'] : 0) . "',
                `currency_code` = '" . $this->db->escape(isset($data['currency_code']) ? $data['currency_code'] : 'USD') . "',
                `language_id` = '" . (int)(isset($data['language_id']) ? $data['language_id'] : 1) . "',
                `shop_name` = '" . $this->db->escape(isset($data['shop_name']) ? $data['shop_name'] : '') . "',
                `company_name` = '" . $this->db->escape(isset($data['company_name']) ? $data['company_name'] : '') . "',
                `status` = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
                `max_products` = '" . (int)(isset($data['max_products']) ? $data['max_products'] : 50000) . "',
                `cache_ttl` = '" . (int)(isset($data['cache_ttl']) ? $data['cache_ttl'] : 3600) . "',
                `split_files` = '" . (int)(isset($data['split_files']) ? $data['split_files'] : 0) . "',
                `products_per_file` = '" . (int)(isset($data['products_per_file']) ? $data['products_per_file'] : 10000) . "',
                `settings` = '" . $this->db->escape(json_encode($settings)) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'
        ");

        // Update filters
        if (isset($data['filters']) && is_array($data['filters'])) {
            $this->setProfileFilters($profile_id, $data['filters']);
        }
    }

    /**
     * Delete profile
     * 
     * @param int $profile_id
     */
    public function deleteProfile($profile_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_export_yml_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_export_yml_profile_filter` WHERE `profile_id` = '" . (int)$profile_id . "'");
    }

    /**
     * Set profile filters
     * 
     * @param int $profile_id
     * @param array $filters
     */
    private function setProfileFilters($profile_id, $filters) {
        // Delete existing filters
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_export_yml_profile_filter` WHERE `profile_id` = '" . (int)$profile_id . "'");

        // Add new filters
        foreach ($filters as $filter_type => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    $this->db->query("
                        INSERT INTO `" . DB_PREFIX . "dockercart_export_yml_profile_filter`
                        SET
                            `profile_id` = '" . (int)$profile_id . "',
                            `filter_type` = '" . $this->db->escape($filter_type) . "',
                            `filter_value` = '" . $this->db->escape($value) . "'
                    ");
                }
            }
        }
    }

    /**
     * Get products for YML export based on profile settings
     * 
     * @param int $profile_id
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getProductsForExport($profile_id, $start = 0, $limit = 1000) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return array();
        }

        $language_id = $profile['language_id'];
        $store_id = $profile['store_id'];

        $sql = "
            SELECT DISTINCT
                p.product_id,
                pd.name,
                pd.description,
                p.model,
                p.sku,
                p.upc,
                p.ean,
                p.jan,
                p.isbn,
                p.mpn,
                p.price,
                p.quantity,
                p.image,
                p.manufacturer_id,
                m.name AS manufacturer,
                p.weight,
                p.length,
                p.width,
                p.height,
                p.status,
                p.date_available
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
            LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
            WHERE pd.language_id = '" . (int)$language_id . "'
            AND p2s.store_id = '" . (int)$store_id . "'
            AND p.status = '1'
            AND p.quantity > 0
        ";

        // Apply category filters
        if (!empty($profile['filters']['category'])) {
            $category_ids = array_map('intval', $profile['filters']['category']);
            $sql .= " AND p2c.category_id IN (" . implode(',', $category_ids) . ")";
        }

        // Apply manufacturer filters
        if (!empty($profile['filters']['manufacturer'])) {
            $manufacturer_ids = array_map('intval', $profile['filters']['manufacturer']);
            $sql .= " AND p.manufacturer_id IN (" . implode(',', $manufacturer_ids) . ")";
        }

        // Apply price filters
        if (!empty($profile['filters']['min_price'])) {
            $sql .= " AND p.price >= '" . (float)$profile['filters']['min_price'][0] . "'";
        }
        if (!empty($profile['filters']['max_price'])) {
            $sql .= " AND p.price <= '" . (float)$profile['filters']['max_price'][0] . "'";
        }

        // Apply quantity filter
        if (!empty($profile['filters']['min_quantity'])) {
            $sql .= " AND p.quantity >= '" . (int)$profile['filters']['min_quantity'][0] . "'";
        }

        // Apply weight filter
        if (!empty($profile['filters']['min_weight'])) {
            $sql .= " AND p.weight >= '" . (float)$profile['filters']['min_weight'][0] . "'";
        }
        if (!empty($profile['filters']['max_weight'])) {
            $sql .= " AND p.weight <= '" . (float)$profile['filters']['max_weight'][0] . "'";
        }

        // Apply tag filter
        if (!empty($profile['filters']['tag'])) {
            $sql .= " AND p.tag LIKE '%" . $this->db->escape($profile['filters']['tag'][0]) . "%'";
        }

        // Only products with special prices (if filter enabled)
        if (!empty($profile['filters']['only_special'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.date_start <= NOW() AND (ps.date_end = '0000-00-00' OR ps.date_end >= NOW()))";
        }

        $sql .= " ORDER BY p.product_id ASC";
        $sql .= " LIMIT " . (int)$start . "," . (int)$limit;

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Get total products count for export
     * 
     * @param int $profile_id
     * @return int
     */
    public function getTotalProductsForExport($profile_id) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return 0;
        }

        $language_id = $profile['language_id'];
        $store_id = $profile['store_id'];

        $sql = "
            SELECT COUNT(DISTINCT p.product_id) AS total
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
            WHERE pd.language_id = '" . (int)$language_id . "'
            AND p2s.store_id = '" . (int)$store_id . "'
            AND p.status = '1'
            AND p.quantity > 0
        ";

        // Apply category filters
        if (!empty($profile['filters']['category'])) {
            $category_ids = array_map('intval', $profile['filters']['category']);
            $sql .= " AND p2c.category_id IN (" . implode(',', $category_ids) . ")";
        }

        // Apply manufacturer filters
        if (!empty($profile['filters']['manufacturer'])) {
            $manufacturer_ids = array_map('intval', $profile['filters']['manufacturer']);
            $sql .= " AND p.manufacturer_id IN (" . implode(',', $manufacturer_ids) . ")";
        }

        // Apply price filters
        if (!empty($profile['filters']['min_price'])) {
            $sql .= " AND p.price >= '" . (float)$profile['filters']['min_price'][0] . "'";
        }
        if (!empty($profile['filters']['max_price'])) {
            $sql .= " AND p.price <= '" . (float)$profile['filters']['max_price'][0] . "'";
        }

        // Apply quantity filter
        if (!empty($profile['filters']['min_quantity'])) {
            $sql .= " AND p.quantity >= '" . (int)$profile['filters']['min_quantity'][0] . "'";
        }

        // Apply weight filter
        if (!empty($profile['filters']['min_weight'])) {
            $sql .= " AND p.weight >= '" . (float)$profile['filters']['min_weight'][0] . "'";
        }
        if (!empty($profile['filters']['max_weight'])) {
            $sql .= " AND p.weight <= '" . (float)$profile['filters']['max_weight'][0] . "'";
        }

        // Apply tag filter
        if (!empty($profile['filters']['tag'])) {
            $sql .= " AND p.tag LIKE '%" . $this->db->escape($profile['filters']['tag'][0]) . "%'";
        }

        // Only products with special prices
        if (!empty($profile['filters']['only_special'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.date_start <= NOW() AND (ps.date_end = '0000-00-00' OR ps.date_end >= NOW()))";
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    /**
     * Get categories for YML export
     * 
     * @param int $profile_id
     * @return array
     */
    public function getCategoriesForExport($profile_id) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return array();
        }

        $language_id = $profile['language_id'];

        $sql = "
            SELECT DISTINCT
                c.category_id,
                cd.name,
                c.parent_id,
                c.sort_order
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id)
            WHERE cd.language_id = '" . (int)$language_id . "'
            AND c.status = '1'
        ";

        // Apply category filters
        if (!empty($profile['filters']['category'])) {
            $category_ids = array_map('intval', $profile['filters']['category']);
            $sql .= " AND c.category_id IN (" . implode(',', $category_ids) . ")";
        }

        $sql .= " ORDER BY c.sort_order ASC, cd.name ASC";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Get product images
     * 
     * @param int $product_id
     * @return array
     */
    public function getProductImages($product_id) {
        $query = $this->db->query("
            SELECT image
            FROM `" . DB_PREFIX . "product_image`
            WHERE product_id = '" . (int)$product_id . "'
            ORDER BY sort_order ASC
        ");

        return $query->rows;
    }

    /**
     * Get product attributes for YML params
     * 
     * @param int $product_id
     * @param int $language_id
     * @return array
     */
    public function getProductAttributes($product_id, $language_id) {
        $query = $this->db->query("
            SELECT
                ad.name AS attribute,
                pa.text AS value
            FROM `" . DB_PREFIX . "product_attribute` pa
            LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (pa.attribute_id = ad.attribute_id)
            WHERE pa.product_id = '" . (int)$product_id . "'
            AND pa.language_id = '" . (int)$language_id . "'
            AND ad.language_id = '" . (int)$language_id . "'
        ");

        return $query->rows;
    }
}
