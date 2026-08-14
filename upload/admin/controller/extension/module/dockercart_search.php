<?php
/**
 * DockerCart Search Module - Admin Controller
 *
 * Provides Manticore Search integration for OpenCart
 * Handles module settings, indexing, and search configuration
 *
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.3
 */

class ControllerExtensionModuleDockercartSearch extends Controller {
    private $error = [];
    private $logger;

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);

        // Initialize centralized logger
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
        // Actions
        $data['action'] = $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['reindex_url'] = $this->url->link('extension/module/dockercart_search/reindex', 'user_token=' . $this->session->data['user_token'], true);
        $data['test_connection_url'] = $this->url->link('extension/module/dockercart_search/testConnection', 'user_token=' . $this->session->data['user_token'], true);
        $data['apply_morphology_url'] = $this->url->link('extension/module/dockercart_search/applyMorphology', 'user_token=' . $this->session->data['user_token'], true);
        $data['mappings_url'] = $this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true);

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        // Errors
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // Module settings
        $data['module_dockercart_search_host'] = $this->getConfigValue('module_dockercart_search_host', 'manticore');
        $data['module_dockercart_search_port'] = $this->getConfigValue('module_dockercart_search_port', 9306);
        $data['module_dockercart_search_http_port'] = $this->getConfigValue('module_dockercart_search_http_port', 9308);
        $data['module_dockercart_search_autocomplete'] = $this->getConfigValue('module_dockercart_search_autocomplete', 1);
        $data['module_dockercart_search_voice'] = $this->getConfigValue('module_dockercart_search_voice', 1);
        $data['module_dockercart_search_autocomplete_limit'] = $this->getConfigValue('module_dockercart_search_autocomplete_limit', 10);
        $data['module_dockercart_search_min_chars'] = $this->getConfigValue('module_dockercart_search_min_chars', 2);
        $data['module_dockercart_search_results_limit'] = $this->getConfigValue('module_dockercart_search_results_limit', 20);

        // Note: Morphology is configured in docker/manticore/manticore.conf
        // Current settings: stem_en, lemmatize_ru

        $data['user_token'] = $this->session->data['user_token'];

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
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        $json = [];

        if ($this->model_extension_module_dockercart_search->testConnection()) {
            $json['success'] = true;
            $json['message'] = $this->language->get('text_connection_success');
        } else {
            $json['success'] = false;
            $json['message'] = $this->language->get('text_connection_failed') . ' ' . $this->model_extension_module_dockercart_search->getLastError();
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
                    $this->language->get('text_reindex_success'),
                    $result['products'],
                    $result['categories'],
                    $result['manufacturers'],
                    $result['information']
                );
            } else {
                $json['success'] = false;
                $json['message'] = $this->language->get('text_reindex_failed') . ' ' . $result['error'];
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
        $this->load->model('setting/setting');

        // Create the query mappings table and migrate any legacy config data
        $this->model_extension_module_dockercart_search->ensureMappingTable();

        // Set default settings
        $this->model_setting_setting->editSetting('module_dockercart_search', [
            'module_dockercart_search_host' => 'manticore',
            'module_dockercart_search_port' => 9306,
            'module_dockercart_search_http_port' => 9308,
            'module_dockercart_search_autocomplete' => 1,
            'module_dockercart_search_voice' => 1,
            'module_dockercart_search_autocomplete_limit' => 10,
            'module_dockercart_search_min_chars' => 2,
            'module_dockercart_search_results_limit' => 20,
            'module_dockercart_search_admin_fallback' => 1,
            'module_dockercart_search_query_mappings' => ''
        ]);

        $this->logger->info('Module installed successfully');

        // Register scheduled Manticore reindex task (daily).
        // Manticore stores RT indexes in a tmpfs volume — they are wiped on
        // container restart, so a periodic reindex keeps them warm even when
        // the boot-time background reindex does not run.
        $this->dockercart_scheduler->registerTask(
            'manticore_search_reindex',
            'Manticore Search Reindex',
            'php /var/www/html/bin/dockercart_search_reindex.php',
            'daily',
            true
        );
    }

    /**
     * Uninstall is a no-op for this system module — events are managed in init.sql.
     */
    public function uninstall() {
        $this->logger->info('System module uninstall is a no-op');
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
     * Query mappings list page.
     * Mappings are stored in a single module setting (one `source=target` per
     * line), but managed here as a table with add/edit/delete and CSV import/export.
     */
    public function mappings() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        $this->document->setTitle($this->language->get('heading_mappings'));

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_search')) {
            $data['error_warning'] = $this->language->get('error_permission');
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = $this->session->data['error_warning'];
            unset($this->session->data['error_warning']);
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $total = $this->model_extension_module_dockercart_search->getTotalQueryMappings();

        if (isset($this->request->get['page']) && (int)$this->request->get['page'] > 0) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }

        $limit = 20;
        $offset = ($page - 1) * $limit;

        $data['mappings'] = [];

        foreach ($this->model_extension_module_dockercart_search->getQueryMappings($offset, $limit) as $mapping) {
            $mapping_id = (int)$mapping['mapping_id'];

            $data['mappings'][] = [
                'mapping_id' => $mapping_id,
                'source'     => $mapping['source'],
                'target'     => $mapping['target'],
                'edit'       => $this->url->link('extension/module/dockercart_search/mapping', 'user_token=' . $this->session->data['user_token'] . '&mapping_id=' . $mapping_id, true),
                'delete'     => $this->url->link('extension/module/dockercart_search/delete', 'user_token=' . $this->session->data['user_token'] . '&mapping_id=' . $mapping_id, true)
            ];
        }

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

        $data['add'] = $this->url->link('extension/module/dockercart_search/mapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true);
        $data['export_url'] = $this->url->link('extension/module/dockercart_search/exportCsv', 'user_token=' . $this->session->data['user_token'], true);
        $data['import_url'] = $this->url->link('extension/module/dockercart_search/importCsv', 'user_token=' . $this->session->data['user_token'], true);

        $data['user_token'] = $this->session->data['user_token'];
        $data['page'] = $page;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_search_mappings', $data));
    }

    /**
     * Add/edit a single query mapping.
     */
    public function mapping() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        $this->document->setTitle($this->language->get('heading_mapping'));

        if (isset($this->request->get['mapping_id'])) {
            $mapping_id = (int)$this->request->get['mapping_id'];
        } else {
            $mapping_id = 0;
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
            $source = trim((string)$this->request->post['source']);
            $target = trim((string)$this->request->post['target']);

            if ($source === '' || strpos($source, '=') !== false || preg_match('/[\r\n]/u', $source)) {
                $this->error['source'] = $this->language->get('error_source');
            }

            if ($target === '' || preg_match('/[\r\n]/u', $target)) {
                $this->error['target'] = $this->language->get('error_target');
            }

            if (!$this->error) {
                $this->model_extension_module_dockercart_search->saveQueryMapping($mapping_id, $source, $target);

                $this->session->data['success'] = $this->language->get('text_success_mapping');

                $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
            }
        }

        $data = [];

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_source'] = isset($this->error['source']) ? $this->error['source'] : '';
        $data['error_target'] = isset($this->error['target']) ? $this->error['target'] : '';

        $data['cancel'] = $this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true);

        $data['mapping_id'] = $mapping_id;

        if (isset($this->request->post['source'])) {
            $data['source'] = $this->request->post['source'];
        } elseif ($mapping_id > 0) {
            $mapping = $this->model_extension_module_dockercart_search->getQueryMapping($mapping_id);

            if ($mapping !== null) {
                $data['source'] = $mapping['source'];
                $data['target'] = $mapping['target'];
            } else {
                $data['source'] = '';
                $data['target'] = '';
            }
        } else {
            $data['source'] = '';
            $data['target'] = '';
        }

        if (isset($this->request->post['target'])) {
            $data['target'] = $this->request->post['target'];
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_search_mapping_form', $data));
    }

    /**
     * Delete a single query mapping (POST).
     */
    public function delete() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm() && isset($this->request->get['mapping_id'])) {
            $this->model_extension_module_dockercart_search->deleteQueryMapping((int)$this->request->get['mapping_id']);

            $this->session->data['success'] = $this->language->get('text_success_delete');
        }

        $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Export query mappings as CSV (UTF-8 with BOM for Excel compatibility).
     */
    public function exportCsv() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_search')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');

            $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
        }

        $rows = $this->model_extension_module_dockercart_search->getQueryMappings();

        $csv = "\xEF\xBB\xBF" . 'source,target' . "\n";

        foreach ($rows as $row) {
            $csv .= $this->csvField($row['source']) . ',' . $this->csvField($row['target']) . "\n";
        }

        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="dockercart_search_mappings.csv"');
        $this->response->addHeader('Content-Length: ' . strlen($csv));
        $this->response->setOutput($csv);
    }

    /**
     * Import query mappings from an uploaded CSV file.
     * Existing sources are replaced case-insensitively.
     */
    public function importCsv() {
        $this->load->language('extension/module/dockercart_search');
        $this->load->model('extension/module/dockercart_search');

        if ($this->request->server['REQUEST_METHOD'] != 'POST') {
            $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
        }

        if (!$this->validateForm()) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');

            $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
        }

        if (!isset($_FILES['file']) || (int)$_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->session->data['error_warning'] = $this->language->get('error_upload');

            $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
        }

        $handle = fopen($_FILES['file']['tmp_name'], 'r');

        if (!$handle) {
            $this->session->data['error_warning'] = $this->language->get('error_upload');

            $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
        }

        $rows = [];
        $skipped = 0;
        $imported = 0;
        $first_row = true;

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if (!is_array($data) || count($data) < 2) {
                $skipped++;
                continue;
            }

            $source = ltrim(trim((string)$data[0]), "\xEF\xBB\xBF");
            $target = trim((string)$data[1]);

            // Skip header row (source,target) if present
            if ($first_row && mb_strtolower($source, 'UTF-8') === 'source' && mb_strtolower($target, 'UTF-8') === 'target') {
                $first_row = false;
                continue;
            }

            $first_row = false;

            if ($source === '' || $target === '' || strpos($source, '#') === 0 || strpos($source, '//') === 0) {
                $skipped++;
                continue;
            }

            $rows[] = ['source' => $source, 'target' => $target];
            $imported++;
        }

        fclose($handle);

        $this->model_extension_module_dockercart_search->insertMappings($rows);

        $message = sprintf($this->language->get('text_import_success'), $imported);

        if ($skipped > 0) {
            $message .= ' ' . sprintf($this->language->get('text_import_skipped'), $skipped);
        }

        $this->session->data['success'] = $message;

        $this->response->redirect($this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Escape a value for CSV output (always quoted, quotes doubled).
     *
     * @param string $value
     * @return string
     */
    private function csvField($value) {
        return '"' . str_replace('"', '""', (string)$value) . '"';
    }

    // Event handlers (will be called by OpenCart event system)

	public function eventProductAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->model_extension_module_dockercart_search->indexProduct($output, $language['language_id']);
        }

        $this->logger->info("Product {$output} indexed for all languages");
    }

    public function eventProductEdit($route, $args) {
        if (isset($args[0])) {
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
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteProduct($args[0]);

            $this->logger->info("Product {$args[0]} deleted from index");
        }
    }

    /**
     * Reindex the parent product after a variant is added.
     * addVariant($product_id, $data) — product_id is args[0].
     */
    public function eventVariantAdd($route, $args) {
        if (isset($args[0])) {
            $this->reindexProductByVariantProductId($args[0]);
        }
    }

    /**
     * Reindex the parent product after a variant is updated.
     * updateVariant($variant_id, $data) — variant_id is args[0].
     */
    public function eventVariantEdit($route, $args) {
        if (isset($args[0])) {
            $this->reindexProductByVariantId((int)$args[0]);
        }
    }

    /**
     * Reindex the parent product after a variant is deleted.
     * deleteVariant($variant_id) — variant_id is args[0].
     */
    public function eventVariantDelete($route, $args) {
        if (isset($args[0])) {
            $this->reindexProductByVariantId((int)$args[0]);
        }
    }

    /**
     * Reindex the parent product after all variants are deleted.
     * deleteAllVariants($product_id) — product_id is args[0].
     */
    public function eventVariantDeleteAll($route, $args) {
        if (isset($args[0])) {
            $this->reindexProductByVariantProductId($args[0]);
        }
    }

    /**
     * Resolve product_id from a variant_id and reindex all languages of that product.
     */
    private function reindexProductByVariantId($variant_id) {
        $query = $this->db->query("
            SELECT product_id FROM " . DB_PREFIX . "product_variant
            WHERE variant_id = '" . (int)$variant_id . "'
        ");

        if ($query->num_rows) {
            $this->reindexProductByVariantProductId((int)$query->row['product_id']);
        }
    }

    /**
     * Reindex a product (by product_id) across all languages.
     */
    private function reindexProductByVariantProductId($product_id) {
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->model_extension_module_dockercart_search->indexProduct((int)$product_id, $language['language_id']);
        }

        $this->logger->info("Product {$product_id} re-indexed after variant change");
    }

    public function eventCategoryAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->model_extension_module_dockercart_search->indexCategory($output, $language['language_id']);
        }

        $this->logger->info("Category {$output} indexed for all languages");
    }

    public function eventCategoryEdit($route, $args) {
        if (isset($args[0])) {
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
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteCategory($args[0]);

            $this->logger->info("Category {$args[0]} deleted from index");
        }
    }

    public function eventManufacturerAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->model_extension_module_dockercart_search->indexManufacturer($output);

        $this->logger->info("Manufacturer {$output} indexed");
    }

    public function eventManufacturerEdit($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexManufacturer($args[0]);

            $this->logger->info("Manufacturer {$args[0]} re-indexed");
        }
    }

    public function eventManufacturerDelete($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteManufacturer($args[0]);

            $this->logger->info("Manufacturer {$args[0]} deleted from index");
        }
    }

    public function eventInformationAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->model_extension_module_dockercart_search->indexInformation($output, $language['language_id']);
        }

        $this->logger->info("Information page {$output} indexed for all languages");
    }

    public function eventInformationEdit($route, $args) {
        if (isset($args[0])) {
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
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteInformation($args[0]);

            $this->logger->info("Information page {$args[0]} deleted from index");
        }
    }

    public function eventOrderAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->model_extension_module_dockercart_search->indexOrder($output);

        $this->logger->info("Order {$output} indexed");
    }

    public function eventOrderEdit($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexOrder($args[0]);

            $this->logger->info("Order {$args[0]} re-indexed");
        }
    }

    public function eventOrderDelete($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteOrder($args[0]);

            $this->logger->info("Order {$args[0]} deleted from index");
        }
    }

    public function eventOrderStatusChange($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexOrder($args[0]);

            $this->logger->info("Order {$args[0]} re-indexed (status change)");
        }
    }

    public function eventCustomerAdd($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->model_extension_module_dockercart_search->indexCustomer($output);

        $this->logger->info("Customer {$output} indexed");
    }

    public function eventCustomerEdit($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexCustomer($args[0]);

            $this->logger->info("Customer {$args[0]} re-indexed");
        }
    }

    public function eventCustomerDelete($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->deleteCustomer($args[0]);

            $this->logger->info("Customer {$args[0]} deleted from index");
        }
    }

    public function eventCustomerAddFront($route, $args, $output) {
        $this->load->model('extension/module/dockercart_search');
        $this->model_extension_module_dockercart_search->indexCustomer($output);

        $this->logger->info("Customer {$output} indexed (front registration)");
    }

    public function eventCustomerEditFront($route, $args) {
        if (isset($args[0])) {
            $this->load->model('extension/module/dockercart_search');
            $this->model_extension_module_dockercart_search->indexCustomer($args[0]);

            $this->logger->info("Customer {$args[0]} re-indexed (front profile edit)");
        }
    }

    private function registerMenuEvent() {
        $this->load->model('setting/event');
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'dockercart_search_admin_menu'");
        $this->model_setting_event->addEvent(
            'dockercart_search_admin_menu',
            'admin/view/common/column_left/before',
            'extension/module/dockercart_search/eventAdminMenu',
            1,
            0
        );
    }

    public function eventAdminMenu(&$route, &$data, &$output) {
        $this->load->language('extension/module/dockercart_search');

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_search')) {
            return;
        }

        $menu = array(
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true),
            'icon' => 'search',
            'children' => array(
                array(
                    'name' => $this->language->get('heading_mappings'),
                    'href' => $this->url->link('extension/module/dockercart_search/mappings', 'user_token=' . $this->session->data['user_token'], true),
                    'icon' => 'list-tree'
                )
            )
        );

        if (!isset($data['menus']) || !is_array($data['menus'])) {
            return;
        }

        foreach ($data['menus'] as &$item) {
            if (isset($item['id']) && $item['id'] === 'menu-catalog' && isset($item['children']) && is_array($item['children'])) {
                $item['children'][] = $menu;
                return;
            }
        }

        $data['menus'][] = array(
            'id' => 'menu-dockercart-search',
            'icon' => 'search',
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_search', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );
    }
}
