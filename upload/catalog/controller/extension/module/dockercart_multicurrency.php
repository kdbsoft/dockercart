<?php
/**
 * DockerCart Multicurrency Module
 * Catalog Controller
 * 
 * @package    DockerCart
 * @subpackage Multicurrency
 * @author     DockerCart
 * @version    1.0.0
 */

class ControllerExtensionModuleDockercartMulticurrency extends Controller {
    
    private $product_currencies = array(); // Cache for product currencies
    
    /**
     * Event: Before model getProduct - store product ID to track
     */
    public function eventModelProductBefore(&$route, &$args) {
        // Just mark that we're tracking this product
        // We'll handle conversion in the after event
    }
    
    /**
     * Event: After model getProduct - convert price from product currency to current
     */
    public function eventModelProductAfter(&$route, &$args, &$output) {
        if (!$this->config->get('module_dockercart_multicurrency_status')) {
            return;
        }
        
        // Проверка лицензии - модуль не работает без валидной лицензии
        if (!$this->checkLicense()) {
            return;
        }
        
        if (!empty($output) && isset($output['product_id'])) {
            $this->convertProductPrice($output);
        }
    }
    
    /**
     * Event: After model getProducts - convert prices for all products
     */
    public function eventModelProductsAfter(&$route, &$args, &$output) {
        if (!$this->config->get('module_dockercart_multicurrency_status')) {
            return;
        }
        
        // Проверка лицензии - модуль не работает без валидной лицензии
        if (!$this->checkLicense()) {
            return;
        }
        
        if (!empty($output) && is_array($output)) {
            foreach ($output as &$product) {
                if (isset($product['product_id'])) {
                    $this->convertProductPrice($product);
                }
            }
        }
    }
    
    /**
     * Convert product price from its currency to current session currency
     */
    private function convertProductPrice(&$product) {
        // Get product's currency_id and RAW price from database
        $query = $this->db->query("SELECT price, currency_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product['product_id'] . "'");
        
        if (!$query->num_rows || empty($query->row['currency_id'])) {
            return; // No specific currency set, use default OpenCart behavior
        }
        
        $currency_id = (int)$query->row['currency_id'];
        $raw_price = (float)$query->row['price'];
        
        // Get product currency value
        $currency_query = $this->db->query("SELECT code, value FROM " . DB_PREFIX . "currency WHERE currency_id = '" . (int)$currency_id . "'");
        
        if (!$currency_query->num_rows) {
            return;
        }
        
        $product_currency_code = $currency_query->row['code'];
        $product_currency_value = (float)$currency_query->row['value'];
        
        // Get default currency (the base currency for OpenCart calculations)
        $default_currency = $this->config->get('config_currency');
        $default_query = $this->db->query("SELECT value FROM " . DB_PREFIX . "currency WHERE code = '" . $this->db->escape($default_currency) . "'");
        
        if (!$default_query->num_rows) {
            return;
        }
        
        $default_currency_value = (float)$default_query->row['value'];
        
        // IMPORTANT: Convert price from product currency TO DEFAULT CURRENCY
        // OpenCart will then convert from default currency to user's current currency
        // Formula: raw_price * (default_value / product_value)
        // Example: 500 EUR → USD (default)
        //   EUR value = 0.7846, USD value = 1.0
        //   500 * (1.0 / 0.7846) = 637.27 USD ✓
        
        $price_in_default_currency = $raw_price * ($default_currency_value / $product_currency_value);
        
        // Replace the price with converted value
        $product['price'] = $price_in_default_currency;
        
        // Convert special price from product_special table
        $special_query = $this->db->query("
            SELECT price FROM " . DB_PREFIX . "product_special 
            WHERE product_id = '" . (int)$product['product_id'] . "' 
            AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' 
            AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) 
            ORDER BY priority ASC, price ASC 
            LIMIT 1
        ");
        
        if ($special_query->num_rows && (float)$special_query->row['price'] > 0) {
            $raw_special = (float)$special_query->row['price'];
            $product['special'] = $raw_special * ($default_currency_value / $product_currency_value);
        }
        
        // Convert tax (based on price)
        if (isset($product['tax'])) {
            $product['tax'] = $price_in_default_currency;
        }
        
        // Convert discounts if they exist in the product data
        if (isset($product['discounts']) && is_array($product['discounts'])) {
            foreach ($product['discounts'] as &$discount) {
                if (isset($discount['price']) && is_numeric($discount['price'])) {
                    $discount['price'] = $discount['price'] * ($default_currency_value / $product_currency_value);
                }
            }
        }
    }
    
    /**
     * Проверка лицензии (блокирует работу модуля если нет валидной лицензии)
     */
    private function checkLicense() {
        $license_key = $this->config->get('module_dockercart_multicurrency_license_key');

        // Allow localhost/dev environments
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false || strpos($domain, '.docker.localhost') !== false) {
            return true;
        }

        if (empty($license_key)) {
            return false;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            return false;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            return false;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, 'dockercart_multicurrency');

            return $result['valid'];
        } catch (Exception $e) {
            return false;
        }
    }
}
