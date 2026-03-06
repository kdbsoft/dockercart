<?php
/**
 * DockerCart Export YML Catalog Controller
 * 
 * Generates YML feed using streaming XMLWriter for memory efficiency
 * 
 * @package DockerCart
 * @subpackage Export YML
 * @version 1.0.0
 */

class ControllerExtensionFeedDockercartExportYml extends Controller {
    private $logger;

    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'export_yml');
    }

    /**
     * Main entry point for YML feed generation
     */
    public function index() {
        // Get profile ID from request
        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 1;

        // Handle rewritten file requests from .htaccess (e.g. export-yml-1-uk-ua.xml)
        $requested_file = '';
        $requested_lang_from_file = null;
        $response_output_file = null;
        $is_rewrite_file_request = false;

        if (!empty($this->request->get['file'])) {
            $requested_file = basename((string)$this->request->get['file']);

            if (preg_match('/^export-yml-(\d+)(?:-([a-z]{2}-[a-z]{2}))?(?:-part-\d+)?\.xml$/i', $requested_file, $matches)) {
                $profile_id = (int)$matches[1];
                if (!empty($matches[2])) {
                    $requested_lang_from_file = strtolower($matches[2]);
                }
                $response_output_file = DIR_APPLICATION . '../' . $requested_file;
                $is_rewrite_file_request = true;
            }
        }

        // Load models
        $this->load->model('setting/setting');
        $this->load->model('extension/feed/dockercart_export_yml');

        // Load license settings
        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_export_yml');
        if (!empty($module_settings['module_dockercart_export_yml_license_key'])) {
            $this->config->set('module_dockercart_export_yml_license_key', $module_settings['module_dockercart_export_yml_license_key']);
        }
        if (!empty($module_settings['module_dockercart_export_yml_public_key'])) {
            $this->config->set('module_dockercart_export_yml_public_key', $module_settings['module_dockercart_export_yml_public_key']);
        }

        // License check (skip if admin request)
        $license_from_admin = isset($this->request->get['admin_request']) && $this->request->get['admin_request'] == '1';

        if (!$license_from_admin) {
            $license_key = $this->config->get('module_dockercart_export_yml_license_key');
            if (!empty($license_key)) {
                if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                    $this->sendErrorXML('License library not found');
                    return;
                }

                require_once(DIR_SYSTEM . 'library/dockercart_license.php');
                if (class_exists('DockercartLicense')) {
                    $license = new DockercartLicense($this->registry);
                    $result = $license->verify($license_key, 'dockercart_export_yml');

                    if (!$result['valid']) {
                        $this->sendErrorXML('Invalid license: ' . ($result['error'] ?? ''));
                        return;
                    }
                }
            } else {
                $this->sendErrorXML('License key not configured');
                return;
            }
        }

        // Get profile
        $profile = $this->model_extension_feed_dockercart_export_yml->getProfile($profile_id);
        if (!$profile || !$profile['status']) {
            $this->sendErrorXML('Profile not found or disabled');
            return;
        }

        // File paths (language-specific)
        $requested_lang = isset($this->request->get['lang']) ? strtolower((string)$this->request->get['lang']) : null;
        if (empty($requested_lang) && !empty($requested_lang_from_file)) {
            $requested_lang = $requested_lang_from_file;
        }
        
        // Get all languages
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        
        // Determine target language(s)
        $target_languages = array();
        
        if ($requested_lang) {
            // Specific language requested
            foreach ($languages as $language) {
                if ($language['code'] == $requested_lang) {
                    $target_languages[] = $language;
                    break;
                }
            }
        } else {
             $target_languages = $languages;
        }
        
        if (empty($target_languages)) {
            $this->sendErrorXML('Invalid language specified');
            return;
        }

        // For rewritten file requests without explicit lang, use profile language
        if ($is_rewrite_file_request && empty($requested_lang) && !empty($profile['language_id'])) {
            foreach ($languages as $language) {
                if ((int)$language['language_id'] === (int)$profile['language_id']) {
                    $requested_lang = strtolower($language['code']);
                    $target_languages = array($language);
                    break;
                }
            }
        }

        // Language to return in HTTP response
        $response_language = reset($target_languages);
        if (empty($requested_lang) && !empty($profile['language_id'])) {
            foreach ($target_languages as $language) {
                if ((int)$language['language_id'] === (int)$profile['language_id']) {
                    $response_language = $language;
                    break;
                }
            }
        }
        if ($response_output_file === null) {
            $response_output_file = DIR_APPLICATION . '../export-yml-' . $profile_id . '-' . $response_language['code'] . '.xml';
        }
        
        // Settings
        $split_files = !empty($profile['split_files']);
        $products_per_file = (int)($profile['products_per_file'] ?: 10000);
        $cache_ttl = (int)$profile['cache_ttl'];
        $regenerate = isset($this->request->get['regenerate']) && $this->request->get['regenerate'] == '1';
        $lock_timeout = 1800; // 30 minutes
        $lock_warning = 600;  // Warn at 10 minutes

        // Serve cached rewritten file directly when fresh
        if (!$regenerate && file_exists($response_output_file) && (time() - filemtime($response_output_file) < $cache_ttl)) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=' . $cache_ttl);
            readfile($response_output_file);
            exit;
        }

        // If rewritten file is stale (or missing), regenerate underlying feed
        if ($is_rewrite_file_request) {
            $regenerate = true;
        }
        
        foreach ($target_languages as $target_language) {
            $lang_code = $target_language['code'];
            $lang_id = (int)$target_language['language_id'];

            // File paths
            $output_file = DIR_APPLICATION . '../export-yml-' . $profile_id . '-' . $lang_code . '.xml';
            $tmp_file = $output_file . '.tmp';
            $lock_file = $output_file . '.lock';
            $gz_file = $output_file . '.gz';

            // Check cache for current language
            if (!$regenerate && file_exists($output_file) && (time() - filemtime($output_file) < $cache_ttl)) {
                $this->logger->debug('Serving cached YML for profile ' . $profile_id . ', language: ' . $lang_code);
                continue;
            }

            // Check lock file
            if (file_exists($lock_file)) {
                $lock_age = time() - filemtime($lock_file);

                if ($lock_age < $lock_warning) {
                    $this->logger->warning('YML generation already in progress for language: ' . $lang_code);
                    continue;
                } elseif ($lock_age < $lock_timeout) {
                    $this->logger->warning('YML generation lock is ' . $lock_age . ' seconds old for language: ' . $lang_code);
                    continue;
                } else {
                    $this->logger->warning('Removing stale lock file (age: ' . $lock_age . 's) for language: ' . $lang_code);
                    @unlink($lock_file);
                }
            }

            // Create lock file
            touch($lock_file);
            $this->logger->info('Starting YML generation for profile ' . $profile_id . ', language: ' . $lang_code);

            try {
                // Set language in config for proper URL generation
                $this->config->set('config_language_id', $lang_id);

                // Set session language
                $this->session->data['language'] = $lang_code;

                // Update languageId in SEO URL controller
                if ($this->config->get('config_seo_url') && isset($this->registry->controller_startup_seo_url)) {
                    $seo_url_controller = $this->registry->controller_startup_seo_url;
                    $reflection = new \ReflectionClass($seo_url_controller);
                    $property = $reflection->getProperty('languageId');
                    $property->setAccessible(true);
                    $property->setValue($seo_url_controller, $lang_id);
                }

                // Update profile language for current generation
                $profile['language_id'] = $lang_id;

                // Generate feed
                if ($split_files) {
                    $total_products = $this->model_extension_feed_dockercart_export_yml->getTotalProductsForExport($profile_id, $lang_id);

                    if ($total_products > $products_per_file) {
                        $this->generateSplitYML($profile, $products_per_file, $lang_code, $lang_id);
                    } else {
                        $this->generateYML($profile, $tmp_file);
                        if (file_exists($output_file)) {
                            @unlink($output_file);
                        }
                        rename($tmp_file, $output_file);
                    }
                } else {
                    $this->generateYML($profile, $tmp_file);
                    if (file_exists($output_file)) {
                        @unlink($output_file);
                    }
                    rename($tmp_file, $output_file);
                }

                $this->logger->info('YML generated for profile ' . $profile_id . ', language: ' . $lang_code . ', size: ' . filesize($output_file) . ' bytes');

                // Generate gzipped version if needed
                if (!empty($profile['settings']['create_gzip'])) {
                    if (file_exists($gz_file)) {
                        @unlink($gz_file);
                    }
                    $gzdata = gzencode(file_get_contents($output_file), 9);
                    file_put_contents($gz_file, $gzdata);
                    $this->logger->debug('Gzipped version created: ' . filesize($gz_file) . ' bytes');
                }

            } catch (Exception $e) {
                if (file_exists($tmp_file)) {
                    @unlink($tmp_file);
                }
                $this->logger->error('YML generation failed for language ' . $lang_code . ': ' . $e->getMessage());
            } finally {
                @unlink($lock_file);
            }
        }

        // Serve file
        if (file_exists($response_output_file)) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=' . $cache_ttl);
            readfile($response_output_file);
        }
        exit;
    }

    /**
     * Generate complete YML file
     *
     * @param array $profile
     * @param string $output_file
     */
    private function generateYML($profile, $output_file) {
        // Initialize XMLWriter
        $xml = new XMLWriter();
        $xml->openURI($output_file);
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        // Start yml_catalog
        $xml->startElement('yml_catalog');
        $xml->writeAttribute('date', date('Y-m-d H:i'));

        // Shop info
        $xml->startElement('shop');

        // Shop name and company
        $xml->writeElement('name', !empty($profile['shop_name']) ? $profile['shop_name'] : $this->config->get('config_name'));
        $xml->writeElement('company', !empty($profile['company_name']) ? $profile['company_name'] : $this->config->get('config_name'));
        $xml->writeElement('url', $this->getStoreUrl($profile['store_id']));

        // Currency
        $this->writeCurrencies($xml, $profile);

        // Categories
        $this->writeCategories($xml, $profile);

        // Offers
        $this->writeOffers($xml, $profile);

        // Close shop
        $xml->endElement();

        // Close yml_catalog
        $xml->endElement();

        $xml->endDocument();
        $xml->flush();
    }


    /**
     * Write currencies block
     * 
     * @param XMLWriter $xml
     * @param array $profile
     */
    private function writeCurrencies($xml, $profile) {
        $xml->startElement('currencies');

        $this->load->model('localisation/currency');
        
        // Get all currencies
        $currencies = $this->model_localisation_currency->getCurrencies();
        
        // Main currency from profile
        $main_currency = $profile['currency_code'];
        
        foreach ($currencies as $currency) {
            if ($currency['status']) {
                $xml->startElement('currency');
                $xml->writeAttribute('id', $currency['code']);
                $xml->writeAttribute('rate', $currency['code'] == $main_currency ? '1' : $currency['value']);
                $xml->endElement();
            }
        }

        $xml->endElement();
    }

    /**
     * Write categories block
     * 
     * @param XMLWriter $xml
     * @param array $profile
     */
    private function writeCategories($xml, $profile) {
        $xml->startElement('categories');

        $categories = $this->model_extension_feed_dockercart_export_yml->getCategoriesForExport(
            $profile['profile_id'],
            isset($profile['language_id']) ? (int)$profile['language_id'] : null
        );

        foreach ($categories as $category) {
            $xml->startElement('category');
            $xml->writeAttribute('id', $category['category_id']);
            
            if ($category['parent_id'] > 0) {
                $xml->writeAttribute('parentId', $category['parent_id']);
            }
            
            $xml->text($category['name']);
            $xml->endElement();
        }

        $xml->endElement();
    }

    /**
     * Write offers block
     * 
     * @param XMLWriter $xml
     * @param array $profile
     */
    private function writeOffers($xml, $profile) {
        $xml->startElement('offers');

        $max_products = (int)$profile['max_products'];
        $batch_size = 100;
        $start = 0;
        $total_exported = 0;

        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        while (true) {
            $products = $this->model_extension_feed_dockercart_export_yml->getProductsForExport(
                $profile['profile_id'],
                $start,
                $batch_size,
                $profile['language_id']  // Pass language_id
            );

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                if ($total_exported >= $max_products) {
                    break 2;
                }

                $this->writeOffer($xml, $product, $profile);
                $total_exported++;
            }

            $start += $batch_size;

            // Memory management
            if ($start % 1000 == 0) {
                gc_collect_cycles();
            }
        }

        $xml->endElement();
    }

    /**
     * Write single offer
     * 
     * @param XMLWriter $xml
     * @param array $product
     * @param array $profile
     */
    private function writeOffer($xml, $product, $profile) {
        $xml->startElement('offer');
        $xml->writeAttribute('id', $product['product_id']);
        $xml->writeAttribute('available', $product['quantity'] > 0 ? 'true' : 'false');

        // Name
        $xml->writeElement('name', $product['name']);

        // URL
        $this->load->model('catalog/product');
        $url = $this->url->link('product/product', 'product_id=' . $product['product_id']);

        // Keep SEO + language prefix generated by url->link(), but use selected store base URL
        if ((int)$profile['store_id'] > 0) {
            $url = $this->replaceUrlBase($url, $this->getStoreUrl($profile['store_id']));
        }
        $xml->writeElement('url', $url);

        // Price
        $this->load->model('localisation/currency');
        $price = $this->currency->convert($product['price'], $this->config->get('config_currency'), $profile['currency_code']);
        $xml->writeElement('price', number_format($price, 2, '.', ''));

        // Currency
        $xml->writeElement('currencyId', $profile['currency_code']);

        // Category ID (first category)
        $this->load->model('catalog/product');
        $categories = $this->db->query("
            SELECT category_id FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product['product_id'] . "'
            ORDER BY category_id ASC LIMIT 1
        ");
        if ($categories->num_rows) {
            $xml->writeElement('categoryId', $categories->row['category_id']);
        }

        // Picture(s)
        if ($product['image']) {
            $image_url = $this->model_tool_image->resize($product['image'], 800, 800);
            // Convert relative URL to absolute
            if (strpos($image_url, 'http') !== 0) {
                $image_url = $this->getStoreUrl($profile['store_id']) . ltrim($image_url, '/');
            }
            $xml->writeElement('picture', $image_url);
        }

        // Additional images
        $images = $this->model_extension_feed_dockercart_export_yml->getProductImages($product['product_id']);
        foreach ($images as $image) {
            $image_url = $this->model_tool_image->resize($image['image'], 800, 800);
            if (strpos($image_url, 'http') !== 0) {
                $image_url = $this->getStoreUrl($profile['store_id']) . ltrim($image_url, '/');
            }
            $xml->writeElement('picture', $image_url);
        }

        // Delivery
        $xml->writeElement('delivery', 'true');
        $xml->writeElement('pickup', 'true');
        $xml->writeElement('store', 'false');

        // Delivery options (if configured in profile)
        if (!empty($profile['settings']['delivery_cost']) || !empty($profile['settings']['delivery_days'])) {
            $xml->startElement('delivery-options');
            $xml->startElement('option');
            $xml->writeAttribute('cost', !empty($profile['settings']['delivery_cost']) ? $profile['settings']['delivery_cost'] : '0');
            $xml->writeAttribute('days', !empty($profile['settings']['delivery_days']) ? $profile['settings']['delivery_days'] : '1');
            if (!empty($profile['settings']['order_before'])) {
                $xml->writeAttribute('order-before', $profile['settings']['order_before']);
            }
            $xml->endElement();
            $xml->endElement();
        }

        // Pickup options (if configured)
        if (!empty($profile['settings']['pickup_enabled'])) {
            $xml->startElement('pickup-options');
            $xml->startElement('option');
            $xml->writeAttribute('cost', '0');
            $xml->writeAttribute('days', '0');
            $xml->endElement();
            $xml->endElement();
        }

        // Vendor (manufacturer)
        if (!empty($product['manufacturer'])) {
            $xml->writeElement('vendor', $product['manufacturer']);
        }

        // Vendor code (model)
        if (!empty($product['model'])) {
            $xml->writeElement('vendorCode', $product['model']);
        }

        // Description
        if (!empty($product['description'])) {
            $description = $this->prepareDescription($product['description'], $profile);
            if (!empty($description)) {
                $xml->startElement('description');
                $xml->writeCData($description);
                $xml->endElement();
            }
        }

        // Sales notes (if configured)
        if (!empty($profile['settings']['sales_notes'])) {
            $xml->writeElement('sales_notes', mb_substr($profile['settings']['sales_notes'], 0, 50));
        }

        // Adult content marker (if configured)
        if (!empty($profile['settings']['adult_content'])) {
            $xml->writeElement('adult', 'true');
        }

        // Dimensions
        if (!empty($product['length']) && !empty($product['width']) && !empty($product['height'])) {
            $xml->writeElement('dimensions', 
                number_format($product['length'], 2, '.', '') . '/' . 
                number_format($product['width'], 2, '.', '') . '/' . 
                number_format($product['height'], 2, '.', '')
            );
        }

        // Weight
        if (!empty($product['weight'])) {
            $xml->writeElement('weight', number_format($product['weight'], 3, '.', ''));
        }

        // Barcode
        if (!empty($product['ean'])) {
            $xml->writeElement('barcode', $product['ean']);
        } elseif (!empty($product['upc'])) {
            $xml->writeElement('barcode', $product['upc']);
        }

        // Params (attributes)
        $attributes = $this->model_extension_feed_dockercart_export_yml->getProductAttributes(
            $product['product_id'],
            $profile['language_id']
        );
        foreach ($attributes as $attribute) {
            $xml->startElement('param');
            $xml->writeAttribute('name', $attribute['attribute']);
            $xml->text($attribute['value']);
            $xml->endElement();
        }

        // Close offer
        $xml->endElement();
    }

    /**
     * Prepare product description for YML
     * 
     * @param string $description
     * @param array $profile
     * @return string
     */
    private function prepareDescription($description, $profile) {
        // Always decode HTML entities first (OpenCart stores them encoded in DB)
        $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Check if HTML tags should be stripped
        $strip_html = !empty($profile['settings']['strip_html_tags']);
        
        if ($strip_html) {
            // Strip all HTML tags
            $description = strip_tags($description);
        } else {
            // Keep HTML but clean it up and fix unclosed tags
            if (function_exists('libxml_use_internal_errors')) {
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $doc->loadHTML('<?xml encoding="UTF-8">' . $description, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $description = $doc->saveHTML();
                libxml_clear_errors();
                
                // Remove XML declaration if added
                $description = preg_replace('/<\?xml[^>]*>/', '', $description);
            }
        }
        
        // Clean up whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        // Apply YML length limit (3000 chars)
        $description = mb_substr($description, 0, 3000);
        
        return $description;
    }

    /**
     * Get store URL
     * 
     * @param int $store_id
     * @return string
     */
    private function getStoreUrl($store_id) {
        if ($store_id == 0) {
            // Use HTTP_SERVER if available (catalog context), fallback to config
            if (defined('HTTP_SERVER')) {
                return HTTP_SERVER;
            }
            return $this->config->get('config_url');
        }

        $this->load->model('setting/store');
        $store_info = $this->model_setting_store->getStore($store_id);
        
        if ($store_info) {
            return $store_info['url'];
        }

        // Fallback
        if (defined('HTTP_SERVER')) {
            return HTTP_SERVER;
        }
        return $this->config->get('config_url');
    }

    /**
     * Replace URL base (scheme + host + base path) preserving route/path/query.
     *
     * @param string $url
     * @param string $new_base
     * @return string
     */
    private function replaceUrlBase($url, $new_base) {
        $new_base = rtrim($new_base, '/') . '/';

        // Try to replace current catalog base directly
        $current_base = '';
        if (defined('HTTP_SERVER')) {
            $current_base = rtrim(HTTP_SERVER, '/') . '/';
        } else {
            $current_base = rtrim((string)$this->config->get('config_url'), '/') . '/';
        }

        if ($current_base && strpos($url, $current_base) === 0) {
            return $new_base . ltrim(substr($url, strlen($current_base)), '/');
        }

        // Fallback via parse_url
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $relative = '';
        if (!empty($parts['path'])) {
            $relative .= ltrim($parts['path'], '/');
        }
        if (!empty($parts['query'])) {
            $relative .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $relative .= '#' . $parts['fragment'];
        }

        return $new_base . $relative;
    }

    /**
     * Generate split YML files + index
     * 
     * @param array $profile
     * @param int $products_per_file
     */
    private function generateSplitYML($profile, $products_per_file, $lang_code = '', $language_id = null) {
        $profile_id = $profile['profile_id'];
        $webroot = DIR_APPLICATION . '../';
        
        // Language suffix
        $lang_suffix = $lang_code ? '-' . $lang_code : '';
        
        // Clean up old parts
        $old_parts = glob($webroot . 'export-yml-' . $profile_id . $lang_suffix . '-part-*.xml');
        foreach ($old_parts as $file) {
            @unlink($file);
        }
        
        // Get total products
        $total_products = $this->model_extension_feed_dockercart_export_yml->getTotalProductsForExport($profile_id, $language_id);
        $total_parts = ceil($total_products / $products_per_file);
        
        $this->logger->info('Generating ' . $total_parts . ' parts for profile ' . $profile_id . $lang_suffix);
        
        $part_files = array();
        
        // Generate each part
        for ($part = 1; $part <= $total_parts; $part++) {
            $part_file = $webroot . 'export-yml-' . $profile_id . $lang_suffix . '-part-' . $part . '.xml';
            $tmp_part_file = $part_file . '.tmp';
            
            $start_offset = ($part - 1) * $products_per_file;
            
            $this->logger->debug('Generating part ' . $part . ' of ' . $total_parts . ' (offset: ' . $start_offset . ')');
            
            // Generate this part
            $this->generateYMLPart($profile, $tmp_part_file, $start_offset, $products_per_file);
            
            // Atomic rename
            if (file_exists($part_file)) {
                @unlink($part_file);
            }
            rename($tmp_part_file, $part_file);
            
            $part_files[] = array(
                'file' => 'export-yml-' . $profile_id . $lang_suffix . '-part-' . $part . '.xml',
                'size' => filesize($part_file)
            );
        }
        
        // Generate index file
        $index_file = $webroot . 'export-yml-' . $profile_id . $lang_suffix . '.xml';
        $tmp_index_file = $index_file . '.tmp';
        
        $this->generateYMLIndex($profile, $tmp_index_file, $part_files);
        
        // Atomic rename
        if (file_exists($index_file)) {
            @unlink($index_file);
        }
        rename($tmp_index_file, $index_file);
        
        $this->logger->info('Index file created: ' . filesize($index_file) . ' bytes');
    }

    /**
     * Generate YML part with offset
     * 
     * @param array $profile
     * @param string $output_file
     * @param int $start_offset
     * @param int $limit
     */
    private function generateYMLPart($profile, $output_file, $start_offset, $limit) {
        // Initialize XMLWriter
        $xml = new XMLWriter();
        $xml->openURI($output_file);
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        // Start yml_catalog
        $xml->startElement('yml_catalog');
        $xml->writeAttribute('date', date('Y-m-d H:i'));

        // Shop info
        $xml->startElement('shop');

        // Shop name and company
        $xml->writeElement('name', !empty($profile['shop_name']) ? $profile['shop_name'] : $this->config->get('config_name'));
        $xml->writeElement('company', !empty($profile['company_name']) ? $profile['company_name'] : $this->config->get('config_name'));
        $xml->writeElement('url', $this->getStoreUrl($profile['store_id']));

        // Currency
        $this->writeCurrencies($xml, $profile);

        // Categories
        $this->writeCategories($xml, $profile);

        // Offers - only this part
        $xml->startElement('offers');
        
        $batch_size = 100;
        $current_offset = $start_offset;
        $products_written = 0;

        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        while ($products_written < $limit) {
            $products = $this->model_extension_feed_dockercart_export_yml->getProductsForExport(
                $profile['profile_id'],
                $current_offset,
                $batch_size,
                $profile['language_id']  // Pass language_id
            );

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                if ($products_written >= $limit) {
                    break 2;
                }

                $this->writeOffer($xml, $product, $profile);
                $products_written++;
            }

            $current_offset += $batch_size;

            // Memory management
            if ($current_offset % 1000 == 0) {
                gc_collect_cycles();
            }
        }

        $xml->endElement(); // offers

        // Close shop
        $xml->endElement();

        // Close yml_catalog
        $xml->endElement();

        $xml->endDocument();
        $xml->flush();
    }

    /**
     * Generate YML index file (sitemapindex-like)
     * 
     * @param array $profile
     * @param string $output_file
     * @param array $part_files
     */
    private function generateYMLIndex($profile, $output_file, $part_files) {
        $xml = new XMLWriter();
        $xml->openURI($output_file);
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        // Start ymlindex
        $xml->startElement('ymlindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $base_url = $this->getStoreUrl($profile['store_id']);

        foreach ($part_files as $part) {
            $xml->startElement('yml');
            $xml->writeElement('loc', $base_url . $part['file']);
            $xml->writeElement('lastmod', date('c'));
            $xml->endElement();
        }

        $xml->endElement(); // ymlindex

        $xml->endDocument();
        $xml->flush();
    }

    /**
     * Send error XML response
     * 
     * @param string $message
     */
    private function sendErrorXML($message) {
        http_response_code(403);
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<yml_catalog>' . "\n";
        echo '  <!-- YML generation disabled: ' . htmlspecialchars($message, ENT_XML1, 'UTF-8') . ' -->' . "\n";
        echo '</yml_catalog>' . "\n";
        exit;
    }
}
