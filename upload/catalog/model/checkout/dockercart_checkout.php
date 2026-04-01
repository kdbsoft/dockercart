<?php
/**
 * DockerCart Checkout - Catalog Model
 * Database operations for catalog side
 * 
 * @package    DockerCart Checkout
 * @author     mathflow-bit
 * @license    Commercial License
 */

class ModelCheckoutDockerCartCheckout extends Model {
    
    // Constants
    const STATUS_RECOVERED = 1;
    const STATUS_ABANDONED = 0;
    
    /**
     * Track checkout step
     * 
     * @param string $step
     * @param array $data
     * @return void
     */
    public function trackStep($step, $data = array()) {
        $sessionId = session_id();
        $customerId = $this->customer->isLogged() ? $this->customer->getId() : 0;
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_checkout_analytics` 
                          SET session_id = '" . $this->db->escape($sessionId) . "',
                              customer_id = " . (int)$customerId . ",
                              step = '" . $this->db->escape($step) . "',
                              data = '" . $this->db->escape(json_encode($data)) . "',
                              date_added = NOW()");
    }
    
    /**
     * Save abandoned cart
     * 
     * @param array $data
     * @return int
     */
    public function saveAbandonedCart($data) {
        $sessionId = session_id();
        $customerId = $this->customer->isLogged() ? $this->customer->getId() : 0;
        
        // Check if exists
        $query = $this->db->query("SELECT abandoned_id 
                                   FROM `" . DB_PREFIX . "dockercart_checkout_abandoned`
                                   WHERE session_id = '" . $this->db->escape($sessionId) . "'
                                   AND recovered = " . self::STATUS_ABANDONED . "");
        
        $email = isset($data['email']) ? $data['email'] : '';
        $phone = isset($data['telephone']) ? $data['telephone'] : '';
        $cartData = isset($data['cart']) ? json_encode($data['cart']) : '';
        $addressData = isset($data['address']) ? json_encode($data['address']) : '';
        $lastStep = isset($data['step']) ? $data['step'] : '';
        
        if ($query->num_rows) {
            // Update existing
            $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
                              SET customer_id = " . (int)$customerId . ",
                                  email = '" . $this->db->escape($email) . "',
                                  phone = '" . $this->db->escape($phone) . "',
                                  cart_data = '" . $this->db->escape($cartData) . "',
                                  address_data = '" . $this->db->escape($addressData) . "',
                                  last_step = '" . $this->db->escape($lastStep) . "',
                                  date_modified = NOW()
                              WHERE abandoned_id = " . (int)$query->row['abandoned_id']);
            
            return (int)$query->row['abandoned_id'];
        } else {
            // Insert new
            $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_checkout_abandoned`
                              SET session_id = '" . $this->db->escape($sessionId) . "',
                                  customer_id = " . (int)$customerId . ",
                                  email = '" . $this->db->escape($email) . "',
                                  phone = '" . $this->db->escape($phone) . "',
                                  cart_data = '" . $this->db->escape($cartData) . "',
                                  address_data = '" . $this->db->escape($addressData) . "',
                                  last_step = '" . $this->db->escape($lastStep) . "',
                                  date_added = NOW(),
                                  date_modified = NOW()");
            
            return $this->db->getLastId();
        }
    }
    
    /**
     * Mark cart as recovered (order placed)
     * 
     * @return void
     */
    public function markRecovered() {
        $sessionId = session_id();
        
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_checkout_abandoned`
                          SET recovered = " . self::STATUS_RECOVERED . ",
                              date_modified = NOW()
                          WHERE session_id = '" . $this->db->escape($sessionId) . "'
                          AND recovered = " . self::STATUS_ABANDONED . "");
    }
    
    /**
     * Get cart items for display
     * 
     * @return array
     */
    public function getCartItems() {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        
        $products = $this->cart->getProducts();
        $items = array();
        
        foreach ($products as $product) {
            // Get product info
            $product_info = $this->model_catalog_product->getProduct($product['product_id']);
            
            if (!$product_info) {
                continue;
            }
            
            // Image
            if ($product['image']) {
                $thumb = $this->model_tool_image->resize($product['image'], 50, 50);
            } else {
                $thumb = $this->model_tool_image->resize('placeholder.png', 50, 50);
            }
            
            // Options
            $option_data = array();
            foreach ($product['option'] as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
                    
                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
                }
                
                $option_data[] = array(
                    'name'  => $option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                );
            }
            
            // Price
            $price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
            $total = $this->tax->calculate($product['total'], $product['tax_class_id'], $this->config->get('config_tax'));
            
            $items[] = array(
                'cart_id'   => $product['cart_id'],
                'product_id'=> $product['product_id'],
                'name'      => $product['name'],
                'model'     => $product['model'],
                'quantity'  => $product['quantity'],
                'option'    => $option_data,
                'price'     => $this->currency->format($price, $this->session->data['currency']),
                'total'     => $this->currency->format($total, $this->session->data['currency']),
                'thumb'     => $thumb,
                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            );
        }
        
        return $items;
    }
    
    /**
     * Get order totals
     * 
     * @return array
     */
    public function getTotals() {
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;
        
        // Load total extensions
        $this->load->model('setting/extension');
        
        $sort_order = array();
        
        $results = $this->model_setting_extension->getExtensions('total');
        
        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }
        
        array_multisort($sort_order, SORT_ASC, $results);
        
        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);
                
                // Each total extension adds to $totals
                $this->{'model_extension_total_' . $result['code']}->getTotal($totals, $taxes, $total);
            }
        }
        
        $sort_order = array();
        
        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }
        
        array_multisort($sort_order, SORT_ASC, $totals);
        
        // Format totals
        $formatted = array();
        foreach ($totals as $total_row) {
            $formatted[] = array(
                'code'       => $total_row['code'],
                'title'      => $total_row['title'],
                'value'      => $total_row['value'],
                'text'       => $this->currency->format($total_row['value'], $this->session->data['currency']),
                'sort_order' => $total_row['sort_order']
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get available shipping methods
     * 
     * @param array $address
     * @return array
     */
    public function getShippingMethods($address = array()) {
        // Use session address if not provided
        if (empty($address) && isset($this->session->data['shipping_address'])) {
            $address = $this->session->data['shipping_address'];
        }
        
        if (empty($address)) {
            return array();
        }
        
        $this->load->model('setting/extension');
        
        $results = $this->model_setting_extension->getExtensions('shipping');
        
        $method_data = array();
        
        foreach ($results as $result) {
            if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                $this->load->model('extension/shipping/' . $result['code']);
                
                $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($address);
                
                if ($quote) {
                    $method_data[$result['code']] = array(
                        'title'      => $quote['title'],
                        'quote'      => $quote['quote'],
                        'sort_order' => $quote['sort_order'],
                        'error'      => $quote['error']
                    );
                }
            }
        }
        
        $sort_order = array();
        
        foreach ($method_data as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }
        
        array_multisort($sort_order, SORT_ASC, $method_data);
        
        return $method_data;
    }
    
    /**
     * Get available payment methods
     * 
     * @param array $address
     * @param float $total
     * @return array
     */
    public function getPaymentMethods($address = array(), $total = 0) {
        // Use session address if not provided
        if (empty($address) && isset($this->session->data['payment_address'])) {
            $address = $this->session->data['payment_address'];
        }
        
        if (empty($address)) {
            // Use shipping address as fallback
            if (isset($this->session->data['shipping_address'])) {
                $address = $this->session->data['shipping_address'];
            } else {
                return array();
            }
        }
        
        // Calculate total if not provided
        if (!$total) {
            $totals = array();
            $taxes = $this->cart->getTaxes();
            $total = 0;
            
            $this->load->model('setting/extension');
            
            $results = $this->model_setting_extension->getExtensions('total');
            
            $sort_order = array();
            
            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
            }
            
            array_multisort($sort_order, SORT_ASC, $results);
            
            foreach ($results as $result) {
                if ($this->config->get('total_' . $result['code'] . '_status')) {
                    $this->load->model('extension/total/' . $result['code']);
                    
                    $this->{'model_extension_total_' . $result['code']}->getTotal($totals, $taxes, $total);
                }
            }
        }
        
        $this->load->model('setting/extension');
        
        $results = $this->model_setting_extension->getExtensions('payment');
        
        $recurring = $this->cart->hasRecurringProducts();
        
        $method_data = array();
        
        foreach ($results as $result) {
            if ($this->config->get('payment_' . $result['code'] . '_status')) {
                $this->load->model('extension/payment/' . $result['code']);
                
                $method = $this->{'model_extension_payment_' . $result['code']}->getMethod($address, $total);
                
                if ($method) {
                    $normalized_methods = $this->normalizePaymentMethods($method, $result['code']);

                    if (!$normalized_methods) {
                        continue;
                    }

                    if ($recurring) {
                        if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
                            foreach ($normalized_methods as $code => $method_item) {
                                $method_data[$code] = $method_item;
                            }
                        }
                    } else {
                        foreach ($normalized_methods as $code => $method_item) {
                            $method_data[$code] = $method_item;
                        }
                    }
                }
            }
        }
        
        $sort_order = array();
        
        foreach ($method_data as $key => $value) {
            $sort_order[$key] = isset($value['sort_order']) ? (int)$value['sort_order'] : 0;
        }
        
        array_multisort($sort_order, SORT_ASC, $method_data);
        
        return $method_data;
    }

    /**
     * Normalize payment methods to a flat list keyed by full method code.
     *
     * Supports both legacy one-method format and grouped quote[] format.
     */
    protected function normalizePaymentMethods($method, $extension_code) {
        $normalized = array();

        if (isset($method['quote']) && is_array($method['quote'])) {
            foreach ($method['quote'] as $quote) {
                if (!is_array($quote) || empty($quote['code'])) {
                    continue;
                }

                if (!isset($quote['sort_order'])) {
                    $quote['sort_order'] = isset($method['sort_order']) ? (int)$method['sort_order'] : 0;
                }

                if (!isset($quote['title']) && isset($method['title'])) {
                    $quote['title'] = $method['title'];
                }

                if (!array_key_exists('terms', $quote)) {
                    $quote['terms'] = isset($method['terms']) ? $method['terms'] : '';
                }

                    // Map to 'description' for compatibility with frontend
                    if (!array_key_exists('description', $quote)) {
                        $quote['description'] = isset($quote['terms']) ? $quote['terms'] : (isset($method['description']) ? $method['description'] : '');
                    }

                $normalized[$quote['code']] = $quote;
            }
        } elseif (is_array($method)) {
            if (empty($method['code'])) {
                $method['code'] = $extension_code;
            }

            if (!isset($method['sort_order'])) {
                $method['sort_order'] = 0;
            }

            if (!array_key_exists('terms', $method)) {
                $method['terms'] = '';
            }

            if (!array_key_exists('description', $method)) {
                $method['description'] = isset($method['terms']) ? $method['terms'] : '';
            }

            $normalized[$method['code']] = $method;
        }

        return $normalized;
    }
    
    /**
     * Get customer addresses
     * 
     * @return array
     */
    public function getCustomerAddresses() {
        if (!$this->customer->isLogged()) {
            return array();
        }
        
        $this->load->model('account/address');
        
        return $this->model_account_address->getAddresses();
    }
    
    /**
     * Get country zones
     * 
     * @param int $country_id
     * @return array
     */
    public function getZonesByCountryId($country_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone 
                                   WHERE country_id = '" . (int)$country_id . "' 
                                   AND status = '1' 
                                   ORDER BY name");
        
        return $query->rows;
    }
    
    /**
     * Validate coupon code
     * 
     * @param string $code
     * @return array
     */
    public function validateCoupon($code) {
        $this->load->model('extension/total/coupon');
        
        $coupon_info = $this->model_extension_total_coupon->getCoupon($code);
        
        if (!$coupon_info) {
            return array(
                'error' => true,
                'message' => $this->language->get('error_coupon')
            );
        }
        
        return array(
            'error' => false,
            'coupon' => $coupon_info
        );
    }
    
    /**
     * Validate voucher code
     * 
     * @param string $code
     * @return array
     */
    public function validateVoucher($code) {
        $this->load->model('extension/total/voucher');
        
        $voucher_info = $this->model_extension_total_voucher->getVoucher($code);
        
        if (!$voucher_info) {
            return array(
                'error' => true,
                'message' => $this->language->get('error_voucher')
            );
        }
        
        return array(
            'error' => false,
            'voucher' => $voucher_info
        );
    }
}
