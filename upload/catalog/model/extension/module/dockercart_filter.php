<?php

class ModelExtensionModuleDockercartFilter extends Model {
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

    private function getCacheTime() {
        $cache_time = $this->config->get('module_dockercart_filter_cache_time');
        return !empty($cache_time) ? (int)$cache_time : 3600;
    }

    public function getPriceRange($data = array()) {
        $start_time = microtime(true);

        if (is_numeric($data)) {
            $category_id = (int)$data;
            $data = array('filter_category_id' => $category_id);
        } else {
            $category_id = !empty($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0;
        }

        $has_filters = !empty($data['filter_manufacturers']) ||
                      !empty($data['filter_attributes']) ||
                      !empty($data['filter_options']);

        $base_currency = $this->config->get('config_currency');
        $current_currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $base_currency;
        $cache_key = 'dockercart_filter.' . (int)$category_id . '.' . (int)$this->config->get('config_customer_group_id') . '.price_range.' . $current_currency;

        $this->logger->debug('getPriceRange: START - category=' . $category_id . ' | base_currency=' . $base_currency . ' | current_currency=' . $current_currency . ' | cache_key=' . $cache_key);

        if (!$has_filters) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null && $cached !== false) {
                $this->logger->debug('getPriceRange: Cache hit');
                return $cached;
            }
        }

        $sql = "SELECT
                    COALESCE(MIN(p.price), 0) as min_price,
                    COALESCE(MAX(p.price), 0) as max_price
                FROM " . DB_PREFIX . "product p";

        if ($category_id) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON p.product_id = p2c.product_id";
        }

        $sql .= " WHERE p.status = 1";

        if ($category_id) {
            $sql .= " AND p2c.category_id = " . (int)$category_id;
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

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

        $query = $this->db->query($sql);

        $min_base = (float)$query->row['min_price'];
        $max_base = (float)$query->row['max_price'];

        $sql_special = "SELECT COALESCE(MIN(ps.price), 0) as min_special
                        FROM " . DB_PREFIX . "product_special ps
                        INNER JOIN " . DB_PREFIX . "product p ON ps.product_id = p.product_id";

        if ($category_id) {
            $sql_special .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON p.product_id = p2c.product_id";
        }

        $sql_special .= " WHERE p.status = 1
                        AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                        AND ps.date_start <= NOW()
                        AND (ps.date_end = '0000-00-00 00:00:00' OR ps.date_end > NOW())";

        if ($category_id) {
            $sql_special .= " AND p2c.category_id = " . (int)$category_id;
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql_special .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

        $query_special = $this->db->query($sql_special);
        $min_special = (float)$query_special->row['min_special'];

        $min_price = ($min_special > 0 && $min_special < $min_base) ? $min_special : $min_base;

        $base_currency = $this->config->get('config_currency');
        $current_currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $base_currency;

        $display_min = (float)$min_price;
        $display_max = (float)$max_base;

        if ($base_currency !== $current_currency) {
            $display_min = (float)$this->currency->convert($display_min, $base_currency, $current_currency);
            $display_max = (float)$this->currency->convert($display_max, $base_currency, $current_currency);
            $this->logger->debug('getPriceRange: Converted from ' . $base_currency . ' to ' . $current_currency . ' | min: ' . $min_price . ' -> ' . $display_min . ' | max: ' . $max_base . ' -> ' . $display_max);
        }

        $result = [
            'min' => round($display_min, 2),
            'max' => round($display_max, 2)
        ];

        if (!$has_filters) {
            $this->cache->set($cache_key, $result, $this->getCacheTime());
        }

        $execution_time = microtime(true) - $start_time;
        $this->logger->debug('getPriceRange: ' . number_format($execution_time * 1000, 2) . 'ms | Category: ' . $category_id . ' | Currency: ' . $current_currency . ' | Filters: ' . ($has_filters ? 'yes' : 'no'));

        return $result;
    }

    public function getManufacturers($data = array()) {
        $start_time = microtime(true);

        if (is_numeric($data)) {
            $category_id = (int)$data;
            $data = array('filter_category_id' => $category_id);
        } else {
            $category_id = !empty($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0;
        }

        $has_filters = !empty($data['filter_attributes']) ||
                      !empty($data['filter_options']) ||
                      isset($data['filter_price_min']) ||
                      isset($data['filter_price_max']);

        $cache_key = 'dockercart_filter.' . (int)$category_id . '.' . (int)$this->config->get('config_language_id') . '.manufacturers';

        if (!$has_filters) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null && $cached !== false) {
                $this->logger->debug('getManufacturers: Cache hit');
                return $cached;
            }
        }

        $sql = "SELECT
                    m.manufacturer_id,
                    m.name,
                    m.sort_order,
                    COUNT(DISTINCT p.product_id) as product_count
                FROM " . DB_PREFIX . "product p
                INNER JOIN " . DB_PREFIX . "manufacturer m ON p.manufacturer_id = m.manufacturer_id";

        if ($category_id) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON p.product_id = p2c.product_id";
        }

        $sql .= " WHERE p.status = 1";

        if ($category_id) {
            $sql .= " AND p2c.category_id = " . (int)$category_id;
        }

        if (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') {
            $sql .= " AND p.price >= " . (float)$data['filter_price_min'];
        }
        if (isset($data['filter_price_max']) && $data['filter_price_max'] !== '') {
            $sql .= " AND p.price <= " . (float)$data['filter_price_max'];
        }

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

        $sql .= " GROUP BY p.manufacturer_id, m.manufacturer_id, m.name
                  HAVING product_count > 0
                  ORDER BY m.sort_order ASC, m.name ASC";

        $this->logger->debug('getManufacturers SQL: ' . $sql);

        $query = $this->db->query($sql);

        if (!$has_filters) {
            $this->cache->set($cache_key, $query->rows, $this->getCacheTime());
        }

        $execution_time = microtime(true) - $start_time;
        $this->logger->debug('getManufacturers: ' . number_format($execution_time * 1000, 2) . 'ms | Category: ' . $category_id . ' | Filters: ' . ($has_filters ? 'yes' : 'no') . ' | Results: ' . count($query->rows));

        return $query->rows;
    }

    public function getAttributes($data = array()) {

        if (is_numeric($data)) {
            $category_id = (int)$data;
            $data = array('filter_category_id' => $category_id);
        } else {
            $category_id = !empty($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0;
        }

        $has_filters = !empty($data['filter_manufacturers']) ||
                      !empty($data['filter_attributes']) ||
                      !empty($data['filter_options']) ||
                      isset($data['filter_price_min']) ||
                      isset($data['filter_price_max']);

        $cache_key = 'dockercart_filter.' . (int)$category_id . '.' . (int)$this->config->get('config_language_id') . '.attributes';

        if (!$has_filters) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null && $cached !== false) {
                $this->logger->debug('getAttributes: Cache hit');
                return $cached;
            }
        }

        $sql_attrs = "SELECT DISTINCT ad.attribute_id, ad.name
                      FROM " . DB_PREFIX . "product p
                      INNER JOIN " . DB_PREFIX . "product_attribute pa ON p.product_id = pa.product_id
                      INNER JOIN " . DB_PREFIX . "attribute_description ad ON pa.attribute_id = ad.attribute_id";

        if ($category_id) {
            $sql_attrs .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c
                          ON p.product_id = p2c.product_id
                          WHERE p2c.category_id = " . (int)$category_id;
        } else {
            $sql_attrs .= " WHERE 1=1";
        }

        $sql_attrs .= " AND p.status = 1
                       AND ad.language_id = " . (int)$this->config->get('config_language_id') . "
                       AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                       ORDER BY ad.name ASC";

        $query_attrs = $this->db->query($sql_attrs);
        $category_attributes = $query_attrs->rows;

        $attributes = [];
        foreach ($category_attributes as $attr) {
            $attr_id = (int)$attr['attribute_id'];

            $data_for_this_attr = array(
                'filter_category_id' => $category_id,
                'filter_manufacturers' => !empty($data['filter_manufacturers']) ? $data['filter_manufacturers'] : array(),
                'filter_attributes' => array(),
                'filter_options' => !empty($data['filter_options']) ? $data['filter_options'] : array(),
                'filter_price_min' => isset($data['filter_price_min']) ? $data['filter_price_min'] : '',
                'filter_price_max' => isset($data['filter_price_max']) ? $data['filter_price_max'] : ''
            );

            if (!empty($data['filter_attributes'])) {
                foreach ($data['filter_attributes'] as $fattr_id => $values) {
                    if ((int)$fattr_id !== $attr_id) {
                        $data_for_this_attr['filter_attributes'][(int)$fattr_id] = $values;
                    }
                }
            }

            $attr_values = $this->getAttributeValuesForAttr($data_for_this_attr, $attr_id);

            if (!empty($attr_values)) {
                $attributes[$attr_id] = array(
                    'attribute_id' => $attr_id,
                    'name' => $attr['name'],
                    'values' => $attr_values
                );
            }
        }

        $result = array_values($attributes);

        if (!$has_filters) {
            $this->cache->set($cache_key, $result, $this->getCacheTime());
        }

        return $result;
    }

    private function getAttributeValuesForAttr($data, $target_attr_id) {
        $start_time = microtime(true);

        $category_id = !empty($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0;

        $sql = "SELECT
                    pa.text as value_text,
                    COUNT(DISTINCT p.product_id) as product_count
                FROM " . DB_PREFIX . "product p
                INNER JOIN " . DB_PREFIX . "product_attribute pa ON p.product_id = pa.product_id";

        if ($category_id) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c
                      ON p.product_id = p2c.product_id
                      WHERE p2c.category_id = " . (int)$category_id;
        } else {
            $sql .= " WHERE 1=1";
        }

        $sql .= " AND pa.attribute_id = " . (int)$target_attr_id;

        if (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') {
            $sql .= " AND p.price >= " . (float)$data['filter_price_min'];
        }
        if (isset($data['filter_price_max']) && $data['filter_price_max'] !== '') {
            $sql .= " AND p.price <= " . (float)$data['filter_price_max'];
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

        if (!empty($data['filter_attributes'])) {
            $attr_index = 0;
            foreach ($data['filter_attributes'] as $attribute_id => $values) {
                $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_attribute pa_filter" . $attr_index;
                $sql .= " WHERE pa_filter" . $attr_index . ".product_id = p.product_id";
                $sql .= " AND pa_filter" . $attr_index . ".attribute_id = " . (int)$attribute_id;
                $sql .= " AND pa_filter" . $attr_index . ".language_id = " . (int)$this->config->get('config_language_id');

                $value_conditions = [];
                foreach ($values as $value) {
                    $value_conditions[] = $this->buildAttributeValueCondition("pa_filter" . $attr_index . ".text", $value);
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

        $sql .= " AND p.status = 1
                  AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                  AND pa.text != ''
                  AND TRIM(pa.text) != ''
                  GROUP BY pa.text
                  HAVING product_count > 0
                  ORDER BY pa.text ASC";

        $query = $this->db->query($sql);

        $separators = $this->config->get('module_dockercart_filter_attribute_separators');

        $values = [];
        $value_counts = [];

        foreach ($query->rows as $row) {

            if (trim($row['value_text']) === '') {
                $this->logger->debug('getAttributeValuesForAttr: Skipping empty value for attribute ' . $target_attr_id);
                continue;
            }

            // If separators are empty, treat the entire value as a single item, otherwise split by separators
            if (empty($separators)) {
                $split_values = array($row['value_text']);
            } else {
                $separator_pattern = '/[' . preg_quote($separators, '/') . ']/';
                $split_values = preg_split($separator_pattern, $row['value_text']);
            }

            foreach ($split_values as $split_val) {
                $split_val = trim($split_val);

                if ($split_val === '') {
                    continue;
                }

                if (!isset($value_counts[$split_val])) {
                    $value_counts[$split_val] = 0;
                }
                $value_counts[$split_val] += (int)$row['product_count'];
            }
        }

        foreach ($value_counts as $text => $count) {
            $values[] = array(
                'text' => $text,
                'count' => $count
            );
            $this->logger->debug('getAttributeValuesForAttr: Added value "' . $text . '" with count ' . $count);
        }

        usort($values, function($a, $b) {
            return $this->smartSort($a['text'], $b['text']);
        });

        $execution_time = microtime(true) - $start_time;
        $this->logger->debug('getAttributeValuesForAttr: ' . number_format($execution_time * 1000, 2) . 'ms | Attribute: ' . $target_attr_id . ' | Category: ' . $category_id . ' | Values: ' . count($values));

        return $values;
    }

    public function getOptions($data = array()) {

        if (is_numeric($data)) {
            $category_id = (int)$data;
            $data = array('filter_category_id' => $category_id);
        } else {
            $category_id = !empty($data['filter_category_id']) ? (int)$data['filter_category_id'] : 0;
        }

        $has_filters = !empty($data['filter_manufacturers']) ||
                      !empty($data['filter_attributes']) ||
                      !empty($data['filter_options']) ||
                      isset($data['filter_price_min']) ||
                      isset($data['filter_price_max']);

        $cache_key = 'dockercart_filter.' . (int)$category_id . '.' . (int)$this->config->get('config_language_id') . '.options';

        if (!$has_filters) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== null && $cached !== false) {
                $this->logger->debug('getOptions: Cache hit');
                return $cached;
            }
        }

        $sql = "SELECT
                    od.option_id,
                    od.name as option_name,
                    ovd.option_value_id,
                    ovd.name as value_name,
                    ov.sort_order,
                    COUNT(DISTINCT p.product_id) as product_count
                FROM " . DB_PREFIX . "product p
                INNER JOIN " . DB_PREFIX . "product_option_value pov ON p.product_id = pov.product_id
                INNER JOIN " . DB_PREFIX . "option_description od ON pov.option_id = od.option_id
                INNER JOIN " . DB_PREFIX . "option_value_description ovd ON pov.option_value_id = ovd.option_value_id
                INNER JOIN " . DB_PREFIX . "option_value ov ON pov.option_value_id = ov.option_value_id";

        if ($category_id) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c
                      ON p.product_id = p2c.product_id
                      WHERE p2c.category_id = " . (int)$category_id;
        } else {
            $sql .= " WHERE 1=1";
        }

        if (!empty($data['filter_manufacturers'])) {
            $sql .= " AND p.manufacturer_id IN (" . implode(',', array_map('intval', $data['filter_manufacturers'])) . ")";
        }

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

        if (isset($data['filter_price_min']) && $data['filter_price_min'] !== '') {
            $sql .= " AND p.price >= " . (float)$data['filter_price_min'];
        }
        if (isset($data['filter_price_max']) && $data['filter_price_max'] !== '') {
            $sql .= " AND p.price <= " . (float)$data['filter_price_max'];
        }

        $sql .= " AND p.status = 1
                  AND od.language_id = " . (int)$this->config->get('config_language_id') . "
                  AND ovd.language_id = " . (int)$this->config->get('config_language_id') . "
                  GROUP BY pov.option_id, pov.option_value_id
                  HAVING product_count > 0
                  ORDER BY od.name ASC, ov.sort_order ASC";

        $query = $this->db->query($sql);

        $options = [];
        foreach ($query->rows as $row) {
            $option_id = (int)$row['option_id'];
            if (!isset($options[$option_id])) {
                $options[$option_id] = [
                    'option_id' => $option_id,
                    'name' => $row['option_name'],
                    'values' => []
                ];
            }

            $options[$option_id]['values'][] = [
                'option_value_id' => (int)$row['option_value_id'],
                'name' => $row['value_name'],
                'count' => (int)$row['product_count']
            ];
        }

        $result = array_values($options);

        if (!$has_filters) {
            $this->cache->set($cache_key, $result, $this->getCacheTime());
        }

        return $result;
    }

    public function getFilteredProducts($data = []) {

        $where_conditions = ["p.status = 1"];

        if (!empty($data['category_id'])) {
            $where_conditions[] = "p2c.category_id = " . (int)$data['category_id'];
        }

        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $where_conditions[] = "p.price >= " . (float)$data['price_min'];
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $where_conditions[] = "p.price <= " . (float)$data['price_max'];
        }

        if (!empty($data['manufacturer']) && is_array($data['manufacturer'])) {
            $manufacturer_ids = array_map('intval', $data['manufacturer']);
            if (!empty($manufacturer_ids)) {
                $where_conditions[] = "p.manufacturer_id IN (" . implode(',', $manufacturer_ids) . ")";
            }
        }

        $sql = "SELECT DISTINCT
                    p.product_id,
                    pd.name,
                    p.image,
                    p.price,
                    p.tax_class_id,
                    (SELECT price FROM " . DB_PREFIX . "product_special ps
                     WHERE ps.product_id = p.product_id
                     AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                     AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW())
                     AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                     ORDER BY ps.priority ASC, ps.price ASC
                     LIMIT 1) AS special
                FROM " . DB_PREFIX . "product p
                INNER JOIN " . DB_PREFIX . "product_description pd
                    ON p.product_id = pd.product_id
                    AND pd.language_id = " . (int)$this->config->get('config_language_id');

        if (!empty($data['category_id'])) {
            $sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c
                      ON p.product_id = p2c.product_id";
        }

        $where_conditions[] = "1=1";
        $sql .= " WHERE " . implode(' AND ', $where_conditions);

        if (!empty($data['attribute']) && is_array($data['attribute'])) {
            $attr_index = 0;
            foreach ($data['attribute'] as $attribute_id => $values) {
                if (!empty($values) && is_array($values)) {
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
        }

        if (!empty($data['option']) && is_array($data['option'])) {
            $opt_index = 0;
            foreach ($data['option'] as $option_id => $values) {
                if (!empty($values) && is_array($values)) {
                    $option_value_ids = array_map('intval', $values);
                    $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_option_value pov" . $opt_index;
                    $sql .= " WHERE pov" . $opt_index . ".product_id = p.product_id";
                    $sql .= " AND pov" . $opt_index . ".option_id = " . (int)$option_id;
                    $sql .= " AND pov" . $opt_index . ".option_value_id IN (" . implode(',', $option_value_ids) . "))";
                    $opt_index++;
                }
            }
        }

        $sql .= " ORDER BY p.sort_order ASC, pd.name ASC";

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

    public function getTotalFilteredProducts($data = []) {

        $sql = "SELECT COUNT(DISTINCT p.product_id) as total
                FROM " . DB_PREFIX . "product p";

        $where_conditions[] = "p.status = 1";

        if (!empty($data['category_id'])) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_category p2c
                      WHERE p2c.product_id = p.product_id
                      AND p2c.category_id = " . (int)$data['category_id'] . ")";
        }

        if (isset($data['price_min']) && $data['price_min'] !== '') {
            $where_conditions[] = "p.price >= " . (float)$data['price_min'];
        }
        if (isset($data['price_max']) && $data['price_max'] !== '') {
            $where_conditions[] = "p.price <= " . (float)$data['price_max'];
        }

        if (!empty($data['manufacturer']) && is_array($data['manufacturer'])) {
            $manufacturer_ids = array_map('intval', $data['manufacturer']);
            if (!empty($manufacturer_ids)) {
                $where_conditions[] = "p.manufacturer_id IN (" . implode(',', $manufacturer_ids) . ")";
            }
        }

        $sql .= " WHERE " . implode(' AND ', $where_conditions);

        if (!empty($data['attribute']) && is_array($data['attribute'])) {
            $attr_index = 0;
            foreach ($data['attribute'] as $attribute_id => $values) {
                if (!empty($values) && is_array($values)) {
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
        }

        if (!empty($data['option']) && is_array($data['option'])) {
            $opt_index = 0;
            foreach ($data['option'] as $option_id => $values) {
                if (!empty($values) && is_array($values)) {
                    $option_value_ids = array_map('intval', $values);
                    $sql .= " AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_option_value pov" . $opt_index;
                    $sql .= " WHERE pov" . $opt_index . ".product_id = p.product_id";
                    $sql .= " AND pov" . $opt_index . ".option_id = " . (int)$option_id;
                    $sql .= " AND pov" . $opt_index . ".option_value_id IN (" . implode(',', $option_value_ids) . "))";
                    $opt_index++;
                }
            }
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    public function createIndexes() {

        $this->db->query("
            CREATE INDEX IF NOT EXISTS idx_product_status_price
            ON " . DB_PREFIX . "product (status, price)
        ");

        $this->db->query("
            CREATE INDEX IF NOT EXISTS idx_product_manufacturer_status
            ON " . DB_PREFIX . "product (manufacturer_id, status)
        ");

        $this->db->query("
            CREATE INDEX IF NOT EXISTS idx_product_to_category
            ON " . DB_PREFIX . "product_to_category (category_id, product_id)
        ");

        $this->db->query("
            CREATE INDEX IF NOT EXISTS idx_product_attribute_filter
            ON " . DB_PREFIX . "product_attribute (product_id, attribute_id, language_id)
        ");

        $this->db->query("
            CREATE INDEX IF NOT EXISTS idx_product_option_value_filter
            ON " . DB_PREFIX . "product_option_value (product_id, option_id, option_value_id)
        ");
    }

    private function smartSort($a, $b) {

        $a_lower = mb_strtolower(trim($a), 'UTF-8');
        $b_lower = mb_strtolower(trim($b), 'UTF-8');

        if (is_numeric($a_lower) && is_numeric($b_lower)) {

            if (strpos($a_lower, '.') !== false || strpos($b_lower, '.') !== false) {

                $a_float = (float)$a_lower;
                $b_float = (float)$b_lower;

                if ($a_float < $b_float) return -1;
                if ($a_float > $b_float) return 1;
                return 0;
            } else {

                $a_int = (int)$a_lower;
                $b_int = (int)$b_lower;

                if ($a_int < $b_int) return -1;
                if ($a_int > $b_int) return 1;
                return 0;
            }
        }

        if (preg_match('/^(\d+(?:\.\d+)?)/', $a_lower, $matches_a) &&
            preg_match('/^(\d+(?:\.\d+)?)/', $b_lower, $matches_b)) {

            $num_a = (float)$matches_a[1];
            $num_b = (float)$matches_b[1];

            if ($num_a < $num_b) return -1;
            if ($num_a > $num_b) return 1;

            return strcasecmp($a_lower, $b_lower);
        }

        if (preg_match('/^(.+?)\s*(\d+(?:\.\d+)?)$/', $a_lower, $matches_a) &&
            preg_match('/^(.+?)\s*(\d+(?:\.\d+)?)$/', $b_lower, $matches_b)) {

            $text_cmp = strcasecmp($matches_a[1], $matches_b[1]);
            if ($text_cmp !== 0) {
                return $text_cmp;
            }

            $num_a = (float)$matches_a[2];
            $num_b = (float)$matches_b[2];

            if ($num_a < $num_b) return -1;
            if ($num_a > $num_b) return 1;
            return 0;
        }

        return strcasecmp($a_lower, $b_lower);
    }
}
