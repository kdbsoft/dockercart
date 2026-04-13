<?php
/**
 * DockerCart Search Module - Admin Controller
 * 
 * Provides Manticore Search integration for OpenCart
 * Handles module settings, indexing, and search configuration
 * 
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.2
 */

class ControllerExtensionModuleDockercartSearch extends Controller {
    private $error = [];
    private $logger;
    private $module_version = '1.0.2';
    
    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'search');
    }
    
    /**
     * Main module settings page
     */
    public function index() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Handle form submission
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
            if (isset($this->request->post['module_dockercart_search_query_mappings'])) {
                $this->request->post['module_dockercart_search_query_mappings'] = $this->normalizeQueryMappingsText($this->request->post['module_dockercart_search_query_mappings']);
            }

            $this->model_setting_setting->editSetting('module_dockercart_search', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        // Prepare data for view
        $data = $this->prepareViewData();
        
        // Load languages for multi-language settings
        $data['languages'] = $this->model_localisation_language->getLanguages();
        
        // Load header, column left, footer
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/dockercart_search', $data));
    }
    
    /**
     * Prepare data for view
     */
    private function prepareViewData() {
        $data = [];
        
        // Breadcrumbs
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true)
        ];
        
        // Actions
        $data['action'] = $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['reindex_url'] = $this->url->link('extension/module/dockercart_search/reindex', 'user_token=' . $this->session->data['user_token'], true);
        $data['test_connection_url'] = $this->url->link('extension/module/dockercart_search/testConnection', 'user_token=' . $this->session->data['user_token'], true);
        $data['apply_morphology_url'] = $this->url->link('extension/module/dockercart_search/applyMorphology', 'user_token=' . $this->session->data['user_token'], true);
        
        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        
        // Errors
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        
        // Module settings
        $data['module_dockercart_search_status'] = $this->getConfigValue('module_dockercart_search_status', 0);
        $data['module_dockercart_search_host'] = $this->getConfigValue('module_dockercart_search_host', 'manticore');
        $data['module_dockercart_search_port'] = $this->getConfigValue('module_dockercart_search_port', 9306);
        $data['module_dockercart_search_http_port'] = $this->getConfigValue('module_dockercart_search_http_port', 9308);
        $data['module_dockercart_search_autocomplete'] = $this->getConfigValue('module_dockercart_search_autocomplete', 1);
        $data['module_dockercart_search_autocomplete_limit'] = $this->getConfigValue('module_dockercart_search_autocomplete_limit', 10);
        $data['module_dockercart_search_min_chars'] = $this->getConfigValue('module_dockercart_search_min_chars', 3);
        $data['module_dockercart_search_results_limit'] = $this->getConfigValue('module_dockercart_search_results_limit', 20);
        $data['module_dockercart_search_query_mappings'] = $this->getConfigValue('module_dockercart_search_query_mappings', '');
        
        // Note: Morphology is configured in docker/manticore/manticore.conf
        // Current settings: stem_en, lemmatize_ru
        
        $data['user_token'] = $this->session->data['user_token'];
        $data['module_version'] = $this->module_version;
        
        // Check Manticore connection
        $data['manticore_connected'] = $this->model_extension_module_dockercart_search->testConnection();
        
        return $data;
    }
    
    /**
     * Get config value with default
     */
    private function getConfigValue($key, $default = null) {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        } elseif ($this->config->has($key)) {
            return $this->config->get($key);
        }
        
        return $default;
    }
    
    /**
     * Test Manticore connection (AJAX)
     */
    public function testConnection() {
        $this->load->model('extension/module/dockercart_search');
        
        $json = [];
        
        if ($this->model_extension_module_dockercart_search->testConnection()) {
            $json['success'] = true;
            $json['message'] = 'Successfully connected to Manticore Search';
        } else {
            $json['success'] = false;
            $json['message'] = 'Failed to connect to Manticore Search: ' . $this->model_extension_module_dockercart_search->getLastError();
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Reindex all products (AJAX)
     */
    public function reindex() {
        $this->load->model('extension/module/dockercart_search');
        $this->load->language('extension/module/dockercart_search');
        
        $json = [];
        
        try {
            $result = $this->model_extension_module_dockercart_search->reindexAll();
            
            if ($result['success']) {
                $json['success'] = true;
                $json['message'] = sprintf(
                    'Reindexing completed: %d products, %d categories, %d manufacturers, %d information pages',
                    $result['products'],
                    $result['categories'],
                    $result['manufacturers'],
                    $result['information']
                );
            } else {
                $json['success'] = false;
                $json['message'] = 'Reindexing failed: ' . $result['error'];
            }
        } catch (Exception $e) {
            $json['success'] = false;
            $json['message'] = 'Exception: ' . $e->getMessage();
            $this->logger->error('Reindex exception: ' . $e->getMessage());
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Install module - creates database table and registers events
     */
    public function install() {
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('setting/event');
        $this->load->model('setting/setting');
        
        // Register events for automatic indexing
        $events = [
            // Product events
            [
                'code'    => 'dockercart_search_product_add',
                'trigger' => 'admin/model/catalog/product/addProduct/after',
                'action'  => 'extension/module/dockercart_search/eventProductAdd'
            ],
            [
                'code'    => 'dockercart_search_product_edit',
                'trigger' => 'admin/model/catalog/product/editProduct/after',
                'action'  => 'extension/module/dockercart_search/eventProductEdit'
            ],
            [
                'code'    => 'dockercart_search_product_delete',
                'trigger' => 'admin/model/catalog/product/deleteProduct/after',
                'action'  => 'extension/module/dockercart_search/eventProductDelete'
            ],
            
            // Category events
            [
                'code'    => 'dockercart_search_category_add',
                'trigger' => 'admin/model/catalog/category/addCategory/after',
                'action'  => 'extension/module/dockercart_search/eventCategoryAdd'
            ],
            [
                'code'    => 'dockercart_search_category_edit',
                'trigger' => 'admin/model/catalog/category/editCategory/after',
                'action'  => 'extension/module/dockercart_search/eventCategoryEdit'
            ],
            [
                'code'    => 'dockercart_search_category_delete',
                'trigger' => 'admin/model/catalog/category/deleteCategory/after',
                'action'  => 'extension/module/dockercart_search/eventCategoryDelete'
            ],
            
            // Manufacturer events
            [
                'code'    => 'dockercart_search_manufacturer_add',
                'trigger' => 'admin/model/catalog/manufacturer/addManufacturer/after',
                'action'  => 'extension/module/dockercart_search/eventManufacturerAdd'
            ],
            [
                'code'    => 'dockercart_search_manufacturer_edit',
                'trigger' => 'admin/model/catalog/manufacturer/editManufacturer/after',
                'action'  => 'extension/module/dockercart_search/eventManufacturerEdit'
            ],
            [
                'code'    => 'dockercart_search_manufacturer_delete',
                'trigger' => 'admin/model/catalog/manufacturer/deleteManufacturer/after',
                'action'  => 'extension/module/dockercart_search/eventManufacturerDelete'
            ],
            
            // Information events
            [
                'code'    => 'dockercart_search_information_add',
                'trigger' => 'admin/model/catalog/information/addInformation/after',
                'action'  => 'extension/module/dockercart_search/eventInformationAdd'
            ],
            [
                'code'    => 'dockercart_search_information_edit',
                'trigger' => 'admin/model/catalog/information/editInformation/after',
                'action'  => 'extension/module/dockercart_search/eventInformationEdit'
            ],
            [
                'code'    => 'dockercart_search_information_delete',
                'trigger' => 'admin/model/catalog/information/deleteInformation/after',
                'action'  => 'extension/module/dockercart_search/eventInformationDelete'
            ],
            
            // Frontend: Add autocomplete script to header
            [
                'code'    => 'dockercart_search_autocomplete',
                'trigger' => 'catalog/view/common/header/after',
                'action'  => 'extension/module/dockercart_search/addAutocompleteScript'
            ],
            
            // Frontend: Override standard search with Manticore (after getProducts completes)
            [
                'code'    => 'dockercart_search_override',
                'trigger' => 'catalog/model/catalog/product/getProducts/after',
                'action'  => 'extension/module/dockercart_search/overrideGetProducts'
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
        $this->model_setting_setting->editSetting('module_dockercart_search', [
            'module_dockercart_search_status' => 0,
            'module_dockercart_search_host' => 'manticore',
            'module_dockercart_search_port' => 9306,
            'module_dockercart_search_http_port' => 9308,
            'module_dockercart_search_autocomplete' => 1,
            'module_dockercart_search_autocomplete_limit' => 10,
            'module_dockercart_search_min_chars' => 3,
            'module_dockercart_search_results_limit' => 20,
            'module_dockercart_search_query_mappings' => ''
        ]);
        
        $this->logger->info('Module installed successfully');
    }
    
    /**
     * Uninstall module - removes events
     */
    public function uninstall() {
        $this->load->model('setting/event');
        
        // Remove all events
        $events = [
            'dockercart_search_product_add',
            'dockercart_search_product_edit',
            'dockercart_search_product_delete',
            'dockercart_search_category_add',
            'dockercart_search_category_edit',
            'dockercart_search_category_delete',
            'dockercart_search_manufacturer_add',
            'dockercart_search_manufacturer_edit',
            'dockercart_search_manufacturer_delete',
            'dockercart_search_information_add',
            'dockercart_search_information_edit',
            'dockercart_search_information_delete',
            'dockercart_search_override'
        ];
        
        foreach ($events as $event_code) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($event_code) . "'");
        }
        
        $this->logger->info('Module uninstalled successfully');
    }
    
    /**
     * Validate form data
     */
    private function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_search')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        return !$this->error;
    }

    /**
     * Normalize query mappings text before save.
     * Keeps one mapping per line and strips empty trailing spaces.
     */
    private function normalizeQueryMappingsText($raw_text) {
        $lines = preg_split('/\R/u', (string)$raw_text);
        $normalized = [];

        foreach ($lines as $line) {
            $normalized[] = trim((string)$line);
        }

        return trim(implode("\n", $normalized));
    }
    
    // Event handlers (will be called by OpenCart event system)

    public function eventProductAdd($route, $args, $output) {
        if ($this->config->get('module_dockercart_search_status')) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexProduct($output, $language['language_id']);
            }

            $this->logger->info("Product {$output} indexed for all languages");
        }
    }

    public function eventProductEdit($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexProduct($args[0], $language['language_id']);
            }

            $this->logger->info("Product {$args[0]} re-indexed for all languages");
        }
    }

    public function eventProductDelete($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteProduct($args[0]);

            $this->logger->info("Product {$args[0]} deleted from index");
        }
    }

    public function eventCategoryAdd($route, $args, $output) {
        if ($this->config->get('module_dockercart_search_status')) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexCategory($output, $language['language_id']);
            }

            $this->logger->info("Category {$output} indexed for all languages");
        }
    }

    public function eventCategoryEdit($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexCategory($args[0], $language['language_id']);
            }

            $this->logger->info("Category {$args[0]} re-indexed for all languages");
        }
    }

    public function eventCategoryDelete($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteCategory($args[0]);

            $this->logger->info("Category {$args[0]} deleted from index");
        }
    }

    public function eventManufacturerAdd($route, $args, $output) {
        if ($this->config->get('module_dockercart_search_status')) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexManufacturer($output);

            $this->logger->info("Manufacturer {$output} indexed");
        }
    }

    public function eventManufacturerEdit($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexManufacturer($args[0]);

            $this->logger->info("Manufacturer {$args[0]} re-indexed");
        }
    }

    public function eventManufacturerDelete($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteManufacturer($args[0]);

            $this->logger->info("Manufacturer {$args[0]} deleted from index");
        }
    }

    public function eventInformationAdd($route, $args, $output) {
        if ($this->config->get('module_dockercart_search_status')) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexInformation($output, $language['language_id']);
            }

            $this->logger->info("Information page {$output} indexed for all languages");
        }
    }

    public function eventInformationEdit($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->load->model('localisation/language');

            $languages = $this->model_localisation_language->getLanguages();

            foreach ($languages as $language) {
                $this->model_extension_module_dockercart_search->indexInformation($args[0], $language['language_id']);
            }

            $this->logger->info("Information page {$args[0]} re-indexed for all languages");
        }
    }

    public function eventInformationDelete($route, $args) {
        if ($this->config->get('module_dockercart_search_status') && isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteInformation($args[0]);

            $this->logger->info("Information page {$args[0]} deleted from index");
        }
    }
}
