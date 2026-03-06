<?php
/**
 * DockerCart Google Base — Catalog (frontend) controller
 *
 * Streaming XML feed generation with XMLWriter for minimal memory usage.
 * Supports file splitting, atomic writes, and file locking.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 *
 * This module is distributed under a commercial/proprietary license.
 */

class ControllerExtensionFeedDockercartGooglebase extends Controller {

    // Google limits
    const MAX_PRODUCTS_PER_FILE = 1000000;
    const MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024; // 50MB
    const MAX_ADDITIONAL_IMAGES = 10;

    /**
     * Main entry point - serve or generate feed
     */
    public function index() {
        // Detect if this is an admin-initiated request (allow regeneration even if module disabled)
        $admin_request = isset($this->request->get['admin_request']) && $this->request->get['admin_request'] == '1';

        // Load module settings first so we know whether the module is enabled
        $this->load->model('setting/setting');
        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_googlebase');
        
        foreach ($module_settings as $key => $value) {
            $this->config->set($key, $value);
        }

        // If not an admin request and module is disabled, return 404
        if (!$admin_request && !$this->config->get('module_dockercart_googlebase_status')) {
            return new Action('error/not_found');
        }

        // Check license (skip for admin preview)
        $admin_request = isset($this->request->get['admin_request']) && $this->request->get['admin_request'] == '1';
        
        if (!$admin_request) {
            if (!$this->verifyLicense()) {
                http_response_code(403);
                header('Content-Type: application/xml; charset=UTF-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
                echo '  <channel>' . "\n";
                echo '    <!-- DockerCart Google Base: License not verified -->' . "\n";
                echo '  </channel>' . "\n";
                echo '</rss>' . "\n";
                exit;
            }
        }

        // Determine which file to serve
        $feed_file = $this->getFeedFilePath();
        $cache_duration = (int)($this->config->get('module_dockercart_googlebase_cache_hours') ?: 24) * 3600;

        // Check if regeneration is needed
        $regenerate = isset($this->request->get['regenerate']) || 
                      !file_exists($feed_file) || 
                      (time() - filemtime($feed_file) > $cache_duration);

        if ($regenerate) {
            $this->generate();
        }

        // Serve the file
        if (file_exists($feed_file)) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=' . $cache_duration);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($feed_file)) . ' GMT');
            readfile($feed_file);
            exit;
        }

        // Fallback error
        http_response_code(500);
        echo 'Feed generation failed';
        exit;
    }

    /**
     * Generate feed (called from admin or on-demand)
     */
    public function generate() {
        // Increase limits for large catalogs
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $this->load->model('setting/setting');
        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_googlebase');
        foreach ($module_settings as $key => $value) {
            $this->config->set($key, $value);
        }

        $this->load->model('extension/feed/dockercart_googlebase');

        // Acquire file lock
        $lock_file = DIR_APPLICATION . '../google-base.lock';
        $lock_fp = @fopen($lock_file, 'c');
        
        if ($lock_fp === false) {
            $this->log('Failed to open lock file');
            return false;
        }

        $lock_acquired = false;
        $lock_start = time();
        
        while (!$lock_acquired) {
            $lock_acquired = @flock($lock_fp, LOCK_EX | LOCK_NB);
            if ($lock_acquired) break;
            if ((time() - $lock_start) > 10) {
                fclose($lock_fp);
                $this->log('Failed to acquire lock within timeout');
                return false;
            }
            usleep(200000);
        }

        try {
            // Clean up old files
            $this->cleanupOldFiles();

            // Generate feeds
            $separate_languages = $this->config->get('module_dockercart_googlebase_separate_languages');
            
            if ($separate_languages) {
                $this->generateMultiLanguageFeeds();
            } else {
                $this->generateSingleFeed();
            }

            // robots.txt update removed

            $this->log('Feed generation completed successfully');

        } catch (Exception $e) {
            $this->log('Feed generation error: ' . $e->getMessage());
        }

        // Release lock
        @flock($lock_fp, LOCK_UN);
        @fclose($lock_fp);
        @unlink($lock_file);

        return true;
    }

    /**
     * Generate single feed for default language
     */
    protected function generateSingleFeed() {
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        
        $this->generateFeedForLanguage($language_id, $store_id, 'google-base');
    }

    /**
     * Generate separate feeds for each language
     */
    protected function generateMultiLanguageFeeds() {
        $query = $this->db->query("SELECT language_id, code FROM " . DB_PREFIX . "language WHERE status = '1' ORDER BY sort_order");
        $languages = $query->rows;
        
        $store_id = (int)$this->config->get('config_store_id');
        $feed_files = array();

        foreach ($languages as $language) {
            // Use primary language code segment (e.g. en-gb -> en) for friendly filenames
            $lang_code = strtolower($language['code']);
            $parts = explode('-', $lang_code);
            $suffix = $parts[0];
            $filename = 'google-base-' . $suffix;
            
            $files = $this->generateFeedForLanguage((int)$language['language_id'], $store_id, $filename);
            $feed_files = array_merge($feed_files, $files);
        }

        // Create index file if multiple feeds
        if (count($feed_files) > 0) {
            $this->createIndexFile($feed_files);
        }
    }

    /**
     * Generate feed for specific language
     */
    protected function generateFeedForLanguage($language_id, $store_id, $base_filename) {
        $settings = $this->getSettings();
        
        // Get products
        $products = $this->model_extension_feed_dockercart_googlebase->getProducts(
            $language_id,
            $store_id,
            $settings
        );

        $total_products = count($products);
        $this->log("Generating feed for language $language_id with $total_products products");

        // Split into multiple files if needed
        $max_products = min(
            (int)($settings['max_products'] ?: self::MAX_PRODUCTS_PER_FILE),
            self::MAX_PRODUCTS_PER_FILE
        );
        $max_size = min(
            (int)($settings['max_file_size'] ?: 50) * 1024 * 1024,
            self::MAX_FILE_SIZE_BYTES
        );

        $feed_files = array();
        $file_index = 0;
        $product_count = 0;
        $current_writer = null;
        $current_tmp = null;
        $current_final = null;

        // Open new writer
        $open_writer = function() use (&$file_index, $base_filename) {
            $file_index++;
            
            if ($file_index > 1) {
                $final = DIR_APPLICATION . '../' . $base_filename . '-' . $file_index . '.xml';
            } else {
                $final = DIR_APPLICATION . '../' . $base_filename . '.xml';
            }
            
            $tmp = $final . '.tmp';

            $writer = new XMLWriter();
            $writer->openURI($tmp);
            $writer->startDocument('1.0', 'UTF-8');
            $writer->setIndent(true);
            $writer->setIndentString('  ');
            
            // Start RSS root
            $writer->startElement('rss');
            $writer->writeAttribute('version', '2.0');
            $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            
            // Start channel
            $writer->startElement('channel');
            
            return array($writer, $tmp, $final);
        };

        // Close writer
        $close_writer = function($writer, $tmp, $final) use (&$feed_files) {
            if (!$writer) return;
            
            // End channel
            $writer->endElement();
            // End rss
            $writer->endElement();
            $writer->endDocument();
            $writer->flush();

            // Atomic rename
            if ($tmp && $final) {
                @rename($tmp, $final);
                @chmod($final, 0644);
                $feed_files[] = $final;
            }
        };

        // Process products
        foreach ($products as $product) {
            // Check if we need a new file
            if ($current_writer === null || 
                $product_count >= $max_products ||
                ($current_tmp && @filesize($current_tmp) >= $max_size)) {
                
                // Close current writer
                if ($current_writer !== null) {
                    $close_writer($current_writer, $current_tmp, $current_final);
                }
                
                // Open new writer
                list($current_writer, $current_tmp, $current_final) = $open_writer();
                $product_count = 0;
                
                // Write channel info
                $this->writeChannelInfo($current_writer);
            }

            // Write product item
            $this->writeProductItem($current_writer, $product, $language_id);
            $product_count++;
            
            // Flush periodically
            if ($product_count % 100 === 0) {
                $current_writer->flush();
            }
        }

        // Close final writer
        if ($current_writer !== null) {
            $close_writer($current_writer, $current_tmp, $current_final);
        }

        // If multiple files were created, rename first file and create index
        if ($file_index > 1) {
            $first_file = DIR_APPLICATION . '../' . $base_filename . '.xml';
            $numbered_first = DIR_APPLICATION . '../' . $base_filename . '-1.xml';
            if (file_exists($first_file) && !file_exists($numbered_first)) {
                rename($first_file, $numbered_first);
                $feed_files[0] = $numbered_first;
            }
            
            // Create index
            $this->createFeedIndex($base_filename, $file_index);
        }

        return $feed_files;
    }

    /**
     * Write channel information
     */
    protected function writeChannelInfo($writer) {
        $writer->writeElement('title', $this->config->get('config_name'));
        $writer->writeElement('link', $this->config->get('config_url'));
        $writer->writeElement('description', $this->config->get('config_meta_description'));
    }

    /**
     * Write single product item
     */
    protected function writeProductItem($writer, $product, $language_id) {
        $settings = $this->getSettings();
        
        $writer->startElement('item');
        
        // Required fields
        $writer->writeElement('g:id', $product['product_id']);
        $writer->startElement('title');
        $writer->writeCdata($this->cleanText($product['name']));
        $writer->endElement();
        
        // Description
        $description = $this->cleanText(strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')));
        if (strlen($description) > 5000) {
            $description = substr($description, 0, 4997) . '...';
        }
        $writer->startElement('description');
        $writer->writeCdata($description);
        $writer->endElement();
        
        // Link
        $link = $this->buildProductUrl($product['product_id'], $language_id);
        $writer->writeElement('link', $link);

        // Image
        if (!empty($product['image'])) {
            $image = $this->getProductImage($product['image'], $settings);
            $writer->writeElement('g:image_link', $image);
        }

        // Additional images
        if (!empty($product['additional_images'])) {
            $additional = explode(',', $product['additional_images']);
            $count = 0;
            foreach ($additional as $img) {
                if ($count >= self::MAX_ADDITIONAL_IMAGES) break;
                $img_url = $this->getProductImage(trim($img), $settings);
                $writer->writeElement('g:additional_image_link', $img_url);
                $count++;
            }
        }

        // Availability
        if ($product['quantity'] > 0) {
            $writer->writeElement('g:availability', 'in_stock');
        } else {
            $stock_status = $this->getStockStatus($product['stock_status_id']);
            $writer->writeElement('g:availability', $stock_status);
        }

        // Price
        $currency = $settings['currency'] ?: 'USD';
        $price = $this->formatPrice($product['price'], $currency);
        $writer->writeElement('g:price', $price);

        // Sale price
        if (!empty($product['special']) && $product['special'] < $product['price']) {
            $sale_price = $this->formatPrice($product['special'], $currency);
            $writer->writeElement('g:sale_price', $sale_price);
            
            // Sale price effective date
            if (!empty($product['special_date_start']) && !empty($product['special_date_end'])) {
                $date_start = date('Y-m-d\TH:i:sO', strtotime($product['special_date_start']));
                $date_end = date('Y-m-d\TH:i:sO', strtotime($product['special_date_end']));
                $writer->writeElement('g:sale_price_effective_date', $date_start . '/' . $date_end);
            }
        }

        // Brand
        $brand = $this->getBrand($product, $settings);
        if ($brand) {
            $writer->startElement('g:brand');
            $writer->writeCdata($brand);
            $writer->endElement();
        }

        // GTIN (EAN)
        if (!empty($product['ean'])) {
            $writer->writeElement('g:gtin', $product['ean']);
        } elseif (!empty($product['upc'])) {
            $writer->writeElement('g:gtin', $product['upc']);
        } elseif (!empty($product['jan'])) {
            $writer->writeElement('g:gtin', $product['jan']);
        }

        // MPN
        if (!empty($product['mpn'])) {
            $writer->writeElement('g:mpn', $product['mpn']);
        } elseif (!empty($product['model'])) {
            $writer->writeElement('g:mpn', $product['model']);
        }

        // Identifier exists
        if (empty($product['ean']) && empty($product['upc']) && empty($product['mpn']) && empty($product['model'])) {
            $writer->writeElement('g:identifier_exists', 'false');
        }

        // Condition
        $condition = $settings['condition'] ?: 'new';
        $writer->writeElement('g:condition', $condition);

        // Google Product Category
        $google_category = $this->getGoogleCategory($product['category_id'], $settings);
        if ($google_category) {
            $writer->startElement('g:google_product_category');
            $writer->writeCdata($google_category);
            $writer->endElement();
        }

        // Product Type (DockerCart category path)
        if (!empty($product['category_path'])) {
            $writer->startElement('g:product_type');
            $writer->writeCdata($product['category_path']);
            $writer->endElement();
        }

        // Weight
        if (!empty($product['weight']) && $product['weight'] > 0) {
            $weight_unit = $this->getWeightUnit($product['weight_class_id']);
            $writer->writeElement('g:shipping_weight', $product['weight'] . ' ' . $weight_unit);
        }

        // Shipping
        if ($settings['shipping_enabled'] && !empty($settings['shipping_country'])) {
            $writer->startElement('g:shipping');
            $writer->writeElement('g:country', $settings['shipping_country']);
            if (!empty($settings['shipping_price'])) {
                $writer->writeElement('g:price', $settings['shipping_price']);
            }
            $writer->endElement();
        }

        // Item group ID (for variants)
        if (!empty($product['master_id']) && $product['master_id'] > 0) {
            $writer->writeElement('g:item_group_id', $product['master_id']);
        }

        // Custom labels
        for ($i = 0; $i <= 4; $i++) {
            $label_value = $settings['custom_label_' . $i] ?? '';
            if (!empty($label_value)) {
                $label_value = $this->processCustomLabel($label_value, $product);
                $writer->writeElement('g:custom_label_' . $i, $label_value);
            }
        }

        // Adult (if applicable)
        // $writer->writeElement('g:adult', 'no');

        $writer->endElement(); // item
    }

    /**
     * Create feed index file for multiple feed files
     */
    protected function createFeedIndex($base_filename, $file_count) {
        $base_url = $this->config->get('config_url');
        if (!$base_url) {
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                        '://' . $_SERVER['HTTP_HOST'] . '/';
        }
        $base_url = rtrim($base_url, '/') . '/';

        $index_file = DIR_APPLICATION . '../' . $base_filename . '.xml';
        $tmp_file = $index_file . '.tmp';

        $writer = new XMLWriter();
        $writer->openURI($tmp_file);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        // Use sitemapindex format for index
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        for ($i = 1; $i <= $file_count; $i++) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $base_url . $base_filename . '-' . $i . '.xml');
            $writer->writeElement('lastmod', date('c'));
            $writer->endElement();
        }

        $writer->endElement(); // sitemapindex
        $writer->endDocument();
        $writer->flush();

        @rename($tmp_file, $index_file);
        @chmod($index_file, 0644);
    }

    /**
     * Create main index file
     */
    protected function createIndexFile($feed_files) {
        $base_url = $this->config->get('config_url');
        if (!$base_url) {
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                        '://' . $_SERVER['HTTP_HOST'] . '/';
        }
        $base_url = rtrim($base_url, '/') . '/';

        $index_file = DIR_APPLICATION . '../google-base.xml';
        $tmp_file = $index_file . '.tmp';

        $writer = new XMLWriter();
        $writer->openURI($tmp_file);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($feed_files as $file) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $base_url . basename($file));
            $writer->writeElement('lastmod', date('c', filemtime($file)));
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        @rename($tmp_file, $index_file);
        @chmod($index_file, 0644);
    }

    /**
     * Verify license
     */
    protected function verifyLicense() {
        $license_key = $this->config->get('module_dockercart_googlebase_license_key');
        
        if (empty($license_key)) {
            $this->log('License key not configured');
            return false;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $this->log('License library not found');
            return false;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');
        
        if (!class_exists('DockercartLicense')) {
            $this->log('DockercartLicense class not found');
            return false;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $public_key = $this->config->get('module_dockercart_googlebase_public_key');
            
            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_googlebase');
            } else {
                $result = $license->verify($license_key, 'dockercart_googlebase');
            }

            if (!$result['valid']) {
                $this->log('License verification failed: ' . ($result['error'] ?? 'Unknown error'));
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->log('License verification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get settings array
     */
    protected function getSettings() {
        return array(
            'currency' => $this->config->get('module_dockercart_googlebase_currency') ?: 'USD',
            'condition' => $this->config->get('module_dockercart_googlebase_condition') ?: 'new',
            'include_disabled' => (bool)$this->config->get('module_dockercart_googlebase_include_disabled'),
            'include_out_of_stock' => (bool)$this->config->get('module_dockercart_googlebase_include_out_of_stock'),
            'brand_source' => $this->config->get('module_dockercart_googlebase_brand_source') ?: 'manufacturer',
            'brand_default' => $this->config->get('module_dockercart_googlebase_brand_default'),
            'category_mapping' => $this->config->get('module_dockercart_googlebase_category_mapping'),
            'exclude_products' => $this->config->get('module_dockercart_googlebase_exclude_products'),
            'exclude_categories' => $this->config->get('module_dockercart_googlebase_exclude_categories'),
            'shipping_enabled' => (bool)$this->config->get('module_dockercart_googlebase_shipping_enabled'),
            'shipping_price' => $this->config->get('module_dockercart_googlebase_shipping_price'),
            'shipping_country' => $this->config->get('module_dockercart_googlebase_shipping_country'),
            'image_width' => (int)($this->config->get('module_dockercart_googlebase_image_width') ?: 800),
            'image_height' => (int)($this->config->get('module_dockercart_googlebase_image_height') ?: 800),
            'max_products' => (int)($this->config->get('module_dockercart_googlebase_max_products') ?: 100000),
            'max_file_size' => (int)($this->config->get('module_dockercart_googlebase_max_file_size') ?: 50),
            'custom_label_0' => $this->config->get('module_dockercart_googlebase_custom_label_0'),
            'custom_label_1' => $this->config->get('module_dockercart_googlebase_custom_label_1'),
            'custom_label_2' => $this->config->get('module_dockercart_googlebase_custom_label_2'),
            'custom_label_3' => $this->config->get('module_dockercart_googlebase_custom_label_3'),
            'custom_label_4' => $this->config->get('module_dockercart_googlebase_custom_label_4'),
        );
    }

    /**
     * Get feed file path
     */
    protected function getFeedFilePath() {
        // Check if specific file requested
        if (isset($this->request->get['file'])) {
            $file = basename($this->request->get['file']);
            if (preg_match('/^google-base(-[\w-]+)?(-\d+)?\.xml$/', $file)) {
                return DIR_APPLICATION . '../' . $file;
            }
        }
        
        return DIR_APPLICATION . '../google-base.xml';
    }

    /**
     * Build product URL
     */
    protected function buildProductUrl($product_id, $language_id) {
        $old_language_id = $this->config->get('config_language_id');
        $this->config->set('config_language_id', $language_id);
        
        $url = $this->url->link('product/product', 'product_id=' . $product_id, true);
        
        $this->config->set('config_language_id', $old_language_id);
        
        return html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get product image URL
     */
    protected function getProductImage($image, $settings) {
        if (empty($image)) {
            return '';
        }

        $this->load->model('tool/image');
        
        $width = $settings['image_width'] ?: 800;
        $height = $settings['image_height'] ?: 800;
        
        $image_url = $this->model_tool_image->resize($image, $width, $height);
        
        // Ensure absolute URL
        if (strpos($image_url, 'http') !== 0) {
            $base = $this->config->get('config_url') ?: '';
            $image_url = rtrim($base, '/') . '/' . ltrim($image_url, '/');
        }
        
        return $image_url;
    }

    /**
     * Format price with currency
     */
    protected function formatPrice($price, $currency_code) {
        $price = (float)$price;
        
        // Get currency value
        $currency_value = $this->currency->getValue($currency_code);
        if (!$currency_value) {
            $currency_value = 1;
        }
        
        $converted_price = $price * $currency_value;
        
        return number_format($converted_price, 2, '.', '') . ' ' . $currency_code;
    }

    /**
     * Get brand from product
     */
    protected function getBrand($product, $settings) {
        if ($settings['brand_source'] === 'manufacturer' && !empty($product['manufacturer'])) {
            return $product['manufacturer'];
        }
        
        if (!empty($settings['brand_default'])) {
            return $settings['brand_default'];
        }
        
        if (!empty($product['manufacturer'])) {
            return $product['manufacturer'];
        }
        
        return '';
    }

    /**
     * Get Google product category
     */
    protected function getGoogleCategory($category_id, $settings) {
        if (empty($settings['category_mapping'])) {
            return '';
        }

        $mapping = $settings['category_mapping'];
        $lines = explode("\n", $mapping);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }
            
            list($oc_id, $google_cat) = explode('=', $line, 2);
            $oc_id = trim($oc_id);
            $google_cat = trim($google_cat);
            
            if ($oc_id == $category_id) {
                return $google_cat;
            }
        }
        
        return '';
    }

    /**
     * Get stock status availability
     */
    protected function getStockStatus($stock_status_id) {
        // Map DockerCart stock status to Google availability
        // Common mappings - can be extended
        $status_map = array(
            5 => 'out_of_stock',  // Out of Stock
            6 => 'preorder',      // Pre-Order
            7 => 'out_of_stock',  // 2-3 Days
            8 => 'backorder',     // Backorder
        );
        
        return isset($status_map[$stock_status_id]) ? $status_map[$stock_status_id] : 'out_of_stock';
    }

    /**
     * Get weight unit
     */
    protected function getWeightUnit($weight_class_id) {
        $units = array(
            1 => 'kg',
            2 => 'g',
            5 => 'lb',
            6 => 'oz'
        );
        
        return isset($units[$weight_class_id]) ? $units[$weight_class_id] : 'kg';
    }

    /**
     * Process custom label placeholders
     */
    protected function processCustomLabel($label, $product) {
        $replacements = array(
            '{manufacturer}' => $product['manufacturer'] ?? '',
            '{category}' => $product['category_name'] ?? '',
            '{sku}' => $product['sku'] ?? '',
            '{model}' => $product['model'] ?? '',
            '{product_id}' => $product['product_id'] ?? '',
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $label);
    }

    /**
     * Clean text for XML
     */
    protected function cleanText($text) {
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Trim
        return trim($text);
    }

    /**
     * Clean up old feed files
     */
    protected function cleanupOldFiles() {
        $files = glob(DIR_APPLICATION . '../google-base*.xml');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        $tmp_files = glob(DIR_APPLICATION . '../google-base*.xml.tmp');
        if ($tmp_files) {
            foreach ($tmp_files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Log message
     */
    protected function log($message) {
        if ($this->config->get('module_dockercart_googlebase_debug')) {
            $log = new Log('dockercart_googlebase.log');
            $log->write($message);
        }
    }
}
