<?php

$modified_product = defined('DIR_MODIFICATION') ? DIR_MODIFICATION . 'catalog/model/catalog/product.php' : '';
if ($modified_product && is_file($modified_product)) {
    require_once($modified_product);
} else {
    require_once(DIR_APPLICATION . 'model/catalog/product.php');
}

class ModelExtensionModuleDockercartFilterProduct extends ModelCatalogProduct {
    private $logger;
    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'filter');
    }

    private function buildAttributeValueCondition($field_name, $search_value) {
        $search_lower = strtolower($search_value);
        $search_escaped = $this->db->escape($search_lower);
        
        $separators = $this->config->get('module_dockercart_filter_attribute_separators');
        
        // If separators are empty, use fast exact match instead of REGEXP
        if (empty($separators)) {
            // Fast comparison - exact match only
            return "LOWER($field_name) = '" . $search_escaped . "'";
        }

        // Build a REGEXP pattern that matches the value as a whole word (not partial).
        // Pattern accounts for:
        // 1. Exact match (whole field equals value)
        // 2. At the beginning (value followed by separator + optional space)
        // 3. At the end (separator + optional space followed by value)
        // 4. In the middle (separator + optional space + value + separator + optional space)
        // 5. Handles multiple separators and variable spacing
        
        // Escape special regex chars in search_value
        $regex_value = preg_quote($search_lower, '/');
        
        // Build character class of separators for regex
        $sep_class = '';
        foreach (str_split($separators) as $sep) {
            $sep_class .= preg_quote($sep, '/');
        }
        
        // Regex pattern: match value as whole item (not partial), accounting for separators and spaces
        // ^value$                              - exact match, entire field is the value
        // ^value[sep_class] *                  - value at start, followed by separator
        // [sep_class] *value$                  - value at end, preceded by separator
        // [sep_class] *value[sep_class] *      - value in middle with separators around it
        $pattern = "^{$regex_value}([{$sep_class}] +)?$|^{$regex_value}[{$sep_class}] *|[{$sep_class}] *{$regex_value}$|[{$sep_class}] *{$regex_value}[{$sep_class}] *";
        
        // Use REGEXP with case-insensitive matching
        return "LOWER($field_name) REGEXP '" . $this->db->escape($pattern) . "'";
    }

    public function getProducts($data = array()) {

        $filter_data = $this->registry->get('dockercart_filter_data');

        $this->logger->debug('getProducts(): filter_data from registry = ' . json_encode($filter_data));

        if (!empty($filter_data)) {
            $data = array_merge($data, $filter_data);
            $this->logger->debug('getProducts(): merged data = ' . json_encode($data));
        }

        $has_tag_search = isset($data['filter_tag']) && trim((string)$data['filter_tag']) !== '';

        $has_custom_filters = !empty($data['filter_manufacturers']) ||
                      !empty($data['filter_attributes']) ||
                      !empty($data['filter_options']) ||
                      (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') ||
                      (isset($data['filter_price_max']) && $data['filter_price_max'] !== '');

        $this->logger->debug('has_custom_filters = ' . ($has_custom_filters ? 'true' : 'false'));

        if ($has_tag_search || !$has_custom_filters) {

            $this->logger->debug('Using parent getProducts()' . ($has_tag_search ? ' (tag search forced to MySQL)' : ''));
            return parent::getProducts($data);
        }

        $this->logger->debug('Building custom SQL query');

        $sql = "SELECT p.product_id, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }
            $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";

        $sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        $sql .= " AND p.status = '1' AND p.date_available <= NOW()";
        $sql .= " AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

        if (!empty($data['filter_attributes'])) {
           $attr_index = 0;
            foreach ($data['filter_attributes'] as $attribute_id => $values) {
                $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_attribute pa" . $attr_index;
                $sql .= " WHERE pa" . $attr_index . ".product_id = p.product_id";
                $sql .= " AND pa" . $attr_index . ".attribute_id = " . (int)$attribute_id;
                $sql .= " AND pa" . $attr_index . ".language_id = " . (int)$this->config->get('config_language_id');

                $value_conditions = [];

                foreach ($values as $value) {
                    $value_conditions[] = $this->buildAttributeValueCondition("pa" . $attr_index . ".text", $value);
                }
                $sql .= " AND (" . implode(' OR ', $value_conditions) . "))";
                $attr_index++;
            }
        }

        if (!empty($data['filter_options'])) {

            $opt_index = 0;
            foreach ($data['filter_options'] as $option_id => $values) {
                $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_option_value pov" . $opt_index;
                $sql .= " WHERE pov" . $opt_index . ".product_id = p.product_id";
                $sql .= " AND pov" . $opt_index . ".option_id = " . (int)$option_id;
                $sql .= " AND pov" . $opt_index . ".option_value_id IN (" . implode(',', array_map('intval', $values)) . "))";
                $opt_index++;
            }
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

        if (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') {
            $sql .= " AND p.price >= " . (float)$data['filter_price_min'];
        }
        if (isset($data['filter_price_max']) && $data['filter_price_max'] !== '') {
            $sql .= " AND p.price <= " . (float)$data['filter_price_max'];
        }

        $sql .= " GROUP BY p.product_id";

        $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
            } elseif ($data['sort'] == 'p.price') {
                $sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
            } else {
                $sql .= " ORDER BY " . $data['sort'];
            }
        } else {
            $sql .= " ORDER BY p.sort_order";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC, LCASE(pd.name) DESC";
        } else {
            $sql .= " ASC, LCASE(pd.name) ASC";
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

        $product_data = array();

        $query = $this->db->query($sql);

        $this->logger->debug('SQL executed, rows returned = ' . count($query->rows));
        $this->logger->debug('SQL = ' . $sql);
        $this->logger->debug('Query rows = ' . json_encode($query->rows));

        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
        }

        $this->logger->debug('Product data count = ' . count($product_data));
        $this->logger->debug('Product IDs returned = ' . json_encode(array_keys($product_data)));

        return $product_data;
    }

    public function getTotalProducts($data = array()) {

        $filter_data = $this->registry->get('dockercart_filter_data');

        if (!empty($filter_data)) {
            $data = array_merge($data, $filter_data);
        }

        $has_tag_search = isset($data['filter_tag']) && trim((string)$data['filter_tag']) !== '';

        $has_custom_filters = !empty($data['filter_manufacturers']) ||
                              !empty($data['filter_attributes']) ||
                              !empty($data['filter_options']) ||
                              (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') ||
                              (isset($data['filter_price_max']) && $data['filter_price_max'] !== '');

        if ($has_tag_search || !$has_custom_filters) {

            return parent::getTotalProducts($data);
        }

        $sql = "SELECT COUNT(DISTINCT p.product_id) as total FROM " . DB_PREFIX . "product p";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
                $sql .= " LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
            }
        }

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)";

        $sql .= " WHERE p.status = '1' AND p.date_available <= NOW()";
        $sql .= " AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

        if (!empty($data['filter_attributes'])) {
            $attr_index = 0;
            foreach ($data['filter_attributes'] as $attribute_id => $values) {
                $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_attribute pa" . $attr_index;
                $sql .= " WHERE pa" . $attr_index . ".product_id = p.product_id";
                $sql .= " AND pa" . $attr_index . ".attribute_id = " . (int)$attribute_id;
                $sql .= " AND pa" . $attr_index . ".language_id = " . (int)$this->config->get('config_language_id');

                $value_conditions = [];

                foreach ($values as $value) {
                    $value_conditions[] = $this->buildAttributeValueCondition("pa" . $attr_index . ".text", $value);
                }
                $sql .= " AND (" . implode(' OR ', $value_conditions) . "))";
                $attr_index++;
            }
        }

        if (!empty($data['filter_options'])) {
            $opt_index = 0;
            foreach ($data['filter_options'] as $option_id => $values) {
                $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_option_value pov" . $opt_index;
                $sql .= " WHERE pov" . $opt_index . ".product_id = p.product_id";
                $sql .= " AND pov" . $opt_index . ".option_id = " . (int)$option_id;
                $sql .= " AND pov" . $opt_index . ".option_value_id IN (" . implode(',', array_map('intval', $values)) . "))";
                $opt_index++;
            }
        }

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

        if (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') {
            $sql .= " AND p.price >= " . (float)$data['filter_price_min'];
        }
        if (isset($data['filter_price_max']) && $data['filter_price_max'] !== '') {
            $sql .= " AND p.price <= " . (float)$data['filter_price_max'];
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }
}

