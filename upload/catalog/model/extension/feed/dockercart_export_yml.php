<?php
/**
 * DockerCart Export YML Catalog Model
 * 
 * Provides data access methods for YML generation
 * 
 * @package DockerCart
 * @subpackage Export YML
 * @version 1.0.0
 */

class ModelExtensionFeedDockercartExportYml extends Model {

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
     * Get products for YML export based on profile settings
     * 
     * @param int $profile_id
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getProductsForExport($profile_id, $start = 0, $limit = 100, $language_id = null) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return array();
        }

        // Use provided language_id or fall back to profile's language
        if ($language_id === null) {
            $language_id = $profile['language_id'];
        }
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
    public function getTotalProductsForExport($profile_id, $language_id = null) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return 0;
        }

        // Use provided language_id or fall back to profile's language
        if ($language_id === null) {
            $language_id = $profile['language_id'];
        }
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

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    /**
     * Get categories for YML export
     * 
     * @param int $profile_id
     * @param int|null $language_id
     * @return array
     */
    public function getCategoriesForExport($profile_id, $language_id = null) {
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return array();
        }

        if ($language_id === null) {
            $language_id = $profile['language_id'];
        }

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
