<?php

class ControllerExtensionModuleDockercartFilter extends Controller {
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



    private function sortOptionValuesByOrder(&$options) {
        if (empty($options) || !is_array($options)) {
            return $options;
        }

        $this->load->model('extension/module/dockercart_filter');

        foreach ($options as $opt_id => $values) {
            if (!is_array($values) || count($values) <= 1) {
                continue;
            }

            $value_ids = array_map('intval', $values);
            $query = $this->db->query("
                SELECT option_value_id, sort_order
                FROM " . DB_PREFIX . "option_value
                WHERE option_value_id IN (" . implode(',', $value_ids) . ")
            ");

            $sort_orders = [];
            foreach ($query->rows as $row) {
                $sort_orders[(int)$row['option_value_id']] = (int)$row['sort_order'];
            }

            usort($options[$opt_id], function($a, $b) use ($sort_orders) {
                $a_id = (int)$a;
                $b_id = (int)$b;
                $a_order = $sort_orders[$a_id] ?? 0;
                $b_order = $sort_orders[$b_id] ?? 0;

                if ($a_order !== $b_order) {
                    return $a_order - $b_order;
                }
                return $a_id - $b_id;
            });
        }

        return $options;
    }

    private function sortAttributeValuesByOrder(&$attributes) {
        if (empty($attributes) || !is_array($attributes)) {
            return $attributes;
        }

        foreach ($attributes as $attr_id => $values) {
            if (!is_array($values) || count($values) <= 1) {
                continue;
            }

            $attr_model = $this->model_extension_module_dockercart_filter;
            usort($attributes[$attr_id], function($a, $b) {

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
            });
        }

        return $attributes;
    }

    private function sortManufacturersByOrder(&$manufacturers) {
        if (empty($manufacturers) || !is_array($manufacturers) || count($manufacturers) <= 1) {
            return $manufacturers;
        }

        $mfr_ids = array_map('intval', $manufacturers);
        $query = $this->db->query("
            SELECT manufacturer_id, sort_order
            FROM " . DB_PREFIX . "manufacturer
            WHERE manufacturer_id IN (" . implode(',', $mfr_ids) . ")
        ");

        $sort_orders = [];
        foreach ($query->rows as $row) {
            $sort_orders[(int)$row['manufacturer_id']] = (int)$row['sort_order'];
        }

        usort($manufacturers, function($a, $b) use ($sort_orders) {
            $a_id = (int)$a;
            $b_id = (int)$b;
            $a_order = $sort_orders[$a_id] ?? 0;
            $b_order = $sort_orders[$b_id] ?? 0;

            if ($a_order !== $b_order) {
                return $a_order - $b_order;
            }
            return $a_id - $b_id;
        });

        return $manufacturers;
    }

    private function encodeFilters($price_min, $price_max, $manufacturers, $attributes, $options) {
        $data = [];

        if ($price_min !== '' && $price_min !== null) {
            $data['price_min'] = $price_min;
        }

        if ($price_max !== '' && $price_max !== null) {
            $data['price_max'] = $price_max;
        }

        if (!empty($manufacturers) && is_array($manufacturers)) {

            $mfr_values = array_map('intval', $manufacturers);
            $data['manufacturers'] = array_values($mfr_values);
        }

        if (!empty($attributes) && is_array($attributes)) {

            $normalized_attrs = [];
            foreach ($attributes as $attr_id => $values) {
                if (is_array($values)) {
                    $normalized_attrs[$attr_id] = array_values($values);
                } else {
                    $normalized_attrs[$attr_id] = [$values];
                }
            }
            $data['attributes'] = $normalized_attrs;
        }

        if (!empty($options) && is_array($options)) {

            $normalized_opts = [];
            foreach ($options as $opt_id => $values) {
                if (is_array($values)) {
                    $sorted_values = array_map('intval', $values);
                    $normalized_opts[$opt_id] = array_values($sorted_values);
                } else {
                    $normalized_opts[$opt_id] = [(int)$values];
                }
            }
            $data['options'] = $normalized_opts;
        }

        if (empty($data)) {
            return '';
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return bin2hex($json);
    }

    private function decodeFilters($dcf) {
        $result = [
            'price_min' => '',
            'price_max' => '',
            'manufacturers' => [],
            'attributes' => [],
            'options' => [],
            'currency' => null
        ];

        if (empty($dcf)) {
            return $result;
        }

        $json = @hex2bin($dcf);
        if ($json === false) {
            return $result;
        }

        $data = @json_decode($json, true);
        if (!is_array($data)) {
            return $result;
        }

        if (isset($data['currency'])) {
            $result['currency'] = $data['currency'];
        }

        if (isset($data['price_min'])) {
            $result['price_min'] = (int)$data['price_min'];
        }
        if (isset($data['price_max'])) {
            $result['price_max'] = (int)$data['price_max'];
        }
        if (isset($data['manufacturers']) && is_array($data['manufacturers'])) {
            $sorted_mfr = array_map('intval', $data['manufacturers']);
            sort($sorted_mfr);
            $result['manufacturers'] = $sorted_mfr;
        }
        if (isset($data['attributes']) && is_array($data['attributes'])) {

            $normalized_attrs = [];
            foreach ($data['attributes'] as $attr_id => $values) {
                if (is_array($values)) {
                    $sorted_values = $values;
                    sort($sorted_values);
                    $normalized_attrs[$attr_id] = $sorted_values;
                } else {
                    $normalized_attrs[$attr_id] = [$values];
                }
            }
            $result['attributes'] = $normalized_attrs;
        }
        if (isset($data['options']) && is_array($data['options'])) {

            $normalized_opts = [];
            foreach ($data['options'] as $opt_id => $values) {
                if (is_array($values)) {
                    $sorted_values = array_map('intval', $values);
                    sort($sorted_values);
                    $normalized_opts[$opt_id] = $sorted_values;
                } else {
                    $normalized_opts[$opt_id] = [(int)$values];
                }
            }
            $result['options'] = $normalized_opts;
        }

        return $result;
    }

    public function index($setting = []) {

        $this->logger->debug('INDEX: Module called with setting=' . json_encode($setting));

        $status = !empty($setting['status']) ? $setting['status'] : $this->config->get('module_dockercart_filter_status');
        $cache_time = !empty($setting['cache_time']) ? $setting['cache_time'] : $this->config->get('module_dockercart_filter_cache_time');

        $this->logger->debug('INDEX: status=' . $status . ', cache_time=' . $cache_time);

        $this->module_setting = $setting;

        if (empty($status)) {
            return '';
        }

        $license_key = $this->config->get('module_dockercart_filter_license_key');
        if (!empty($license_key)) {
            if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                $this->logger->debug('ERROR: License library not found at ' . DIR_SYSTEM . 'library/dockercart_license.php');
                return '';
            }

            require_once(DIR_SYSTEM . 'library/dockercart_license.php');
            if (class_exists('DockercartLicense')) {
                $license = new DockercartLicense($this->registry);
                $result = $license->verify($license_key, 'dockercart_filter');

                if (!$result['valid']) {
                    $this->logger->debug('ERROR: Invalid license - ' . (isset($result['error']) ? $result['error'] : 'Unknown error'));
                    return '';
                }

                $this->logger->debug('LICENSE: Valid license verified for dockercart_filter');
            }
        }

        $this->load->language('extension/module/dockercart_filter');
        $this->load->model('extension/module/dockercart_filter');

        $data = [];

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_price'] = $this->language->get('text_price');
        $data['text_manufacturer'] = $this->language->get('text_manufacturer');
        $data['text_filter'] = $this->language->get('text_filter');
        $data['text_reset'] = $this->language->get('text_reset');
        $data['text_from'] = $this->language->get('text_from');
        $data['text_to'] = $this->language->get('text_to');
        $data['text_all'] = $this->language->get('text_all');
        $data['text_select'] = $this->language->get('text_select');
        $data['text_search'] = $this->language->get('text_search');
        $data['text_clear'] = $this->language->get('text_clear');
        $data['text_apply'] = $this->language->get('text_apply');
        $data['text_active_filters'] = $this->language->get('text_active_filters');
        $data['text_remove_filter'] = $this->language->get('text_remove_filter');
        $data['text_clear_all'] = $this->language->get('text_clear_all');

        $category_id = 0;
        if (isset($this->request->get['path'])) {
            $path = explode('_', $this->request->get['path']);
            $category_id = (int)end($path);
        }

        $decoded = $this->decodeFilters($this->request->get['dcf'] ?? '');

        $filter_price_min = $decoded['price_min'];
        $filter_price_max = $decoded['price_max'];
        $filter_manufacturer = $decoded['manufacturers'];
        $filter_attribute = $decoded['attributes'];
        $filter_option = $decoded['options'];
        $filter_currency = $decoded['currency'];

        $user_set_price_min = $filter_price_min;
        $user_set_price_max = $filter_price_max;

        $this->logger->debug('CONTROLLER: dcf param = ' . ($this->request->get['dcf'] ?? 'none'));
        $this->logger->debug('CONTROLLER: Filter currency from URL = ' . ($filter_currency ?? 'none'));
        $this->logger->debug('CONTROLLER: Current session currency = ' . $this->session->data['currency']);
        $this->logger->debug('CONTROLLER: Parsed filter_manufacturer = ' . json_encode($filter_manufacturer));
        $this->logger->debug('CONTROLLER: Parsed filter_attribute = ' . json_encode($filter_attribute));
        $this->logger->debug('CONTROLLER: Parsed filter_option = ' . json_encode($filter_option));

        $cache_time = $cache_time ? (int)$cache_time : 3600;

        $filter_data = array(
            'filter_category_id' => $category_id
        );

        if (!empty($filter_manufacturer)) {
            $filter_data['filter_manufacturers'] = array_map('intval', $filter_manufacturer);
        }

        if (!empty($filter_attribute)) {
            $filter_data['filter_attributes'] = $filter_attribute;
        }

        if (!empty($filter_option)) {
            $filter_data['filter_options'] = $filter_option;
        }

        if ($filter_price_min !== '') {

            $price_currency = $filter_currency ?: $this->session->data['currency'];
            $filter_price_min = (float)$this->currency->convert($filter_price_min, $price_currency, $this->config->get('config_currency'));
            $filter_data['filter_price_min'] = $filter_price_min;
            $this->logger->debug('CONTROLLER: Converted price_min from ' . $price_currency . ' to base: ' . $filter_price_min);
        }

        if ($filter_price_max !== '') {

            $price_currency = $filter_currency ?: $this->session->data['currency'];
            $filter_price_max = (float)$this->currency->convert($filter_price_max, $price_currency, $this->config->get('config_currency'));
            $filter_data['filter_price_max'] = $filter_price_max;
            $this->logger->debug('CONTROLLER: Converted price_max from ' . $price_currency . ' to base: ' . $filter_price_max);
        }

        $base_currency = $this->config->get('config_currency');
        $current_currency = isset($this->session->data['currency']) ? $this->session->data['currency'] : $base_currency;

        $base_cache_key = 'dockercart_filter.' . $category_id . '.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id');

        $has_active_filters = !empty($filter_manufacturer) ||
                             !empty($filter_attribute) ||
                             !empty($filter_option) ||
                             $filter_price_min !== '' ||
                             $filter_price_max !== '';

        if ($cache_time > 0 && !$has_active_filters) {
            $this->logger->debug('CONTROLLER: Requesting price_range from model with currency=' . $current_currency);
            $data['price_range'] = $this->model_extension_module_dockercart_filter->getPriceRange($filter_data);
        } else {
            $data['price_range'] = $this->model_extension_module_dockercart_filter->getPriceRange($filter_data);
        }

        $manufacturers_cache_key = $base_cache_key . '.manufacturers';
        $manufacturer_filter_data = $filter_data;
        unset($manufacturer_filter_data['filter_manufacturers']);

        if ($cache_time > 0 && !$has_active_filters) {
            $cached_manufacturers = $this->cache->get($manufacturers_cache_key);
            if ($cached_manufacturers !== null && $cached_manufacturers !== false) {
                $this->logger->debug('CONTROLLER: Using cached manufacturers');
                $data['manufacturers'] = $cached_manufacturers;
            } else {
                $data['manufacturers'] = $this->model_extension_module_dockercart_filter->getManufacturers($manufacturer_filter_data);
                $this->cache->set($manufacturers_cache_key, $data['manufacturers'], $cache_time);
            }
        } else {
            $data['manufacturers'] = $this->model_extension_module_dockercart_filter->getManufacturers($manufacturer_filter_data);
        }

        $attributes_cache_key = $base_cache_key . '.attributes';
        if ($cache_time > 0 && !$has_active_filters) {
            $cached_attributes = $this->cache->get($attributes_cache_key);
            if ($cached_attributes !== null && $cached_attributes !== false) {
                $this->logger->debug('CONTROLLER: Using cached attributes');
                $data['attributes'] = $cached_attributes;
            } else {
                $data['attributes'] = $this->model_extension_module_dockercart_filter->getAttributes($filter_data);
                $this->cache->set($attributes_cache_key, $data['attributes'], $cache_time);
            }
        } else {
            $data['attributes'] = $this->model_extension_module_dockercart_filter->getAttributes($filter_data);
        }

        $disabledAttributesRaw = $this->config->get('module_dockercart_filter_disabled_attributes');
        $disabledAttributes = array();
        if ($disabledAttributesRaw) {
            if (is_string($disabledAttributesRaw)) {
                $disabledAttributes = unserialize($disabledAttributesRaw);
            } else {
                $disabledAttributes = (array)$disabledAttributesRaw;
            }
        }

        if (!empty($disabledAttributes)) {
            $data['attributes'] = array_filter($data['attributes'], function($attr) use ($disabledAttributes) {
                return !in_array($attr['attribute_id'], $disabledAttributes);
            });
        }

        $options_cache_key = $base_cache_key . '.options';
        $option_filter_data = $filter_data;
        if (!empty($filter_option)) {
            $option_filter_data['skip_filter_options'] = array_keys($filter_option);
        }

        if ($cache_time > 0 && !$has_active_filters) {
            $cached_options = $this->cache->get($options_cache_key);
            if ($cached_options !== null && $cached_options !== false) {
                $this->logger->debug('CONTROLLER: Using cached options');
                $data['options'] = $cached_options;
            } else {
                $data['options'] = $this->model_extension_module_dockercart_filter->getOptions($option_filter_data);
                $this->cache->set($options_cache_key, $data['options'], $cache_time);
            }
        } else {
            $data['options'] = $this->model_extension_module_dockercart_filter->getOptions($option_filter_data);
        }

        $disabledOptionsRaw = $this->config->get('module_dockercart_filter_disabled_options');
        $disabledOptions = array();
        if ($disabledOptionsRaw) {
            if (is_string($disabledOptionsRaw)) {
                $disabledOptions = unserialize($disabledOptionsRaw);
            } else {
                $disabledOptions = (array)$disabledOptionsRaw;
            }
        }

        if (!empty($disabledOptions)) {
            $data['options'] = array_filter($data['options'], function($opt) use ($disabledOptions) {
                return !in_array($opt['option_id'], $disabledOptions);
            });
        }

        $this->logger->debug('CONTROLLER: Model returned attributes = ' . json_encode(array_map(function($a) {
            return ['attribute_id' => $a['attribute_id'], 'name' => $a['name'], 'values' => array_map(function($v) { return $v['text']; }, $a['values'])];
        }, $data['attributes'])));

        $this->logger->debug('CONTROLLER: Model returned options = ' . json_encode(array_map(function($o) {
            return ['option_id' => $o['option_id'], 'name' => $o['name'], 'values_count' => count($o['values'])];
        }, $data['options'])));

        if ($this->config->get('module_dockercart_filter_seo_mode')) {

            foreach ($data['manufacturers'] as &$manufacturer) {
                $manufacturer['filter_url'] = $this->buildFilterItemUrl('manufacturer', $manufacturer['manufacturer_id']);
            }

            foreach ($data['attributes'] as &$attribute) {
                foreach ($attribute['values'] as &$value) {
                    $value['filter_url'] = $this->buildFilterItemUrl('attribute', $attribute['attribute_id'], $value['text']);
                }
            }

            foreach ($data['options'] as &$option) {
                foreach ($option['values'] as &$value) {
                    $value['filter_url'] = $this->buildFilterItemUrl('option', $option['option_id'], $value['option_value_id']);
                }
            }
        }

        if (!empty($filter_manufacturer)) {
            $available_mfr_ids = array_column($data['manufacturers'], 'manufacturer_id');
            $missing_mfr_ids = [];

            foreach ($filter_manufacturer as $selected_mfr_id) {
                if (!in_array($selected_mfr_id, $available_mfr_ids)) {
                    $missing_mfr_ids[] = (int)$selected_mfr_id;
                }
            }

            if (!empty($missing_mfr_ids)) {
                $query = $this->db->query("
                    SELECT manufacturer_id, name
                    FROM " . DB_PREFIX . "manufacturer
                    WHERE manufacturer_id IN (" . implode(',', $missing_mfr_ids) . ")
                ");

                $mfr_data_map = [];
                foreach ($query->rows as $mfr) {
                    $mfr_data_map[(int)$mfr['manufacturer_id']] = $mfr['name'];
                }

                foreach ($missing_mfr_ids as $mfr_id) {
                    if (isset($mfr_data_map[$mfr_id])) {
                        $unavailable_mfr = array(
                            'manufacturer_id' => $mfr_id,
                            'name' => $mfr_data_map[$mfr_id],
                            'product_count' => 0,
                            'available' => false
                        );
                        if ($this->config->get('module_dockercart_filter_seo_mode')) {
                            $unavailable_mfr['filter_url'] = $this->buildFilterItemUrl('manufacturer', $mfr_id);
                        }
                        $data['manufacturers'][] = $unavailable_mfr;
                    }
                }
            }
        }

        if (!empty($filter_attribute)) {
            $available_attr_ids = array_column($data['attributes'], 'attribute_id');
            $missing_attr_ids = [];

            foreach ($filter_attribute as $selected_attr_id => $selected_values) {
                if (!in_array($selected_attr_id, $available_attr_ids)) {
                    $missing_attr_ids[] = (int)$selected_attr_id;
                }
            }

            foreach ($filter_attribute as $selected_attr_id => $selected_values) {
                $found_attr = false;

                foreach ($data['attributes'] as &$attr) {
                    if ($attr['attribute_id'] == $selected_attr_id) {
                        $found_attr = true;

                        $existing_values = array_column($attr['values'], 'text');
                        foreach ($selected_values as $sel_value) {
                            if (!empty($sel_value) && trim($sel_value) !== '') {

                                $found_value = false;
                                foreach ($existing_values as $ex_val) {
                                    if (strcasecmp($ex_val, $sel_value) === 0) {
                                        $found_value = true;
                                        break;
                                    }
                                }

                                if (!$found_value) {
                                    $missing_value = array(
                                        'text' => $sel_value,
                                        'count' => 0
                                    );
                                    if ($this->config->get('module_dockercart_filter_seo_mode')) {
                                        $missing_value['filter_url'] = $this->buildFilterItemUrl('attribute', $selected_attr_id, $sel_value);
                                    }
                                    $attr['values'][] = $missing_value;
                                }
                            }
                        }
                        break;
                    }
                }

                if (!$found_attr && !empty($missing_attr_ids)) {
                    if (in_array($selected_attr_id, $missing_attr_ids)) {

                        continue;
                    }
                }
            }

            if (!empty($missing_attr_ids)) {
                $query = $this->db->query("
                    SELECT attribute_id, name
                    FROM " . DB_PREFIX . "attribute_description
                    WHERE attribute_id IN (" . implode(',', $missing_attr_ids) . ")
                    AND language_id = " . (int)$this->config->get('config_language_id') . "
                ");

                $attr_data_map = [];
                foreach ($query->rows as $attr) {
                    $attr_data_map[(int)$attr['attribute_id']] = $attr['name'];
                }

                foreach ($missing_attr_ids as $attr_id) {
                    if (isset($attr_data_map[$attr_id]) && isset($filter_attribute[$attr_id])) {
                        $unavailable_attr = array(
                            'attribute_id' => $attr_id,
                            'name' => $attr_data_map[$attr_id],
                            'values' => [],
                            'available' => false
                        );

                        foreach ($filter_attribute[$attr_id] as $value) {
                            if (!empty($value) && trim($value) !== '') {
                                $val_entry = array(
                                    'text' => $value,
                                    'count' => 0
                                );
                                if ($this->config->get('module_dockercart_filter_seo_mode')) {
                                    $val_entry['filter_url'] = $this->buildFilterItemUrl('attribute', $attr_id, $value);
                                }
                                $unavailable_attr['values'][] = $val_entry;
                            }
                        }

                        if (!empty($unavailable_attr['values'])) {
                            $data['attributes'][] = $unavailable_attr;
                        }
                    }
                }
            }
        }

        if (!empty($filter_option)) {

            $all_option_ids = array_keys($filter_option);
            $value_names_map = [];
            if (!empty($all_option_ids)) {
                $query_values = $this->db->query("SELECT option_id, option_value_id, name FROM " . DB_PREFIX . "option_value_description WHERE option_id IN (" . implode(',', array_map('intval', $all_option_ids)) . ") AND language_id = " . (int)$this->config->get('config_language_id'));
                foreach ($query_values->rows as $row) {
                    $value_names_map[$row['option_id']][$row['option_value_id']] = $row['name'];
                }
            }

            foreach ($filter_option as $selected_opt_id => $selected_values) {
                $found_opt = false;

                foreach ($data['options'] as &$opt) {
                    if ($opt['option_id'] == $selected_opt_id) {
                        $found_opt = true;

                        $existing_value_ids = array_column($opt['values'], 'option_value_id');
                        foreach ($selected_values as $sel_value_id) {
                            if (!in_array($sel_value_id, $existing_value_ids)) {

                                $value_name = isset($value_names_map[$selected_opt_id][$sel_value_id]) ? $value_names_map[$selected_opt_id][$sel_value_id] : '';
                                if (!empty($value_name) && trim($value_name) !== '') {
                                    $missing_value = array(
                                        'option_value_id' => (int)$sel_value_id,
                                        'name' => $value_name,
                                        'count' => 0
                                    );
                                    if ($this->config->get('module_dockercart_filter_seo_mode')) {
                                        $missing_value['filter_url'] = $this->buildFilterItemUrl('option', $selected_opt_id, $sel_value_id);
                                    }
                                    $opt['values'][] = $missing_value;
                                }
                            }
                        }
                        break;
                    }
                }

                if (!$found_opt) {

                    $query = $this->db->query("SELECT option_id, name FROM " . DB_PREFIX . "option_description WHERE option_id = " . (int)$selected_opt_id . " AND language_id = " . (int)$this->config->get('config_language_id'));
                    if ($query->row) {
                        $unavailable_opt = array(
                            'option_id' => (int)$query->row['option_id'],
                            'name' => $query->row['name'],
                            'values' => [],
                            'available' => false
                        );

                        foreach ($selected_values as $value_id) {
                            $value_name = isset($value_names_map[$selected_opt_id][$value_id]) ? $value_names_map[$selected_opt_id][$value_id] : '';
                            if (!empty($value_name) && trim($value_name) !== '') {
                                $val_entry = array(
                                    'option_value_id' => (int)$value_id,
                                    'name' => $value_name,
                                    'count' => 0
                                );
                                if ($this->config->get('module_dockercart_filter_seo_mode')) {
                                    $val_entry['filter_url'] = $this->buildFilterItemUrl('option', $selected_opt_id, $value_id);
                                }
                                $unavailable_opt['values'][] = $val_entry;
                            }
                        }

                        if (!empty($unavailable_opt['values'])) {
                            $data['options'][] = $unavailable_opt;
                        }
                    }
                }
            }
        }

        if (empty($filter_price_min) && isset($data['price_range']['min'])) {
            $filter_price_min = $data['price_range']['min'];
        }
        if (empty($filter_price_max) && isset($data['price_range']['max'])) {
            $filter_price_max = $data['price_range']['max'];
        }

        $has_content = !empty($data['manufacturers']) ||
                       !empty($data['attributes']) ||
                       !empty($data['options']) ||
                       ($data['price_range']['min'] > 0 || $data['price_range']['max'] > 0);

        if (!$has_content) {
            return '';
        }

        $data['filter_price_min'] = $user_set_price_min;
        $data['filter_price_max'] = $user_set_price_max;
        $data['filter_manufacturer'] = $filter_manufacturer;

        $filter_attr_with_str_keys = [];
        if (is_array($filter_attribute)) {
            foreach ($filter_attribute as $attr_id => $values) {
                $filter_attr_with_str_keys[(string)$attr_id] = $values;
            }
        }
        $data['filter_attribute'] = $filter_attr_with_str_keys;

        $filter_opt_with_str_keys = [];
        if (is_array($filter_option)) {
            foreach ($filter_option as $opt_id => $values) {
                $filter_opt_with_str_keys[(string)$opt_id] = $values;
            }
        }
        $data['filter_option'] = $filter_opt_with_str_keys;

        $this->logger->debug('CONTROLLER: Price range ready (with currency conversion from model): min=' . (!empty($data['price_range']) ? $data['price_range']['min'] : 'N/A') . ' max=' . (!empty($data['price_range']) ? $data['price_range']['max'] : 'N/A'));

        $this->logger->debug('CONTROLLER: Passing to template - filter_attribute = ' . json_encode($data['filter_attribute']));
        $this->logger->debug('CONTROLLER: Passing to template - attributes count = ' . count($data['attributes']));

        $data['active_filters'] = $this->buildActiveFilters(
            $user_set_price_min,
            $user_set_price_max,
            $filter_manufacturer,
            $filter_attribute,
            $filter_option,
            $data['manufacturers'],
            $data['attributes'],
            $data['options'],
            $filter_currency
        );

        $data['filter_depth'] = $this->countFilterDepth($filter_manufacturer, $filter_attribute, $filter_option, $user_set_price_min, $user_set_price_max);

        $data['dynamic_heading'] = $this->generateDynamicHeading($category_id, $filter_manufacturer, $filter_attribute, $filter_option, $data['manufacturers'], $data['attributes'], $data['options']);

        $data['filter_mode'] = $this->config->get('module_dockercart_filter_mode') ?: 'button';
        $data['seo_mode'] = $this->config->get('module_dockercart_filter_seo_mode') ?: 1;
        $data['items_limit'] = (int)$this->config->get('module_dockercart_filter_items_limit') ?: 10;
        $data['debug_mode'] = (int)$this->config->get('module_dockercart_filter_debug') ?: 0;
        $data['filter_theme'] = $this->config->get('module_dockercart_filter_theme') ?: 'light';
        $data['custom_css'] = $this->config->get('module_dockercart_filter_custom_css') ?: '';
        $data['mobile_breakpoint'] = (int)$this->config->get('module_dockercart_filter_mobile_breakpoint') ?: 768;

        $primary_color = $this->config->get('module_dockercart_filter_primary_color') ?: '#007bff';
        $primary_color = trim((string)$primary_color);
        if ($primary_color !== '' && strpos($primary_color, '#') !== 0) {
            $primary_color = '#' . $primary_color;
        }
        $data['primary_color'] = $primary_color ?: '#007bff';
        $data['category_id'] = $category_id;

        $data['text_show_more'] = $this->language->get('text_show_more');
        $data['text_show_less'] = $this->language->get('text_show_less');

        $this->document->addStyle('catalog/view/javascript/dockercart_filter/dockercart_filter.css');
        $this->document->addScript('catalog/view/javascript/dockercart_filter/dockercart_filter.js');

        $display_currency_for_symbol = $filter_currency ?: $this->session->data['currency'];
        // Provide both left and right currency symbols to the view so templates can position them correctly
        $data['currency_symbol_left'] = $this->currency->getSymbolLeft($display_currency_for_symbol);
        $data['currency_symbol_right'] = $this->currency->getSymbolRight($display_currency_for_symbol);
        // Backwards compatibility: keep `currency_symbol` as the left symbol when available, otherwise right
        if (!empty($data['currency_symbol_left'])) {
            $data['currency_symbol'] = $data['currency_symbol_left'];
        } else {
            $data['currency_symbol'] = $data['currency_symbol_right'];
        }

        $data['current_currency'] = $this->session->data['currency'];
        $data['base_currency'] = $this->config->get('config_currency');

        try {
            return $this->load->view('extension/module/dockercart_filter', $data);
        } catch (Exception $e) {
            $this->logger->debug('ERROR in dockercart_filter view: ' . $e->getMessage());
            $this->logger->debug('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    public function ajaxFilter() {

        $this->load->language('extension/module/dockercart_filter');
        $this->load->model('extension/module/dockercart_filter');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $json = [];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $post_data = json_decode(file_get_contents('php://input'), true);

            $category_id = isset($post_data['category_id']) ? (int)$post_data['category_id'] : 0;
            $price_min = isset($post_data['price_min']) ? $post_data['price_min'] : '';
            $price_max = isset($post_data['price_max']) ? $post_data['price_max'] : '';
            $manufacturer = isset($post_data['manufacturer']) ? $post_data['manufacturer'] : [];
            $attribute = isset($post_data['attribute']) ? $post_data['attribute'] : [];
            $option = isset($post_data['option']) ? $post_data['option'] : [];

            $filter_data = [
                'category_id' => $category_id,
                'price_min' => $price_min,
                'price_max' => $price_max,
                'manufacturer' => $manufacturer,
                'attribute' => $attribute,
                'option' => $option,
                'start' => 0,
                'limit' => 20
            ];

            $results = $this->model_extension_module_dockercart_filter->getFilteredProducts($filter_data);
            $total = $this->model_extension_module_dockercart_filter->getTotalFilteredProducts($filter_data);

            $products = [];
            foreach ($results as $result) {
                if ($result['image']) {
                    $image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
                } else {
                    $image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
                }

                if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                    $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $price = false;
                }

                if ((float)$result['special']) {
                    $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $special = false;
                }

                $products[] = [
                    'product_id' => $result['product_id'],
                    'name' => $result['name'],
                    'image' => $image,
                    'price' => $price,
                    'special' => $special,
                    'href' => $this->url->link('product/product', 'product_id=' . $result['product_id'])
                ];
            }

            $json['success'] = true;
            $json['products'] = $products;
            $json['total'] = $total;
        } else {
            $json['error'] = 'Invalid request method';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function buildFilterUrl($filter_data = []) {
        $price_min = !empty($filter_data['price_min']) ? $filter_data['price_min'] : '';
        $price_max = !empty($filter_data['price_max']) ? $filter_data['price_max'] : '';
        $manufacturers = !empty($filter_data['manufacturer']) ? (array)$filter_data['manufacturer'] : [];
        $attributes = !empty($filter_data['attribute']) ? $filter_data['attribute'] : [];
        $options = !empty($filter_data['option']) ? $filter_data['option'] : [];

        $dcf = $this->encodeFilters($price_min, $price_max, $manufacturers, $attributes, $options);

        if (empty($dcf)) {
            return '';
        }

        return '&dcf=' . $dcf;
    }

    private function buildActiveFilters($price_min, $price_max, $manufacturers, $attributes, $options, $manufacturer_data, $attribute_data, $option_data, $filter_currency = null) {
        $manufacturers = is_array($manufacturers) ? $manufacturers : [];
        $attributes = is_array($attributes) ? $attributes : [];
        $options = is_array($options) ? $options : [];
        $manufacturer_data = is_array($manufacturer_data) ? $manufacturer_data : [];
        $attribute_data = is_array($attribute_data) ? $attribute_data : [];
        $option_data = is_array($option_data) ? $option_data : [];

        $this->logger->debug('buildActiveFilters: Manufacturers input=' . json_encode($manufacturers));
        $this->logger->debug('buildActiveFilters: Attributes input=' . json_encode($attributes));
        $this->logger->debug('buildActiveFilters: Options input=' . json_encode($options));        

        $active = [];
        $current_url = $this->getCurrentUrl();

        if ($price_min !== '' || $price_max !== '') {
            $label = $this->language->get('text_price') . ': ';

            $display_currency = $filter_currency ?: $this->session->data['currency'];

            $currency_symbol_left = $this->currency->getSymbolLeft($display_currency);
            $currency_symbol_right = $this->currency->getSymbolRight($display_currency);

            $this->logger->debug('buildActiveFilters: display_currency=' . $display_currency . ', symbol_left=' . ($currency_symbol_left ?: 'empty') . ', symbol_right=' . ($currency_symbol_right ?: 'empty'));

            $format_price = function($price) use ($currency_symbol_left, $currency_symbol_right) {
                $price = number_format((float)$price, 2, '.', '');
                if ($currency_symbol_left) {
                    return $currency_symbol_left . $price;
                } elseif ($currency_symbol_right) {
                    return $price . $currency_symbol_right;
                }
                return $price;
            };

            if ($price_min !== '' && $price_max !== '') {
                $label .= $format_price($price_min) . ' - ' . $format_price($price_max);
            } elseif ($price_min !== '') {
                $label .= $this->language->get('text_from') . ' ' . $format_price($price_min);
            } else {
                $label .= $this->language->get('text_to') . ' ' . $format_price($price_max);
            }

            $active[] = [
                'type' => 'price',
                'label' => $label,
                'remove_url' => $this->buildRemoveUrl($current_url, 'price')
            ];
        }

        if (!empty($manufacturers)) {
            foreach ($manufacturers as $man_id) {
                $manufacturer_name = '';

                foreach ($manufacturer_data as $m) {
                    if ($m['manufacturer_id'] == $man_id) {
                        $manufacturer_name = $m['name'];
                        break;
                    }
                }

                if (!$manufacturer_name) {
                    $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = " . (int)$man_id);
                    if ($query->row) {
                        $manufacturer_name = $query->row['name'];
                    }
                }

                if ($manufacturer_name) {
                    $active[] = [
                        'type' => 'manufacturer',
                        'id' => $man_id,
                        'label' => $this->language->get('text_manufacturer') . ': ' . $manufacturer_name,
                        'remove_url' => $this->buildRemoveUrl($current_url, 'manufacturer', $man_id)
                    ];
                }
            }
        }

        if (!empty($attributes)) {
            foreach ($attributes as $attr_id => $values) {
                $attribute_name = '';

                foreach ($attribute_data as $a) {
                    if ($a['attribute_id'] == $attr_id) {
                        $attribute_name = $a['name'];
                        break;
                    }
                }

                if (!$attribute_name) {
                    $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "attribute_description
                                            WHERE attribute_id = " . (int)$attr_id . "
                                            AND language_id = " . (int)$this->config->get('config_language_id'));
                    if ($query->row) {
                        $attribute_name = $query->row['name'];
                    }
                }

                foreach ($values as $value) {
                    if ($attribute_name && $value) {
                        $active[] = [
                            'type' => 'attribute',
                            'id' => $attr_id,
                            'value' => $value,
                            'label' => $attribute_name . ': ' . $value,
                            'remove_url' => $this->buildRemoveUrl($current_url, 'attribute', $attr_id, $value)
                        ];
                    }
                }
            }
        }

        if (!empty($options)) {
            foreach ($options as $opt_id => $values) {
                $option_name = '';
                $option_values_map = [];

                foreach ($option_data as $o) {
                    if ($o['option_id'] == $opt_id) {
                        $option_name = $o['name'];
                        foreach ($o['values'] as $ov) {
                            $option_values_map[$ov['option_value_id']] = $ov['name'];
                        }
                        break;
                    }
                }

                if (!$option_name) {
                    $query = $this->db->query("SELECT name FROM " . DB_PREFIX . "option_description
                                            WHERE option_id = " . (int)$opt_id . "
                                            AND language_id = " . (int)$this->config->get('config_language_id'));
                    if ($query->row) {
                        $option_name = $query->row['name'];
                    }
                }

                if (empty($option_values_map)) {
                    $query = $this->db->query("SELECT option_value_id, name FROM " . DB_PREFIX . "option_value_description
                                            WHERE option_id = " . (int)$opt_id . "
                                            AND language_id = " . (int)$this->config->get('config_language_id'));
                    foreach ($query->rows as $row) {
                        $option_values_map[$row['option_value_id']] = $row['name'];
                    }
                }

                foreach ($values as $value_id) {
                    $value_name = isset($option_values_map[$value_id]) ? $option_values_map[$value_id] : '';
                    if ($option_name && $value_name) {
                        $remove_url = $this->buildRemoveUrl($current_url, 'option', $opt_id, $value_id);
                        $this->logger->debug('buildActiveFilters: Creating active filter - opt_id=' . $opt_id . ', value_id=' . $value_id . ', label=' . $option_name . ': ' . $value_name);

                        $active[] = [
                            'type' => 'option',
                            'id' => $opt_id,
                            'value' => $value_id,
                            'label' => $option_name . ': ' . $value_name,
                            'remove_url' => $remove_url
                        ];
                    }
                }
            }
        }

        $this->logger->debug('buildActiveFilters: Total active filters = ' . count($active));

        return $active;
    }

    private function getCurrentUrl() {
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : 'product/category';
        return $this->buildSeoUrl($route, $this->request->get);
    }

    private function buildRemoveUrl($current_url, $type, $id = null, $value = null) {
        $this->logger->debug('buildRemoveUrl: type=' . $type . ', id=' . $id . ', value=' . $value);

        $decoded = $this->decodeFilters($this->request->get['dcf'] ?? '');

        $price_min = $decoded['price_min'];
        $price_max = $decoded['price_max'];
        $manufacturers = $decoded['manufacturers'];
        $attributes = $decoded['attributes'];
        $options = $decoded['options'];

        $this->logger->debug('buildRemoveUrl: BEFORE - decoded=' . json_encode($decoded));
        
        switch ($type) {
            case 'price':
                $price_min = '';
                $price_max = '';
                break;

            case 'manufacturer':
                $manufacturers = array_values(array_filter($manufacturers, function($m) use ($id) {
                    return (int)$m != (int)$id;
                }));
                break;

            case 'attribute':
                if (isset($attributes[$id])) {
                    $attributes[$id] = array_values(array_filter($attributes[$id], function($v) use ($value) {
                        return strcasecmp(trim($v), trim($value)) !== 0;
                    }));

                    if (empty($attributes[$id])) {
                        unset($attributes[$id]);
                    }
                }
                break;

            case 'option':
                if (isset($options[$id])) {
                    $options[$id] = array_values(array_filter($options[$id], function($v) use ($value) {
                        return (int)$v != (int)$value;
                    }));

                    if (empty($options[$id])) {
                        unset($options[$id]);
                    }
                }
                break;
        }

        $this->logger->debug('buildRemoveUrl: AFTER - manufacturers=' . json_encode($manufacturers) . ', attributes=' . json_encode($attributes) . ', options=' . json_encode($options));

        $dcf = $this->encodeFilters($price_min, $price_max, $manufacturers, $attributes, $options);

        $params = $this->request->get;
        unset($params['route']);
        unset($params['_route_']);
        unset($params['dcf']);

        if (!empty($dcf)) {
            $params['dcf'] = $dcf;
        }

        $route = 'product/category';
        if (isset($this->request->get['product_id'])) {
            $route = 'product/product';
        } elseif (isset($this->request->get['manufacturer_id'])) {
            $route = 'product/manufacturer/info';
        }

        $url = $this->buildSeoUrl($route, $params);

        $this->logger->debug('buildRemoveUrl: Final URL = ' . $url);
        

        return $url;
    }

    private function countFilterDepth($manufacturers, $attributes, $options, $price_min, $price_max) {

        if ($price_min !== '' || $price_max !== '') {
            return [
                'should_index' => false,
                'reason' => 'Price filter applied - always noindex'
            ];
        }

        $filter_types_count = 0;
        $has_manufacturer = !empty($manufacturers);
        $has_attribute = !empty($attributes);
        $has_option = !empty($options);

        if (!$has_manufacturer && !$has_attribute && !$has_option) {
            return [
                'should_index' => true,
                'reason' => 'No filters applied - main category page'
            ];
        }

        $manufacturer_count = is_array($manufacturers) ? count($manufacturers) : 0;

        if ($has_manufacturer) $filter_types_count++;
        if ($has_attribute) $filter_types_count++;
        if ($has_option) $filter_types_count++;

        if ($has_manufacturer && !$has_attribute && !$has_option && $manufacturer_count == 1) {
            return [
                'should_index' => true,
                'reason' => 'Single manufacturer only - INDEX'
            ];
        }

        if (!$has_manufacturer && $filter_types_count == 1 && ($has_attribute || $has_option)) {

            $attr_value_count = 0;
            if (!empty($attributes)) {
                foreach ($attributes as $attr_id => $values) {
                    $attr_value_count += is_array($values) ? count($values) : 1;
                }
            }

            $opt_value_count = 0;
            if (!empty($options)) {
                foreach ($options as $opt_id => $values) {
                    $opt_value_count += is_array($values) ? count($values) : 1;
                }
            }

            if ($has_attribute && !$has_option && $attr_value_count <= 1) {
                return [
                    'should_index' => true,
                    'reason' => 'Single attribute with 1 value - INDEX'
                ];
            }
            if ($has_option && !$has_attribute && $opt_value_count <= 1) {
                return [
                    'should_index' => true,
                    'reason' => 'Single option with 1 value - INDEX'
                ];
            }
        }

        if ($has_manufacturer && $filter_types_count == 2 && $manufacturer_count == 1) {

        }

        return [
            'should_index' => false,
            'reason' => 'Multiple or complex filters - noindex'
        ];
    }

    private function generateDynamicHeading($category_id, $manufacturers, $attributes, $options, $manufacturer_data, $attribute_data, $option_data) {
        $this->load->model('catalog/category');

        $heading_parts = [];

        if ($category_id) {
            $category_info = $this->model_catalog_category->getCategory($category_id);
            if ($category_info) {
                $heading_parts[] = $category_info['name'];
            }
        }

        if (!empty($manufacturers)) {

            $sorted_mfr = $this->sortManufacturersByOrder($manufacturers);

            $man_names = [];
            foreach ($sorted_mfr as $man_id) {
                foreach ($manufacturer_data as $m) {
                    if ($m['manufacturer_id'] == $man_id) {
                        $man_names[] = $m['name'];
                        break;
                    }
                }
            }
            if (!empty($man_names)) {
                $heading_parts[] = implode(', ', $man_names);
            }
        }

        if (!empty($attributes)) {
            foreach ($attributes as $attr_id => $values) {
                foreach ($attribute_data as $a) {
                    if ($a['attribute_id'] == $attr_id) {

                        $sorted_values = $values;
                        usort($sorted_values, function($x, $y) {
                            $x_lower = mb_strtolower(trim($x), 'UTF-8');
                            $y_lower = mb_strtolower(trim($y), 'UTF-8');

                            if (is_numeric($x_lower) && is_numeric($y_lower)) {
                                if (strpos($x_lower, '.') !== false || strpos($y_lower, '.') !== false) {
                                    $x_float = (float)$x_lower;
                                    $y_float = (float)$y_lower;
                                    if ($x_float < $y_float) return -1;
                                    if ($x_float > $y_float) return 1;
                                    return 0;
                                } else {
                                    $x_int = (int)$x_lower;
                                    $y_int = (int)$y_lower;
                                    if ($x_int < $y_int) return -1;
                                    if ($x_int > $y_int) return 1;
                                    return 0;
                                }
                            }

                            if (preg_match('/^(\d+(?:\.\d+)?)/', $x_lower, $matches_x) &&
                                preg_match('/^(\d+(?:\.\d+)?)/', $y_lower, $matches_y)) {
                                $num_x = (float)$matches_x[1];
                                $num_y = (float)$matches_y[1];
                                if ($num_x < $num_y) return -1;
                                if ($num_x > $num_y) return 1;
                                return strcasecmp($x_lower, $y_lower);
                            }

                            if (preg_match('/^(.+?)\s*(\d+(?:\.\d+)?)$/', $x_lower, $matches_x) &&
                                preg_match('/^(.+?)\s*(\d+(?:\.\d+)?)$/', $y_lower, $matches_y)) {
                                $text_cmp = strcasecmp($matches_x[1], $matches_y[1]);
                                if ($text_cmp !== 0) {
                                    return $text_cmp;
                                }
                                $num_x = (float)$matches_x[2];
                                $num_y = (float)$matches_y[2];
                                if ($num_x < $num_y) return -1;
                                if ($num_x > $num_y) return 1;
                                return 0;
                            }

                            return strcasecmp($x_lower, $y_lower);
                        });

                        $heading_parts[] = $a['name'] . ': ' . implode(', ', $sorted_values);
                        break 2;
                    }
                }
            }
        }

        if (!empty($options)) {

            foreach ($options as $opt_id => $values) {
                foreach ($option_data as $o) {
                    if ($o['option_id'] == $opt_id) {

                        $value_ids = array_map('intval', $values);
                        if (count($value_ids) > 1) {
                            $query = $this->db->query("
                                SELECT option_value_id, sort_order
                                FROM " . DB_PREFIX . "option_value
                                WHERE option_value_id IN (" . implode(',', $value_ids) . ")
                            ");

                            $sort_orders = [];
                            foreach ($query->rows as $row) {
                                $sort_orders[(int)$row['option_value_id']] = (int)$row['sort_order'];
                            }

                            usort($value_ids, function($a, $b) use ($sort_orders) {
                                $a_id = (int)$a;
                                $b_id = (int)$b;
                                $a_order = $sort_orders[$a_id] ?? 0;
                                $b_order = $sort_orders[$b_id] ?? 0;

                                if ($a_order !== $b_order) {
                                    return $a_order - $b_order;
                                }
                                return $a_id - $b_id;
                            });
                        }

                        $opt_names = [];
                        foreach ($value_ids as $val_id) {
                            foreach ($o['values'] as $v) {
                                if ($v['option_value_id'] == $val_id) {
                                    $opt_names[] = $v['name'];
                                    break;
                                }
                            }
                        }

                        if (!empty($opt_names)) {
                            $heading_parts[] = $o['name'] . ': ' . implode(', ', $opt_names);
                        }
                        break;
                    }
                }
            }
        }

        return implode(' - ', $heading_parts);
    }

    public function ensureCanonicalUrl() {
        $this->logger->debug('ensureCanonicalUrl: CALLED');        

        if (!isset($this->request->get['route']) || strpos($this->request->get['route'], 'product/category') === false) {
            $this->logger->debug('ensureCanonicalUrl: Not a category page, skipping');
            return;
        }

        $canonical_url = $this->buildCanonicalFilterUrl();

        if (!$canonical_url) {
            $this->logger->debug('ensureCanonicalUrl: Could not build canonical URL');
            return;
        }

        $current_url = $this->getCurrentUrl();
        $this->logger->debug('ensureCanonicalUrl: Current URL: ' . $current_url);
        $this->logger->debug('ensureCanonicalUrl: Canonical URL: ' . $canonical_url);

        $current_path = $this->normalizeUrlForComparison($current_url);
        $canonical_path = $this->normalizeUrlForComparison($canonical_url);

        if ($current_path !== $canonical_path) {
            $this->logger->debug('ensureCanonicalUrl: URL NOT canonical, redirecting');

            $this->response->redirect($canonical_url, 301);
            exit;
        } else {
            $this->logger->debug('ensureCanonicalUrl: URL is canonical');
        }
    }

    public function buildCanonicalFilterUrl() {
        if (!isset($this->request->get['path'])) {
            return null;
        }

        if (isset($this->request->get['dcf'])) {
            $decoded = $this->decodeFilters($this->request->get['dcf']);

            $normalized_dcf = $this->encodeFilters(
                $decoded['price_min'],
                $decoded['price_max'],
                $decoded['manufacturers'],
                $decoded['attributes'],
                $decoded['options']
            );

            if ($normalized_dcf !== $this->request->get['dcf']) {
                $params = $this->request->get;
                $params['dcf'] = $normalized_dcf;
                unset($params['route']);
                unset($params['_route_']);
                return $this->buildSeoUrl('product/category', $params);
            }

            return null;
        }

        $path_parts = explode('_', $this->request->get['path']);
        $category_id = (int)end($path_parts);

        if (!$category_id) {
            return null;
        }

        $params = [
            'path' => $this->request->get['path'],
            'route' => 'product/category'
        ];

        if (isset($this->request->get['manufacturer'])) {
            $params['manufacturer'] = $this->request->get['manufacturer'];
        }

        if (isset($this->request->get['attribute']) && is_array($this->request->get['attribute'])) {
            $params['attribute'] = $this->request->get['attribute'];

            $this->load->model('extension/module/dockercart_filter');
            $attributes_data = $this->model_extension_module_dockercart_filter->getAttributes(['filter_category_id' => $category_id]);

            foreach ($params['attribute'] as $attr_id => &$value) {
                if (strpos($value, ',') !== false) {

                    $selected_values = explode(',', $value);

                    $reordered = [];
                    foreach ($attributes_data as $attr) {
                        if ($attr['attribute_id'] == $attr_id) {

                            if (isset($attr['values']) && is_array($attr['values'])) {
                                foreach ($attr['values'] as $attr_val) {
                                    if (in_array($attr_val, $selected_values)) {
                                        $reordered[] = $attr_val;
                                    }
                                }
                            }
                            break;
                        }
                    }

                    if (!empty($reordered)) {
                        $value = implode(',', $reordered);
                    }
                }
            }
        }

        if (isset($this->request->get['option']) && is_array($this->request->get['option'])) {
            $params['option'] = $this->request->get['option'];
        }

        if (isset($this->request->get['price_min'])) {
            $params['price_min'] = $this->request->get['price_min'];
        }
        if (isset($this->request->get['price_max'])) {
            $params['price_max'] = $this->request->get['price_max'];
        }

        if (!empty($params['option'])) {
            $this->load->model('extension/module/dockercart_filter');
            $options_data = $this->model_extension_module_dockercart_filter->getOptions(['filter_category_id' => $category_id]);

            foreach ($params['option'] as $option_id => &$value) {
                if (strpos($value, ',') !== false) {

                    $selected_values = explode(',', $value);
                    $selected_values = array_map('intval', $selected_values);

                    $reordered = [];
                    foreach ($options_data as $opt) {
                        if ($opt['option_id'] == $option_id) {
                            foreach ($opt['values'] as $opt_val) {
                                if (in_array($opt_val['option_value_id'], $selected_values)) {
                                    $reordered[] = $opt_val['option_value_id'];
                                }
                            }
                            break;
                        }
                    }

                    if (!empty($reordered)) {
                        $value = implode(',', $reordered);
                    }
                }
            }
        }

        return $this->buildSeoUrl('product/category', $params);
    }

    public function buildFilterItemUrl($type, $id, $value = null) {
        $decoded = $this->decodeFilters($this->request->get['dcf'] ?? '');

        $price_min = $decoded['price_min'];
        $price_max = $decoded['price_max'];
        $manufacturers = $decoded['manufacturers'];
        $attributes = $decoded['attributes'];
        $options = $decoded['options'];

        $this->logger->debug('buildFilterItemUrl: START - type=' . $type . ', id=' . $id . ', value=' . $value);
        $this->logger->debug('buildFilterItemUrl: Current dcf=' . ($this->request->get['dcf'] ?? 'none'));
        $this->logger->debug('buildFilterItemUrl: Decoded - m=' . json_encode($manufacturers) . ', a=' . json_encode($attributes) . ', o=' . json_encode($options));

        switch ($type) {
            case 'manufacturer':

                $key = array_search((int)$id, $manufacturers);
                if ($key !== false) {
                    unset($manufacturers[$key]);
                } else {
                    $manufacturers[] = (int)$id;
                }
                break;

            case 'attribute':

                if (!isset($attributes[$id])) {
                    $attributes[$id] = [];
                }

                $key = array_search($value, $attributes[$id]);
                if ($key !== false) {
                    unset($attributes[$id][$key]);
                    if (empty($attributes[$id])) {
                        unset($attributes[$id]);
                    }
                } else {
                    $attributes[$id][] = $value;
                }
                break;

            case 'option':

                if (!isset($options[$id])) {
                    $options[$id] = [];
                }

                $key = array_search((int)$value, $options[$id]);
                if ($key !== false) {
                    unset($options[$id][$key]);
                    if (empty($options[$id])) {
                        unset($options[$id]);
                    }
                } else {
                    $options[$id][] = (int)$value;
                }
                break;
        }

        $options = $this->sortOptionValuesByOrder($options);
        $attributes = $this->sortAttributeValuesByOrder($attributes);
        $manufacturers = $this->sortManufacturersByOrder($manufacturers);

        $dcf = $this->encodeFilters($price_min, $price_max, $manufacturers, $attributes, $options);

        $this->logger->debug('buildFilterItemUrl: After toggle - m=' . json_encode($manufacturers) . ', a=' . json_encode($attributes) . ', o=' . json_encode($options));
        $this->logger->debug('buildFilterItemUrl: New dcf=' . $dcf);

        $params = $this->request->get;
        unset($params['route']);
        unset($params['_route_']);
        unset($params['dcf']);

        if (!empty($dcf)) {
            $params['dcf'] = $dcf;
        }

        $route = 'product/category';
        if (isset($this->request->get['product_id'])) {
            $route = 'product/product';
        } elseif (isset($this->request->get['manufacturer_id'])) {
            $route = 'product/manufacturer/info';
        }

        $url = $this->buildSeoUrl($route, $params);

        $this->logger->debug('buildFilterItemUrl: Final URL=' . $url);

        return $url;
    }

    private function normalizeFilterValueOrder(&$params) {
        if (empty($params['path'])) {
            return $params;
        }

        $path_parts = explode('_', $params['path']);
        $category_id = (int)end($path_parts);

        if (!$category_id) {
            return $params;
        }

        $this->load->model('extension/module/dockercart_filter');

        if (!empty($params['a'])) {
            $attr_pairs = explode('|', $params['a']);
            $parsed_attrs = [];

            foreach ($attr_pairs as $pair) {
                if (strpos($pair, '_') !== false) {
                    list($attr_id, $hash) = explode('_', $pair, 2);
                    $attr_id = (int)$attr_id;
                    if (!isset($parsed_attrs[$attr_id])) {
                        $parsed_attrs[$attr_id] = [];
                    }
                    $parsed_attrs[$attr_id][] = $hash;
                }
            }

            $attributes_data = $this->model_extension_module_dockercart_filter->getAttributes(['filter_category_id' => $category_id]);
            $reordered_parts = [];

            foreach ($parsed_attrs as $attr_id => $hashes) {

                $db_order = [];
                foreach ($attributes_data as $attr) {
                    if ($attr['attribute_id'] == $attr_id && isset($attr['values'])) {
                        foreach ($attr['values'] as $val) {
                            $val_text = is_array($val) && isset($val['text']) ? $val['text'] : $val;
                            $val_hash = $this->encodeAttributeValue($val_text);
                            if (in_array($val_hash, $hashes)) {
                                $db_order[] = $val_hash;
                            }
                        }
                        break;
                    }
                }

                $ordered_hashes = !empty($db_order) ? $db_order : $hashes;
                foreach ($ordered_hashes as $hash) {
                    $reordered_parts[] = $attr_id . '_' . $hash;
                }
            }

            if (!empty($reordered_parts)) {
                $params['a'] = implode('|', $reordered_parts);
            }
        }

        if (!empty($params['o'])) {
            $opt_pairs = explode('|', $params['o']);
            $parsed_opts = [];

            foreach ($opt_pairs as $pair) {
                if (strpos($pair, '_') !== false) {
                    list($opt_id, $val_id) = explode('_', $pair, 2);
                    $opt_id = (int)$opt_id;
                    $val_id = (int)$val_id;
                    if (!isset($parsed_opts[$opt_id])) {
                        $parsed_opts[$opt_id] = [];
                    }
                    $parsed_opts[$opt_id][] = $val_id;
                }
            }

            $options_data = $this->model_extension_module_dockercart_filter->getOptions(['filter_category_id' => $category_id]);
            $reordered_parts = [];

            foreach ($parsed_opts as $opt_id => $val_ids) {

                $db_order = [];
                foreach ($options_data as $opt) {
                    if ($opt['option_id'] == $opt_id) {
                        foreach ($opt['values'] as $val) {
                            if (in_array($val['option_value_id'], $val_ids)) {
                                $db_order[] = $val['option_value_id'];
                            }
                        }
                        break;
                    }
                }

                $ordered_vals = !empty($db_order) ? $db_order : $val_ids;
                foreach ($ordered_vals as $val_id) {
                    $reordered_parts[] = $opt_id . '_' . $val_id;
                }
            }

            if (!empty($reordered_parts)) {
                $params['o'] = implode('|', $reordered_parts);
            }
        }

        return $params;
    }

    private function buildSeoUrl($route, $params = []) {
        $this->logger->debug('buildSeoUrl: CALLED with route=' . $route . ', params=' . json_encode($params));

        $seo_mode = $this->config->get('config_seo_url');
        $this->logger->debug('buildSeoUrl: SEO mode=' . ($seo_mode ? 'ENABLED' : 'DISABLED'));

        if ($route === 'product/category' && isset($params['path'])) {

            $this->load->model('catalog/category');
            $path_parts = explode('_', $params['path']);
            $category_id = (int)end($path_parts);

            if ($category_id) {
                $category_info = $this->model_catalog_category->getCategory($category_id);
                if ($category_info) {
                    if ($seo_mode) {

                        $query = $this->db->query("
                            SELECT keyword
                            FROM " . DB_PREFIX . "seo_url
                            WHERE query = 'category_id=" . (int)$category_id . "'
                            AND store_id = '" . (int)$this->config->get('config_store_id') . "'
                            LIMIT 1
                        ");

                        if ($query->num_rows) {


                            $base_url = $this->url->link('product/category', '');


                            $parsed = parse_url($base_url);
                            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
                            $host = isset($parsed['host']) ? $parsed['host'] : '';
                            $base_path = isset($parsed['path']) ? $parsed['path'] : '/';

                            // Ensure we don't keep an "index.php" segment when building SEO URLs.
                            // Some installations return paths like "/index.php" from $this->url->link(),
                            // which would produce URLs like "/index.php/keyword". Strip that.
                            if (strpos($base_path, 'index.php') !== false) {
                                $base_path = str_replace('index.php', '', $base_path);
                                $base_path = rtrim($base_path, '/');
                                if ($base_path === '') {
                                    $base_path = '/';
                                } else {
                                    $base_path = '/' . ltrim($base_path, '/');
                                }
                            }


                            $langSeg = '';
                            if (!empty($this->request->server['REQUEST_URI'])) {
                                $req_path = trim(parse_url($this->request->server['REQUEST_URI'], PHP_URL_PATH), '/');
                                $parts = explode('/', $req_path);
                                if (isset($parts[0]) && preg_match('/^[a-z]{2}-[a-z]{2}$/i', $parts[0])) {
                                    $langSeg = $parts[0];
                                }
                            }

                            $keyword = $query->row['keyword'];


                            if ($langSeg && strpos($base_url, '/' . $langSeg . '/') === false) {
                                $url = $scheme . $host . '/' . $langSeg . '/' . ltrim($keyword, '/');
                            } else {

                                $root = $scheme . $host;
                                if (!empty($base_path) && $base_path !== '/') {
                                    $root .= rtrim($base_path, '/') . '/';
                                } else {
                                    $root .= '/';
                                }
                                $url = $root . ltrim($keyword, '/');
                            }
                            $this->logger->debug('buildSeoUrl: Using SEO URL keyword: ' . $url);
                        } else {

                            $url = $this->url->link('product/category', 'path=' . $params['path']);
                            $this->logger->debug('buildSeoUrl: No SEO keyword found, using url->link: ' . $url);
                        }
                    } else {

                        $url = $this->url->link('product/category', 'path=' . $params['path']);
                        $this->logger->debug('buildSeoUrl: SEO disabled, using url->link with path: ' . $url);
                    }
                } else {
                    $url = $this->url->link($route);
                }
            } else {
                $url = $this->url->link($route);
            }
        } else {

            $url = $this->url->link($route);
        }

        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

        $this->logger->debug('buildSeoUrl: Base URL (decoded): ' . $url);

        unset($params['route']);
        unset($params['path']);
        unset($params['_route_']);

        $query_parts = [];

        if (!empty($params['dcf'])) {
            $query_parts[] = 'dcf=' . urlencode($params['dcf']);
        }

        if (!empty($query_parts)) {
            $separator = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $separator . implode('&', $query_parts);
        }

        $this->logger->debug('buildSeoUrl: Final URL: ' . $url);

        return $url;
    }

    public function updateFilters() {
        $this->load->model('extension/module/dockercart_filter');

        $json = array();

        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
        $path = isset($this->request->get['path']) ? $this->request->get['path'] : '';

        if ($path) {
            $parts = explode('_', $path);
            $category_id = (int)array_pop($parts);
        }

        if (!$category_id) {
            $json['error'] = 'No category specified';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $filter_data = array(
            'filter_category_id' => $category_id,
            'filter_sub_category' => true
        );

        if (isset($this->request->get['manufacturer'])) {
            $manufacturers = array_map('intval', explode(',', $this->request->get['manufacturer']));
            $filter_data['filter_manufacturers'] = $manufacturers;
        }

        if (isset($this->request->get['attribute']) && is_array($this->request->get['attribute'])) {
            $attributes = array();
            foreach ($this->request->get['attribute'] as $attr_id => $values) {
                if (!empty($values)) {
                    $attributes[(int)$attr_id] = array_map(function($v) {
                        return $this->db->escape($v);
                    }, explode(',', $values));
                }
            }
            if (!empty($attributes)) {
                $filter_data['filter_attributes'] = $attributes;
            }
        }

        if (isset($this->request->get['option']) && is_array($this->request->get['option'])) {
            $options = array();
            foreach ($this->request->get['option'] as $opt_id => $values) {
                if (!empty($values)) {
                    $options[(int)$opt_id] = array_map('intval', explode(',', $values));
                }
            }
            if (!empty($options)) {
                $filter_data['filter_options'] = $options;
            }
        }

        if (isset($this->request->get['price_min'])) {
            $price_min = (float)$this->request->get['price_min'];
            if ($price_min > 0) {

                $price_min = (float)$this->currency->convert($price_min, $this->session->data['currency'], $this->config->get('config_currency'));
                $filter_data['filter_price_min'] = $price_min;
            }
        }

        if (isset($this->request->get['price_max'])) {
            $price_max = (float)$this->request->get['price_max'];
            if ($price_max > 0) {

                $price_max = (float)$this->currency->convert($price_max, $this->session->data['currency'], $this->config->get('config_currency'));
                $filter_data['filter_price_max'] = $price_max;
            }
        }

        $json['manufacturers'] = $this->model_extension_module_dockercart_filter->getManufacturers($filter_data);

        $json['attributes'] = $this->model_extension_module_dockercart_filter->getAttributes($filter_data);

        $json['options'] = $this->model_extension_module_dockercart_filter->getOptions($filter_data);

        $json['price_range'] = $this->model_extension_module_dockercart_filter->getPriceRange($filter_data);

        if (!empty($json['price_range']) && !empty($this->session->data['currency'])) {
            $base_currency = $this->config->get('config_currency');
            $current_currency = $this->session->data['currency'];

            if ($base_currency !== $current_currency) {
                $json['price_range']['min'] = (float)$this->currency->convert($json['price_range']['min'], $base_currency, $current_currency);
                $json['price_range']['max'] = (float)$this->currency->convert($json['price_range']['max'], $base_currency, $current_currency);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}

