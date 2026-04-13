<?php
/**
 * DockerCart Checkout — Admin Controller
 *
 * One-Page Checkout Module for OpenCart 3.0.3.8+
 * Installation WITHOUT OCMOD - uses OpenCart Event System only
 *
 * License: GNU General Public License v3.0 (GPL-3.0)
 * Copyright (c) mathflow-bit
 */

class ControllerExtensionModuleDockercartCheckout extends Controller {
    private $error = array();
    private $module_version = '1.0.1';
    private $logger;
    
    // Configuration constants
    const CACHE_TTL_MIN = 0;
    const CACHE_TTL_MAX = 86400;
    const CACHE_TTL_DEFAULT = 3600;
    const LEGACY_NEWSLETTER_FIELD = 'newsletter';
    const LEGACY_PAYMENT_AGREE_FIELD = 'payment_agree';
    const MODULE_PREFIX = 'module_dockercart_checkout_';

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
     * Main settings page
     */
    public function index() {
        $this->load->language('extension/module/dockercart_checkout');

        $module_heading_title = $this->language->get('heading_title');

        $this->document->setTitle($module_heading_title);

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // Process blocks structure if present
            if (isset($this->request->post['module_dockercart_checkout_blocks'])) {
                $blocks = $this->request->post['module_dockercart_checkout_blocks'];
                
                if (is_array($blocks)) {
                    $blocks = $this->processBlocksData($blocks);
                }
                
                $this->request->post['module_dockercart_checkout_blocks'] = json_encode($blocks);
            }

            // Process shipping method overrides (convert to JSON for storage)
            if (isset($this->request->post['module_dockercart_checkout_shipping_override'])) {
                $shipping_overrides = $this->request->post['module_dockercart_checkout_shipping_override'];
                if (is_array($shipping_overrides)) {
                    $this->request->post['module_dockercart_checkout_shipping_override'] = json_encode($shipping_overrides);
                }
            }

            // Process payment method overrides (convert to JSON for storage)
            if (isset($this->request->post['module_dockercart_checkout_payment_override'])) {
                $payment_overrides = $this->request->post['module_dockercart_checkout_payment_override'];
                if (is_array($payment_overrides)) {
                    $this->request->post['module_dockercart_checkout_payment_override'] = json_encode($payment_overrides);
                }
            }

            $this->model_setting_setting->editSetting('module_dockercart_checkout', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        // Error handling
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_checkout', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_checkout', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // AJAX URLs
        $data['save_blocks_ajax'] = $this->url->link('extension/module/dockercart_checkout/saveBlocksOrder', 'user_token=' . $this->session->data['user_token'], true);
        $data['save_block_fields_ajax'] = $this->url->link('extension/module/dockercart_checkout/saveBlockFieldsAjax', 'user_token=' . $this->session->data['user_token'], true);

        // Load settings with defaults
        $settings = array(
            'status' => 0,
            'redirect_standard' => 1,
            'cache_ttl' => self::CACHE_TTL_DEFAULT,
            'theme' => 'light',
            'custom_header_footer' => 1,
            'show_progress' => 1,
            'geo_detect' => 1,
            'guest_create_account' => 1,
            'show_company' => 0,
            'show_tax_id' => 0,
            'recaptcha_enabled' => 0,
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
            'custom_css' => '',
            'custom_js' => '',
            'require_telephone' => 1,
            'require_address2' => 0,
            'require_postcode' => 1,
            'require_company' => 0,
            'journal3_compat' => 1,
            'debug' => 0
        );

        foreach ($settings as $key => $default) {
            $fullKey = self::MODULE_PREFIX . $key;
            $data[$fullKey] = $this->getSettingValue($fullKey, $default);
        }

        // Load and merge checkout blocks configuration
        $blocks_data = $this->getSettingValue('module_dockercart_checkout_blocks');
        $default_blocks = $this->getDefaultBlocks();
        
        // Decode blocks if JSON string
        if (!empty($blocks_data) && is_string($blocks_data)) {
            $blocks_data = json_decode($blocks_data, true);
        }
        
        $data['blocks'] = $this->mergeBlocksWithDefaults(
            is_array($blocks_data) ? $blocks_data : array(),
            $default_blocks
        );
        
        // Remove legacy fields from admin UI
        $data['blocks'] = $this->cleanupBlocksForAdminUI($data['blocks']);
        // Normalize localized labels/placeholders for existing saved blocks (including legacy raw keys like entry_comment)
        $data['blocks'] = $this->normalizeBlocksForAdminUI($data['blocks']);

        // Theme options
        $data['theme_options'] = array(
            'light' => $this->language->get('text_theme_light'),
            'dark' => $this->language->get('text_theme_dark'),
            'custom' => $this->language->get('text_theme_custom')
        );

        // Module version
        $data['module_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : $this->module_version;

        // Load available shipping and payment methods for Method Overrides tab
        $data['available_shipping_methods'] = $this->getAvailableShippingMethods();
        $data['available_payment_methods'] = $this->getAvailablePaymentMethods();

        // getAvailable*Methods() loads languages of shipping/payment extensions and can overwrite common keys
        // (for example heading_title). Reload module language and lock explicit heading title for template.
        $this->load->language('extension/module/dockercart_checkout');
        $data['heading_title'] = $module_heading_title;
        
        // Load saved overrides
        $shipping_overrides_data = $this->getSettingValue('module_dockercart_checkout_shipping_override', array());
        if (is_string($shipping_overrides_data)) {
            $shipping_overrides_data = json_decode($shipping_overrides_data, true);
        }
        $data['shipping_overrides'] = is_array($shipping_overrides_data) ? $shipping_overrides_data : array();
        
        $payment_overrides_data = $this->getSettingValue('module_dockercart_checkout_payment_override', array());
        if (is_string($payment_overrides_data)) {
            $payment_overrides_data = json_decode($payment_overrides_data, true);
        }
        $data['payment_overrides'] = is_array($payment_overrides_data) ? $payment_overrides_data : array();
        
        // Load available languages for multilingual support
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_checkout', $data));
    }

    /**
     * Get default checkout blocks configuration
     * New structure: rows with 1-3 columns per row
     * Each row contains: columns (1, 2, or 3) and fields array
     */
    private function getDefaultBlocks() {
        // Ensure language loaded when called from places that haven't loaded it yet
        $this->load->language('extension/module/dockercart_checkout');

        return array(
            // LEFT COLUMN (60%)
            array(
                'id' => 'customer_details',
                'name' => $this->language->get('block_customer_details') ?: 'Customer Details',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 1,
                'collapsible' => 0,
                'rows' => array(
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'firstname', 'label' => $this->language->get('entry_firstname'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_firstname') ?: $this->language->get('entry_firstname')),
                            array('id' => 'lastname', 'label' => $this->language->get('entry_lastname'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_lastname') ?: $this->language->get('entry_lastname'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'email', 'label' => $this->language->get('entry_email'), 'visible' => 1, 'required' => 1, 'type' => 'email', 'placeholder' => $this->language->get('placeholder_email') ?: $this->language->get('entry_email'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'telephone', 'label' => $this->language->get('entry_telephone'), 'visible' => 1, 'required' => 1, 'type' => 'tel', 'placeholder' => $this->language->get('placeholder_telephone') ?: $this->language->get('entry_telephone'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'fax', 'label' => $this->language->get('entry_fax') ?: 'Fax', 'visible' => 0, 'required' => 0, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_fax') ?: ($this->language->get('entry_fax') ?: 'Fax'))
                        )
                    )
                )
            ),
            array(
                'id' => 'shipping_address',
                'name' => $this->language->get('block_shipping_address') ?: 'Shipping Address',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 2,
                'collapsible' => 0,
                'rows' => array(
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'company', 'label' => $this->language->get('entry_company'), 'visible' => 0, 'required' => 0, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_company') ?: $this->language->get('entry_company'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'address_1', 'label' => $this->language->get('entry_address_1'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_address_1') ?: $this->language->get('entry_address_1'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'address_2', 'label' => $this->language->get('entry_address_2'), 'visible' => 0, 'required' => 0, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_address_2') ?: $this->language->get('entry_address_2'))
                        )
                    ),
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'city', 'label' => $this->language->get('entry_city'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_city') ?: $this->language->get('entry_city')),
                            array('id' => 'postcode', 'label' => $this->language->get('entry_postcode'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_postcode') ?: $this->language->get('entry_postcode'))
                        )
                    ),
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'country_id', 'label' => $this->language->get('entry_country'), 'visible' => 1, 'required' => 1, 'type' => 'select', 'placeholder' => $this->language->get('placeholder_country') ?: $this->language->get('entry_country')),
                            array('id' => 'zone_id', 'label' => $this->language->get('entry_zone'), 'visible' => 1, 'required' => 1, 'type' => 'select', 'placeholder' => $this->language->get('placeholder_zone') ?: $this->language->get('entry_zone'))
                        )
                    )
                )
            ),
            array(
                'id' => 'payment_address',
                'name' => $this->language->get('block_payment_address') ?: 'Payment Address',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 3,
                'collapsible' => 1,
                'rows' => array(
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'payment_firstname', 'label' => $this->language->get('entry_firstname'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_firstname') ?: $this->language->get('entry_firstname')),
                            array('id' => 'payment_lastname', 'label' => $this->language->get('entry_lastname'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_lastname') ?: $this->language->get('entry_lastname'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'payment_company', 'label' => $this->language->get('entry_company'), 'visible' => 0, 'required' => 0, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_company') ?: $this->language->get('entry_company'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'payment_address_1', 'label' => $this->language->get('entry_address_1'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_address_1') ?: $this->language->get('entry_address_1'))
                        )
                    ),
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'payment_address_2', 'label' => $this->language->get('entry_address_2'), 'visible' => 0, 'required' => 0, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_address_2') ?: $this->language->get('entry_address_2'))
                        )
                    ),
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'payment_city', 'label' => $this->language->get('entry_city'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_city') ?: $this->language->get('entry_city')),
                            array('id' => 'payment_postcode', 'label' => $this->language->get('entry_postcode'), 'visible' => 1, 'required' => 1, 'type' => 'text', 'placeholder' => $this->language->get('placeholder_payment_postcode') ?: $this->language->get('entry_postcode'))
                        )
                    ),
                    array(
                        'columns' => 2,
                        'fields' => array(
                            array('id' => 'payment_country_id', 'label' => $this->language->get('entry_country'), 'visible' => 1, 'required' => 1, 'type' => 'select', 'placeholder' => $this->language->get('placeholder_country') ?: $this->language->get('entry_country')),
                            array('id' => 'payment_zone_id', 'label' => $this->language->get('entry_zone'), 'visible' => 1, 'required' => 1, 'type' => 'select', 'placeholder' => $this->language->get('placeholder_zone') ?: $this->language->get('entry_zone'))
                        )
                    )
                )
            ),
            array(
                'id' => 'payment_method',
                'name' => $this->language->get('block_payment_method') ?: 'Payment Method',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 4,
                'collapsible' => 0,
                'rows' => array(
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'payment_method', 'label' => $this->language->get('text_payment_method') ?: 'Payment Method', 'visible' => 1, 'required' => 1, 'type' => 'radio')
                        )
                    )
                )
            ),
            array(
                'id' => 'comment',
                'name' => $this->language->get('block_comment') ?: 'Order Comment',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 5,
                'collapsible' => 0,
                'rows' => array(
                    array(
                        'columns' => 1,
                        'fields' => array(
                            array('id' => 'comment', 'label' => $this->language->get('entry_comment') ?: 'Order Comment', 'visible' => 1, 'required' => 0, 'type' => 'textarea', 'placeholder' => $this->language->get('text_comment_placeholder') ?: 'Notes about your order, e.g. special notes for delivery.')
                        )
                    )
                )
            ),
            array(
                'id' => 'terms',
                'name' => $this->language->get('block_agree') ?: 'Terms & Conditions',
                'column' => 'left',
                'width' => 60,
                'enabled' => 1,
                'sort_order' => 6,
                'collapsible' => 0,
                'rows' => array()
            )
        );
    }

    /**
     * AJAX: Save blocks order
     */
    public function saveBlocksOrder() {
        $json = array();
        $this->load->language('extension/module/dockercart_checkout');

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_checkout')) {
            $json['success'] = false;
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            try {
                $this->load->model('setting/setting');
                $this->model_setting_setting->editSettingValue('module_dockercart_checkout', 'module_dockercart_checkout_blocks', json_encode($data['blocks']));
                $json['success'] = true;
            } catch (Exception $e) {
                $json['success'] = false;
                $json['error'] = sprintf($this->language->get('error_exception'), $e->getMessage());
            }
        } else {
            $json['success'] = false;
            $json['error'] = $this->language->get('error_invalid_blocks_data');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Install module - registers events, creates layout and SEO URL
     */
    public function install() {
        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        $this->load->model('design/layout');

        // Default settings
        $defaults = array(
            'module_dockercart_checkout_status' => 0,
            'module_dockercart_checkout_redirect_standard' => 1,
            'module_dockercart_checkout_cache_ttl' => 3600,
            'module_dockercart_checkout_theme' => 'light',
            'module_dockercart_checkout_custom_header_footer' => 1,
            'module_dockercart_checkout_show_progress' => 1,
            'module_dockercart_checkout_geo_detect' => 1,
            'module_dockercart_checkout_guest_create_account' => 1,
            'module_dockercart_checkout_show_company' => 0,
            'module_dockercart_checkout_show_tax_id' => 0,
            'module_dockercart_checkout_recaptcha_enabled' => 0,
            'module_dockercart_checkout_journal3_compat' => 1,
            'module_dockercart_checkout_debug' => 0,
            'module_dockercart_checkout_blocks' => json_encode($this->getDefaultBlocks())
        );

        $this->model_setting_setting->editSetting('module_dockercart_checkout', $defaults);

        // Register events for checkout redirect
        $events = array(
            // Redirect standard checkout to DockerCart checkout
            array(
                'code'    => 'dockercart_checkout_redirect_checkout',
                'trigger' => 'catalog/controller/checkout/checkout/before',
                'action'  => 'extension/module/dockercart_checkout/eventRedirectCheckout'
            ),
            // Redirect cart page to DockerCart checkout (optional, can be configured)
            array(
                'code'    => 'dockercart_checkout_redirect_cart',
                'trigger' => 'catalog/controller/checkout/cart/before',
                'action'  => 'extension/module/dockercart_checkout/eventRedirectCart'
            ),
            // Add custom scripts to header
            array(
                'code'    => 'dockercart_checkout_header',
                'trigger' => 'catalog/view/common/header/after',
                'action'  => 'extension/module/dockercart_checkout/eventHeaderAfter'
            )
        );

        foreach ($events as $event) {
            // Delete if exists (clean reinstall)
            $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($event['code']) . "'");

            // Add event
            $this->model_setting_event->addEvent(
                $event['code'],
                $event['trigger'],
                $event['action']
            );
        }

        // Create layout for DockerCart Checkout
        $this->load->language('extension/module/dockercart_checkout');

        $layout_name = $this->language->get('text_layout_name') ?: 'DockerCart Checkout';

        $layout_data = array(
            'name' => $layout_name
        );

        // Check if layout exists
        $query = $this->db->query("SELECT layout_id FROM `" . DB_PREFIX . "layout` WHERE `name` = '" . $this->db->escape($layout_name) . "'");
        
        if (!$query->num_rows) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "layout` SET `name` = '" . $this->db->escape($layout_name) . "'");
            $layout_id = $this->db->getLastId();

            // Add layout route
            $this->db->query("INSERT INTO `" . DB_PREFIX . "layout_route` SET 
                `layout_id` = '" . (int)$layout_id . "',
                `store_id` = '0',
                `route` = 'checkout/dockercart_checkout'");
        }

        // Add SEO URL if SEO URLs are enabled
        if ($this->config->get('config_seo_url')) {
            // Check all languages
            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                // Check if SEO URL already exists
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` 
                    WHERE `query` = 'checkout/dockercart_checkout' 
                    AND `language_id` = '" . (int)$language['language_id'] . "'
                    AND `store_id` = '0'");

                if (!$query->num_rows) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET 
                        `store_id` = '0',
                        `language_id` = '" . (int)$language['language_id'] . "',
                        `query` = 'checkout/dockercart_checkout',
                        `keyword` = 'fast-checkout'");
                }
            }
        }

        $this->logger->info('Module installed successfully');
    }

    /**
     * Uninstall module - removes events and settings
     */
    public function uninstall() {
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        // Remove events
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'dockercart_checkout_%'");

        // Remove SEO URLs
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'checkout/dockercart_checkout'");

        // Remove layout
        $query = $this->db->query("SELECT layout_id FROM `" . DB_PREFIX . "layout` WHERE `name` = '" . $this->db->escape($layout_name) . "'");
        if ($query->num_rows) {
            $layout_id = $query->row['layout_id'];
            $this->db->query("DELETE FROM `" . DB_PREFIX . "layout_route` WHERE `layout_id` = '" . (int)$layout_id . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "layout` WHERE `layout_id` = '" . (int)$layout_id . "'");
        }

        // Remove settings
        $this->model_setting_setting->deleteSetting('module_dockercart_checkout');

        $this->logger->info('Module uninstalled successfully');
    }

    /**
     * AJAX: Save individual block fields without form submit
     */
    public function saveBlockFieldsAjax() {
        $json = array('success' => false, 'error' => '');
        $this->load->language('extension/module/dockercart_checkout');

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_checkout')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!isset($data['block_index']) || !isset($data['fields'])) {
            $this->load->language('extension/module/dockercart_checkout');
            $json['error'] = $this->language->get('error_missing_block_index_or_fields') . ' Received: ' . json_encode($data);
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->model('setting/setting');

            // Get current blocks
            $blocks_data = $this->config->get('module_dockercart_checkout_blocks');
            if (is_string($blocks_data)) {
                $blocks = json_decode($blocks_data, true);
            } else {
                $blocks = (is_array($blocks_data)) ? $blocks_data : array();
            }

            // Ensure it's an array
            if (!is_array($blocks)) {
                $blocks = array();
            }

            // Update the specific block's fields
            $block_index = intval($data['block_index']);
                if (isset($blocks[$block_index])) {
                // Ensure fields is an array
                $fields = $data['fields'];
                if (is_string($fields)) {
                    $fields = json_decode($fields, true);
                }
                if (!is_array($fields)) {
                    $fields = array();
                }

                    // Sanitize: remove any 'newsletter' fields — module does not manage newsletter subscriptions
                    $fields = array_values(array_filter($fields, function($f) {
                        return !isset($f['id']) || $f['id'] !== 'newsletter';
                    }));

                    $blocks[$block_index]['fields'] = $fields;

                // Save back to settings
                $this->model_setting_setting->editSettingValue('module_dockercart_checkout', 'module_dockercart_checkout_blocks', json_encode($blocks));

                $json['success'] = true;
                $json['message'] = $this->language->get('text_block_fields_saved');
            } else {
                $json['error'] = $this->language->get('error_block_index_not_found');
            }
        } catch (Exception $e) {
            $json['error'] = sprintf($this->language->get('error_exception'), $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Validate form
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_checkout')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Validate cache TTL
        if (isset($this->request->post['module_dockercart_checkout_cache_ttl'])) {
            $ttl = (int)$this->request->post['module_dockercart_checkout_cache_ttl'];
            if ($ttl < self::CACHE_TTL_MIN || $ttl > self::CACHE_TTL_MAX) {
                $this->error['warning'] = $this->language->get('error_cache_ttl');
            }
        }

        return !$this->error;
    }

    /**
     * Merge saved blocks with default blocks
     */
    private function mergeBlocksWithDefaults($savedBlocks, $defaultBlocks) {
        $blocksById = array();
        foreach ($savedBlocks as $block) {
            if (isset($block['id'])) {
                $blocksById[$block['id']] = $block;
            }
        }
        
        $finalBlocks = array();
        foreach ($defaultBlocks as $defaultBlock) {
            $blockId = $defaultBlock['id'];
            
            if (isset($blocksById[$blockId])) {
                $block = $this->migrateBlockStructure($blocksById[$blockId], $defaultBlock);
                $finalBlocks[] = $block;
            } else {
                $finalBlocks[] = $defaultBlock;
            }
        }
        
        return $finalBlocks;
    }
    
    /**
     * Migrate block from old structure to new and merge with defaults
     */
    private function migrateBlockStructure($savedBlock, $defaultBlock) {
        // Migration: convert old 'fields' to 'rows'
        if (isset($savedBlock['fields']) && !isset($savedBlock['rows'])) {
            $fields = is_array($savedBlock['fields']) ? $savedBlock['fields'] : array();
            $savedBlock['rows'] = array();
            
            foreach ($fields as $field) {
                $savedBlock['rows'][] = array(
                    'columns' => 1,
                    'fields' => array($field)
                );
            }
            unset($savedBlock['fields']);
        }
        
        // Use default rows if empty
        if (empty($savedBlock['rows']) || !is_array($savedBlock['rows'])) {
            $savedBlock['rows'] = $defaultBlock['rows'];
            return $savedBlock;
        }
        
        // Merge with default rows to restore missing fields
        $savedFieldIds = $this->extractFieldIds($savedBlock['rows']);
        $missingRows = $this->findMissingRows($defaultBlock['rows'], $savedFieldIds);
        
        if (!empty($missingRows)) {
            $savedBlock['rows'] = array_merge($savedBlock['rows'], $missingRows);
        }
        
        return $savedBlock;
    }
    
    /**
     * Extract all field IDs from rows
     */
    private function extractFieldIds($rows) {
        $fieldIds = array();
        
        foreach ($rows as $row) {
            if (isset($row['fields']) && is_array($row['fields'])) {
                foreach ($row['fields'] as $field) {
                    if (isset($field['id'])) {
                        $fieldIds[$field['id']] = true;
                    }
                }
            }
        }
        
        return $fieldIds;
    }
    
    /**
     * Find rows with missing fields
     */
    private function findMissingRows($defaultRows, $existingFieldIds) {
        $missingRows = array();
        
        foreach ($defaultRows as $row) {
            if (!isset($row['fields']) || !is_array($row['fields'])) {
                continue;
            }
            
            foreach ($row['fields'] as $field) {
                if (isset($field['id']) && !isset($existingFieldIds[$field['id']])) {
                    $missingRows[] = $row;
                    break;
                }
            }
        }
        
        return $missingRows;
    }
    
    /**
     * Clean up blocks for admin UI (remove legacy fields)
     */
    private function cleanupBlocksForAdminUI($blocks) {
        $legacyFields = array(self::LEGACY_NEWSLETTER_FIELD, self::LEGACY_PAYMENT_AGREE_FIELD);
        
        foreach ($blocks as &$block) {
            if (!isset($block['rows']) || !is_array($block['rows'])) {
                continue;
            }
            
            foreach ($block['rows'] as &$row) {
                if (isset($row['fields']) && is_array($row['fields'])) {
                    $row['fields'] = array_values(
                        array_filter($row['fields'], function($field) use ($legacyFields) {
                            return !isset($field['id']) || !in_array($field['id'], $legacyFields);
                        })
                    );
                }
            }
        }
        
        return $blocks;
    }

    /**
     * Normalize block/field labels and placeholders for admin UI.
     * This keeps old saved configs in sync with current localization keys.
     */
    private function normalizeBlocksForAdminUI($blocks) {
        $blockTranslations = array(
            'customer_details' => 'block_customer_details',
            'shipping_address' => 'block_shipping_address',
            'payment_address'  => 'block_payment_address',
            'shipping_method'  => 'block_shipping_method',
            'payment_method'   => 'block_payment_method',
            'coupon'           => 'block_coupon',
            'comment'          => 'block_comment',
            'terms'            => 'block_agree',
            'agree'            => 'block_agree',
            'cart'             => 'block_cart'
        );

        $fieldLabelTranslations = array(
            'firstname'          => 'entry_firstname',
            'lastname'           => 'entry_lastname',
            'email'              => 'entry_email',
            'telephone'          => 'entry_telephone',
            'fax'                => 'entry_fax',
            'company'            => 'entry_company',
            'address_1'          => 'entry_address_1',
            'address_2'          => 'entry_address_2',
            'city'               => 'entry_city',
            'postcode'           => 'entry_postcode',
            'country_id'         => 'entry_country',
            'zone_id'            => 'entry_zone',
            'payment_firstname'  => 'entry_firstname',
            'payment_lastname'   => 'entry_lastname',
            'payment_company'    => 'entry_company',
            'payment_address_1'  => 'entry_address_1',
            'payment_address_2'  => 'entry_address_2',
            'payment_city'       => 'entry_city',
            'payment_postcode'   => 'entry_postcode',
            'payment_country_id' => 'entry_country',
            'payment_zone_id'    => 'entry_zone',
            'comment'            => 'entry_comment',
            'payment_method'     => 'text_payment_method'
        );

        $fieldPlaceholderTranslations = array(
            'firstname'          => 'placeholder_firstname',
            'lastname'           => 'placeholder_lastname',
            'email'              => 'placeholder_email',
            'telephone'          => 'placeholder_telephone',
            'fax'                => 'placeholder_fax',
            'company'            => 'placeholder_company',
            'address_1'          => 'placeholder_address_1',
            'address_2'          => 'placeholder_address_2',
            'city'               => 'placeholder_city',
            'postcode'           => 'placeholder_postcode',
            'country_id'         => 'placeholder_country',
            'zone_id'            => 'placeholder_zone',
            'payment_firstname'  => 'placeholder_payment_firstname',
            'payment_lastname'   => 'placeholder_payment_lastname',
            'payment_company'    => 'placeholder_payment_company',
            'payment_address_1'  => 'placeholder_payment_address_1',
            'payment_address_2'  => 'placeholder_payment_address_2',
            'payment_city'       => 'placeholder_payment_city',
            'payment_postcode'   => 'placeholder_payment_postcode',
            'payment_country_id' => 'placeholder_country',
            'payment_zone_id'    => 'placeholder_zone',
            'comment'            => 'text_comment_placeholder'
        );

        foreach ($blocks as &$block) {
            if (isset($block['id']) && isset($blockTranslations[$block['id']])) {
                $translatedBlockName = $this->language->get($blockTranslations[$block['id']]);

                if ($translatedBlockName && $translatedBlockName !== $blockTranslations[$block['id']]) {
                    $block['name'] = $translatedBlockName;
                }
            }

            if (!isset($block['rows']) || !is_array($block['rows'])) {
                continue;
            }

            foreach ($block['rows'] as &$row) {
                if (!isset($row['fields']) || !is_array($row['fields'])) {
                    continue;
                }

                foreach ($row['fields'] as &$field) {
                    if (empty($field['id'])) {
                        continue;
                    }

                    $field_id = (string)$field['id'];

                    if (isset($fieldLabelTranslations[$field_id])) {
                        $translatedLabel = $this->language->get($fieldLabelTranslations[$field_id]);

                        if ($translatedLabel && $translatedLabel !== $fieldLabelTranslations[$field_id]) {
                            $field['label'] = $translatedLabel;
                        }
                    } elseif (isset($field['label']) && preg_match('/^(entry_|text_)/', (string)$field['label'])) {
                        $translatedLabel = $this->language->get((string)$field['label']);

                        if ($translatedLabel && $translatedLabel !== (string)$field['label']) {
                            $field['label'] = $translatedLabel;
                        }
                    }

                    if (isset($fieldPlaceholderTranslations[$field_id])) {
                        $translatedPlaceholder = $this->language->get($fieldPlaceholderTranslations[$field_id]);

                        if ($translatedPlaceholder && $translatedPlaceholder !== $fieldPlaceholderTranslations[$field_id]) {
                            $currentPlaceholder = isset($field['placeholder']) ? trim((string)$field['placeholder']) : '';

                            if ($currentPlaceholder === '' || preg_match('/^(entry_|text_|placeholder_)/', $currentPlaceholder)) {
                                $field['placeholder'] = $translatedPlaceholder;
                            }
                        }
                    }
                }
            }
        }

        return $blocks;
    }
    
    /**
     * Process blocks data: decode JSON strings and sanitize fields
     */
    private function processBlocksData($blocks) {
        foreach ($blocks as $idx => $block) {
            // Decode rows if JSON string
            if (isset($block['rows']) && is_string($block['rows'])) {
                $blocks[$idx]['rows'] = $this->decodeJsonField($block['rows']);
            }
            
            // Sanitize rows: remove legacy fields
            if (isset($blocks[$idx]['rows']) && is_array($blocks[$idx]['rows'])) {
                $blocks[$idx]['rows'] = $this->sanitizeBlockRows($blocks[$idx]['rows']);
            }
            
            // Legacy: handle old 'fields' structure
            if (isset($block['fields']) && is_string($block['fields'])) {
                $blocks[$idx]['fields'] = $this->decodeJsonField($block['fields']);
            }
        }
        
        return $blocks;
    }
    
    /**
     * Decode JSON field with HTML entity handling
     */
    private function decodeJsonField($jsonString) {
        if (empty($jsonString) || $jsonString === 'null') {
            return array();
        }
        
        // Try HTML-decoded JSON first
        $decoded = json_decode(html_entity_decode($jsonString), true);
        
        // Fallback to plain JSON
        if ($decoded === null) {
            $decoded = json_decode($jsonString, true);
        }
        
        return is_array($decoded) ? $decoded : array();
    }
    
    /**
     * Sanitize block rows: remove legacy/unsupported fields
     */
    private function sanitizeBlockRows($rows) {
        $legacyFields = array(self::LEGACY_NEWSLETTER_FIELD, self::LEGACY_PAYMENT_AGREE_FIELD);
        
        foreach ($rows as $rowIdx => $row) {
            if (isset($row['fields']) && is_array($row['fields'])) {
                $rows[$rowIdx]['fields'] = array_values(
                    array_filter($row['fields'], function($field) use ($legacyFields) {
                        return !isset($field['id']) || !in_array($field['id'], $legacyFields);
                    })
                );
            }
        }
        
        return $rows;
    }
    
    /**
     * Get setting value with default fallback
     */
    private function getSettingValue($key, $default = null) {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }
        
        $value = $this->config->get($key);
        return ($value !== null) ? $value : $default;
    }


    /**
     * Get available shipping methods from installed extensions
     * @return array Array of shipping methods with their default titles
     */
    private function getAvailableShippingMethods() {
        $methods = array();
        
        $this->load->model('setting/extension');
        $extensions = $this->model_setting_extension->getInstalled('shipping');
        
        foreach ($extensions as $code) {
            // Check if extension is enabled
            $status = $this->config->get('shipping_' . $code . '_status');
            
            if ($status) {
                // Load language file to get default title
                $this->load->language('extension/shipping/' . $code);
                
                $default_title = $this->language->get('heading_title');
                if (empty($default_title) || $default_title == 'heading_title') {
                    $default_title = ucfirst(str_replace('_', ' ', $code));
                }
                
                $methods[$code] = array(
                    'code' => $code,
                    'default_title' => $default_title,
                    'status' => $status
                );
            }
        }
        
        return $methods;
    }

    /**
     * Get available payment methods from installed extensions
     * @return array Array of payment methods with their default titles
     */
    private function getAvailablePaymentMethods() {
        $methods = array();
        
        $this->load->model('setting/extension');
        $extensions = $this->model_setting_extension->getInstalled('payment');
        
        foreach ($extensions as $code) {
            // Check if extension is enabled
            $status = $this->config->get('payment_' . $code . '_status');
            
            if ($status) {
                // Load language file to get default title
                $this->load->language('extension/payment/' . $code);
                
                $default_title = $this->language->get('heading_title');
                if (empty($default_title) || $default_title == 'heading_title') {
                    $default_title = ucfirst(str_replace('_', ' ', $code));
                }
                
                $methods[$code] = array(
                    'code' => $code,
                    'default_title' => $default_title,
                    'status' => $status
                );
            }
        }
        
        return $methods;
    }
}
