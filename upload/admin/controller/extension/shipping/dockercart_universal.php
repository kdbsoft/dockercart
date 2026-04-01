<?php
/**
 * DockerCart Universal Shipping Module
 * Flexible shipping configuration with support for multiple methods,
 * geo zones, weight-based rates, and multilingual descriptions.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ControllerExtensionShippingDockercartUniversal extends Controller {
    
    private $error = [];
    
    /**
     * Main admin page - displays list of shipping methods
     */
    public function index() {
        $this->load->language('extension/shipping/dockercart_universal');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('extension/shipping/dockercart_universal');
        $this->load->model('setting/setting');
        
        // Handle POST for general settings
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('shipping_dockercart_universal', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true));
        }
        
        // Breadcrumbs
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        // Errors
        $data['error_warning'] = $this->error['warning'] ?? '';
        
        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }
        
        // URLs
        $data['action'] = $this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
        $data['add'] = $this->url->link('extension/shipping/dockercart_universal/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];
        
        // Get shipping methods
        $methods = $this->model_extension_shipping_dockercart_universal->getMethods();
        foreach ($methods as &$method) {
            $method['edit'] = $this->url->link('extension/shipping/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . '&method_id=' . $method['method_id'], true);
        }
        unset($method);
        $data['shipping_methods'] = $methods;
        
        // Languages for display
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        
        // Config language ID
        $data['config_language_id'] = $this->config->get('config_language_id');
        
        // Settings
        if (isset($this->request->post['shipping_dockercart_universal_status'])) {
            $data['shipping_dockercart_universal_status'] = $this->request->post['shipping_dockercart_universal_status'];
        } else {
            $data['shipping_dockercart_universal_status'] = $this->config->get('shipping_dockercart_universal_status');
        }
        
        if (isset($this->request->post['shipping_dockercart_universal_sort_order'])) {
            $data['shipping_dockercart_universal_sort_order'] = $this->request->post['shipping_dockercart_universal_sort_order'];
        } else {
            $data['shipping_dockercart_universal_sort_order'] = $this->config->get('shipping_dockercart_universal_sort_order');
        }
        
        // Tax classes
        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        
        if (isset($this->request->post['shipping_dockercart_universal_tax_class_id'])) {
            $data['shipping_dockercart_universal_tax_class_id'] = $this->request->post['shipping_dockercart_universal_tax_class_id'];
        } else {
            $data['shipping_dockercart_universal_tax_class_id'] = $this->config->get('shipping_dockercart_universal_tax_class_id');
        }
        
        // Geo Zones for display
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        // Layout
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/shipping/dockercart_universal', $data));
    }
    
    /**
     * Add or edit shipping method form
     */
    public function form() {
        $this->load->language('extension/shipping/dockercart_universal');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $this->load->model('extension/shipping/dockercart_universal');
        $this->load->model('localisation/language');
        $this->load->model('localisation/geo_zone');
        $this->load->model('localisation/tax_class');
        
        // Determine if editing
        $method_id = isset($this->request->get['method_id']) ? (int)$this->request->get['method_id'] : 0;
        
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
            if ($method_id) {
                $this->model_extension_shipping_dockercart_universal->editMethod($method_id, $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success_edit');
            } else {
                $this->model_extension_shipping_dockercart_universal->addMethod($this->request->post);
                $this->session->data['success'] = $this->language->get('text_success_add');
            }
            
            $this->response->redirect($this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true));
        }
        
        // Breadcrumbs
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $method_id ? $this->language->get('text_edit_method') : $this->language->get('text_add_method'),
            'href' => $this->url->link('extension/shipping/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . ($method_id ? '&method_id=' . $method_id : ''), true)
        ];
        
        // Errors
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['error_name'] = $this->error['name'] ?? '';
        $data['error_cost'] = $this->error['cost'] ?? '';
        
        // URLs
        $data['action'] = $this->url->link('extension/shipping/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . ($method_id ? '&method_id=' . $method_id : ''), true);
        $data['cancel'] = $this->url->link('extension/shipping/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];
        
        // Languages
        $data['languages'] = $this->model_localisation_language->getLanguages();
        
        // Geo zones
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        
        // Tax classes
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
        
        // Load existing method data or defaults
        if ($method_id && ($method_info = $this->model_extension_shipping_dockercart_universal->getMethod($method_id))) {
            $data['method_id'] = $method_id;
            $data['method_description'] = $this->model_extension_shipping_dockercart_universal->getMethodDescriptions($method_id);
            
            $data['cost'] = $method_info['cost'];
            $data['cost_type'] = $method_info['cost_type'];
            $data['weight_rates'] = $method_info['weight_rates'];
            $data['geo_zone_id'] = $method_info['geo_zone_id'];
            $data['tax_class_id'] = $method_info['tax_class_id'];
            $data['min_total'] = $method_info['min_total'];
            $data['max_total'] = $method_info['max_total'];
            $data['min_weight'] = $method_info['min_weight'];
            $data['max_weight'] = $method_info['max_weight'];
            $data['free_shipping_threshold'] = $method_info['free_shipping_threshold'];
            $data['status'] = $method_info['status'];
            $data['sort_order'] = $method_info['sort_order'];
        } else {
            $data['method_id'] = 0;
            $data['method_description'] = [];
            
            $data['cost'] = '';
            $data['cost_type'] = 'fixed';
            $data['weight_rates'] = '';
            $data['geo_zone_id'] = 0;
            $data['tax_class_id'] = 0;
            $data['min_total'] = '';
            $data['max_total'] = '';
            $data['min_weight'] = '';
            $data['max_weight'] = '';
            $data['free_shipping_threshold'] = '';
            $data['status'] = 1;
            $data['sort_order'] = 0;
        }
        
        // Layout
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/shipping/dockercart_universal_form', $data));
    }
    
    /**
     * Add method - redirects to form
     */
    public function add() {
        $this->response->redirect($this->url->link('extension/shipping/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'], true));
    }
    
    /**
     * Delete shipping method via AJAX
     */
    public function delete() {
        $this->load->language('extension/shipping/dockercart_universal');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_universal')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->post['method_id'])) {
                $this->load->model('extension/shipping/dockercart_universal');
                $this->model_extension_shipping_dockercart_universal->deleteMethod((int)$this->request->post['method_id']);
                $json['success'] = $this->language->get('text_success_delete');
            } else {
                $json['error'] = $this->language->get('error_method_id');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Install - creates database tables
     */
    public function install() {
        $this->load->model('extension/shipping/dockercart_universal');
        $this->model_extension_shipping_dockercart_universal->install();
    }
    
    /**
     * Uninstall - removes database tables
     */
    public function uninstall() {
        $this->load->model('extension/shipping/dockercart_universal');
        $this->model_extension_shipping_dockercart_universal->uninstall();
    }
    
    /**
     * Validate main settings
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_universal')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        return !$this->error;
    }
    
    /**
     * Validate shipping method form
     */
    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_universal')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        // Validate name for default language
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        
        foreach ($languages as $language) {
            if (empty($this->request->post['method_description'][$language['language_id']]['name'])) {
                $this->error['name'][$language['language_id']] = $this->language->get('error_name');
            }
        }
        
        // Validate cost for fixed type (allow empty for no-price methods)
        if (isset($this->request->post['cost_type']) && $this->request->post['cost_type'] == 'fixed') {
            if (isset($this->request->post['cost']) && $this->request->post['cost'] !== '' && !is_numeric($this->request->post['cost'])) {
                $this->error['cost'] = $this->language->get('error_cost');
            }
        }
        
        return !$this->error;
    }
}
