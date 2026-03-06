<?php
/**
 * DockerCart Google Base — Catalog Model
 *
 * Database queries for product data retrieval.
 * Optimized for large catalogs with efficient SQL.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 */

class ModelExtensionFeedDockercartGooglebase extends Model {

    /**
     * Get products for feed generation
     *
     * @param int $language_id Language ID
     * @param int $store_id Store ID
     * @param array $settings Module settings
     * @return array Products data
     */
    public function getProducts($language_id, $store_id, $settings = array()) {
        // Build exclusion lists
        $exclude_products = array();
        if (!empty($settings['exclude_products'])) {
            $exclude_products = array_map('intval', array_filter(explode(',', $settings['exclude_products'])));
        }

        $exclude_categories = array();
        if (!empty($settings['exclude_categories'])) {
            $exclude_categories = array_map('intval', array_filter(explode(',', $settings['exclude_categories'])));
        }

        // Build WHERE conditions
        $where = array();
        
        // Status filter
        if (empty($settings['include_disabled'])) {
            $where[] = "p.status = '1'";
        }

        // Quantity filter
        if (empty($settings['include_out_of_stock'])) {
            $where[] = "p.quantity > 0";
        }

        // Exclude products
        if (!empty($exclude_products)) {
            $where[] = "p.product_id NOT IN (" . implode(',', $exclude_products) . ")";
        }

        // Build category exclusion subquery
        $category_exclusion = '';
        if (!empty($exclude_categories)) {
            $category_exclusion = " AND p.product_id NOT IN (
                SELECT p2c.product_id 
                FROM " . DB_PREFIX . "product_to_category p2c 
                WHERE p2c.category_id IN (" . implode(',', $exclude_categories) . ")
            )";
        }

        $where_clause = '';
        if (!empty($where)) {
            $where_clause = implode(' AND ', $where);
        } else {
            $where_clause = '1=1';
        }

        // Main query with all needed data
        $sql = "
            SELECT 
                p.product_id,
                p.model,
                p.sku,
                p.upc,
                p.ean,
                p.jan,
                p.isbn,
                p.mpn,
                p.image,
                p.quantity,
                p.stock_status_id,
                p.weight,
                p.weight_class_id,
                p.price,
                p.date_available,
                p.date_added,
                p.date_modified,
                pd.name,
                pd.description,
                pd.meta_title,
                pd.meta_description,
                m.name AS manufacturer,
                (
                    SELECT ps.price 
                    FROM " . DB_PREFIX . "product_special ps 
                    WHERE ps.product_id = p.product_id 
                      AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                      AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) 
                           AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                    ORDER BY ps.priority ASC, ps.price ASC 
                    LIMIT 1
                ) AS special,
                (
                    SELECT ps.date_start 
                    FROM " . DB_PREFIX . "product_special ps 
                    WHERE ps.product_id = p.product_id 
                      AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                      AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) 
                           AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                    ORDER BY ps.priority ASC, ps.price ASC 
                    LIMIT 1
                ) AS special_date_start,
                (
                    SELECT ps.date_end 
                    FROM " . DB_PREFIX . "product_special ps 
                    WHERE ps.product_id = p.product_id 
                      AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                      AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) 
                           AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                    ORDER BY ps.priority ASC, ps.price ASC 
                    LIMIT 1
                ) AS special_date_end,
                (
                    SELECT GROUP_CONCAT(pi.image SEPARATOR ',')
                    FROM " . DB_PREFIX . "product_image pi
                    WHERE pi.product_id = p.product_id
                    ORDER BY pi.sort_order ASC
                    LIMIT 10
                ) AS additional_images,
                (
                    SELECT p2c.category_id
                    FROM " . DB_PREFIX . "product_to_category p2c
                    LEFT JOIN " . DB_PREFIX . "category c ON (p2c.category_id = c.category_id)
                    WHERE p2c.product_id = p.product_id 
                      AND c.status = '1'
                    ORDER BY p2c.category_id ASC
                    LIMIT 1
                ) AS category_id
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd 
                ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "')
            LEFT JOIN " . DB_PREFIX . "product_to_store p2s 
                ON (p.product_id = p2s.product_id)
            LEFT JOIN " . DB_PREFIX . "manufacturer m 
                ON (p.manufacturer_id = m.manufacturer_id)
            WHERE p2s.store_id = '" . (int)$store_id . "'
              AND " . $where_clause . "
              " . $category_exclusion . "
            ORDER BY p.product_id ASC
        ";

        $query = $this->db->query($sql);
        $products = $query->rows;

        // Enrich products with category paths
        $category_paths = $this->getCategoryPaths($language_id);
        
        foreach ($products as &$product) {
            $category_id = $product['category_id'];
            if ($category_id && isset($category_paths[$category_id])) {
                $product['category_path'] = $category_paths[$category_id]['path'];
                $product['category_name'] = $category_paths[$category_id]['name'];
            } else {
                $product['category_path'] = '';
                $product['category_name'] = '';
            }
        }

        return $products;
    }

    /**
     * Get category paths for all categories
     *
     * @param int $language_id Language ID
     * @return array Category paths indexed by category_id
     */
    public function getCategoryPaths($language_id) {
        $sql = "
            SELECT 
                c.category_id,
                cd.name,
                (
                    SELECT GROUP_CONCAT(cd2.name ORDER BY cp.level SEPARATOR ' > ')
                    FROM " . DB_PREFIX . "category_path cp
                    LEFT JOIN " . DB_PREFIX . "category_description cd2 
                        ON (cp.path_id = cd2.category_id AND cd2.language_id = '" . (int)$language_id . "')
                    WHERE cp.category_id = c.category_id
                ) AS path
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd 
                ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
            WHERE c.status = '1'
        ";

        $query = $this->db->query($sql);
        
        $paths = array();
        foreach ($query->rows as $row) {
            $paths[$row['category_id']] = array(
                'name' => $row['name'],
                'path' => $row['path']
            );
        }

        return $paths;
    }

    /**
     * Get product count for statistics
     *
     * @param int $language_id Language ID
     * @param int $store_id Store ID
     * @param array $settings Module settings
     * @return int Product count
     */
    public function getProductCount($language_id, $store_id, $settings = array()) {
        $where = array();
        
        if (empty($settings['include_disabled'])) {
            $where[] = "p.status = '1'";
        }

        if (empty($settings['include_out_of_stock'])) {
            $where[] = "p.quantity > 0";
        }

        $where_clause = '';
        if (!empty($where)) {
            $where_clause = ' AND ' . implode(' AND ', $where);
        }

        $sql = "
            SELECT COUNT(DISTINCT p.product_id) as total
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_to_store p2s 
                ON (p.product_id = p2s.product_id)
            WHERE p2s.store_id = '" . (int)$store_id . "'
            " . $where_clause . "
        ";

        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Get languages
     *
     * @return array Languages
     */
    public function getLanguages() {
        $sql = "
            SELECT *
            FROM " . DB_PREFIX . "language
            WHERE status = '1'
            ORDER BY sort_order, name
        ";

        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get stores
     *
     * @return array Stores
     */
    public function getStores() {
        $sql = "
            SELECT *
            FROM " . DB_PREFIX . "store
            ORDER BY url
        ";

        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get product options (for variants)
     *
     * @param int $product_id Product ID
     * @param int $language_id Language ID
     * @return array Options
     */
    public function getProductOptions($product_id, $language_id) {
        $sql = "
            SELECT 
                po.product_option_id,
                po.option_id,
                od.name AS option_name,
                pov.product_option_value_id,
                pov.option_value_id,
                ovd.name AS option_value_name,
                pov.quantity,
                pov.price,
                pov.price_prefix,
                pov.weight,
                pov.weight_prefix
            FROM " . DB_PREFIX . "product_option po
            LEFT JOIN " . DB_PREFIX . "option_description od 
                ON (po.option_id = od.option_id AND od.language_id = '" . (int)$language_id . "')
            LEFT JOIN " . DB_PREFIX . "product_option_value pov 
                ON (po.product_option_id = pov.product_option_id)
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd 
                ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . (int)$language_id . "')
            WHERE po.product_id = '" . (int)$product_id . "'
            ORDER BY po.option_id, pov.option_value_id
        ";

        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get product attributes
     *
     * @param int $product_id Product ID
     * @param int $language_id Language ID
     * @return array Attributes
     */
    public function getProductAttributes($product_id, $language_id) {
        $sql = "
            SELECT 
                pa.attribute_id,
                ad.name AS attribute_name,
                pa.text AS attribute_value
            FROM " . DB_PREFIX . "product_attribute pa
            LEFT JOIN " . DB_PREFIX . "attribute_description ad 
                ON (pa.attribute_id = ad.attribute_id AND ad.language_id = '" . (int)$language_id . "')
            WHERE pa.product_id = '" . (int)$product_id . "'
              AND pa.language_id = '" . (int)$language_id . "'
            ORDER BY ad.name
        ";

        $query = $this->db->query($sql);
        
        return $query->rows;
    }
}
