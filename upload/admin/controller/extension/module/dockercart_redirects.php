<?php
/**
 * DockerCart Redirects (renamed from Redirect Manager)
 */

class ControllerExtensionModuleDockercartRedirects extends Controller {
    private $error = array();
    private $logger;
    // Module version — update on release
    private $module_version = '1.0.0';
    // Request-scoped storage for "before" SEO keywords
    private static $old_seo = array();

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'redirects');
    }

    public function index() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');
        $this->load->model('setting/setting');

        // Non-blocking admin license validation (logs warnings)
        $this->validateLicense();

        // Save module settings (status/debug)
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
                if (!$this->user->hasPermission('modify', 'extension/module/dockercart_redirects')) {
                $this->session->data['warning'] = $this->language->get('error_permission');
            } else {
                $this->model_setting_setting->editSetting('module_dockercart_redirects', $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success_status');
                $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
            }
        }

        $this->document->setTitle($this->language->get('heading_title'));

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
            'href' => $this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true)
        );

        // URLs
        $data['add'] = $this->url->link('extension/module/dockercart_redirects/form', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete'] = $this->url->link('extension/module/dockercart_redirects/delete', 'user_token=' . $this->session->data['user_token'], true);
        $data['import'] = $this->url->link('extension/module/dockercart_redirects/import', 'user_token=' . $this->session->data['user_token'], true);
        $data['export'] = $this->url->link('extension/module/dockercart_redirects/export', 'user_token=' . $this->session->data['user_token'], true);
        $data['clear_stats'] = $this->url->link('extension/module/dockercart_redirects/clearStats', 'user_token=' . $this->session->data['user_token'], true);
        $data['check_duplicates'] = $this->url->link('extension/module/dockercart_redirects/checkDuplicates', 'user_token=' . $this->session->data['user_token'], true);

        // Get filters
        $filter_old_url = isset($this->request->get['filter_old_url']) ? $this->request->get['filter_old_url'] : '';
        $filter_new_url = isset($this->request->get['filter_new_url']) ? $this->request->get['filter_new_url'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $filter_is_regex = isset($this->request->get['filter_is_regex']) ? $this->request->get['filter_is_regex'] : '';

        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        $limit = 50;

        $data['redirects'] = array();

        $filter_data = array(
            'filter_old_url'  => $filter_old_url,
            'filter_new_url'  => $filter_new_url,
            'filter_status'   => $filter_status,
            'filter_is_regex' => $filter_is_regex,
            'start'           => ($page - 1) * $limit,
            'limit'           => $limit
        );

        $redirect_total = $this->model_extension_module_dockercart_redirects->getTotalRedirects($filter_data);
        $results = $this->model_extension_module_dockercart_redirects->getRedirects($filter_data);

        foreach ($results as $result) {
            $data['redirects'][] = array(
                'redirect_id'    => $result['redirect_id'],
                'old_url'        => $result['old_url'],
                'new_url'        => $result['new_url'],
                'code'           => $result['code'],
                'status'         => $result['status'],
                'is_regex'       => $result['is_regex'],
                'preserve_query' => $result['preserve_query'],
                'hits'           => $result['hits'],
                'last_hit'       => $result['last_hit'] ? date($this->language->get('datetime_format'), strtotime($result['last_hit'])) : '-',
                'date_added'     => date($this->language->get('date_format'), strtotime($result['date_added'])),
                'edit'           => $this->url->link('extension/module/dockercart_redirects/form', 'user_token=' . $this->session->data['user_token'] . '&redirect_id=' . $result['redirect_id'], true),
                'delete_single'  => $this->url->link('extension/module/dockercart_redirects/remove', 'user_token=' . $this->session->data['user_token'] . '&redirect_id=' . $result['redirect_id'], true)
            );
        }

        // Statistics
        $stats = $this->model_extension_module_dockercart_redirects->getStatistics();
        $data['total_redirects'] = $stats['total'];
        $data['active_redirects'] = $stats['active'];
        $data['regex_redirects'] = $stats['regex'];
        $data['total_hits'] = $stats['total_hits'];

        // Pagination
        $pagination = new Pagination();
        $pagination->total = $redirect_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($redirect_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($redirect_total - $limit)) ? $redirect_total : ((($page - 1) * $limit) + $limit), $redirect_total, ceil($redirect_total / $limit));

        // Filters
        $data['filter_old_url'] = $filter_old_url;
        $data['filter_new_url'] = $filter_new_url;
        $data['filter_status'] = $filter_status;
        $data['filter_is_regex'] = $filter_is_regex;

        // Module settings values
        if (isset($this->request->post['module_dockercart_redirects_status'])) {
            $data['module_dockercart_redirects_status'] = $this->request->post['module_dockercart_redirects_status'];
        } else {
            $data['module_dockercart_redirects_status'] = $this->config->get('module_dockercart_redirects_status') !== null ? $this->config->get('module_dockercart_redirects_status') : 1;
        }

        if (isset($this->request->post['module_dockercart_redirects_debug'])) {
            $data['module_dockercart_redirects_debug'] = $this->request->post['module_dockercart_redirects_debug'];
        } else {
            $data['module_dockercart_redirects_debug'] = $this->config->get('module_dockercart_redirects_debug') !== null ? $this->config->get('module_dockercart_redirects_debug') : 0;
        }

        // License settings
        if (isset($this->request->post['module_dockercart_redirects_license_key'])) {
            $data['module_dockercart_redirects_license_key'] = $this->request->post['module_dockercart_redirects_license_key'];
        } else {
            $data['module_dockercart_redirects_license_key'] = $this->config->get('module_dockercart_redirects_license_key') !== null ? $this->config->get('module_dockercart_redirects_license_key') : '';
        }

        if (isset($this->request->post['module_dockercart_redirects_public_key'])) {
            $data['module_dockercart_redirects_public_key'] = $this->request->post['module_dockercart_redirects_public_key'];
        } else {
            $data['module_dockercart_redirects_public_key'] = $this->config->get('module_dockercart_redirects_public_key') !== null ? $this->config->get('module_dockercart_redirects_public_key') : '';
        }

        $data['user_token'] = $this->session->data['user_token'];
        // Link back to modules/extensions list
        $data['extensions'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Form action for saving module settings
        $data['action'] = $this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true);

        // Success messages
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Warning messages
        if (isset($this->session->data['warning'])) {
            $data['warning'] = $this->session->data['warning'];
            unset($this->session->data['warning']);
        } else {
            $data['warning'] = '';
        }

        // Expose module version to template. Prefer global DOCKERCART_VERSION if defined.
        $data['module_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : $this->module_version;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_redirects', $data));
    }

    /**
     * Form for adding/editing redirects
     */
    public function form() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        $this->document->setTitle($this->language->get('heading_title'));

        if (isset($this->request->get['redirect_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $redirect_info = $this->model_extension_module_dockercart_redirects->getRedirect($this->request->get['redirect_id']);
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true)
        );

        if (isset($this->request->get['redirect_id'])) {
            $data['action'] = $this->url->link('extension/module/dockercart_redirects/edit', 'user_token=' . $this->session->data['user_token'] . '&redirect_id=' . $this->request->get['redirect_id'], true);
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_edit'),
                'href' => $this->url->link('extension/module/dockercart_redirects/form', 'user_token=' . $this->session->data['user_token'] . '&redirect_id=' . $this->request->get['redirect_id'], true)
            );
        } else {
            $data['action'] = $this->url->link('extension/module/dockercart_redirects/add', 'user_token=' . $this->session->data['user_token'], true);
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_add'),
                'href' => $this->url->link('extension/module/dockercart_redirects/form', 'user_token=' . $this->session->data['user_token'], true)
            );
        }

        $data['cancel'] = $this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true);

        // Form data
        if (isset($redirect_info)) {
            $data['old_url'] = $redirect_info['old_url'];
            $data['new_url'] = $redirect_info['new_url'];
            $data['code'] = $redirect_info['code'];
            $data['status'] = $redirect_info['status'];
            $data['is_regex'] = $redirect_info['is_regex'];
            $data['preserve_query'] = $redirect_info['preserve_query'];
        } else {
            $data['old_url'] = '';
            $data['new_url'] = '';
            $data['code'] = 301;
            $data['status'] = 1;
            $data['is_regex'] = 0;
            $data['preserve_query'] = 1;
        }

        // Redirect codes
        $data['redirect_codes'] = array(
            301 => '301 ' . $this->language->get('text_moved_permanently'),
            302 => '302 ' . $this->language->get('text_found'),
            303 => '303 ' . $this->language->get('text_see_other'),
            307 => '307 ' . $this->language->get('text_temporary_redirect'),
            308 => '308 ' . $this->language->get('text_permanent_redirect')
        );

        $data['user_token'] = $this->session->data['user_token'];

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['old_url'])) {
            $data['error_old_url'] = $this->error['old_url'];
        } else {
            $data['error_old_url'] = '';
        }

        if (isset($this->error['new_url'])) {
            $data['error_new_url'] = $this->error['new_url'];
        } else {
            $data['error_new_url'] = '';
        }

        // Expose module version to template for the form view as well.
        $data['module_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : $this->module_version;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_redirects_form', $data));
    }

    /**
     * Add new redirect
     */
    public function add() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_dockercart_redirects->addRedirect($this->request->post);

            $this->session->data['success'] = $this->language->get('text_success_add');

            $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->form();
    }

    /**
     * Edit existing redirect
     */
    public function edit() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_dockercart_redirects->editRedirect($this->request->get['redirect_id'], $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success_edit');

            $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->form();
    }

    /**
     * Delete redirects (supports bulk delete)
     */
    public function delete() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $redirect_id) {
                $this->model_extension_module_dockercart_redirects->deleteRedirect($redirect_id);
            }

            $this->session->data['success'] = $this->language->get('text_success_delete');
        }

        $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Remove a single redirect (used by per-row Delete action)
     */
    public function remove() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        if (isset($this->request->get['redirect_id']) && $this->validateDelete()) {
            $redirect_id = (int)$this->request->get['redirect_id'];
            $this->model_extension_module_dockercart_redirects->deleteRedirect($redirect_id);
            $this->session->data['success'] = $this->language->get('text_success_delete');
        } else {
            $this->session->data['warning'] = $this->language->get('error_permission');
        }

        $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Bulk enable/disable redirects
     */
    public function bulkStatus() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        $json = array();

        if (isset($this->request->post['selected']) && isset($this->request->post['status'])) {
            foreach ($this->request->post['selected'] as $redirect_id) {
                $this->model_extension_module_dockercart_redirects->updateStatus($redirect_id, (int)$this->request->post['status']);
            }

            $json['success'] = $this->language->get('text_success_status');
        } else {
            $json['error'] = $this->language->get('error_select');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Import redirects from CSV
     */
    public function import() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        $json = array();
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // Uploaded file
            if (isset($this->request->files['file']) && is_uploaded_file($this->request->files['file']['tmp_name'])) {
                $file = $this->request->files['file'];
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

                if (strtolower($extension) === 'csv') {
                    $imported = $this->importCSV($file['tmp_name']);
                    $json['success'] = sprintf($this->language->get('text_success_import'), $imported);
                } else {
                    $json['error'] = $this->language->get('error_file_type');
                }
            } else {
                $json['error'] = $this->language->get('error_upload');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Import from CSV file
     */
    private function importCSV($file_path) {
        $this->load->model('extension/module/dockercart_redirects');

        $imported = 0;

        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 2) {
                    $redirect_data = array(
                        'old_url'        => isset($data[0]) ? $data[0] : '',
                        'new_url'        => isset($data[1]) ? $data[1] : '',
                        'code'           => isset($data[2]) && in_array($data[2], array(301, 302, 303, 307, 308)) ? (int)$data[2] : 301,
                        'status'         => isset($data[3]) ? (int)$data[3] : 1,
                        'is_regex'       => isset($data[4]) ? (int)$data[4] : 0,
                        'preserve_query' => isset($data[5]) ? (int)$data[5] : 1
                    );

                    if (!empty($redirect_data['old_url']) && !empty($redirect_data['new_url'])) {
                        $this->model_extension_module_dockercart_redirects->addRedirect($redirect_data);
                        $imported++;
                    }
                }
            }
            fclose($handle);
        }

        return $imported;
    }

    

    /**
     * Export redirects to CSV/XLSX
     */
    public function export() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        // Force CSV-only export to keep implementation simple and compatible
        $redirects = $this->model_extension_module_dockercart_redirects->getRedirects(array('limit' => 100000));

        $this->exportCSV($redirects);
    }

    /**
     * Export to CSV
     */
    private function exportCSV($redirects) {
        $filename = 'redirects_' . date('Y-m-d_H-i-s') . '.csv';

        // Build CSV in-memory to avoid sending headers after output
        $fp = fopen('php://temp', 'r+');

        // Headers
        fputcsv($fp, array('Old URL', 'New URL', 'Code', 'Status', 'Is RegEx', 'Preserve Query', 'Hits', 'Last Hit', 'Date Added'));

        // Data
        foreach ($redirects as $redirect) {
            fputcsv($fp, array(
                $redirect['old_url'],
                $redirect['new_url'],
                $redirect['code'],
                $redirect['status'],
                $redirect['is_regex'],
                $redirect['preserve_query'],
                $redirect['hits'],
                $redirect['last_hit'],
                $redirect['date_added']
            ));
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        // Use OpenCart response headers to avoid PHP header() issues
        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->response->setOutput($csv);
        return;
    }

    

    /**
     * Clear statistics (reset hits)
     */
    public function clearStats() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        $this->model_extension_module_dockercart_redirects->clearStatistics();

        $this->session->data['success'] = $this->language->get('text_success_clear_stats');

        $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Check for duplicate redirects
     */
    public function checkDuplicates() {
        $this->load->language('extension/module/dockercart_redirects');
        $this->load->model('extension/module/dockercart_redirects');

        $duplicates = $this->model_extension_module_dockercart_redirects->findDuplicates();

        if ($duplicates) {
            $this->session->data['warning'] = sprintf($this->language->get('warning_duplicates_found'), count($duplicates));
        } else {
            $this->session->data['success'] = $this->language->get('text_no_duplicates');
        }

        $this->response->redirect($this->url->link('extension/module/dockercart_redirects', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Install module - creates database table and registers events
     */
    public function install() {
        $this->load->model('extension/module/dockercart_redirects');
        $this->load->model('setting/event');
        $this->load->model('setting/setting');

        // Create database table
        $this->model_extension_module_dockercart_redirects->createTable();

        // Register events: main startup hook + 404 handling + auto-redirects
        $events = array(
            // Main redirect checking - fire after SEO URL processing so language prefixes are resolved
            array(
                'code'    => 'dockercart_redirects_check',
                'trigger' => 'catalog/controller/common/language/before',
                'action'  => 'extension/module/dockercart_redirects/checkRedirect'
            ),
            // Optional 404 handling
            array(
                'code'    => 'dockercart_redirects_404',
                'trigger' => 'catalog/controller/error/not_found/before',
                'action'  => 'extension/module/dockercart_redirects/handle404'
            ),
            // Auto-redirect on SEO URL changes - Product (before + after)
            array(
                'code'    => 'dockercart_redirects_product_edit_before',
                'trigger' => 'admin/model/catalog/product/editProduct/before',
                'action'  => 'extension/module/dockercart_redirects/captureSeoBefore'
            ),
            array(
                'code'    => 'dockercart_redirects_product_edit_after',
                'trigger' => 'admin/model/catalog/product/editProduct/after',
                'action'  => 'extension/module/dockercart_redirects/processSeoAfter'
            ),
            // Auto-redirect on SEO URL changes - Category
            array(
                'code'    => 'dockercart_redirects_category_edit_before',
                'trigger' => 'admin/model/catalog/category/editCategory/before',
                'action'  => 'extension/module/dockercart_redirects/captureSeoBefore'
            ),
            array(
                'code'    => 'dockercart_redirects_category_edit_after',
                'trigger' => 'admin/model/catalog/category/editCategory/after',
                'action'  => 'extension/module/dockercart_redirects/processSeoAfter'
            ),
            // Auto-redirect on SEO URL changes - Manufacturer
            array(
                'code'    => 'dockercart_redirects_manufacturer_edit_before',
                'trigger' => 'admin/model/catalog/manufacturer/editManufacturer/before',
                'action'  => 'extension/module/dockercart_redirects/captureSeoBefore'
            ),
            array(
                'code'    => 'dockercart_redirects_manufacturer_edit_after',
                'trigger' => 'admin/model/catalog/manufacturer/editManufacturer/after',
                'action'  => 'extension/module/dockercart_redirects/processSeoAfter'
            ),
            // Auto-redirect on SEO URL changes - Information
            array(
                'code'    => 'dockercart_redirects_information_edit_before',
                'trigger' => 'admin/model/catalog/information/editInformation/before',
                'action'  => 'extension/module/dockercart_redirects/captureSeoBefore'
            ),
            array(
                'code'    => 'dockercart_redirects_information_edit_after',
                'trigger' => 'admin/model/catalog/information/editInformation/after',
                'action'  => 'extension/module/dockercart_redirects/processSeoAfter'
            )
        );

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

        // Set default settings: disable module by default, debug disabled
        $this->model_setting_setting->editSetting('module_dockercart_redirects', array(
            'module_dockercart_redirects_status' => 0,
            'module_dockercart_redirects_debug' => 0
        ));

        $this->logger->info('Module installed successfully');
    }

    /**
     * Uninstall module - removes database table and events
     */
    public function uninstall() {
        $this->load->model('extension/module/dockercart_redirects');
        $this->load->model('setting/event');

        // Remove events
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'dockercart_redirects_%'");

        // Drop database table (optional - you might want to keep data)
        // Uncomment the next line if you want to remove all data on uninstall
        // $this->model_extension_module_dockercart_redirects->dropTable();

        $this->logger->info('Module uninstalled successfully');
    }

    /**
     * Validate form data
     */
    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_redirects')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['old_url'])) {
            $this->error['old_url'] = $this->language->get('error_old_url');
        }

        if (empty($this->request->post['new_url'])) {
            $this->error['new_url'] = $this->language->get('error_new_url');
        }

        // Validate RegEx if is_regex is enabled
        if (isset($this->request->post['is_regex']) && $this->request->post['is_regex']) {
            if (@preg_match($this->request->post['old_url'], '') === false) {
                $this->error['old_url'] = $this->language->get('error_invalid_regex');
            }
        }

        return !$this->error;
    }

    /**
     * Validate delete permission
     */
    protected function validateDelete() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_redirects')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Capture current SEO URLs before the edit occurs.
     * This handler is registered on the admin model "edit" before-event.
     * It stores the existing keywords in request-scoped storage for later comparison.
     */
    public function captureSeoBefore($route, $args) {
        if (!$this->config->get('module_dockercart_redirects_status')) {
            return;
        }

        // Determine entity type and id from the route/args
        $entity_type = '';
        if (strpos($route, 'product/editProduct') !== false) {
            $entity_type = 'product';
        } elseif (strpos($route, 'category/editCategory') !== false) {
            $entity_type = 'category';
        } elseif (strpos($route, 'manufacturer/editManufacturer') !== false) {
            $entity_type = 'manufacturer';
        } elseif (strpos($route, 'information/editInformation') !== false) {
            $entity_type = 'information';
        }

        if (!$entity_type) {
            return;
        }

        $entity_id = isset($args[0]) ? (int)$args[0] : 0;
        if ($entity_id < 1) {
            return;
        }

        $this->load->model('extension/module/dockercart_redirects');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $key = $entity_type . '_' . (int)$entity_id;
        if (!isset(self::$old_seo[$key])) {
            self::$old_seo[$key] = array();
        }

        foreach ($languages as $language) {
            $language_id = $language['language_id'];

            $keyword = $this->model_extension_module_dockercart_redirects->getCurrentSeoUrl($entity_type, $entity_id, $language_id);

            // Store even empty string to indicate absence
            self::$old_seo[$key][(int)$language_id] = $keyword;
        }
    }

    /**
     * Compare SEO URLs after the edit and create redirects if keywords changed.
     * Registered on the admin model "edit" after-event.
     */
    public function processSeoAfter($route, $args) {
        if (!$this->config->get('module_dockercart_redirects_status')) {
            return;
        }

        // Determine entity type and id from the route/args
        $entity_type = '';
        if (strpos($route, 'product/editProduct') !== false) {
            $entity_type = 'product';
        } elseif (strpos($route, 'category/editCategory') !== false) {
            $entity_type = 'category';
        } elseif (strpos($route, 'manufacturer/editManufacturer') !== false) {
            $entity_type = 'manufacturer';
        } elseif (strpos($route, 'information/editInformation') !== false) {
            $entity_type = 'information';
        }

        if (!$entity_type) {
            return;
        }

        $entity_id = isset($args[0]) ? (int)$args[0] : 0;
        if ($entity_id < 1) {
            return;
        }

        $this->load->model('extension/module/dockercart_redirects');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        $key = $entity_type . '_' . (int)$entity_id;
        $old_map = isset(self::$old_seo[$key]) ? self::$old_seo[$key] : array();

        foreach ($languages as $language) {
            $language_id = (int)$language['language_id'];

            $new_keyword = $this->model_extension_module_dockercart_redirects->getCurrentSeoUrl($entity_type, $entity_id, $language_id);

            $old_keyword = isset($old_map[$language_id]) ? $old_map[$language_id] : '';

            // If the new keyword is now used by this entity, ensure there are no redirects claiming that path.
            // This handles the case: A -> B created earlier, then changed back to A — we must remove A -> B.
            if (!empty($new_keyword)) {
                $this->db->query(
                    "DELETE FROM `" . DB_PREFIX . "redirect_manager` WHERE old_url = '/" . $this->db->escape($new_keyword) . "'"
                );
                $this->logger->info('Removed redirects that used ' . $new_keyword . ' as old_url because it is now active');
            }

            // If there was no old keyword, skip (we don't create redirects from empty)
            if (empty($old_keyword) || $old_keyword === $new_keyword) {
                continue;
            }

            // Resolve final target by following existing redirects to avoid chains/loops
            $final_target = '/' . $new_keyword;
            $seen = array('/' . $old_keyword);
            $max_depth = 10;
            $depth = 0;

            while ($depth < $max_depth) {
                $q = $this->db->query(
                    "SELECT `new_url` FROM `" . DB_PREFIX . "redirect_manager` WHERE old_url = '" . $this->db->escape($final_target) . "' AND status = 1 LIMIT 1"
                );

                if (!$q->num_rows) {
                    break;
                }

                $final_target = $q->row['new_url'];

                // Detect loop
                if (in_array($final_target, $seen)) {
                    $this->logger->info('Detected potential redirect loop resolving target for /' . $old_keyword . ' -> ' . $final_target);
                    $final_target = '';
                    break;
                }

                $seen[] = $final_target;
                $depth++;
            }

            // If resolution produced nothing or resolves back to the old URL, delete any existing stale redirect and skip
            if (empty($final_target) || $final_target === ('/' . $old_keyword)) {
                // Remove stale redirect if present
                $this->db->query(
                    "DELETE FROM `" . DB_PREFIX . "redirect_manager` WHERE old_url = '/" . $this->db->escape($old_keyword) . "'"
                );
                $this->logger->info('Deleted stale redirect for /' . $old_keyword . ' to avoid loop or empty target');
                continue;
            }

            // Check if a redirect for this old URL already exists
            $existing = $this->db->query(
                "SELECT redirect_id FROM `" . DB_PREFIX . "redirect_manager` WHERE old_url = '/" . $this->db->escape($old_keyword) . "' LIMIT 1"
            );

            if ($existing->num_rows) {
                // Update existing redirect to point to resolved final target to avoid duplicates/chains
                $this->db->query(
                    "UPDATE `" . DB_PREFIX . "redirect_manager` SET new_url = '" . $this->db->escape($final_target) . "', date_modified = NOW() WHERE redirect_id = '" . (int)$existing->row['redirect_id'] . "'"
                );
                $this->logger->info('Updated existing redirect for /' . $old_keyword . ' -> ' . $final_target);
                continue;
            }

            // Create auto-redirect pointing to the resolved final target
            $redirect_data = array(
                'old_url'        => '/' . $old_keyword,
                'new_url'        => $final_target,
                'code'           => 301,
                'status'         => 1,
                'is_regex'       => 0,
                'preserve_query' => 1
            );

            $this->model_extension_module_dockercart_redirects->addRedirect($redirect_data);
            $this->logger->info('Auto-redirect created: /' . $old_keyword . ' -> ' . $final_target . ' for ' . $entity_type . ' ' . $entity_id);
        }

        // Clear stored before-state for this entity
        if (isset(self::$old_seo[$key])) {
            unset(self::$old_seo[$key]);
        }
    }

    /**
     * Write to log file
     */
    /**
     * Validate license in admin context (non-blocking; logs warnings)
     */
    private function validateLicense() {
        $license_key = $this->config->get('module_dockercart_redirects_license_key');

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false) {
            return true;
        }

        if (empty($license_key)) {
            return true;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            return true;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            return true;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, 'dockercart_redirects');

            if (!$result['valid']) {
                $error_msg = $this->language->get('error_license_invalid');
                if (isset($result['error'])) {
                    $error_msg .= ': ' . $result['error'];
                }

                $this->logger->info('WARNING: License validation failed in admin: ' . $error_msg);
            }
        } catch (Exception $e) {
            $this->logger->info('ERROR: License verification exception: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * AJAX license verification endpoint
     */
    public function verifyLicenseAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        $this->logger->info('AJAX: verifyLicenseAjax() called with key: ' . substr($license_key, 0, 20) . '...');

        if (empty($license_key)) {
            $json['valid'] = false;
            $json['error'] = 'License key is empty';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $json['valid'] = false;
            $json['error'] = 'License library not found';
            $this->logger->info('AJAX: License library not found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $json['valid'] = false;
            $json['error'] = 'DockercartLicense class not found';
            $this->logger->info('AJAX: DockercartLicense class not found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);

            if (!empty($public_key)) {
                $this->logger->info('AJAX: Using provided public key for verification');
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_redirects', true);
            } else {
                $this->logger->info('AJAX: Using saved public key from database');
                $result = $license->verify($license_key, 'dockercart_redirects', true);
            }

            $this->logger->info('AJAX: Verification result: ' . json_encode($result));

            $json = $result;

            if ($result['valid']) {
                $this->logger->info('AJAX: License verified successfully');
            } else {
                $this->logger->info('AJAX: License verification failed - ' . (isset($result['error']) ? $result['error'] : 'Unknown error'));
            }
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
            $this->logger->info('AJAX: Exception during verification - ' . $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
