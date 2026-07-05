<?php
/**
 * DockerCart 1-Click Checkout Module
 * Admin Controller
 * 
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    Commercial
 * @version    1.0.0
 */

class ControllerExtensionModuleDockercartOneclickcheckout extends Controller {
    
    private $error = array();
    
    /**
     * Module settings page
     */
    public function index() {
        $this->load->language('extension/module/dockercart_oneclickcheckout');
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_oneclickcheckout', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        // Error messages
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
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_oneclickcheckout', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        // URLs
        $data['action'] = $this->url->link('extension/module/dockercart_oneclickcheckout', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        // Get languages
        $data['languages'] = $this->model_localisation_language->getLanguages();
        
        // Get module settings
        if (isset($this->request->post['module_dockercart_oneclickcheckout_status'])) {
            $data['module_dockercart_oneclickcheckout_status'] = $this->request->post['module_dockercart_oneclickcheckout_status'];
        } else {
            $data['module_dockercart_oneclickcheckout_status'] = $this->config->get('module_dockercart_oneclickcheckout_status');
        }
        
        // Field settings
        $fields = array('firstname', 'lastname', 'telephone', 'email', 'comment', 'address', 'city', 'postcode', 'country', 'zone');
        
        foreach ($fields as $field) {
            // Required field
            $key = 'module_dockercart_oneclickcheckout_field_' . $field . '_required';
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $default = in_array($field, array('firstname', 'lastname', 'telephone', 'email')) ? 1 : 0;
                $data[$key] = $this->config->get($key) !== null ? $this->config->get($key) : $default;
            }
            
            // Show field
            $key = 'module_dockercart_oneclickcheckout_field_' . $field . '_show';
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $default = in_array($field, array('firstname', 'lastname', 'telephone', 'email', 'comment')) ? 1 : 0;
                $data[$key] = $this->config->get($key) !== null ? $this->config->get($key) : $default;
            }
        }
        
        // Use captcha setting
        if (isset($this->request->post['module_dockercart_oneclickcheckout_use_captcha'])) {
            $data['module_dockercart_oneclickcheckout_use_captcha'] = $this->request->post['module_dockercart_oneclickcheckout_use_captcha'];
        } else {
            $data['module_dockercart_oneclickcheckout_use_captcha'] = $this->config->get('module_dockercart_oneclickcheckout_use_captcha');
        }
        
        // Button text (multilingual)
        if (isset($this->request->post['module_dockercart_oneclickcheckout_button_text'])) {
            $data['module_dockercart_oneclickcheckout_button_text'] = $this->request->post['module_dockercart_oneclickcheckout_button_text'];
        } else {
            $data['module_dockercart_oneclickcheckout_button_text'] = $this->config->get('module_dockercart_oneclickcheckout_button_text');
        }
        
        // Modal title (multilingual)
        if (isset($this->request->post['module_dockercart_oneclickcheckout_modal_title'])) {
            $data['module_dockercart_oneclickcheckout_modal_title'] = $this->request->post['module_dockercart_oneclickcheckout_modal_title'];
        } else {
            $data['module_dockercart_oneclickcheckout_modal_title'] = $this->config->get('module_dockercart_oneclickcheckout_modal_title');
        }
        
        // Color theme
        if (isset($this->request->post['module_dockercart_oneclickcheckout_color_theme'])) {
            $data['module_dockercart_oneclickcheckout_color_theme'] = $this->request->post['module_dockercart_oneclickcheckout_color_theme'];
        } else {
            $data['module_dockercart_oneclickcheckout_color_theme'] = $this->config->get('module_dockercart_oneclickcheckout_color_theme');
            if (empty($data['module_dockercart_oneclickcheckout_color_theme'])) {
                $data['module_dockercart_oneclickcheckout_color_theme'] = 'theme-purple';
            }
        }
        
        // Color themes list
        $data['color_themes'] = array(
            'theme-purple' => 'Purple Gradient',
            'theme-blue' => 'Blue Ocean',
            'theme-green' => 'Green Forest',
            'theme-red' => 'Red Fire',
            'theme-orange' => 'Orange Sunset',
            'theme-pink' => 'Pink Rose',
            'theme-dark' => 'Dark Night',
            'theme-gold' => 'Gold Luxury'
        );
        
        // License key
        if (isset($this->request->post['module_dockercart_oneclickcheckout_license_key'])) {
            $data['module_dockercart_oneclickcheckout_license_key'] = $this->request->post['module_dockercart_oneclickcheckout_license_key'];
        } else {
            $data['module_dockercart_oneclickcheckout_license_key'] = $this->config->get('module_dockercart_oneclickcheckout_license_key');
        }

        $data['text_tab_license'] = $this->language->get('text_tab_license');
        $data['text_tab_license_subtitle'] = $this->language->get('text_tab_license_subtitle');
        $data['text_license_info'] = $this->language->get('text_license_info');
        $data['text_license_domain'] = $this->language->get('text_license_domain');
        $data['entry_license_key'] = $this->language->get('entry_license_key');
        $data['help_license_key'] = $this->language->get('help_license_key');
        $data['error_license_invalid'] = $this->language->get('error_license_invalid');
        $data['button_verify_license'] = $this->language->get('button_verify_license');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['success_license_valid'] = $this->language->get('success_license_valid');

        $data['license_valid'] = true;

        // Public key
        if (isset($this->request->post['module_dockercart_oneclickcheckout_public_key'])) {
            $data['module_dockercart_oneclickcheckout_public_key'] = $this->request->post['module_dockercart_oneclickcheckout_public_key'];
        } else {
            $data['module_dockercart_oneclickcheckout_public_key'] = $this->config->get('module_dockercart_oneclickcheckout_public_key');
        }
        
        // User token for AJAX
        $data['user_token'] = $this->session->data['user_token'];
        $data['license_domain'] =
            (!empty($this->request->server['HTTP_HOST']) ? $this->request->server['HTTP_HOST'] : '')
            ?: (defined('HTTPS_CATALOG') && HTTPS_CATALOG ? parse_url(HTTPS_CATALOG, PHP_URL_HOST) : '')
            ?: (defined('HTTP_CATALOG') && HTTP_CATALOG ? parse_url(HTTP_CATALOG, PHP_URL_HOST) : '')
            ?: (!empty($this->config->get('config_url')) ? parse_url($this->config->get('config_url'), PHP_URL_HOST) : '')
            ?: 'localhost';
        
        // Order status
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        if (isset($this->request->post['module_dockercart_oneclickcheckout_order_status_id'])) {
            $data['module_dockercart_oneclickcheckout_order_status_id'] = $this->request->post['module_dockercart_oneclickcheckout_order_status_id'];
        } else {
            $data['module_dockercart_oneclickcheckout_order_status_id'] = $this->config->get('module_dockercart_oneclickcheckout_order_status_id');
        }
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/dockercart_oneclickcheckout', $data));
    }
    
    /**
     * Install module - register events
     */
    public function install() {
        $this->load->model('setting/event');
        
        // Register events
        $events = [
            // Catalog: Add 1-click button after "Add to Cart" button on product page
            [
                'code'    => 'dockercart_oneclickcheckout_product_view',
                'trigger' => 'catalog/view/product/product/after',
                'action'  => 'extension/module/dockercart_oneclickcheckout/eventProductViewAfter'
            ]
        ];
        
        foreach ($events as $event) {
            // Delete if exists (for clean reinstall)
            $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($event['code']) . "'");
            
            // Add event
            $this->model_setting_event->addEvent(
                $event['code'],
                $event['trigger'],
                $event['action']
            );
        }
        
        // Set default settings
        $this->load->model('setting/setting');
        $defaults = array(
            'module_dockercart_oneclickcheckout_status' => 0,
            'module_dockercart_oneclickcheckout_field_firstname_required' => 1,
            'module_dockercart_oneclickcheckout_field_firstname_show' => 1,
            'module_dockercart_oneclickcheckout_field_lastname_required' => 1,
            'module_dockercart_oneclickcheckout_field_lastname_show' => 1,
            'module_dockercart_oneclickcheckout_field_telephone_required' => 1,
            'module_dockercart_oneclickcheckout_field_telephone_show' => 1,
            'module_dockercart_oneclickcheckout_field_email_required' => 1,
            'module_dockercart_oneclickcheckout_field_email_show' => 1,
            'module_dockercart_oneclickcheckout_field_comment_required' => 0,
            'module_dockercart_oneclickcheckout_field_comment_show' => 1,
            'module_dockercart_oneclickcheckout_field_address_required' => 0,
            'module_dockercart_oneclickcheckout_field_address_show' => 0,
            'module_dockercart_oneclickcheckout_field_city_required' => 0,
            'module_dockercart_oneclickcheckout_field_city_show' => 0,
            'module_dockercart_oneclickcheckout_field_postcode_required' => 0,
            'module_dockercart_oneclickcheckout_field_postcode_show' => 0,
            'module_dockercart_oneclickcheckout_field_country_required' => 0,
            'module_dockercart_oneclickcheckout_field_country_show' => 0,
            'module_dockercart_oneclickcheckout_field_zone_required' => 0,
            'module_dockercart_oneclickcheckout_field_zone_show' => 0,
            'module_dockercart_oneclickcheckout_use_captcha' => 0,
            'module_dockercart_oneclickcheckout_order_status_id' => 1,
        );
        
        $this->model_setting_setting->editSetting('module_dockercart_oneclickcheckout', $defaults);
    }
    
    /**
     * Uninstall module - remove events
     */
    public function uninstall() {
        $this->load->model('setting/event');
        
        // Remove all module events
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'dockercart_oneclickcheckout_%'");
    }
    
    /**
     * Validate form data
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_oneclickcheckout')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        return !$this->error;
    }
    
    /**
     * Проверка лицензии для работы модуля (блокирует работу если нет лицензии)
     */
    private function checkLicenseForOperation() {
        if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
            return false;
        }

        require_once DIR_SYSTEM . 'library/dockercart/licensing.php';

        $licensing = new DockercartLicensing($this->registry);

        return $licensing->check('dockercart_oneclickcheckout');
    }
}
