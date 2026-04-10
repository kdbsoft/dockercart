<?php
/**
 * DockerCart 1-Click Checkout Module
 * Catalog Controller
 * 
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    Commercial
 * @version    1.0.0
 */

class ControllerExtensionModuleDockercartOneclickcheckout extends Controller {
    
    /**
     * Event: Add 1-click button after "Add to Cart" button on product page
     */
    public function eventProductViewAfter(&$route, &$data, &$output) {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_oneclickcheckout_status')) {
            return;
        }
        
        // Check license
        if (!$this->checkLicense()) {
            return;
        }
        
        // Load language
        $this->load->language('extension/module/dockercart_oneclickcheckout');
        
        // Get button text from settings or use default
        $button_text_array = $this->config->get('module_dockercart_oneclickcheckout_button_text');
        $language_id = $this->config->get('config_language_id');
        
        if (!empty($button_text_array) && is_array($button_text_array) && isset($button_text_array[$language_id])) {
            $button_text = $button_text_array[$language_id];
        } else {
            $button_text = $this->language->get('button_oneclickcheckout');
        }
        
        // Get color theme
        $color_theme = $this->config->get('module_dockercart_oneclickcheckout_color_theme');
        if (empty($color_theme)) {
            $color_theme = 'theme-purple';
        }
        
        // Get product_id from data
        $product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        
        // Build button HTML
        $button_html = '<div class="dockercart-oneclickcheckout-wrapper">' . "\n";
        $button_html .= '  <button type="button" id="button-oneclickcheckout" data-product-id="' . $product_id . '" data-theme="' . htmlspecialchars($color_theme, ENT_QUOTES, 'UTF-8') . '" class="dockercart-oneclickcheckout-button mt-3 w-full py-3 border border-gray-200 text-gray-800 font-semibold rounded-xl hover:bg-gray-50 transition text-sm">';
        $button_html .= htmlspecialchars($button_text, ENT_QUOTES, 'UTF-8');
        $button_html .= '</button>' . "\n";
        $button_html .= '</div>' . "\n";
        
        // Build modal HTML
        $modal_html = $this->buildModalHtml($color_theme);
        
        // Add CSS and JS
        $active_theme = (string)$this->config->get('config_theme');
        $css_link = '';
        if ($active_theme !== 'dockercart') {
            $css_link = '<link rel="stylesheet" type="text/css" href="catalog/view/theme/default/stylesheet/dockercart_oneclickcheckout.css" />' . "\n";
        }
        $js_link = '<script src="catalog/view/javascript/dockercart_oneclickcheckout.js"></script>' . "\n";
        
        // Find the "Add to Cart" button and insert our button after it
        $patterns = [
            // Pattern 1: Look for id="button-cart"
            'id="button-cart"',
            // Pattern 2: Look for button-cart class
            'class="btn btn-primary btn-lg btn-block"'
        ];
        
        $inserted = false;
        foreach ($patterns as $pattern) {
            $pos = strpos($output, $pattern);
            if ($pos !== false) {
                // Find the end of the button tag
                $button_end = strpos($output, '</button>', $pos);
                if ($button_end !== false) {
                    // Find the end of parent div (usually <div class="form-group">)
                    $div_end = strpos($output, '</div>', $button_end);
                    if ($div_end !== false) {
                        $insert_pos = $div_end + 6; // 6 = length of '</div>'
                        $output = substr($output, 0, $insert_pos) . "\n" . $button_html . "\n" . substr($output, $insert_pos);
                        $inserted = true;
                        break;
                    }
                }
            }
        }
        
        // If we couldn't find the add to cart button, insert before product tabs
        if (!$inserted) {
            $pos = strpos($output, '<ul class="nav nav-tabs">');
            if ($pos !== false) {
                $output = substr($output, 0, $pos) . $button_html . "\n" . substr($output, $pos);
            }
        }
        
        // Insert modal, CSS and JS before closing body tag
        $pos = strpos($output, '</body>');
        if ($pos !== false) {
            $output = substr($output, 0, $pos) . $css_link . $js_link . $modal_html . substr($output, $pos);
        } else {
            // If no </body> tag, append at the end
            $output .= $css_link . $js_link . $modal_html;
        }
    }
    
    /**
     * Build modal HTML
     */
    private function buildModalHtml($color_theme = 'theme-purple') {
        $this->load->language('extension/module/dockercart_oneclickcheckout');
        
        // Get customer data if logged in
        $customer_data = [];
        $is_logged = false;
        
        if ($this->customer->isLogged()) {
            $is_logged = true;
            
            // Get customer basic info
            $customer_data['firstname'] = $this->customer->getFirstName();
            $customer_data['lastname'] = $this->customer->getLastName();
            $customer_data['email'] = $this->customer->getEmail();
            $customer_data['telephone'] = $this->customer->getTelephone();
            
            // Get customer default address
            $this->load->model('account/address');
            $address_id = $this->customer->getAddressId();
            
            if ($address_id) {
                $address = $this->model_account_address->getAddress($address_id);
                
                if ($address) {
                    $customer_data['address'] = $address['address_1'];
                    if (!empty($address['address_2'])) {
                        $customer_data['address'] .= ', ' . $address['address_2'];
                    }
                    $customer_data['city'] = $address['city'];
                    $customer_data['postcode'] = $address['postcode'];
                    $customer_data['country_id'] = $address['country_id'];
                }
            }
        }
        
        // Get modal title from settings (multilingual) or use default
        $modal_title_array = $this->config->get('module_dockercart_oneclickcheckout_modal_title');
        $language_id = $this->config->get('config_language_id');
        
        if (!empty($modal_title_array) && is_array($modal_title_array) && isset($modal_title_array[$language_id])) {
            $modal_title = $modal_title_array[$language_id];
        } else {
            $modal_title = $this->language->get('heading_oneclickcheckout');
        }
        
        $captcha_code = (string)$this->config->get('config_captcha');
        // Determine localized success button text (fallback to text_success)
        $success_button_text = $this->language->get('text_order_placed');
        if ($success_button_text === 'text_order_placed' || $success_button_text === '') {
            $success_button_text = $this->language->get('text_success');
        }

        $html = '<div class="modal fade ' . htmlspecialchars($color_theme, ENT_QUOTES, 'UTF-8') . '" id="oneclickcheckout-modal" tabindex="-1" role="dialog" data-captcha="' . htmlspecialchars($captcha_code, ENT_QUOTES, 'UTF-8') . '" data-success-text="' . htmlspecialchars($success_button_text, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        $html .= '  <div class="modal-dialog" role="document">' . "\n";
        $html .= '    <div class="modal-content">' . "\n";
        $html .= '      <div class="modal-header">' . "\n";
        $html .= '        <h4 class="modal-title">' . htmlspecialchars($modal_title, ENT_QUOTES, 'UTF-8') . '</h4>' . "\n";
        $html .= '        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . "\n";
        $html .= '      </div>' . "\n";
        $html .= '      <div class="modal-body">' . "\n";
        $html .= '        <form id="oneclickcheckout-form">' . "\n";
        $html .= '          <div class="alert alert-danger" id="oneclickcheckout-error" style="display:none;"></div>' . "\n";
        
        // Build form fields based on settings
        $fields = [
            'firstname' => ['type' => 'text', 'label' => $this->language->get('entry_firstname')],
            'lastname' => ['type' => 'text', 'label' => $this->language->get('entry_lastname')],
            'email' => ['type' => 'email', 'label' => $this->language->get('entry_email')],
            'telephone' => ['type' => 'tel', 'label' => $this->language->get('entry_telephone')],
            'address' => ['type' => 'text', 'label' => $this->language->get('entry_address')],
            'city' => ['type' => 'text', 'label' => $this->language->get('entry_city')],
            'postcode' => ['type' => 'text', 'label' => $this->language->get('entry_postcode')],
            'country' => ['type' => 'select', 'label' => $this->language->get('entry_country')],
            'comment' => ['type' => 'textarea', 'label' => $this->language->get('entry_comment')],
        ];
        
        foreach ($fields as $field_name => $field_info) {
            $show_key = 'module_dockercart_oneclickcheckout_field_' . $field_name . '_show';
            $required_key = 'module_dockercart_oneclickcheckout_field_' . $field_name . '_required';
            
            if ($this->config->get($show_key)) {
                $required = $this->config->get($required_key) ? ' required' : '';
                $required_label = $this->config->get($required_key) ? ' <span class="required">*</span>' : '';
                
                // Determine if field should be readonly/disabled
                $readonly = '';
                $disabled = '';
                $value = '';
                
                if ($is_logged && isset($customer_data[$field_name]) && !empty($customer_data[$field_name])) {
                    if ($field_name === 'country') {
                        // Use disabled for select elements
                        $disabled = ' disabled';
                    } else {
                        $readonly = ' readonly';
                    }
                    $value = htmlspecialchars($customer_data[$field_name], ENT_QUOTES, 'UTF-8');
                } elseif ($is_logged && $field_name === 'country' && isset($customer_data['country_id'])) {
                    // Country is disabled if customer has it
                    $disabled = ' disabled';
                }
                
                $html .= '          <div class="form-group' . $required . '">' . "\n";
                $html .= '            <label for="input-' . $field_name . '" class="control-label">' . $field_info['label'] . $required_label . '</label>' . "\n";
                
                if ($field_info['type'] === 'textarea') {
                    $html .= '            <textarea name="' . $field_name . '" id="input-' . $field_name . '" class="form-control" rows="3"' . $readonly . '>' . $value . '</textarea>' . "\n";
                } elseif ($field_info['type'] === 'select' && $field_name === 'country') {
                    // Load countries
                    $this->load->model('localisation/country');
                    $countries = $this->model_localisation_country->getCountries();
                    
                    $selected_country_id = isset($customer_data['country_id']) ? $customer_data['country_id'] : '';
                    
                    $html .= '            <select name="country_id" id="input-country" class="form-control"' . $disabled . '>' . "\n";
                    $html .= '              <option value="">--- Please Select ---</option>' . "\n";
                    
                    foreach ($countries as $country) {
                        $selected = ($selected_country_id == $country['country_id']) ? ' selected' : '';
                        $html .= '              <option value="' . $country['country_id'] . '"' . $selected . '>' . htmlspecialchars($country['name'], ENT_QUOTES, 'UTF-8') . '</option>' . "\n";
                    }
                    
                    $html .= '            </select>' . "\n";
                } else {
                    $html .= '            <input type="' . $field_info['type'] . '" name="' . $field_name . '" id="input-' . $field_name . '" class="form-control" value="' . $value . '"' . $readonly . ' />' . "\n";
                }
                
                $html .= '          </div>' . "\n";
            }
        }
        
        // Add captcha if enabled
        if ($this->config->get('module_dockercart_oneclickcheckout_use_captcha') && $this->config->get('captcha_' . $this->config->get('config_captcha') . '_status')) {
            $html .= '          <div class="form-group">' . "\n";
            $html .= '            <label class="control-label">' . $this->language->get('entry_captcha') . '</label>' . "\n";
            $html .= '            <div id="oneclickcheckout-captcha"></div>' . "\n";
            $html .= '          </div>' . "\n";
        }
        
        $html .= '        </form>' . "\n";
        $html .= '      </div>' . "\n";
        $html .= '      <div class="modal-footer">' . "\n";
        $html .= '        <button type="button" class="btn btn-cancel" data-dismiss="modal">' . htmlspecialchars($this->language->get('button_cancel'), ENT_QUOTES, 'UTF-8') . '</button>' . "\n";
        $html .= '        <button type="button" class="btn btn-primary" id="oneclickcheckout-submit">' . htmlspecialchars($this->language->get('button_submit'), ENT_QUOTES, 'UTF-8') . '</button>' . "\n";
        $html .= '      </div>' . "\n";
        $html .= '    </div>' . "\n";
        $html .= '  </div>' . "\n";
        $html .= '</div>' . "\n";
        
        return $html;
    }
    
    /**
     * AJAX: Process 1-click checkout order
     */
    public function submit() {
        $this->load->language('extension/module/dockercart_oneclickcheckout');
        
        $json = array();
        
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_oneclickcheckout_status')) {
            $json['error'] = $this->language->get('error_module_disabled');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        // Check license
        if (!$this->checkLicense()) {
            $json['error'] = $this->language->get('error_license_invalid');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        
        // Validate required fields
        $fields = ['firstname', 'lastname', 'telephone', 'email', 'comment', 'address', 'city', 'postcode', 'country'];
        
        foreach ($fields as $field) {
            $required_key = 'module_dockercart_oneclickcheckout_field_' . $field . '_required';
            
            // For country, check country_id instead
            if ($field === 'country') {
                if ($this->config->get($required_key) && empty($this->request->post['country_id'])) {
                    $json['error']['country_id'] = sprintf($this->language->get('error_required'), $this->language->get('entry_' . $field));
                }
            } else {
                if ($this->config->get($required_key) && empty($this->request->post[$field])) {
                    $json['error'][$field] = sprintf($this->language->get('error_required'), $this->language->get('entry_' . $field));
                }
            }
        }
        
        // Validate email format
        if (!empty($this->request->post['email'])) {
            if (!$this->validateEmail($this->request->post['email'])) {
                $json['error']['email'] = $this->language->get('error_email');
            }
        }
        
        // Validate telephone format
        if (!empty($this->request->post['telephone'])) {
            $phone_validation = $this->validateTelephone($this->request->post['telephone']);
            if (!$phone_validation['valid']) {
                $json['error']['telephone'] = $this->language->get('error_telephone');
            } else {
                // Normalize the phone number for storage
                $this->request->post['telephone'] = $phone_validation['normalized'];
            }
        }
        
        // Validate captcha if enabled
        if ($this->config->get('module_dockercart_oneclickcheckout_use_captcha') && $this->config->get('config_captcha')) {
            $captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');
            
            if ($captcha) {
                $json['error']['captcha'] = $captcha;
            }
        }
        
        // Validate product_id
        if (empty($this->request->post['product_id'])) {
            $json['error']['product'] = $this->language->get('error_product');
        }
        
        if (!isset($json['error'])) {
            // Load product model
            $this->load->model('catalog/product');
            
            $product_id = (int)$this->request->post['product_id'];
            $product_info = $this->model_catalog_product->getProduct($product_id);
            
            if (!$product_info) {
                $json['error']['product'] = $this->language->get('error_product');
            } else {
                // Create order
                $order_id = $this->createOrder($product_info);
                
                if ($order_id) {
                    // Multilingual success message from language files (may contain HTML <br> for line breaks)
                    $json['success'] = $this->language->get('text_oneclick_success');
                    $json['order_id'] = $order_id;
                } else {
                    $json['error']['general'] = $this->language->get('error_order_create');
                }
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Create order from 1-click checkout
     */
    private function createOrder($product_info) {
        $this->load->model('checkout/order');
        
        // Prepare order data
        $order_data = array();
        
        // Store info
        $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
        $order_data['store_id'] = $this->config->get('config_store_id');
        $order_data['store_name'] = $this->config->get('config_name');
        $order_data['store_url'] = $this->config->get('config_url');
        
        // Customer info
        if ($this->customer->isLogged()) {
            $order_data['customer_id'] = $this->customer->getId();
            $order_data['customer_group_id'] = $this->customer->getGroupId();
        } else {
            $order_data['customer_id'] = 0; // Guest customer
            $order_data['customer_group_id'] = $this->config->get('config_customer_group_id');
        }
        
        $order_data['firstname'] = isset($this->request->post['firstname']) ? $this->request->post['firstname'] : '';
        $order_data['lastname'] = isset($this->request->post['lastname']) ? $this->request->post['lastname'] : '';
        $order_data['email'] = isset($this->request->post['email']) ? $this->request->post['email'] : '';
        $order_data['telephone'] = isset($this->request->post['telephone']) ? $this->request->post['telephone'] : '';
        $order_data['custom_field'] = array();
        
        // Payment address
        $order_data['payment_firstname'] = isset($this->request->post['firstname']) ? $this->request->post['firstname'] : '';
        $order_data['payment_lastname'] = isset($this->request->post['lastname']) ? $this->request->post['lastname'] : '';
        $order_data['payment_company'] = '';
        $order_data['payment_address_1'] = isset($this->request->post['address']) ? $this->request->post['address'] : '';
        $order_data['payment_address_2'] = '';
        $order_data['payment_city'] = isset($this->request->post['city']) ? $this->request->post['city'] : '';
        $order_data['payment_postcode'] = isset($this->request->post['postcode']) ? $this->request->post['postcode'] : '';
        
        // Get country data if provided
        if (!empty($this->request->post['country_id'])) {
            $this->load->model('localisation/country');
            $country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);
            
            if ($country_info) {
                $order_data['payment_country'] = $country_info['name'];
                $order_data['payment_country_id'] = $country_info['country_id'];
                $order_data['payment_address_format'] = $country_info['address_format'];
            } else {
                $order_data['payment_country'] = '';
                $order_data['payment_country_id'] = 0;
                $order_data['payment_address_format'] = '';
            }
        } else {
            $order_data['payment_country'] = '';
            $order_data['payment_country_id'] = 0;
            $order_data['payment_address_format'] = '';
        }
        
        $order_data['payment_zone'] = '';
        $order_data['payment_zone_id'] = 0;
        $order_data['payment_custom_field'] = array();
        $order_data['payment_method'] = '1-Click Checkout';
        $order_data['payment_code'] = 'oneclickcheckout';
        
        // Shipping address (same as payment)
        $order_data['shipping_firstname'] = $order_data['payment_firstname'];
        $order_data['shipping_lastname'] = $order_data['payment_lastname'];
        $order_data['shipping_company'] = '';
        $order_data['shipping_address_1'] = $order_data['payment_address_1'];
        $order_data['shipping_address_2'] = '';
        $order_data['shipping_city'] = $order_data['payment_city'];
        $order_data['shipping_postcode'] = $order_data['payment_postcode'];
        $order_data['shipping_country'] = $order_data['payment_country'];
        $order_data['shipping_country_id'] = $order_data['payment_country_id'];
        $order_data['shipping_zone'] = '';
        $order_data['shipping_zone_id'] = 0;
        $order_data['shipping_address_format'] = $order_data['payment_address_format'];
        $order_data['shipping_custom_field'] = array();
        $order_data['shipping_method'] = '1-Click Checkout';
        $order_data['shipping_code'] = 'oneclickcheckout';
        
        // Products
        $order_data['products'] = array();
        
        $order_data['products'][] = array(
            'product_id' => $product_info['product_id'],
            'name'       => $product_info['name'],
            'model'      => $product_info['model'],
            'option'     => array(),
            'download'   => array(),
            'quantity'   => 1,
            'subtract'   => $product_info['subtract'],
            'price'      => $product_info['price'],
            'total'      => $product_info['price'],
            'tax'        => $this->tax->getTax($product_info['price'], $product_info['tax_class_id']),
            'reward'     => $product_info['reward']
        );
        
        // Totals
        $order_data['totals'] = array();
        
        $total = $product_info['price'];
        
        $order_data['totals'][] = array(
            'code'       => 'sub_total',
            'title'      => 'Sub-Total',
            'value'      => $total,
            'sort_order' => 1
        );
        
        $order_data['totals'][] = array(
            'code'       => 'total',
            'title'      => 'Total',
            'value'      => $total,
            'sort_order' => 9
        );
        
        $order_data['total'] = $total;
        
        // Other info
        $order_data['comment'] = isset($this->request->post['comment']) ? $this->request->post['comment'] : '';
        $order_data['affiliate_id'] = 0;
        $order_data['commission'] = 0;
        $order_data['marketing_id'] = 0;
        $order_data['tracking'] = '';
        $order_data['language_id'] = $this->config->get('config_language_id');
        $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
        $order_data['currency_code'] = $this->session->data['currency'];
        $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
        $order_data['ip'] = $this->request->server['REMOTE_ADDR'];
        
        if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
            $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
        } else {
            $order_data['forwarded_ip'] = '';
        }
        
        if (isset($this->request->server['HTTP_USER_AGENT'])) {
            $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
        } else {
            $order_data['user_agent'] = '';
        }
        
        if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
            $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
        } else {
            $order_data['accept_language'] = '';
        }
        
        // Add order
        $order_id = $this->model_checkout_order->addOrder($order_data);
        
        // Set order status
        $order_status_id = $this->config->get('module_dockercart_oneclickcheckout_order_status_id');
        if (!$order_status_id) {
            $order_status_id = $this->config->get('config_order_status_id');
        }
        
        $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '1-Click Checkout Order', true);
        
        return $order_id;
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email address to validate
     * @return bool True if valid, false otherwise
     */
    private function validateEmail($email) {
        if (empty($email)) {
            return false;
        }
        
        // Basic format check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Additional checks
        // Check for valid domain
        $parts = explode('@', $email);
        if (count($parts) != 2) {
            return false;
        }
        
        list($local, $domain) = $parts;
        
        // Local part length check (max 64 characters)
        if (strlen($local) > 64 || strlen($local) < 1) {
            return false;
        }
        
        // Domain length check (max 255 characters)
        if (strlen($domain) > 255 || strlen($domain) < 3) {
            return false;
        }
        
        // Check if domain has at least one dot
        if (strpos($domain, '.') === false) {
            return false;
        }
        
        // Check for consecutive dots
        if (strpos($email, '..') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate and normalize telephone number
     * Accepts various formats:
     * - +7 (999) 123-45-67
     * - 8 999 123 45 67
     * - +380991234567
     * - (999) 123-45-67
     * - 9991234567
     * 
     * @param string $telephone Telephone number to validate
     * @return array ['valid' => bool, 'normalized' => string]
     */
    private function validateTelephone($telephone) {
        if (empty($telephone)) {
            return ['valid' => false, 'normalized' => ''];
        }
        
        // Remove all non-digit characters except plus sign at the beginning
        $cleaned = preg_replace('/[^0-9+]/', '', $telephone);
        
        // If starts with +, keep it, otherwise remove all plus signs
        if (strpos($cleaned, '+') === 0) {
            $cleaned = '+' . preg_replace('/[^0-9]/', '', substr($cleaned, 1));
        } else {
            $cleaned = preg_replace('/[^0-9]/', '', $cleaned);
        }
        
        // Get configuration for min/max length
        $min_length = 7;  // Minimum digits (local number)
        $max_length = 15; // Maximum digits per ITU-T E.164
        
        // Count digits only (without plus)
        $digits_only = preg_replace('/[^0-9]/', '', $cleaned);
        $digit_count = strlen($digits_only);
        
        // Validate length
        if ($digit_count < $min_length || $digit_count > $max_length) {
            return ['valid' => false, 'normalized' => ''];
        }
        
        // Normalize common formats
        // Replace leading 8 with +7 for Russian numbers
        if (strlen($digits_only) === 11 && $digits_only[0] === '8') {
            $normalized = '+7' . substr($digits_only, 1);
        } elseif (strpos($cleaned, '+') === 0) {
            $normalized = $cleaned;
        } else {
            // Add + if number looks international (10+ digits)
            if ($digit_count >= 10) {
                $normalized = '+' . $digits_only;
            } else {
                $normalized = $digits_only;
            }
        }
        
        return ['valid' => true, 'normalized' => $normalized];
    }

    /**
     * Проверка лицензии (блокирует работу модуля если нет валидной лицензии)
     */
    private function checkLicense() {
        $license_key = $this->config->get('module_dockercart_oneclickcheckout_license_key');

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
            $result = $license->verify($license_key, 'dockercart_oneclickcheckout');

            return $result['valid'];
        } catch (Exception $e) {
            return false;
        }
    }
}
