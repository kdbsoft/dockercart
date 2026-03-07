<?php
/**
 * DockerCart Checkout — Catalog Controller
 *
 * Premium One-Page Checkout Module for DockerCart 3.0.3.8+
 * Main checkout page controller with AJAX handlers
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 */

class ControllerCheckoutDockercartCheckout extends Controller {
    private $logger;
    
    // Configuration constants
    const LOCALHOST_DOMAINS = array('localhost', '127.0.0.1', '::1');
    const JOURNAL_THEME_KEYWORD = 'journal';
    const JOURNAL3_TEMPLATE_PATH = 'journal3/';
    const IMAGE_THUMB_WIDTH = 64;
    const IMAGE_THUMB_HEIGHT = 64;
    const IMAGE_LOGO_WIDTH = 200;
    const IMAGE_LOGO_HEIGHT = 60;
    const VALUE_DISPLAY_MAX_LENGTH = 20;
    const DEFAULT_THEME = 'light';

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'checkout');
    }

    /**
     * Main checkout page
     */
    public function index() {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_checkout_status')) {
            $this->response->redirect($this->url->link('checkout/checkout'));
            return;
        }

        // Verify license on production
        if (!$this->verifyLicense()) {
            $this->response->redirect($this->url->link('checkout/checkout'));
            return;
        }

        // Check cart has products
        if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        // Check stock
        if (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        // Check minimum quantity requirements
        $products = $this->cart->getProducts();
        foreach ($products as $product) {
            $product_total = 0;
            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }
            if ($product['minimum'] > $product_total) {
                $this->response->redirect($this->url->link('checkout/cart'));
                return;
            }
        }

        $this->load->language('checkout/dockercart_checkout');

        $this->document->setTitle($this->language->get('heading_title'));

        // Custom CSS/JS from settings
        $custom_css = $this->config->get('module_dockercart_checkout_custom_css');
        if (!empty($custom_css)) {
            $data['custom_css'] = $custom_css;
        } else {
            $data['custom_css'] = '';
        }

        $custom_js = $this->config->get('module_dockercart_checkout_custom_js');
        if (!empty($custom_js)) {
            $data['custom_js'] = $custom_js;
        } else {
            $data['custom_js'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('checkout/dockercart_checkout')
        );

        // Settings
        $data['logged'] = $this->customer->isLogged();
        $data['shipping_required'] = $this->cart->hasShipping();
        $data['show_progress'] = $this->config->get('module_dockercart_checkout_show_progress');
        $data['theme'] = $this->config->get('module_dockercart_checkout_theme') ?: self::DEFAULT_THEME;
        $data['geo_detect'] = $this->config->get('module_dockercart_checkout_geo_detect');
        $data['guest_create_account'] = $this->config->get('module_dockercart_checkout_guest_create_account');
        $data['show_company'] = $this->config->get('module_dockercart_checkout_show_company');
        $data['show_tax_id'] = $this->config->get('module_dockercart_checkout_show_tax_id');
        $data['require_telephone'] = $this->config->get('module_dockercart_checkout_require_telephone');
        $data['require_postcode'] = $this->config->get('module_dockercart_checkout_require_postcode');
        $data['require_address2'] = $this->config->get('module_dockercart_checkout_require_address2');
        $data['require_company'] = $this->config->get('module_dockercart_checkout_require_company');
        $data['journal3_compat'] = $this->config->get('module_dockercart_checkout_journal3_compat');

        // reCAPTCHA
        $data['recaptcha_enabled'] = $this->config->get('module_dockercart_checkout_recaptcha_enabled');
        $data['recaptcha_site_key'] = $this->config->get('module_dockercart_checkout_recaptcha_site_key');

        // Load and process checkout blocks
        $blocksData = $this->config->get('module_dockercart_checkout_blocks');
        $data['blocks'] = $this->processBlocksForDisplay($this->decodeBlocksData($blocksData));
        
        // Sort blocks by sort_order
        usort($data['blocks'], function($a, $b) {
            return ($a['sort_order'] ?? 0) - ($b['sort_order'] ?? 0);
        });

        // AJAX URLs
        $data['ajax_cart'] = $this->url->link('checkout/dockercart_checkout/cart');
        $data['ajax_customer'] = $this->url->link('checkout/dockercart_checkout/customer');
        $data['ajax_shipping_address'] = $this->url->link('checkout/dockercart_checkout/shipping_address');
        $data['ajax_payment_address'] = $this->url->link('checkout/dockercart_checkout/payment_address');
        $data['ajax_shipping_method'] = $this->url->link('checkout/dockercart_checkout/shipping_method');
        $data['ajax_payment_method'] = $this->url->link('checkout/dockercart_checkout/payment_method');
        $data['ajax_payment_extension'] = $this->url->link('checkout/dockercart_checkout/payment_extension');
        $data['ajax_coupon'] = $this->url->link('checkout/dockercart_checkout/coupon');
        $data['ajax_voucher'] = $this->url->link('checkout/dockercart_checkout/voucher');
        $data['ajax_reward'] = $this->url->link('checkout/dockercart_checkout/reward');
        $data['ajax_confirm'] = $this->url->link('checkout/dockercart_checkout/confirm');
        $data['ajax_country'] = $this->url->link('checkout/checkout/country');
        $data['ajax_update_cart'] = $this->url->link('checkout/dockercart_checkout/updateCart');

        // Countries
        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getCountries();

        // Default country/zone
        if (isset($this->session->data['shipping_address']['country_id'])) {
            $data['country_id'] = $this->session->data['shipping_address']['country_id'];
        } else {
            $data['country_id'] = $this->config->get('config_country_id');
        }

        if (isset($this->session->data['shipping_address']['zone_id'])) {
            $data['zone_id'] = $this->session->data['shipping_address']['zone_id'];
        } else {
            $data['zone_id'] = $this->config->get('config_zone_id');
        }

        // Default payment country/zone (if previously saved in session)
        if (isset($this->session->data['payment_address']['country_id'])) {
            $data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
        } else {
            $data['payment_country_id'] = $data['country_id'];
        }

        if (isset($this->session->data['payment_address']['zone_id'])) {
            $data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
        } else {
            $data['payment_zone_id'] = '';
        }

        // Customer groups
        $this->load->model('account/customer_group');
        $data['customer_groups'] = array();

        if (is_array($this->config->get('config_customer_group_display'))) {
            $customer_groups = $this->model_account_customer_group->getCustomerGroups();

            foreach ($customer_groups as $customer_group) {
                if (in_array($customer_group['customer_group_id'], $this->config->get('config_customer_group_display'))) {
                    $data['customer_groups'][] = $customer_group;
                }
            }
        }

        $data['customer_group_id'] = $this->config->get('config_customer_group_id');

        // Custom fields
        $this->load->model('account/custom_field');
        $data['custom_fields'] = $this->model_account_custom_field->getCustomFields();

        // Store information
        $data['store_name'] = $this->config->get('config_name');
        $data['store_email'] = $this->config->get('config_email');
        $data['store_telephone'] = $this->config->get('config_telephone');
        $data['store_address'] = nl2br($this->config->get('config_address'));

        // Terms & Conditions
        $this->load->model('catalog/information');
        $data['agree_text'] = '';
        $data['agree_id'] = $this->config->get('config_checkout_id');
        
        if ($data['agree_id']) {
            $information_info = $this->model_catalog_information->getInformation($data['agree_id']);
            if ($information_info) {
                $data['agree_text'] = $information_info['title'];
                $data['agree_link'] = $this->url->link('information/information/agree', 'information_id=' . $data['agree_id']);
            }
        }

        // Pre-fill customer data if logged in
        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $this->load->model('account/address');

            $data['firstname'] = $this->customer->getFirstName();
            $data['lastname'] = $this->customer->getLastName();
            $data['email'] = $this->customer->getEmail();
            $data['telephone'] = $this->customer->getTelephone();

            $address_id = $this->customer->getAddressId();

            // Always prefer customer's default address from database on fresh page load
            // Session may contain stale data from previous order
            $active_shipping = null;
            if ($address_id) {
                $active_shipping = $this->model_account_address->getAddress($address_id);
            }

            if ($active_shipping) {
                $data['address_1'] = $active_shipping['address_1'] ?? '';
                $data['address_2'] = $active_shipping['address_2'] ?? '';
                $data['city'] = $active_shipping['city'] ?? '';
                $data['postcode'] = $active_shipping['postcode'] ?? '';
                $data['country_id'] = $active_shipping['country_id'] ?? $data['country_id'];
                $data['zone_id'] = $active_shipping['zone_id'] ?? $data['zone_id'];
                $data['company'] = $active_shipping['company'] ?? '';

                // Initialize shipping address in session for logged-in users
                if (!isset($this->session->data['shipping_address'])) {
                    $this->session->data['shipping_address'] = $active_shipping;
                }

                // Also initialize payment address to same as shipping by default
                if (!isset($this->session->data['payment_address'])) {
                    $this->session->data['payment_address'] = $active_shipping;
                }
            }

            // Customer addresses
            $data['addresses'] = $this->model_account_address->getAddresses();
            $data['default_address_id'] = (int)$this->customer->getAddressId();
            $data['shipping_address_id'] = isset($this->session->data['shipping_address']['address_id']) ? (int)$this->session->data['shipping_address']['address_id'] : (int)$address_id;
            $data['payment_address_id'] = isset($this->session->data['payment_address']['address_id']) ? (int)$this->session->data['payment_address']['address_id'] : (int)$address_id;
        } else {
            $data['firstname'] = '';
            $data['lastname'] = '';
            $data['email'] = '';
            $data['telephone'] = '';
            $data['address_1'] = '';
            $data['address_2'] = '';
            $data['city'] = '';
            $data['postcode'] = '';
            $data['company'] = '';
            $data['addresses'] = array();
            $data['default_address_id'] = 0;
            $data['shipping_address_id'] = 0;
            $data['payment_address_id'] = 0;
        }

        // Get cart contents for initial display
        $data['cart_products'] = $this->getCartProducts();
        $data['totals'] = $this->getCartTotals();

        // Journal 3 detection
        $data['is_journal3'] = $this->isJournal3Theme();

        // Layout parts
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['text_footer'] = $this->load->controller('common/content_bottom');
        
        // Premium template: Add all language variables
        $data['text_checkout_account'] = $this->language->get('text_your_details');
        $data['text_checkout_shipping'] = $this->language->get('text_shipping_address');
        $data['text_checkout_payment'] = $this->language->get('text_payment_method');
        $data['text_step'] = $this->language->get('text_step') ?: 'Step';
        $data['text_optional'] = $this->language->get('text_optional') ?: 'Optional';
        $data['text_telephone_hint'] = $this->language->get('text_telephone_hint') ?: '(min. 7 digits, e.g. +1 (555) 123-4567)';
        $data['text_select'] = $this->language->get('text_select') ?: '-- Please Select --';
        $data['text_or'] = 'or pay with card';
        $data['text_credit_card'] = 'Credit Card';
        $data['entry_cc_number'] = 'Card Number';
        $data['entry_cc_expire'] = 'Expiry Date';
        $data['text_terms'] = $data['agree_text'] ?: 'Terms & Conditions';
        $data['text_order_summary'] = $this->language->get('text_cart_summary') ?: 'Order Summary';
        $data['text_coupon'] = $this->language->get('entry_coupon') ?: 'Enter promo code';
        $data['button_coupon'] = $this->language->get('button_apply_coupon') ?: 'Apply';
        $data['text_sub_total'] = 'Subtotal';
        $data['text_tax'] = 'Tax';
        $data['text_show_summary'] = 'Show order summary';
        $data['text_hide_summary'] = 'Hide order summary';
        // Avoid duplicating the agree error: the confirm() endpoint returns a structured field error.
        // Leave this empty so frontend doesn't show a second message.
        $data['text_agree_required'] = '';
        $data['text_secure'] = $this->language->get('text_secure') ?: 'Secure SSL Encryption';
        $data['text_return'] = $this->language->get('text_return') ?: '30-Day Returns';
        $data['text_payment_secure'] = $this->language->get('text_payment_secure') ?: 'Safe Payment';
        $data['text_checkout_notice'] = $this->language->get('text_payment_encrypted') ?: 'Your payment information is secure and encrypted';

        // Buttons / inline controls used by JS
        $data['button_edit'] = $this->language->get('button_edit') ?: 'Edit';
        $data['button_cancel'] = $this->language->get('button_cancel') ?: 'Cancel';
        $data['button_save'] = $this->language->get('button_save') ?: 'Save';
        $data['text_save_to_account'] = $this->language->get('text_save_to_account') ?: 'Save to account';
        $data['text_empty_cart'] = $this->language->get('text_empty_cart');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_processing'] = $this->language->get('text_processing') ?: 'Processing...';
        $data['text_agree'] = $this->language->get('text_agree') ?: 'I have read and agree to the';
        $data['text_saved_addresses'] = $this->language->get('text_saved_addresses') ?: 'Saved addresses';
        $data['text_select_address'] = $this->language->get('text_select_address');
        $data['text_new_address'] = $this->language->get('text_new_address');
        // Validation / inline messages used by JS
        $data['text_email_required_create_account'] = $this->language->get('text_email_required_create_account') ?: 'Email is required to create an account';
        $data['text_email_invalid'] = $this->language->get('text_email_invalid') ?: 'Email must be a valid email address';
        $data['text_password_required_create_account'] = $this->language->get('text_password_required_create_account') ?: 'Password is required to create an account';
        $data['text_password_minlength'] = $this->language->get('text_password_minlength') ?: 'Password must be at least %s characters';
        $data['text_passwords_do_not_match'] = $this->language->get('text_passwords_do_not_match') ?: 'Passwords do not match';
        $data['text_required_field'] = $this->language->get('text_required_field') ?: '%s is required';
        $data['text_field_invalid_email'] = $this->language->get('text_field_invalid_email') ?: '%s must be a valid email';
        $data['error_agree'] = $this->language->get('error_agree');
        $data['text_default_address'] = $this->language->get('text_default_address') ?: 'Default';
        
        // Logo and store info (use original image so template can size via CSS)
        $this->load->model('tool/image');
        $data['logo'] = '';
        
        $data['name'] = $this->config->get('config_name');
        $data['home'] = $this->url->link('common/home');
        $data['shopping_cart'] = $this->url->link('checkout/cart');
        

        if ($this->request->server['HTTPS']) {
            $server = $this->config->get('config_ssl');
        } else {
            $server = $this->config->get('config_url');
        }

        // Prefer theme light logo if provided, otherwise fall back to main config logo
        $logo_light = (string)$this->config->get('dockercart_theme_logo_light');
        if ($logo_light && is_file(DIR_IMAGE . $logo_light)) {
            $data['logo'] = $server . 'image/' . $logo_light;
        } elseif (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
            $data['logo'] = $server . 'image/' . $this->config->get('config_logo');
        } else {
            $data['logo'] = '';
        }

        $data['favicon_links'] = array();

        $favicon_master = (string)$this->config->get('dockercart_theme_favicon_master');

        if ($favicon_master === '') {
            // Backward compatibility for previously stored value in theme settings.
            $favicon_master = (string)$this->config->get('theme_dockercart_favicon_master');
        }
        $favicon_source = '';

        if ($favicon_master && is_file(DIR_IMAGE . $favicon_master)) {
            $favicon_source = $favicon_master;
        } else {
            $config_icon = (string)$this->config->get('config_icon');

            if ($config_icon && is_file(DIR_IMAGE . $config_icon)) {
                $favicon_source = $config_icon;
            }
        }

        if ($favicon_source) {
            $favicon_sizes = array(16, 32, 48, 64, 96, 128);

            foreach ($favicon_sizes as $size) {
                $favicon_href = $this->model_tool_image->resize($favicon_source, $size, $size, 'cover');

                if ($favicon_href) {
                    $data['favicon_links'][] = array(
                        'rel' => 'icon',
                        'type' => 'image/png',
                        'sizes' => $size . 'x' . $size,
                        'href' => $favicon_href
                    );
                }
            }

            $apple_touch = $this->model_tool_image->resize($favicon_source, 120, 120, 'cover');

            if ($apple_touch) {
                $data['favicon_links'][] = array(
                    'rel' => 'apple-touch-icon',
                    'type' => 'image/png',
                    'sizes' => '120x120',
                    'href' => $apple_touch
                );
            }
        } elseif (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}


        // Expose analytics snippets similar to common/header
        $this->load->model('setting/extension');
        $data['analytics'] = array();

        $analytics = $this->model_setting_extension->getExtensions('analytics');

        foreach ($analytics as $analytic) {
            if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
                $data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
            }
        }

        // Also provide legacy google_analytics config as a guard for templates
        $ga = $this->config->get('config_google_analytics');
        $data['google_analytics'] = $ga !== null ? html_entity_decode($ga, ENT_QUOTES, 'UTF-8') : '';

        $data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('dockercart_checkout');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

        $this->response->setOutput($this->load->view('checkout/dockercart_checkout', $data));
    }

    /**
     * AJAX: Get cart contents
     */
    public function cart() {
        $json = array(
            'products' => $this->getCartProducts(),
            'vouchers' => $this->getCartVouchers(),
            'totals' => $this->getCartTotals(),
            'shipping_required' => $this->cart->hasShipping()
        );

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Update cart item quantity
     */
    public function updateCart() {
        $data = $this->getJsonInput();
        $json = array();

        if (isset($data['cart_id']) && isset($data['quantity'])) {
            $quantity = (int)$data['quantity'];

            if ($quantity > 0) {
                $this->cart->update($data['cart_id'], $quantity);
            } else {
                $this->cart->remove($data['cart_id']);
            }

            $json['success'] = true;
            $json['products'] = $this->getCartProducts();
            $json['totals'] = $this->getCartTotals();
        } else {
            $json['error'] = 'Invalid request';
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Save customer information (for guest)
     */
    public function customer() {
        $this->load->language('checkout/dockercart_checkout');
        
        $data = $this->getJsonInput();
        $json = array();

        // Validate customer data - only enforce fields marked required in admin
        if ($this->isFieldRequired('firstname')) {
            if (empty($data['firstname']) || utf8_strlen($data['firstname']) < 1 || utf8_strlen($data['firstname']) > 32) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }
        }

        if ($this->isFieldRequired('lastname')) {
            if (empty($data['lastname']) || utf8_strlen($data['lastname']) < 1 || utf8_strlen($data['lastname']) > 32) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }
        }

        // Validate email only if configured as required in admin blocks (or legacy behavior)
        if ($this->isFieldRequired('email')) {
            if (empty($data['email']) || utf8_strlen($data['email']) > 96 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $json['error']['email'] = $this->language->get('error_email');
            }
        }

        // Check if email already registered (for guest checkout)
        if (!$this->customer->isLogged()) {
            $this->load->model('account/customer');
            if ($this->model_account_customer->getTotalCustomersByEmail($data['email'])) {
                $json['error']['email'] = $this->language->get('error_email_exists');
            }
        }

        // Telephone validation depends on admin blocks / legacy config
        if ($this->isFieldRequired('telephone')) {
            if (empty($data['telephone']) || utf8_strlen($data['telephone']) < 3 || utf8_strlen($data['telephone']) > 32) {
                $json['error']['telephone'] = $this->language->get('error_telephone');
            }
        }

        if (!isset($json['error'])) {
            // If customer is logged in, store temporary customer overrides in session
            // so the current order uses the edited values even if we don't persist
            // them to the account. If the front-end requests to save to account,
            // persist via model_account_customer->editCustomer().
            if ($this->customer->isLogged()) {
                // Optionally persist to account if requested - check validation FIRST
                if (!empty($data['save_to_account'])) {
                    $this->load->model('account/customer');

                    // Prevent email collision with other accounts
                    // Only check if email was actually changed
                    if ($data['email'] !== $this->customer->getEmail()) {
                        $existing = $this->model_account_customer->getCustomerByEmail($data['email']);
                        if ($existing) {
                            $json['error']['email'] = $this->language->get('error_email_exists');
                            // Don't save temporary override if email validation failed
                            $this->sendJsonResponse($json);
                            return;
                        }
                    }

                    // Email is unique (or unchanged), so persist the changes
                    try {
                        $this->model_account_customer->editCustomer($this->customer->getId(), array(
                            'firstname' => $data['firstname'],
                            'lastname'  => $data['lastname'],
                            'email'     => $data['email'],
                            'telephone' => $data['telephone'] ?? ''
                        ));
                        $json['success'] = true;
                        $json['message'] = 'Account updated successfully';
                    } catch (Exception $e) {
                        $json['error']['general'] = 'Failed to update account: ' . $e->getMessage();
                        $this->sendJsonResponse($json);
                        return;
                    }
                } else {
                    // Not persisting to account, just use temporary session override
                    $json['success'] = true;
                }

                // Store temporary overrides regardless of persistence
                // This ensures the order uses the latest submitted values
                $this->session->data['dockercart_temp_customer'] = array(
                    'firstname' => $data['firstname'],
                    'lastname'  => $data['lastname'],
                    'email'     => $data['email'],
                    'telephone' => $data['telephone'] ?? ''
                );
            } else {
            $this->session->data['guest'] = array(
                'customer_group_id' => $this->config->get('config_customer_group_id'),
                'firstname'         => $data['firstname'],
                'lastname'          => $data['lastname'],
                'email'             => $data['email'],
                'telephone'         => $data['telephone'] ?? '',
                'fax'               => '',
                'custom_field'      => isset($data['custom_field']) ? $data['custom_field'] : array()
            );

            // If guest wants to create account
            if (!empty($data['create_account']) && !empty($data['password'])) {
                $this->session->data['guest']['create_account'] = true;
                $this->session->data['guest']['password'] = $data['password'];
            }

            $this->session->data['account'] = 'guest';

            $json['success'] = true;
            }
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Save/set shipping address
     */
    public function shipping_address() {
        $json = array();

        $this->load->language('checkout/dockercart_checkout');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Optional debug logging (centralized)
        $this->logger->debug('shipping_address called with payload: ' . print_r($data, true));

        // Use existing address
        if (!empty($data['address_id']) && $this->customer->isLogged()) {
            $this->load->model('account/address');
            $address_info = $this->model_account_address->getAddress($data['address_id']);

            if ($address_info) {
                // Ensure address_id is always present in the session address
                $address_info['address_id'] = (int)$data['address_id'];
                $this->session->data['shipping_address'] = $address_info;
                $json['address_id'] = (int)$data['address_id'];

                $this->logger->debug('Selected existing address ID: ' . (int)$data['address_id']);
                $this->logger->debug('Loaded address from DB: ' . print_r($address_info, true));

                // If the customer has no default address yet, promote the selected one
                if (!$this->customer->getAddressId()) {
                    $this->load->model('account/customer');
                    $this->model_account_customer->editAddressId($this->customer->getId(), (int)$data['address_id']);
                }

                // Expose full address book so frontend dropdowns stay in sync
                $json['addresses'] = $this->model_account_address->getAddresses();
                $json['success'] = true;
            } else {
                $json['error'] = $this->language->get('error_address');
            }
        } else {
            // Validate new address — only validate fields that admin marked as required
            if ($this->isFieldRequired('address_1')) {
                if (empty($data['address_1']) || utf8_strlen($data['address_1']) < 3) {
                    $json['error']['address_1'] = $this->language->get('error_address_1');
                }
            }

            if ($this->isFieldRequired('city')) {
                if (empty($data['city']) || utf8_strlen($data['city']) < 2) {
                    $json['error']['city'] = $this->language->get('error_city');
                }
            }

            $this->load->model('localisation/country');
            $country_info = $this->model_localisation_country->getCountry($data['country_id'] ?? 0);

            if ($country_info && $country_info['postcode_required'] && (empty($data['postcode']) || utf8_strlen($data['postcode']) < 2)) {
                // Country-specific postcode requirement should always be enforced
                $json['error']['postcode'] = $this->language->get('error_postcode');
            } elseif ($this->isFieldRequired('postcode')) {
                if (empty($data['postcode']) || utf8_strlen($data['postcode']) < 2) {
                    $json['error']['postcode'] = $this->language->get('error_postcode');
                }
            }

            if (empty($data['country_id'])) {
                $json['error']['country'] = $this->language->get('error_country');
            }

            if (empty($data['zone_id'])) {
                $json['error']['zone'] = $this->language->get('error_zone');
            }

            if (!isset($json['error'])) {
                $this->session->data['shipping_address'] = array(
                    'firstname'    => $data['firstname'],
                    'lastname'     => $data['lastname'],
                    'company'      => $data['company'] ?? '',
                    'address_1'    => $data['address_1'],
                    'address_2'    => $data['address_2'] ?? '',
                    'postcode'     => $data['postcode'] ?? '',
                    'city'         => $data['city'],
                    'zone_id'      => $data['zone_id'],
                    'zone'         => '',
                    'zone_code'    => '',
                    'country_id'   => $data['country_id'],
                    'country'      => '',
                    'iso_code_2'   => '',
                    'iso_code_3'   => '',
                    'address_format' => '',
                    'custom_field' => isset($data['custom_field']) ? $data['custom_field'] : array()
                );

                // Get country/zone names
                if ($country_info) {
                    $this->session->data['shipping_address']['country'] = $country_info['name'];
                    $this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
                    $this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
                    $this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
                }

                $this->load->model('localisation/zone');
                $zone_info = $this->model_localisation_zone->getZone($data['zone_id']);

                if ($zone_info) {
                    $this->session->data['shipping_address']['zone'] = $zone_info['name'];
                    $this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
                }

                $json['success'] = true;
                // Note: Address persistence for logged-in customers is now handled
                // in saveCustomerShippingAddress() during order confirmation,
                // not during interactive address updates. This prevents duplicate
                // address creation and ensures only final checkout address is saved.
            }
        }

        // Get shipping methods
        if (isset($json['success'])) {
            // Fetch methods and optionally write debug info
            $methods = $this->getShippingMethods();

            $this->logger->debug('session shipping_address after save: ' . print_r($this->session->data['shipping_address'], true));
            $this->logger->debug('computed shipping_methods: ' . print_r($methods, true));

            $json['shipping_methods'] = $methods;
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Save/set payment address
     */
    public function payment_address() {
        $json = array();

        $this->load->language('checkout/dockercart_checkout');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Same as shipping
        if (!empty($data['same_as_shipping']) && isset($this->session->data['shipping_address'])) {
            $this->session->data['payment_address'] = $this->session->data['shipping_address'];
            $json['success'] = true;
        }
        // Use existing address
        elseif (!empty($data['address_id']) && $this->customer->isLogged()) {
            $this->load->model('account/address');
            $address_info = $this->model_account_address->getAddress($data['address_id']);

            if ($address_info) {
                $this->session->data['payment_address'] = $address_info;
                $json['success'] = true;
            } else {
                $json['error'] = $this->language->get('error_address');
            }
        } else {
            // Validate new address — only validate fields that admin marked as required
            if ($this->isFieldRequired('payment_address_1') || $this->isFieldRequired('address_1')) {
                if (empty($data['address_1']) || utf8_strlen($data['address_1']) < 3) {
                    $json['error']['address_1'] = $this->language->get('error_address_1');
                }
            }

            if ($this->isFieldRequired('payment_city') || $this->isFieldRequired('city')) {
                if (empty($data['city']) || utf8_strlen($data['city']) < 2) {
                    $json['error']['city'] = $this->language->get('error_city');
                }
            }

            $this->load->model('localisation/country');
            $country_info = $this->model_localisation_country->getCountry($data['country_id'] ?? 0);

            if ($country_info && $country_info['postcode_required'] && (empty($data['postcode']) || utf8_strlen($data['postcode']) < 2)) {
                $json['error']['postcode'] = $this->language->get('error_postcode');
            }

            if (empty($data['country_id'])) {
                $json['error']['country'] = $this->language->get('error_country');
            }

            if (empty($data['zone_id'])) {
                $json['error']['zone'] = $this->language->get('error_zone');
            }

            if (!isset($json['error'])) {
                $this->session->data['payment_address'] = array(
                    'firstname'    => $data['firstname'],
                    'lastname'     => $data['lastname'],
                    'company'      => $data['company'] ?? '',
                    'address_1'    => $data['address_1'],
                    'address_2'    => $data['address_2'] ?? '',
                    'postcode'     => $data['postcode'] ?? '',
                    'city'         => $data['city'],
                    'zone_id'      => $data['zone_id'],
                    'zone'         => '',
                    'zone_code'    => '',
                    'country_id'   => $data['country_id'],
                    'country'      => '',
                    'iso_code_2'   => '',
                    'iso_code_3'   => '',
                    'address_format' => '',
                    'custom_field' => isset($data['custom_field']) ? $data['custom_field'] : array()
                );

                if ($country_info) {
                    $this->session->data['payment_address']['country'] = $country_info['name'];
                    $this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
                    $this->session->data['payment_address']['iso_code_3'] = $country_info['iso_code_3'];
                    $this->session->data['payment_address']['address_format'] = $country_info['address_format'];
                }

                $this->load->model('localisation/zone');
                $zone_info = $this->model_localisation_zone->getZone($data['zone_id']);

                if ($zone_info) {
                    $this->session->data['payment_address']['zone'] = $zone_info['name'];
                    $this->session->data['payment_address']['zone_code'] = $zone_info['code'];
                }

                $json['success'] = true;
                // If logged in and user requested to save this payment address to their account
                if ($this->customer->isLogged() && !empty($data['save_to_account'])) {
                    $this->load->model('account/address');
                    $address_post = array(
                        'firstname' => $data['firstname'],
                        'lastname'  => $data['lastname'],
                        'company'   => $data['company'] ?? '',
                        'address_1' => $data['address_1'],
                        'address_2' => $data['address_2'] ?? '',
                        'postcode'  => $data['postcode'] ?? '',
                        'city'      => $data['city'],
                        'country_id'=> $data['country_id'],
                        'zone_id'   => $data['zone_id'],
                        'custom_field' => isset($data['custom_field']) ? $data['custom_field'] : array()
                    );

                    $address_id = $this->model_account_address->addAddress($this->customer->getId(), $address_post);

                    if ($address_id) {
                        $this->session->data['payment_address'] = $this->model_account_address->getAddress($address_id);

                        if (!$this->customer->getAddressId()) {
                            $this->load->model('account/customer');
                            $this->model_account_customer->editAddressId($this->customer->getId(), $address_id);
                        }
                    }
                }
            }
        }

        // Get payment methods
        if (isset($json['success'])) {
            $json['payment_methods'] = $this->getPaymentMethods();
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Set shipping method
     */
    public function shipping_method() {
        $json = array();

        $this->load->language('checkout/dockercart_checkout');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (empty($data['shipping_method'])) {
            $json['error'] = $this->language->get('error_shipping_method');
        } else {
            $shipping = explode('.', $data['shipping_method']);

            if (!isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                $json['error'] = $this->language->get('error_shipping_method');
            }

            if (!isset($json['error'])) {
                $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                $json['success'] = true;
                $json['totals'] = $this->getCartTotals();
            }
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Set payment method (or get list if no method specified)
     */
    public function payment_method() {
        $json = array();

        $this->load->language('checkout/dockercart_checkout');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // If no payment_method provided, just return list of available methods
        if (empty($data['payment_method'])) {
            // Check if payment_address is set
            if (!isset($this->session->data['payment_address'])) {
                $json['error'] = $this->language->get('error_address');
                $this->sendJsonResponse($json);
                return;
            }

            // Get and return payment methods
            $json['payment_methods'] = $this->getPaymentMethods();
            $this->sendJsonResponse($json);
            return;
        }

        // Otherwise, set the selected payment method
        if (!isset($this->session->data['payment_methods'][$data['payment_method']])) {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!isset($json['error'])) {
            $this->session->data['payment_method'] = $this->session->data['payment_methods'][$data['payment_method']];

            // Save comment
            if (isset($data['comment'])) {
                $this->session->data['comment'] = strip_tags($data['comment']);
            }

            $json['success'] = true;
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Load payment extension HTML (form/instructions)
     */
    public function payment_extension() {
        $json = array();

        $this->load->language('checkout/dockercart_checkout');

        // Validate payment method is selected
        if (empty($this->session->data['payment_method'])) {
            $json['error'] = $this->language->get('error_payment_method');
            $this->sendJsonResponse($json);
            return;
        }

        $payment_method = $this->session->data['payment_method']['code'];
        $payment_extension = explode('.', $payment_method);
        $code = $payment_extension[0];

        // Load the payment controller to get form/instructions HTML
        $json['payment'] = $this->load->controller('extension/payment/' . $code);
        $json['success'] = true;

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Apply coupon
     */
    public function coupon() {
        $this->load->language('checkout/dockercart_checkout');
        
        $data = $this->getJsonInput();
        $json = array();

        $this->load->model('extension/total/coupon');

        if (empty($data['coupon'])) {
            // Remove coupon
            unset($this->session->data['coupon']);
            $json['success'] = $this->language->get('text_coupon_removed');
        } else {
            $coupon_info = $this->model_extension_total_coupon->getCoupon($data['coupon']);

            if ($coupon_info) {
                $this->session->data['coupon'] = $data['coupon'];
                $json['success'] = $this->language->get('text_coupon_applied');
            } else {
                $json['error'] = $this->language->get('error_coupon');
            }
        }

        $json['totals'] = $this->getCartTotals();

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Apply voucher
     */
    public function voucher() {
        $this->load->language('checkout/dockercart_checkout');
        
        $data = $this->getJsonInput();
        $json = array();

        $this->load->model('extension/total/voucher');

        if (empty($data['voucher'])) {
            unset($this->session->data['voucher']);
            $json['success'] = $this->language->get('text_voucher_removed');
        } else {
            $voucher_info = $this->model_extension_total_voucher->getVoucher($data['voucher']);

            if ($voucher_info) {
                $this->session->data['voucher'] = $data['voucher'];
                $json['success'] = $this->language->get('text_voucher_applied');
            } else {
                $json['error'] = $this->language->get('error_voucher');
            }
        }

        $json['totals'] = $this->getCartTotals();

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX: Apply reward points
     */
    public function reward() {
        $this->load->language('checkout/dockercart_checkout');
        
        $data = $this->getJsonInput();
        $json = array();

        $points = isset($data['reward']) ? abs((int)$data['reward']) : 0;

        if ($points == 0) {
            unset($this->session->data['reward']);
            $json['success'] = $this->language->get('text_reward_removed');
        } else {
            $available = $this->customer->getRewardPoints();
            $points_total = 0;

            foreach ($this->cart->getProducts() as $product) {
                if ($product['points']) {
                    $points_total += $product['points'];
                }
            }

            if ($points > $available) {
                $json['error'] = sprintf($this->language->get('error_reward_max'), $available);
            } elseif ($points > $points_total) {
                $json['error'] = sprintf($this->language->get('error_reward_max'), $points_total);
            } else {
                $this->session->data['reward'] = $points;
                $json['success'] = $this->language->get('text_reward_applied');
            }
        }

        $json['totals'] = $this->getCartTotals();

        $this->sendJsonResponse($json);
    }

    /**
     * Prepare order data from session for addOrder() method
     * Builds a complete order array with all required fields from session, cart, and config
     */
    private function prepareOrderData() {
        // Get payment and shipping addresses from session
        $payment_address = isset($this->session->data['payment_address']) ? $this->session->data['payment_address'] : array();
        $shipping_address = isset($this->session->data['shipping_address']) ? $this->session->data['shipping_address'] : array();
        
        // Get payment and shipping methods
        $payment_method = isset($this->session->data['payment_method']) ? $this->session->data['payment_method'] : array();
        $shipping_method = isset($this->session->data['shipping_method']) ? $this->session->data['shipping_method'] : array();
        
        // Get guest/customer data
        $guest = isset($this->session->data['guest']) ? $this->session->data['guest'] : array();
        
        // Get customer info (logged in or guest)
        $customer_id = $this->customer->isLogged() ? $this->customer->getId() : 0;
        $customer_group_id = $this->customer->isLogged() ? $this->customer->getGroupId() : $this->config->get('config_customer_group_id');
        
        // Load required models for address formatting and country/zone data
        $this->load->model('localisation/country');
        $this->load->model('localisation/zone');
        
        // Payment address
        $payment_country = $this->model_localisation_country->getCountry($payment_address['country_id']);
        $payment_zone = $this->model_localisation_zone->getZone($payment_address['zone_id']);
        
        // Shipping address (use payment if same)
        if (!empty($shipping_address)) {
            $shipping_country = $this->model_localisation_country->getCountry($shipping_address['country_id']);
            $shipping_zone = $this->model_localisation_zone->getZone($shipping_address['zone_id']);
        } else {
            // Use payment as shipping if not set
            $shipping_address = $payment_address;
            $shipping_country = $payment_country;
            $shipping_zone = $payment_zone;
        }
        
        // Derive canonical customer fields: prefer logged-in customer -> guest -> payment -> shipping
        $order_firstname = '';
        $order_lastname = '';
        $order_email = '';
        $order_telephone = '';

        if ($this->customer->isLogged()) {
            // If a temporary customer override exists (edited in checkout UI), prefer it
            if (isset($this->session->data['dockercart_temp_customer']) && is_array($this->session->data['dockercart_temp_customer'])) {
                $temp = $this->session->data['dockercart_temp_customer'];
                $order_firstname = $temp['firstname'] ?? $this->customer->getFirstName();
                $order_lastname = $temp['lastname'] ?? $this->customer->getLastName();
                $order_email = $temp['email'] ?? $this->customer->getEmail();
                $order_telephone = $temp['telephone'] ?? $this->customer->getTelephone();
            } else {
                $order_firstname = $this->customer->getFirstName();
                $order_lastname = $this->customer->getLastName();
                $order_email = $this->customer->getEmail();
                $order_telephone = $this->customer->getTelephone();
            }
        } else {
            // Check multiple session places where front-end might have saved data.
            $sources = array('guest', 'payment_address', 'shipping_address');

            foreach ($sources as $src) {
                if (!empty($this->session->data[$src]) && is_array($this->session->data[$src])) {
                    $src_data = $this->session->data[$src];

                    if (empty($order_firstname) && !empty($src_data['firstname'])) {
                        $order_firstname = $src_data['firstname'];
                    }

                    if (empty($order_lastname) && !empty($src_data['lastname'])) {
                        $order_lastname = $src_data['lastname'];
                    }

                    if (empty($order_email) && !empty($src_data['email'])) {
                        $order_email = $src_data['email'];
                    }

                    if (empty($order_telephone) && !empty($src_data['telephone'])) {
                        $order_telephone = $src_data['telephone'];
                    }
                }
            }

            // Final fallbacks: use payment/shipping address variables (already loaded above)
            if (empty($order_firstname)) {
                $order_firstname = !empty($payment_address['firstname']) ? $payment_address['firstname'] : (!empty($shipping_address['firstname']) ? $shipping_address['firstname'] : '');
            }

            if (empty($order_lastname)) {
                $order_lastname = !empty($payment_address['lastname']) ? $payment_address['lastname'] : (!empty($shipping_address['lastname']) ? $shipping_address['lastname'] : '');
            }

            if (empty($order_email)) {
                $order_email = !empty($payment_address['email']) ? $payment_address['email'] : (!empty($guest['email']) ? $guest['email'] : '');
            }

            if (empty($order_telephone)) {
                $order_telephone = !empty($payment_address['telephone']) ? $payment_address['telephone'] : (!empty($shipping_address['telephone']) ? $shipping_address['telephone'] : '');
            }
        }

        // If email is still empty, generate a fallback localhost email to prevent database errors
        if (empty($order_email)) {
            // Generate pseudo-unique email: localhost_<unix_timestamp>@localhost
            $order_email = 'localhost_' . time() . '@localhost';
        }

        // Prepare order array with all required fields
        $order_data = array(
            // Store information
            'invoice_prefix' => $this->config->get('config_invoice_prefix'),
            'store_id' => $this->config->get('config_store_id'),
            'store_name' => $this->config->get('config_name'),
            'store_url' => ($this->config->get('config_secure') ? $this->config->get('config_ssl') : $this->config->get('config_url')),
            
            // Customer information
            'customer_id' => $customer_id,
            'customer_group_id' => $customer_group_id,
            'firstname' => $order_firstname,
            'lastname' => $order_lastname,
            'email' => $order_email,
            'telephone' => $order_telephone,
            'custom_field' => array(),
            
            // Payment address
            'payment_firstname' => isset($payment_address['firstname']) ? $payment_address['firstname'] : '',
            'payment_lastname' => isset($payment_address['lastname']) ? $payment_address['lastname'] : '',
            'payment_company' => isset($payment_address['company']) ? $payment_address['company'] : '',
            'payment_address_1' => isset($payment_address['address_1']) ? $payment_address['address_1'] : '',
            'payment_address_2' => isset($payment_address['address_2']) ? $payment_address['address_2'] : '',
            'payment_city' => isset($payment_address['city']) ? $payment_address['city'] : '',
            'payment_postcode' => isset($payment_address['postcode']) ? $payment_address['postcode'] : '',
            'payment_country' => isset($payment_country['name']) ? $payment_country['name'] : '',
            'payment_country_id' => isset($payment_address['country_id']) ? $payment_address['country_id'] : 0,
            'payment_zone' => isset($payment_zone['name']) ? $payment_zone['name'] : '',
            'payment_zone_id' => isset($payment_address['zone_id']) ? $payment_address['zone_id'] : 0,
            'payment_address_format' => isset($payment_country['address_format']) ? $payment_country['address_format'] : '',
            'payment_custom_field' => array(),
            'payment_method' => isset($payment_method['title']) ? $payment_method['title'] : '',
            'payment_code' => isset($payment_method['code']) ? $payment_method['code'] : '',
            
            // Shipping address
            'shipping_firstname' => isset($shipping_address['firstname']) ? $shipping_address['firstname'] : '',
            'shipping_lastname' => isset($shipping_address['lastname']) ? $shipping_address['lastname'] : '',
            'shipping_company' => isset($shipping_address['company']) ? $shipping_address['company'] : '',
            'shipping_address_1' => isset($shipping_address['address_1']) ? $shipping_address['address_1'] : '',
            'shipping_address_2' => isset($shipping_address['address_2']) ? $shipping_address['address_2'] : '',
            'shipping_city' => isset($shipping_address['city']) ? $shipping_address['city'] : '',
            'shipping_postcode' => isset($shipping_address['postcode']) ? $shipping_address['postcode'] : '',
            'shipping_country' => isset($shipping_country['name']) ? $shipping_country['name'] : '',
            'shipping_country_id' => isset($shipping_address['country_id']) ? $shipping_address['country_id'] : 0,
            'shipping_zone' => isset($shipping_zone['name']) ? $shipping_zone['name'] : '',
            'shipping_zone_id' => isset($shipping_address['zone_id']) ? $shipping_address['zone_id'] : 0,
            'shipping_address_format' => isset($shipping_country['address_format']) ? $shipping_country['address_format'] : '',
            'shipping_custom_field' => array(),
            'shipping_method' => isset($shipping_method['title']) ? $shipping_method['title'] : '',
            'shipping_code' => isset($shipping_method['code']) ? $shipping_method['code'] : '',
            
            // Order details
            'comment' => isset($this->session->data['comment']) ? $this->session->data['comment'] : '',
            'total' => $this->cart->getTotal(),
            'affiliate_id' => 0,
            'commission' => 0,
            'marketing_id' => 0,
            'tracking' => '',
            'language_id' => $this->config->get('config_language_id'),
            'currency_id' => $this->config->get('config_currency_id'),
            // In some contexts $this->currency may be an instance that doesn't expose getCode()/getValue()
            // (for example Cart\Currency). Use session values if available, otherwise fall back to config.
            'currency_code' => isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency'),
            'currency_value' => isset($this->session->data['currency_value']) ? $this->session->data['currency_value'] : 1.0,
            
            // Environment
            'ip' => $this->request->server['REMOTE_ADDR'],
            'forwarded_ip' => isset($this->request->server['HTTP_X_FORWARDED_FOR']) ? $this->request->server['HTTP_X_FORWARDED_FOR'] : '',
            'user_agent' => isset($this->request->server['HTTP_USER_AGENT']) ? substr($this->request->server['HTTP_USER_AGENT'], 0, 255) : '',
            'accept_language' => isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? substr($this->request->server['HTTP_ACCEPT_LANGUAGE'], 0, 255) : '',
            
            // Products, totals, vouchers (from cart/session)
            'products' => array(),
            // We'll compute raw totals (code/title/value/sort_order) here so addOrder() can persist order_total rows
            'totals' => array(),
            'vouchers' => isset($this->session->data['vouchers']) ? $this->session->data['vouchers'] : array()
        );
        
        // Add products from cart
        foreach ($this->cart->getProducts() as $product) {
            $order_data['products'][] = array(
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'quantity' => $product['quantity'],
                'price' => $product['price'],
                'total' => $product['total'],
                'tax' => isset($product['tax']) ? $product['tax'] : 0,
                'reward' => isset($product['reward']) ? $product['reward'] : 0,
                'option' => isset($product['option']) ? $product['option'] : array()
            );
        }

        // Compute raw totals using the total extension pipeline so order_total rows will be inserted correctly
        $this->load->model('setting/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        $results = $this->model_setting_extension->getExtensions('total');

        $sort_order = array();
        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);

                // Each getTotal() appends an array with keys: code, title, value, sort_order
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        // Ensure totals are present in raw (code/title/value/sort_order) shape expected by addOrder
        $order_data['totals'] = array();
        foreach ($totals as $t) {
            $order_data['totals'][] = array(
                'code' => isset($t['code']) ? $t['code'] : '',
                'title' => isset($t['title']) ? $t['title'] : (isset($t['text']) ? $t['text'] : ''),
                'value' => isset($t['value']) ? $t['value'] : 0,
                'sort_order' => isset($t['sort_order']) ? (int)$t['sort_order'] : 0
            );
        }

        return $order_data;
    }

    /**
     * AJAX: Confirm order
     */
    public function confirm() {
        $this->load->language('checkout/dockercart_checkout');
        
        $data = $this->getJsonInput();
        $json = array();

        // Validate cart
        if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
            $json['redirect'] = $this->url->link('checkout/cart');
        }

        // Validate terms agreement
        if (!empty($this->config->get('config_checkout_id')) && empty($data['agree'])) {
            // Return a structured field error so the frontend highlights the
            // specific 'agree' field instead of showing a duplicate generic
            // notification. Frontend showFieldErrors() will display this properly.
            $json['error'] = isset($json['error']) && is_array($json['error']) ? $json['error'] : array();
            $json['error']['agree'] = $this->language->get('error_agree');
        }

        // Validate customer/guest
        if (!$this->customer->isLogged() && !$this->config->get('config_checkout_guest')) {
            $json['error'] = $this->language->get('error_customer');
        }

        // Validate shipping address
        if ($this->cart->hasShipping() && empty($this->session->data['shipping_address'])) {
            $json['error'] = $this->language->get('error_shipping_address');
        }

        // Validate payment address
        if (empty($this->session->data['payment_address'])) {
            $json['error'] = $this->language->get('error_payment_address');
        }

        // Validate shipping method
        if ($this->cart->hasShipping() && empty($this->session->data['shipping_method'])) {
            $json['error'] = $this->language->get('error_shipping_method');
        }

        // Validate payment method
        if (empty($this->session->data['payment_method'])) {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!isset($json['error']) && !isset($json['redirect'])) {
            // Store comment
            if (isset($data['comment'])) {
                $this->session->data['comment'] = strip_tags($data['comment']);
            }

            // Note: customer account will be created AFTER order creation to avoid
            // side-effects from login (which can affect cart/session state).

            // Create order and add order_id to session (needed by payment extensions)
            $this->load->model('checkout/order');
            
            // Prepare order data from session
            $order_data = $this->prepareOrderData();
            $order_id = $this->model_checkout_order->addOrder($order_data);
            $this->session->data['order_id'] = $order_id;

            // Add initial order history so the order has a valid status in admin (uses config default)
            if ($order_id) {
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'));
            }

            // Create customer account if requested — do this AFTER order creation so
            // the login process does not interfere with the session/cart used to
            // build the order. createCustomerAccount() will add the address and
            // log the customer in, then clear guest data from session.
            if (!$this->customer->isLogged() && isset($this->session->data['guest']['create_account']) && $this->session->data['guest']['create_account']) {
                $this->createCustomerAccount();
            }

            // For logged-in customers, save shipping address if it has changed
            if ($this->customer->isLogged() && isset($this->session->data['shipping_address'])) {
                $this->saveCustomerShippingAddress();
            }

            // Redirect to success (order confirmation will handle order, don't process payment twice)
            $json['redirect'] = $this->url->link('checkout/success');
            $json['success'] = true;
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Event handler: Redirect from standard checkout
     */
    public function eventRedirectCheckout(&$route, &$args) {
        if (!$this->config->get('module_dockercart_checkout_status')) {
            return;
        }

        if (!$this->config->get('module_dockercart_checkout_redirect_standard')) {
            return;
        }

        if (!$this->verifyLicense()) {
            return;
        }

        // Redirect to DockerCart Checkout
        $this->response->redirect($this->url->link('checkout/dockercart_checkout'));
    }



    /**
     * Event handler: Add custom header scripts
     */
    public function eventHeaderAfter(&$route, &$data, &$output) {
        // Add Journal 3 compatibility CSS if enabled and detected
        if ($this->config->get('module_dockercart_checkout_journal3_compat') && $this->isJournal3Theme()) {
            $journal3_css = '<link href="catalog/view/theme/default/stylesheet/dockercart_checkout_journal3.css" rel="stylesheet" type="text/css" />';
            $output = str_replace('</head>', $journal3_css . '</head>', $output);
        }
    }

    /**
     * Get cart products
     */
    private function getCartProducts() {
        $this->load->model('tool/image');
        
        $products = array();

        foreach ($this->cart->getProducts() as $product) {
            $option_data = array();

            foreach ($product['option'] as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $value = utf8_substr($option['value'], 0, utf8_strrpos($option['value'], '.'));
                }

                $option_data[] = array(
                    'name'  => $option['name'],
                    'value' => (utf8_strlen($value) > self::VALUE_DISPLAY_MAX_LENGTH ? utf8_substr($value, 0, self::VALUE_DISPLAY_MAX_LENGTH) . '..' : $value)
                );
            }

            $products[] = array(
                'cart_id'   => $product['cart_id'],
                'product_id' => $product['product_id'],
                'name'      => $product['name'],
                'model'     => $product['model'],
                'thumb'     => $this->model_tool_image->resize($product['image'] ?? 'placeholder.png', self::IMAGE_THUMB_WIDTH, self::IMAGE_THUMB_HEIGHT),
                'option'    => $option_data,
                'quantity'  => $product['quantity'],
                'price'     => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                'total'     => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            );
        }

        return $products;
    }

    /**
     * Get cart vouchers
     */
    private function getCartVouchers() {
        $vouchers = array();

        if (isset($this->session->data['vouchers'])) {
            foreach ($this->session->data['vouchers'] as $key => $voucher) {
                $vouchers[] = array(
                    'key'         => $key,
                    'description' => $voucher['description'],
                    'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
                );
            }
        }

        return $vouchers;
    }

    /**
     * Get cart totals
     */
    private function getCartTotals() {
        $this->load->model('setting/extension');

        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep passing by reference so we call getTotal directly
        $total_data = array(
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        );

        $sort_order = array();

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/total/' . $result['code']);
                $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
            }
        }

        $result_totals = array();

        foreach ($totals as $total_item) {
            $result_totals[] = array(
                'title' => $total_item['title'],
                'text'  => $this->currency->format($total_item['value'], $this->session->data['currency'])
            );
        }

        return $result_totals;
    }

    /**
     * Get available shipping methods
     */
    private function getShippingMethods() {
        $method_data = array();

        // Protect: if shipping_address is not in session, return empty
        if (!isset($this->session->data['shipping_address']) || !is_array($this->session->data['shipping_address'])) {
            $this->logger->debug('getShippingMethods: shipping_address not in session or invalid');
            return $method_data;
        }

        $this->load->model('setting/extension');

        $results = $this->model_setting_extension->getExtensions('shipping');

        $this->logger->debug('getShippingMethods: found ' . count($results) . ' shipping extensions');

        foreach ($results as $result) {
            $this->logger->debug('  - Extension: ' . $result['code'] . ', status: ' . ($this->config->get('shipping_' . $result['code'] . '_status') ? 'enabled' : 'disabled'));

            if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                $this->load->model('extension/shipping/' . $result['code']);

                $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

                $this->logger->debug('    - Quote returned: ' . ($quote ? 'yes' : 'no'));
                if ($quote) {
                    $this->logger->debug('    - Quote data: ' . print_r($quote, true));
                }                

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

        // Apply method overrides from admin settings
        $method_data = $this->applyShippingMethodOverrides($method_data);

        $this->session->data['shipping_methods'] = $method_data;

        return $method_data;
    }

    /**
     * Get available payment methods
     */
    private function getPaymentMethods() {
        $method_data = array();

        $this->load->model('setting/extension');

        $results = $this->model_setting_extension->getExtensions('payment');

        $recurring = $this->cart->hasRecurringProducts();

        // Debug: log payment address and cart total when debug enabled
        try {
            $addr = isset($this->session->data['payment_address']) ? $this->session->data['payment_address'] : array();
            $this->logger->debug('getPaymentMethods: payment_address=' . json_encode($addr));
            $this->logger->debug('getPaymentMethods: cart_total=' . (float)$this->cart->getTotal());
        } catch (Exception $e) {
            // ignore logging errors
        }

        foreach ($results as $result) {
            $enabled_flag = (bool)$this->config->get('payment_' . $result['code'] . '_status');
            $this->logger->debug('Evaluating payment extension: ' . $result['code'] . ' (enabled_flag=' . ($enabled_flag ? '1' : '0') . ')');

            if ($enabled_flag) {
                $this->load->model('extension/payment/' . $result['code']);

                $method = $this->{'model_extension_payment_' . $result['code']}->getMethod(isset($this->session->data['payment_address']) ? $this->session->data['payment_address'] : array(), $recurring ? 0 : $this->cart->getTotal());

                if ($method) {
                    $this->logger->debug('Payment method ' . $result['code'] . ' AVAILABLE (title=' . ($method['title'] ?? '') . ')');
                    if ($recurring) {
                        if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
                            $method_data[$result['code']] = $method;
                        }
                    } else {
                        $method_data[$result['code']] = $method;
                    }
                } else {
                    // Add extra diagnostics for common built-in methods
                    if ($result['code'] === 'cod') {
                        try {
                            $cod_geo = (int)$this->config->get('payment_cod_geo_zone_id');
                            $cod_total_threshold = (float)$this->config->get('payment_cod_total');
                            $hasShipping = $this->cart->hasShipping() ? '1' : '0';
                            $query = $this->db->query("SELECT COUNT(*) AS cnt FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $cod_geo . "' AND country_id = '" . (int)$this->session->data['payment_address']['country_id'] . "' AND (zone_id = '" . (int)$this->session->data['payment_address']['zone_id'] . "' OR zone_id = '0')");
                            $this->logger->debug('COD diagnostics: geo_zone_id=' . $cod_geo . ', threshold=' . $cod_total_threshold . ', hasShipping=' . $hasShipping . ', geo_match_count=' . ($query->row['cnt'] ?? 0));
                        } catch (Exception $e) {}
                    }

                    if ($result['code'] === 'free_checkout') {
                        $this->logger->debug('Free checkout diagnostics: cart_total=' . (float)$this->cart->getTotal());
                    }

                    $this->logger->debug('Payment method ' . $result['code'] . ' NOT available for provided payment_address/total');
                }
            } else {
                $this->logger->debug('Payment extension ' . $result['code'] . ' is disabled in config');
            }
        }

        $sort_order = array();

        foreach ($method_data as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $method_data);

        // Apply method overrides from admin settings
        $method_data = $this->applyPaymentMethodOverrides($method_data);

        $this->session->data['payment_methods'] = $method_data;

        return $method_data;
    }

    /**
     * Save shipping address to logged-in customer account
     * Called during order confirmation to persist address changes
     */
    private function saveCustomerShippingAddress() {
        $shipping_address = $this->session->data['shipping_address'];
        
        // Check if this address already exists in customer's addresses
        $this->load->model('account/address');
        $existing_addresses = $this->model_account_address->getAddresses();
        
        $address_exists = false;
        foreach ($existing_addresses as $addr) {
            if ($addr['address_1'] === $shipping_address['address_1'] &&
                $addr['city'] === $shipping_address['city'] &&
                $addr['country_id'] === $shipping_address['country_id'] &&
                $addr['zone_id'] === $shipping_address['zone_id']) {
                $address_exists = true;
                break;
            }
        }
        
        // If address doesn't exist, save it
        if (!$address_exists) {
            $this->load->model('account/customer');
            
            $address_data = array(
                'firstname'    => $shipping_address['firstname'],
                'lastname'     => $shipping_address['lastname'],
                'company'      => $shipping_address['company'] ?? '',
                'address_1'    => $shipping_address['address_1'],
                'address_2'    => $shipping_address['address_2'] ?? '',
                'city'         => $shipping_address['city'],
                'postcode'     => $shipping_address['postcode'] ?? '',
                'country_id'   => $shipping_address['country_id'],
                'zone_id'      => $shipping_address['zone_id'],
                'custom_field' => array(),
                'default'      => 0
            );
            
            $address_id = $this->model_account_address->addAddress($this->customer->getId(), $address_data);
            
            // If customer has no default address, set this as default
            if (!$this->customer->getAddressId() && $address_id) {
                $this->model_account_customer->editAddressId($this->customer->getId(), $address_id);
            }
        }
    }

    /**
     * Create customer account from guest data
     */
    private function createCustomerAccount() {
        if (!isset($this->session->data['guest']['create_account'])) {
            return false;
        }

        $this->load->model('account/customer');

        $customer_data = array(
            'customer_group_id' => $this->config->get('config_customer_group_id'),
            'firstname'         => $this->session->data['guest']['firstname'],
            'lastname'          => $this->session->data['guest']['lastname'],
            'email'             => $this->session->data['guest']['email'],
            'telephone'         => $this->session->data['guest']['telephone'],
            'password'          => $this->session->data['guest']['password'],
            'newsletter'        => 0,
            'custom_field'      => array()
        );

        $customer_id = $this->model_account_customer->addCustomer($customer_data);

        if ($customer_id) {
            // Add shipping address
            if (isset($this->session->data['shipping_address'])) {
                $this->load->model('account/address');

                $address_data = array(
                    'firstname'    => $this->session->data['shipping_address']['firstname'],
                    'lastname'     => $this->session->data['shipping_address']['lastname'],
                    'company'      => $this->session->data['shipping_address']['company'],
                    'address_1'    => $this->session->data['shipping_address']['address_1'],
                    'address_2'    => $this->session->data['shipping_address']['address_2'],
                    'city'         => $this->session->data['shipping_address']['city'],
                    'postcode'     => $this->session->data['shipping_address']['postcode'],
                    'country_id'   => $this->session->data['shipping_address']['country_id'],
                    'zone_id'      => $this->session->data['shipping_address']['zone_id'],
                    'custom_field' => array(),
                    'default'      => 1
                );

                $this->model_account_address->addAddress($customer_id, $address_data);
            }

            // Login customer
            $this->customer->login($this->session->data['guest']['email'], $this->session->data['guest']['password']);

            unset($this->session->data['guest']);

            return true;
        }

        return false;
    }

    /**
     * Verify license
     */
    private function verifyLicense() {
        // Allow on localhost
        if ($this->isLocalhost()) {
            return true;
        }

        $license_key = trim((string)$this->config->get('module_dockercart_checkout_license_key'));

        if (empty($license_key)) {
            $this->logger->debug('License key not configured');
            return false;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $this->logger->debug('License library not found');
            return false;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $this->logger->debug('DockercartLicense class not found');
            return false;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $public_key = $this->config->get('module_dockercart_checkout_public_key');

            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_checkout');
            } else {
                $result = $license->verify($license_key, 'dockercart_checkout');
            }

            if (empty($result) || !$result['valid']) {
                $this->logger->debug('Invalid license: ' . ($result['error'] ?? 'Unknown error'));
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->logger->debug('License verification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Journal 3 theme is active
     */
    private function isJournal3Theme() {
        $theme = $this->config->get('config_theme');
        if (stripos($theme, self::JOURNAL_THEME_KEYWORD) !== false) {
            return true;
        }

        return file_exists(DIR_TEMPLATE . self::JOURNAL3_TEMPLATE_PATH);
    }

    /**
     * Get field metadata for rendering (input type, placeholder, etc.)
     */
    private function getFieldMetadata($field_id) {
        // Ensure language strings are loaded so placeholders and labels come from language files
        $this->load->language('checkout/dockercart_checkout');

        $meta = array(
            'type' => 'text',
            'placeholder' => '',
            'options' => array()
        );

        // Build field map using language placeholders where available (fall back to sensible literals)
        $field_map = array(
            'email' => array('type' => 'email', 'placeholder' => $this->language->get('placeholder_email') ?: 'you@example.com'),
            'firstname' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_firstname') ?: $this->language->get('entry_firstname') ?: 'First Name'),
            'lastname' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_lastname') ?: $this->language->get('entry_lastname') ?: 'Last Name'),
            'telephone' => array('type' => 'tel', 'placeholder' => $this->language->get('placeholder_telephone') ?: '+1 (555) 000-0000'),
            'fax' => array('type' => 'tel', 'placeholder' => $this->language->get('placeholder_fax') ?: 'Fax'),
            // 'newsletter' removed: newsletter subscription is handled via account settings, not checkout module
            'company' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_company') ?: $this->language->get('entry_company') ?: 'Company Name'),
            'address_1' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_address_1') ?: $this->language->get('entry_address_1') ?: '123 Main Street'),
            'address_2' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_address_2') ?: $this->language->get('entry_address_2') ?: 'Apartment, suite, etc.'),
            'city' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_city') ?: $this->language->get('entry_city') ?: 'City'),
            'postcode' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_postcode') ?: $this->language->get('entry_postcode') ?: '10001'),
            'country_id' => array('type' => 'select', 'placeholder' => $this->language->get('placeholder_country') ?: $this->language->get('entry_country') ?: 'Select Country'),
            'zone_id' => array('type' => 'select', 'placeholder' => $this->language->get('placeholder_zone') ?: $this->language->get('entry_zone') ?: 'Select State/Province'),
            'payment_firstname' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_firstname') ?: $this->language->get('entry_firstname') ?: 'First Name'),
            'payment_lastname' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_lastname') ?: $this->language->get('entry_lastname') ?: 'Last Name'),
            'payment_company' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_company') ?: $this->language->get('entry_company') ?: 'Company'),
            'payment_address_1' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_address_1') ?: $this->language->get('entry_address_1') ?: '123 Main Street'),
            'payment_address_2' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_address_2') ?: $this->language->get('entry_address_2') ?: 'Apartment, suite, etc.'),
            'payment_city' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_city') ?: $this->language->get('entry_city') ?: 'City'),
            'payment_postcode' => array('type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_postcode') ?: $this->language->get('entry_postcode') ?: '10001'),
            'payment_country_id' => array('type' => 'select', 'placeholder' => $this->language->get('placeholder_country') ?: $this->language->get('entry_country') ?: 'Select Country'),
            'payment_zone_id' => array('type' => 'select', 'placeholder' => $this->language->get('placeholder_zone') ?: $this->language->get('entry_zone') ?: 'Select State/Province'),
            'payment_method' => array('type' => 'radio', 'placeholder' => ''),
            'comment' => array('type' => 'textarea', 'placeholder' => $this->language->get('text_comment_placeholder') ?: 'Notes about your order, e.g. special notes for delivery.')
        );

        if (isset($field_map[$field_id])) {
            $meta = array_merge($meta, $field_map[$field_id]);
        }

        return $meta;
    }

    /**
     * Determine if a checkout field is marked as required in admin blocks config.
     * Falls back to legacy module config flags for certain fields if not present in blocks.
     */
    private function isFieldRequired($field_id) {
        // Load blocks configuration
        $blocks_data = $this->config->get('module_dockercart_checkout_blocks');
        $found = null;

        if (!empty($blocks_data)) {
            if (is_string($blocks_data)) {
                $blocks = json_decode($blocks_data, true);
            } else {
                $blocks = $blocks_data;
            }

            if (is_array($blocks)) {
                foreach ($blocks as $block) {
                    if (!isset($block['rows']) || !is_array($block['rows'])) continue;
                    foreach ($block['rows'] as $row) {
                        if (!isset($row['fields']) || !is_array($row['fields'])) continue;
                        foreach ($row['fields'] as $field) {
                            if (!isset($field['id'])) continue;
                            if ($field['id'] === $field_id) {
                                // If 'required' explicitly set, honor it
                                if (isset($field['required'])) {
                                    return ($field['required'] == 1);
                                }

                                // Field exists in blocks but no explicit 'required' flag — treat as not required
                                return false;
                            }
                        }
                    }
                }
            }
        }

        // Fallbacks for legacy module-level config
        switch ($field_id) {
            case 'telephone':
                return (bool)$this->config->get('module_dockercart_checkout_require_telephone');
            case 'postcode':
                return (bool)$this->config->get('module_dockercart_checkout_require_postcode');
            case 'address_2':
            case 'payment_address_2':
                return (bool)$this->config->get('module_dockercart_checkout_require_address2');
            case 'company':
            case 'payment_company':
                return (bool)$this->config->get('module_dockercart_checkout_require_company');
            case 'email':
                // Historically email was required by default; if not present in blocks, keep requiring it
                return true;
            default:
                return false;
        }
    }

    /**
     * Check if current domain is localhost
     */
    private function isLocalhost() {
        $domain = strtolower($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
        
        foreach (self::LOCALHOST_DOMAINS as $localhost) {
            if (strpos($domain, $localhost) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Decode blocks data from JSON string or array
     */
    private function decodeBlocksData($blocksData) {
        if (empty($blocksData)) {
            return $this->getDefaultBlocks();
        }
        
        if (is_string($blocksData)) {
            $decoded = json_decode($blocksData, true);
            $blocks = is_array($decoded) ? $decoded : $this->getDefaultBlocks();
        } else {
            $blocks = is_array($blocksData) ? $blocksData : $this->getDefaultBlocks();
        }
        
        // Apply translations to block names and field labels
        return $this->applyBlockTranslations($blocks);
    }
    
    /**
     * Apply language translations to block names and field labels
     */
    private function applyBlockTranslations($blocks) {
        $blockTranslations = array(
            'customer_details' => 'text_customer_details',
            'shipping_address' => 'text_shipping_address',
            'payment_address' => 'text_payment_address',
            'shipping_method' => 'text_shipping_method',
            'payment_method' => 'text_payment_method',
            'coupon' => 'text_coupon_voucher',
            'comment' => 'text_order_comment',
            'terms' => 'text_agree',
            'agree' => 'text_agree',
            'cart' => 'text_cart_summary'
        );
        
        $fieldTranslations = array(
            'firstname' => 'text_field_firstname',
            'lastname' => 'text_field_lastname',
            'email' => 'text_field_email',
            'telephone' => 'text_field_telephone',
            'fax' => 'text_field_fax',
            'company' => 'text_field_company',
            'address_1' => 'text_field_address_1',
            'address_2' => 'text_field_address_2',
            'city' => 'text_field_city',
            'postcode' => 'text_field_postcode',
            'country_id' => 'text_field_country',
            'zone_id' => 'text_field_zone',
            'payment_firstname' => 'text_field_payment_firstname',
            'payment_lastname' => 'text_field_payment_lastname',
            'payment_company' => 'text_field_payment_company',
            'payment_address_1' => 'text_field_payment_address_1',
            'payment_address_2' => 'text_field_payment_address_2',
            'payment_city' => 'text_field_payment_city',
            'payment_postcode' => 'text_field_payment_postcode',
            'payment_country_id' => 'text_field_payment_country',
            'payment_zone_id' => 'text_field_payment_zone'
        );
        
        foreach ($blocks as &$block) {
            // Translate block name
            if (isset($block['id']) && isset($blockTranslations[$block['id']])) {
                $translatedName = $this->language->get($blockTranslations[$block['id']]);
                if ($translatedName) {
                    $block['name'] = $translatedName;
                }
            }
            
            // Translate field labels
            if (isset($block['rows']) && is_array($block['rows'])) {
                foreach ($block['rows'] as &$row) {
                    if (isset($row['fields']) && is_array($row['fields'])) {
                        foreach ($row['fields'] as &$field) {
                            if (isset($field['id']) && isset($fieldTranslations[$field['id']])) {
                                $translatedLabel = $this->language->get($fieldTranslations[$field['id']]);
                                if ($translatedLabel) {
                                    $field['label'] = $translatedLabel;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $blocks;
    }
    
    /**
     * Process blocks: decode rows and migrate old structure
     */
    private function processBlocks($blocks) {
        foreach ($blocks as $idx => &$block) {
            // Decode rows if JSON string
            if (isset($block['rows']) && is_string($block['rows'])) {
                $decoded = json_decode($block['rows'], true);
                $block['rows'] = is_array($decoded) ? $decoded : array();
            }
            
            // Migration: convert old 'fields' to 'rows'
            if (isset($block['fields']) && !isset($block['rows'])) {
                $fields = is_string($block['fields']) ? json_decode($block['fields'], true) : $block['fields'];
                $fields = is_array($fields) ? $fields : array();
                
                $block['rows'] = array();
                foreach ($fields as $field) {
                    $block['rows'][] = array(
                        'columns' => 1,
                        'fields' => array($field)
                    );
                }
            }
            
            // Filter out hidden fields and empty rows
            if (isset($block['rows']) && is_array($block['rows'])) {
                foreach ($block['rows'] as &$row) {
                    if (isset($row['fields']) && is_array($row['fields'])) {
                        $row['fields'] = array_filter($row['fields'], function($field) {
                            return !isset($field['visible']) || $field['visible'];
                        });
                    }
                }
                
                $block['rows'] = array_filter($block['rows'], function($row) {
                    return !empty($row['fields']);
                });
            }
        }
        
        return $blocks;
    }
    
    /**
     * Process blocks for display: add metadata, filter fields
     */
    private function processBlocksForDisplay($blocks) {
        $legacyFields = array('payment_agree', 'newsletter');
        
        foreach ($blocks as &$block) {
            $block['rows'] = isset($block['rows']) ? $block['rows'] : array();
            
            // Process each row
            foreach ($block['rows'] as &$row) {
                $row['fields'] = isset($row['fields']) ? $row['fields'] : array();
                
                // Filter visible fields and remove legacy fields
                $visibleFields = array_filter($row['fields'], function($field) use ($legacyFields) {
                    if (!isset($field['visible']) || $field['visible'] != 1) {
                        return false;
                    }
                    if (isset($field['id']) && in_array($field['id'], $legacyFields)) {
                        return false;
                    }
                    return true;
                });
                
                // Add metadata to fields
                foreach ($visibleFields as &$field) {
                    if (isset($field['id'])) {
                        $metadata = $this->getFieldMetadata($field['id']);
                        $field = array_merge($field, $metadata);
                    }
                }
                
                $row['fields'] = array_values($visibleFields);
            }
            
            // Remove empty rows
            $block['rows'] = array_values(array_filter($block['rows'], function($row) {
                return !empty($row['fields']);
            }));
        }
        
        return $blocks;
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data) {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }
    
    /**
     * Get JSON input from request
     */
    private function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: array();
    }
    
    /**
     * Get default blocks
     */
    private function getDefaultBlocks() {
        $this->load->language('checkout/dockercart_checkout');

        return array(
            array('id' => 'cart', 'name' => $this->language->get('text_cart_summary') ?: 'Cart Summary', 'enabled' => 1, 'sort_order' => 1, 'collapsible' => 0),
            array('id' => 'shipping_address', 'name' => $this->language->get('text_shipping_address') ?: 'Shipping Address', 'enabled' => 1, 'sort_order' => 2, 'collapsible' => 0),
            array('id' => 'payment_address', 'name' => $this->language->get('text_payment_address') ?: 'Payment Address', 'enabled' => 1, 'sort_order' => 3, 'collapsible' => 1),
            array('id' => 'shipping_method', 'name' => $this->language->get('text_shipping_method') ?: 'Shipping Method', 'enabled' => 1, 'sort_order' => 4, 'collapsible' => 0),
            array('id' => 'payment_method', 'name' => $this->language->get('text_payment_method') ?: 'Payment Method', 'enabled' => 1, 'sort_order' => 5, 'collapsible' => 0),
            array('id' => 'coupon', 'name' => $this->language->get('text_coupon_voucher') ?: 'Coupon / Voucher / Reward', 'enabled' => 1, 'sort_order' => 6, 'collapsible' => 1),
            array('id' => 'comment', 'name' => $this->language->get('text_order_comment') ?: 'Order Comment', 'enabled' => 1, 'sort_order' => 7, 'collapsible' => 1),
            array('id' => 'agree', 'name' => $this->language->get('text_agree') ?: 'Terms & Conditions', 'enabled' => 1, 'sort_order' => 8, 'collapsible' => 0)
        );
    }

    /**
     * Write debug log
     */
    /**
     * Write log message using centralized logger
     * @deprecated Use $this->logger methods directly (info, error, warning, debug, exception)
     * @param string $message Log message
     */
    private function writeLog($message) {
        $this->logger->info($message);
    }

    /**
     * Apply shipping method overrides from admin settings
     * @param array $methods Shipping methods array
     * @return array Modified methods array with custom titles/descriptions
     */
    private function applyShippingMethodOverrides($methods) {
        $overrides_data = $this->config->get('module_dockercart_checkout_shipping_override');
        
        // Deserialize if stored as JSON string
        if (is_string($overrides_data)) {
            $overrides_data = json_decode($overrides_data, true);
        }
        
        $overrides = is_array($overrides_data) ? $overrides_data : array();
        
        if (empty($overrides)) {
            return $methods;
        }
        
        // Get current customer language (from session or config)
        $language_code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');
        
        foreach ($methods as $code => &$method) {
            // Check if override is enabled for this method
            if (isset($overrides[$code]) && !empty($overrides[$code]['enabled'])) {
                $override = $overrides[$code];
                
                // Apply custom title if set (check language-specific first, then fallback to non-localized)
                $custom_title = null;
                if (isset($override['title']) && is_array($override['title']) && !empty($override['title'][$language_code])) {
                    $custom_title = $override['title'][$language_code];
                } elseif (isset($override['title']) && is_string($override['title']) && !empty($override['title'])) {
                    // Fallback for old non-multilingual format
                    $custom_title = $override['title'];
                }
                
                if ($custom_title) {
                    // Apply title to method level AND each quote
                    $method['title'] = $custom_title;
                    
                    if (isset($method['quote']) && is_array($method['quote'])) {
                        foreach ($method['quote'] as $quote_code => &$quote) {
                            $quote['title'] = $custom_title;
                        }
                    }
                }
                
                // Apply custom description if set (add as separate field, don't overwrite 'text' which contains price)
                $custom_description = null;
                if (isset($override['description']) && is_array($override['description']) && !empty($override['description'][$language_code])) {
                    $custom_description = $override['description'][$language_code];
                } elseif (isset($override['description']) && is_string($override['description']) && !empty($override['description'])) {
                    // Fallback for old non-multilingual format
                    $custom_description = $override['description'];
                }
                
                if ($custom_description) {
                    // Add description to method level (for display under title)
                    $method['description'] = $custom_description;
                    
                    // Also add to each quote for consistency
                    if (isset($method['quote']) && is_array($method['quote'])) {
                        foreach ($method['quote'] as $quote_code => &$quote) {
                            $quote['description'] = $custom_description;
                        }
                    }
                }
            }
        }
        
        return $methods;
    }

    /**
     * Apply payment method overrides from admin settings
     * @param array $methods Payment methods array
     * @return array Modified methods array with custom titles/descriptions
     */
    private function applyPaymentMethodOverrides($methods) {
        $overrides_data = $this->config->get('module_dockercart_checkout_payment_override');
        
        // Deserialize if stored as JSON string
        if (is_string($overrides_data)) {
            $overrides_data = json_decode($overrides_data, true);
        }
        
        $overrides = is_array($overrides_data) ? $overrides_data : array();
        
        if (empty($overrides)) {
            return $methods;
        }
        
        // Get current customer language (from session or config)
        $language_code = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_language');
        
        foreach ($methods as $code => &$method) {
            // Check if override is enabled for this method
            if (isset($overrides[$code]) && !empty($overrides[$code]['enabled'])) {
                $override = $overrides[$code];
                
                // Apply custom title if set (check language-specific first, then fallback to non-localized)
                $custom_title = null;
                if (isset($override['title']) && is_array($override['title']) && !empty($override['title'][$language_code])) {
                    $custom_title = $override['title'][$language_code];
                } elseif (isset($override['title']) && is_string($override['title']) && !empty($override['title'])) {
                    // Fallback for old non-multilingual format
                    $custom_title = $override['title'];
                }
                
                if ($custom_title) {
                    $method['title'] = $custom_title;
                }
                
                // Apply custom description if set (add as separate field, don't overwrite other fields)
                $custom_description = null;
                if (isset($override['description']) && is_array($override['description']) && !empty($override['description'][$language_code])) {
                    $custom_description = $override['description'][$language_code];
                } elseif (isset($override['description']) && is_string($override['description']) && !empty($override['description'])) {
                    // Fallback for old non-multilingual format
                    $custom_description = $override['description'];
                }
                
                if ($custom_description) {
                    // Add description field (don't overwrite 'text' field which may contain other info)
                    $method['description'] = $custom_description;
                }
            }
        }
        
        return $methods;
    }
}
