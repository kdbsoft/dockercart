<?php
/**
 * DockerCart Google Base — Admin controller
 *
 * Google Merchant Center feed generation and settings management.
 * Streaming XML generation with XMLWriter for minimal memory usage.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 *
 * This module is distributed under a commercial/proprietary license.
 * Use, copying, modification, and distribution are permitted only under
 * the terms of a valid commercial license agreement with the copyright owner.
 *
 * For licensing inquiries contact: licensing@mathflow-bit.example
 */

class ControllerExtensionFeedDockercartGooglebase extends Controller {
    private $logger;
    private $error = array();
    private $module_version = '1.0.0';

     /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'googlebase');
    }

    /**
     * Main settings page
     */
    public function index() {
        $this->load->language('extension/feed/dockercart_googlebase');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        // Save settings on POST
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // Process category mapping
            if (isset($this->request->post['module_dockercart_googlebase_category_mapping'])) {
                $this->request->post['module_dockercart_googlebase_category_mapping'] = 
                    trim($this->request->post['module_dockercart_googlebase_category_mapping']);
            }
            
            // Process excluded products
            if (isset($this->request->post['module_dockercart_googlebase_exclude_products'])) {
                $this->request->post['module_dockercart_googlebase_exclude_products'] = 
                    trim($this->request->post['module_dockercart_googlebase_exclude_products']);
            }
            
            // Process excluded categories
            if (isset($this->request->post['module_dockercart_googlebase_exclude_categories'])) {
                $this->request->post['module_dockercart_googlebase_exclude_categories'] = 
                    trim($this->request->post['module_dockercart_googlebase_exclude_categories']);
            }

            $this->model_setting_setting->editSetting('module_dockercart_googlebase', $this->request->post);

            // Keep native feed status in sync so Extensions > Feeds shows the correct state
            if (isset($this->request->post['feed_dockercart_googlebase_status'])) {
                $feed_status = (int)$this->request->post['feed_dockercart_googlebase_status'];
            } elseif (isset($this->request->post['module_dockercart_googlebase_status'])) {
                $feed_status = (int)$this->request->post['module_dockercart_googlebase_status'];
            } else {
                $feed_status = 0;
            }
            $this->model_setting_setting->editSettingValue('feed_dockercart_googlebase', 'feed_dockercart_googlebase_status', $feed_status);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
        }

        // Error handling
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/feed/dockercart_googlebase', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/feed/dockercart_googlebase', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);

        // Load settings with defaults
        $settings = array(
            'module_dockercart_googlebase_status' => 0,
            'module_dockercart_googlebase_cache_hours' => 24,
            'module_dockercart_googlebase_max_file_size' => 50,
            'module_dockercart_googlebase_max_products' => 100000,
            'module_dockercart_googlebase_currency' => 'USD',
            'module_dockercart_googlebase_condition' => 'new',
            'module_dockercart_googlebase_include_disabled' => 0,
            'module_dockercart_googlebase_include_out_of_stock' => 1,
            'module_dockercart_googlebase_shipping_enabled' => 0,
            'module_dockercart_googlebase_shipping_price' => '',
            'module_dockercart_googlebase_shipping_country' => '',
            'module_dockercart_googlebase_brand_source' => 'manufacturer',
            'module_dockercart_googlebase_brand_default' => '',
            'module_dockercart_googlebase_category_mapping' => '',
            'module_dockercart_googlebase_exclude_products' => '',
            'module_dockercart_googlebase_exclude_categories' => '',
            'module_dockercart_googlebase_custom_label_0' => '',
            'module_dockercart_googlebase_custom_label_1' => '',
            'module_dockercart_googlebase_custom_label_2' => '',
            'module_dockercart_googlebase_custom_label_3' => '',
            'module_dockercart_googlebase_custom_label_4' => '',
            'module_dockercart_googlebase_image_width' => 800,
            'module_dockercart_googlebase_image_height' => 800,
            'module_dockercart_googlebase_separate_languages' => 0,
            'module_dockercart_googlebase_separate_stores' => 0,
            'module_dockercart_googlebase_debug' => 0,
            'module_dockercart_googlebase_license_key' => '',
            'module_dockercart_googlebase_public_key' => ''
        );

        foreach ($settings as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $value = $this->config->get($key);
                $data[$key] = ($value !== null) ? $value : $default;
            }
        }

        // Get languages
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // Get stores
        $this->load->model('setting/store');
        $stores = array();
        $stores[] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name') . ' ' . $this->language->get('text_default')
        );
        $store_list = $this->model_setting_store->getStores();
        foreach ($store_list as $store) {
            $stores[] = array(
                'store_id' => $store['store_id'],
                'name' => $store['name']
            );
        }
        $data['stores'] = $stores;

        // Get currencies
        $this->load->model('localisation/currency');
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();

        // Feed URLs
        $base_url = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
        $data['feed_url'] = rtrim($base_url, '/') . '/google-base.xml';
        $data['feed_url_preview'] = $this->url->link('extension/feed/dockercart_googlebase/preview', 'user_token=' . $this->session->data['user_token'], true);

        // If separate languages enabled, expose per-language feed URLs
        $data['feed_urls'] = array();
        if ($data['module_dockercart_googlebase_separate_languages']) {
            foreach ($data['languages'] as $lang) {
                $code = strtolower($lang['code']);
                $parts = explode('-', $code);
                $suffix = $parts[0];
                $data['feed_urls'][] = rtrim($base_url, '/') . '/google-base-' . $suffix . '.xml';
            }
        }

        // Calculate feed stats
        $data['feed_stats'] = $this->getFeedStats();

        $data['user_token'] = $this->session->data['user_token'];
        $data['module_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : $this->module_version;
        $data['license_domain'] =
            (!empty($this->request->server['HTTP_HOST']) ? $this->request->server['HTTP_HOST'] : '')
            ?: (defined('HTTPS_CATALOG') && HTTPS_CATALOG ? parse_url(HTTPS_CATALOG, PHP_URL_HOST) : '')
            ?: (defined('HTTP_CATALOG') && HTTP_CATALOG ? parse_url(HTTP_CATALOG, PHP_URL_HOST) : '')
            ?: (!empty($this->config->get('config_url')) ? parse_url($this->config->get('config_url'), PHP_URL_HOST) : '')
            ?: 'localhost';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/feed/dockercart_googlebase', $data));
    }

    /**
     * Validate form data
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_googlebase')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Validate cache hours
        if (isset($this->request->post['module_dockercart_googlebase_cache_hours'])) {
            $cache_hours = (int)$this->request->post['module_dockercart_googlebase_cache_hours'];
            if ($cache_hours < 1 || $cache_hours > 168) {
                $this->error['warning'] = $this->language->get('error_cache_hours');
            }
        }

        // Validate max file size
        if (isset($this->request->post['module_dockercart_googlebase_max_file_size'])) {
            $max_size = (int)$this->request->post['module_dockercart_googlebase_max_file_size'];
            if ($max_size < 1 || $max_size > 50) {
                $this->error['warning'] = $this->language->get('error_max_file_size');
            }
        }

        // Validate max products
        if (isset($this->request->post['module_dockercart_googlebase_max_products'])) {
            $max_products = (int)$this->request->post['module_dockercart_googlebase_max_products'];
            if ($max_products < 1000 || $max_products > 1000000) {
                $this->error['warning'] = $this->language->get('error_max_products');
            }
        }

        return !$this->error;
    }

    /**
     * Get feed statistics
     */
    protected function getFeedStats() {
        $stats = array(
            'total_products' => 0,
            'enabled_products' => 0,
            'in_stock_products' => 0,
            'last_generated' => null,
            'file_size' => null,
            'file_exists' => false
        );

        // Count products
        $query = $this->db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as enabled,
            SUM(CASE WHEN status = 1 AND quantity > 0 THEN 1 ELSE 0 END) as in_stock
            FROM " . DB_PREFIX . "product");

        if ($query->row) {
            $stats['total_products'] = (int)$query->row['total'];
            $stats['enabled_products'] = (int)$query->row['enabled'];
            $stats['in_stock_products'] = (int)$query->row['in_stock'];
        }

        // Check feed file (use DIR_CATALOG which points to catalog/, go up one level for root)
        $feed_file = DIR_CATALOG . '../google-base.xml';
        if (file_exists($feed_file)) {
            $stats['file_exists'] = true;
            $stats['last_generated'] = date('Y-m-d H:i:s', filemtime($feed_file));
            $stats['file_size'] = $this->formatBytes(filesize($feed_file));
        }
        
        // Also check for language-specific files
        $all_files = glob(DIR_CATALOG . '../google-base*.xml');
        if ($all_files && count($all_files) > 0) {
            $stats['file_exists'] = true;
            // Get the most recent file for stats
            $latest_time = 0;
            $total_size = 0;
            foreach ($all_files as $file) {
                $mtime = filemtime($file);
                if ($mtime > $latest_time) {
                    $latest_time = $mtime;
                }
                $total_size += filesize($file);
            }
            $stats['last_generated'] = date('Y-m-d H:i:s', $latest_time);
            $stats['file_size'] = $this->formatBytes($total_size);
            $stats['file_count'] = count($all_files);
        }

        return $stats;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Generate feed now (AJAX)
     */
    public function generate() {
        $this->load->language('extension/feed/dockercart_googlebase');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });

            // Build catalog URL for feed generation. Prefer HTTPS_CATALOG when available.
            if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
                $base = rtrim(HTTPS_CATALOG, '/');
            } elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
                $base = rtrim(HTTP_CATALOG, '/');
            } else {
                $base = rtrim($this->config->get('config_url'), '/');
            }

            $catalog_url = $base . '/index.php?route=extension/feed/dockercart_googlebase&regenerate=1&admin_request=1';

            // Clean up old files before regeneration
            @array_map('unlink', glob(DIR_CATALOG . '../google-base*.xml'));
            @array_map('unlink', glob(DIR_CATALOG . '../google-base*.xml.tmp'));

            // Make HTTP request to catalog to generate the feed
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $catalog_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes for large catalogs
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $response = curl_exec($ch);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curl_errno) {
                    // Include response if any for debugging
                    $msg = 'cURL error: ' . $curl_error;
                    if (!empty($response)) $msg .= ' | response: ' . substr($response, 0, 1000);
                    throw new \RuntimeException($msg);
                }

                if ($http_code >= 400) {
                    $msg = 'HTTP error: ' . $http_code;
                    if (!empty($response)) $msg .= ' | response: ' . substr($response, 0, 1000);
                    throw new \RuntimeException($msg);
                }
            } else {
                // Fallback to file_get_contents
                $context = stream_context_create(array(
                    'http' => array(
                        'timeout' => 300,
                        'ignore_errors' => true
                    ),
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    )
                ));
                $response = @file_get_contents($catalog_url, false, $context);
                if ($response === false) {
                    throw new \RuntimeException('file_get_contents failed');
                }
            }

            restore_error_handler();

            // Check if files were generated
            $files = glob(DIR_CATALOG . '../google-base*.xml');
            
            if (empty($files)) {
                throw new \RuntimeException('Feed file was not created');
            }

            $json['success'] = $this->language->get('text_feed_generated');
            $json['stats'] = $this->getFeedStats();
            $json['files'] = array();
            
            foreach ($files as $f) {
                $json['files'][] = array(
                    'name' => basename($f),
                    'size' => is_file($f) ? $this->formatBytes(filesize($f)) : 0,
                    'lastmod' => is_file($f) ? date('Y-m-d H:i:s', filemtime($f)) : null
                );
            }

        } catch (\Throwable $e) {
            restore_error_handler();
            $json['error'] = $this->language->get('error_generation') . ': ' . $e->getMessage();
            $this->logger->info('DockerCart Google Base generation error: ' . $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Clear feed cache (AJAX)
     */
    public function clearCache() {
        $this->load->language('extension/feed/dockercart_googlebase');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Delete all google-base*.xml files (use DIR_CATALOG which points to catalog/)
            $files = glob(DIR_CATALOG . '../google-base*.xml');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            
            // Also delete .tmp files
            $tmp_files = glob(DIR_CATALOG . '../google-base*.xml.tmp');
            if ($tmp_files) {
                foreach ($tmp_files as $file) {
                    @unlink($file);
                }
            }
            
            // Delete lock file
            @unlink(DIR_CATALOG . '../google-base.lock');

            // Clear cache entries
            $this->cache->delete('dockercart_googlebase');

            $json['success'] = $this->language->get('text_cache_cleared');
            $json['stats'] = $this->getFeedStats();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Preview feed (first 10 products)
     */
    public function preview() {
        $this->load->language('extension/feed/dockercart_googlebase');

        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('catalog/product');
            $this->load->model('catalog/manufacturer');
            $this->load->model('tool/image');

            $products = $this->model_catalog_product->getProducts(array(
                'start' => 0,
                'limit' => 10,
                'filter_status' => 1
            ));

            $preview_items = array();
            foreach ($products as $product) {
                $manufacturer = '';
                if ($product['manufacturer_id']) {
                    $manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($product['manufacturer_id']);
                    if ($manufacturer_info) {
                        $manufacturer = $manufacturer_info['name'];
                    }
                }

                $image = '';
                if ($product['image']) {
                    $image = $this->model_tool_image->resize($product['image'], 100, 100);
                }

                $preview_items[] = array(
                    'product_id' => $product['product_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'price' => $this->currency->format($product['price'], $this->config->get('config_currency')),
                    'manufacturer' => $manufacturer,
                    'quantity' => $product['quantity'],
                    'image' => $image
                );
            }

            $json['products'] = $preview_items;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Verify license (AJAX)
     */
    public function verifyLicenseAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (empty($license_key)) {
            $json['valid'] = false;
            $json['error'] = 'License key is empty';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $json['valid'] = false;
            $json['error'] = 'License library not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $json['valid'] = false;
            $json['error'] = 'DockercartLicense class not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);

            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_googlebase', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_googlebase', true);
            }

            $json = $result;
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Save license key (AJAX)
     */
    public function saveLicenseKeyAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_googlebase')) {
            $json['success'] = false;
            $json['error'] = 'No permission';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSettingValue('module_dockercart_googlebase', 'module_dockercart_googlebase_license_key', $license_key);

            if (!empty($public_key)) {
                $this->model_setting_setting->editSettingValue('module_dockercart_googlebase', 'module_dockercart_googlebase_public_key', $public_key);
            }

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Search products for autocomplete (AJAX)
     */
    public function searchProducts() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $filter = isset($this->request->get['filter']) ? $this->request->get['filter'] : '';
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;
        
        if (empty($filter) || mb_strlen($filter) < 2) {
            $json['products'] = array();
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('catalog/product');
        
        $filter_data = array(
            'filter_name' => $filter,
            'start' => 0,
            'limit' => $limit
        );
        
        $products = $this->model_catalog_product->getProducts($filter_data);
        
        $result = array();
        foreach ($products as $product) {
            $result[] = array(
                'product_id' => $product['product_id'],
                'name' => strip_tags(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')),
                'model' => $product['model'],
                'sku' => $product['sku'] ?? '',
                'price' => $this->currency->format($product['price'], $this->config->get('config_currency'))
            );
        }

        $json['products'] = $result;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Search categories for autocomplete (AJAX)
     */
    public function searchCategories() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $filter = isset($this->request->get['filter']) ? $this->request->get['filter'] : '';
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 50;
        
        // Get categories
        $this->load->model('catalog/category');
        $categories = $this->model_catalog_category->getCategories(array());
        
        // Build flat list with paths
        $result = array();
        $category_paths = array();
        
        // Build path mapping
        foreach ($categories as $category) {
            $path_ids = explode('_', $category['path_id'] ?? $category['category_id']);
            $path_names = array();
            
            foreach ($path_ids as $path_id) {
                foreach ($categories as $cat) {
                    if ($cat['category_id'] == $path_id) {
                        $path_names[] = strip_tags(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'));
                        break;
                    }
                }
            }
            
            $category_paths[$category['category_id']] = implode(' > ', $path_names);
        }
        
        // Filter and format
        foreach ($categories as $category) {
            $name = strip_tags(html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'));
            $path = $category_paths[$category['category_id']] ?? $name;
            
            // Filter by name if filter provided
            if (!empty($filter) && mb_strlen($filter) >= 2) {
                if (mb_stripos($name, $filter) === false && mb_stripos($path, $filter) === false) {
                    continue;
                }
            }
            
            $result[] = array(
                'category_id' => $category['category_id'],
                'name' => $name,
                'path' => $path
            );
            
            if (count($result) >= $limit) {
                break;
            }
        }

        $json['categories'] = $result;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get product info by ID (AJAX)
     */
    public function getProductInfo() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $product_ids = array();
        
        if (isset($this->request->get['product_ids'])) {
            $ids_str = $this->request->get['product_ids'];
            $product_ids = array_map('intval', array_filter(explode(',', $ids_str)));
        }
        
        if (empty($product_ids)) {
            $json['products'] = array();
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('catalog/product');
        
        $result = array();
        foreach ($product_ids as $product_id) {
            $product = $this->model_catalog_product->getProduct($product_id);
            if ($product) {
                $result[] = array(
                    'product_id' => $product['product_id'],
                    'name' => strip_tags(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')),
                    'model' => $product['model']
                );
            }
        }

        $json['products'] = $result;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get category info by ID (AJAX)
     */
    public function getCategoryInfo() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $category_ids = array();
        
        if (isset($this->request->get['category_ids'])) {
            $ids_str = $this->request->get['category_ids'];
            $category_ids = array_map('intval', array_filter(explode(',', $ids_str)));
        }
        
        if (empty($category_ids)) {
            $json['categories'] = array();
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('catalog/category');
        $all_categories = $this->model_catalog_category->getCategories(array());
        
        // Build path mapping
        $category_paths = array();
        foreach ($all_categories as $category) {
            $path_ids = explode('_', $category['path_id'] ?? $category['category_id']);
            $path_names = array();
            
            foreach ($path_ids as $path_id) {
                foreach ($all_categories as $cat) {
                    if ($cat['category_id'] == $path_id) {
                        $path_names[] = strip_tags(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'));
                        break;
                    }
                }
            }
            
            $category_paths[$category['category_id']] = implode(' > ', $path_names);
        }
        
        $result = array();
        foreach ($category_ids as $category_id) {
            $category = $this->model_catalog_category->getCategory($category_id);
            if ($category) {
                $name = strip_tags(html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'));
                $result[] = array(
                    'category_id' => $category['category_id'],
                    'name' => $name,
                    'path' => $category_paths[$category_id] ?? $name
                );
            }
        }

        $json['categories'] = $result;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Return full category tree (AJAX)
     */
    public function getCategoryTree() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('catalog/category');
        $all_categories = $this->model_catalog_category->getCategories(array());

        // Build path mapping
        $category_paths = array();
        foreach ($all_categories as $category) {
            $path_ids = explode('_', $category['path_id'] ?? $category['category_id']);
            $path_names = array();

            foreach ($path_ids as $path_id) {
                foreach ($all_categories as $cat) {
                    if ($cat['category_id'] == $path_id) {
                        $path_names[] = strip_tags(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'));
                        break;
                    }
                }
            }

            $category_paths[$category['category_id']] = implode(' > ', $path_names);
        }

        // Build nodes and tree
        $nodes = array();
        foreach ($all_categories as $category) {
            $id = (int)$category['category_id'];
            $parent = isset($category['parent_id']) ? (int)$category['parent_id'] : (int)($category['parent'] ?? 0);
            $name = strip_tags(html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'));
            $nodes[$id] = array(
                'category_id' => $id,
                'parent_id' => $parent,
                'name' => $name,
                'path' => $category_paths[$id] ?? $name,
                'children' => array()
            );
        }

        $tree = array();
        foreach ($nodes as $id => &$node) {
            if ($node['parent_id'] && isset($nodes[$node['parent_id']])) {
                $nodes[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        $json['tree'] = $tree;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Upload and save Google taxonomy file to storage (AJAX)
     */
    public function uploadTaxonomy() {
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!isset($this->request->files['taxonomy']) || !is_uploaded_file($this->request->files['taxonomy']['tmp_name'])) {
            $json['error'] = 'No file uploaded';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $file = $this->request->files['taxonomy'];

        // Basic size limit (10 MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $json['error'] = 'File too large';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $storage_dir = defined('DIR_STORAGE') ? DIR_STORAGE : (DIR_SYSTEM . '../storage/');
        $target_dir = rtrim($storage_dir, '/') . '/upload/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);

        $target_file = $target_dir . 'dockercart_googlebase_taxonomy.txt';

        if (!move_uploaded_file($file['tmp_name'], $target_file)) {
            // try copy as fallback
            if (!@copy($file['tmp_name'], $target_file)) {
                $json['error'] = 'Failed to save file';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
        }

        // Count entries
        $count = 0;
        $entries = array();
        $contents = @file_get_contents($target_file);
        if ($contents !== false) {
            $lines = preg_split('/\r?\n/', $contents);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                // parse similar to client-side
                $parts = preg_split('/\t/', $line);
                if (count($parts) >= 2 && is_numeric($parts[0])) {
                    $entries[] = array('id' => trim($parts[0]), 'label' => trim(implode('\t', array_slice($parts,1))));
                } else {
                    if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                        $entries[] = array('id' => $m[1], 'label' => trim($m[2]));
                    } else if (preg_match('/^(\d+)\s*-\s*(.+)$/', $line, $m2)) {
                        $entries[] = array('id' => $m2[1], 'label' => trim($m2[2]));
                    } else {
                        $entries[] = array('id' => null, 'label' => $line);
                    }
                }
            }
            $count = count($entries);
        }

        $json['success'] = true;
        $json['count'] = $count;
        $json['file'] = basename($target_file);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Return saved taxonomy entries (AJAX)
     */
    public function getSavedTaxonomy() {
        $json = array();

        if (!$this->user->hasPermission('access', 'extension/feed/dockercart_googlebase')) {
            $json['error'] = 'Permission denied';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $storage_dir = defined('DIR_STORAGE') ? DIR_STORAGE : (DIR_SYSTEM . '../storage/');
        $target_file = rtrim($storage_dir, '/') . '/upload/dockercart_googlebase_taxonomy.txt';

        $entries = array();
        if (file_exists($target_file)) {
            $contents = @file_get_contents($target_file);
            if ($contents !== false) {
                $lines = preg_split('/\r?\n/', $contents);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = preg_split('/\t/', $line);
                    if (count($parts) >= 2 && is_numeric($parts[0])) {
                        $entries[] = array('id' => trim($parts[0]), 'label' => trim(implode('\t', array_slice($parts,1))));
                    } else {
                        if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                            $entries[] = array('id' => $m[1], 'label' => trim($m[2]));
                        } else if (preg_match('/^(\d+)\s*-\s*(.+)$/', $line, $m2)) {
                            $entries[] = array('id' => $m2[1], 'label' => trim($m2[2]));
                        } else {
                            $entries[] = array('id' => null, 'label' => $line);
                        }
                    }
                }
            }
        }

        $json['entries'] = $entries;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Install module
     */
    public function install() {
        // Set default settings
        $this->load->model('setting/setting');

        $defaults = array(
            'module_dockercart_googlebase_status' => 0,
            'module_dockercart_googlebase_cache_hours' => 24,
            'module_dockercart_googlebase_max_file_size' => 50,
            'module_dockercart_googlebase_max_products' => 100000,
            'module_dockercart_googlebase_currency' => 'USD',
            'module_dockercart_googlebase_condition' => 'new',
            'module_dockercart_googlebase_include_disabled' => 0,
            'module_dockercart_googlebase_include_out_of_stock' => 1,
            'module_dockercart_googlebase_shipping_enabled' => 0,
            'module_dockercart_googlebase_brand_source' => 'manufacturer',
            'module_dockercart_googlebase_image_width' => 800,
            'module_dockercart_googlebase_image_height' => 800,
            'module_dockercart_googlebase_separate_languages' => 0,
            'module_dockercart_googlebase_separate_stores' => 0,
            'module_dockercart_googlebase_debug' => 0
        );

        $this->model_setting_setting->editSetting('module_dockercart_googlebase', $defaults);
        $this->model_setting_setting->editSettingValue('feed_dockercart_googlebase', 'feed_dockercart_googlebase_status', 0);
        
        // Add rewrite rules to .htaccess
        $this->updateHtaccess(true);
    }

    /**
     * Uninstall module
     */
    public function uninstall() {
        // Remove settings
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_dockercart_googlebase');

        // Remove feed files (use DIR_CATALOG for correct path)
        $files = glob(DIR_CATALOG . '../google-base*.xml');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        // Remove .htaccess rules
        $this->updateHtaccess(false);
    }
    
    /**
     * Update .htaccess with Google Base rewrite rules
     * 
     * @param bool $install True to add rules, false to remove
     */
    protected function updateHtaccess($install = true) {
        $webroot = DIR_CATALOG . '../';
        $htaccess = $webroot . '.htaccess';
        
        $marker_start = "# DockerCart Google Base - BEGIN\n";
        $marker_end = "# DockerCart Google Base - END\n";
        
        $snippet = $marker_start
            . "<IfModule mod_rewrite.c>\n"
            . "RewriteEngine On\n"
            . "RewriteRule ^google-base(-[a-z-]+)?(-\\d+)?\\.xml$ index.php?route=extension/feed/dockercart_googlebase&file=$0 [L,QSA]\n"
            . "</IfModule>\n"
            . $marker_end;
        
        try {
            if ($install) {
                // Add rules
                if (file_exists($htaccess)) {
                    $content = @file_get_contents($htaccess);
                    if ($content !== false) {
                        // Check if already exists
                        if (strpos($content, 'DockerCart Google Base - BEGIN') === false) {
                            // Backup original
                            @copy($htaccess, $htaccess . '.bak.' . time());
                            
                            // Add at the beginning
                            $content = $snippet . "\n" . $content;
                            @file_put_contents($htaccess, $content, LOCK_EX);
                        }
                    }
                } else {
                    // Create new .htaccess
                    @file_put_contents($htaccess, $snippet, LOCK_EX);
                    @chmod($htaccess, 0644);
                }
            } else {
                // Remove rules
                if (file_exists($htaccess)) {
                    $content = @file_get_contents($htaccess);
                    if ($content !== false) {
                        $new = preg_replace('/# DockerCart Google Base - BEGIN[\s\S]*?# DockerCart Google Base - END\n?/i', '', $content);
                        if ($new !== null && $new !== $content) {
                            @file_put_contents($htaccess, $new, LOCK_EX);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->info('DockerCart Google Base: failed to update .htaccess: ' . $e->getMessage());
        }
    }
}
