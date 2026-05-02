<?php
class ControllerExtensionModuleDockercartImportExportExcel extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/dockercart_import_export_excel');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/dockercart_import_export_excel');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_import_export_excel', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

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
            'href' => $this->url->link('extension/module/dockercart_import_export_excel', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_import_export_excel', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['ajax_delete_profile'] = $this->url->link('extension/module/dockercart_import_export_excel/deleteProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_run_import'] = $this->url->link('extension/module/dockercart_import_export_excel/runImportAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_run_export'] = $this->url->link('extension/module/dockercart_import_export_excel/runExportAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_run_filtered_export'] = $this->url->link('extension/module/dockercart_import_export_excel/runFilteredExportAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['license_verify_ajax'] = $this->url->link('extension/module/dockercart_import_export_excel/verifyLicenseAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['license_save_ajax'] = $this->url->link('extension/module/dockercart_import_export_excel/saveLicenseKeyAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['add_profile_link'] = $this->url->link('extension/module/dockercart_import_export_excel/profile', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->post['module_dockercart_import_export_excel_status'])) {
            $data['module_dockercart_import_export_excel_status'] = (int)$this->request->post['module_dockercart_import_export_excel_status'];
        } else {
            $data['module_dockercart_import_export_excel_status'] = (int)$this->config->get('module_dockercart_import_export_excel_status');
        }

        $this->load->model('setting/store');
        $data['stores'] = array();
        $data['stores'][] = array('store_id' => 0, 'name' => $this->config->get('config_name') . ' (Default)');
        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = $store;
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model('localisation/currency');
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();

        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(array());

        $this->load->model('catalog/manufacturer');
        $data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers(array('start' => 0, 'limit' => 10000));

        $this->load->model('catalog/attribute');
        $data['attributes'] = $this->model_catalog_attribute->getAttributes(array('start' => 0, 'limit' => 10000));

        $this->load->model('catalog/option');
        $data['options'] = $this->model_catalog_option->getOptions(array('start' => 0, 'limit' => 10000));

        $data['profiles'] = $this->model_extension_module_dockercart_import_export_excel->getProfiles();
        foreach ($data['profiles'] as &$profile) {
            $profile['edit_link'] = $this->url->link('extension/module/dockercart_import_export_excel/profile', 'user_token=' . $this->session->data['user_token'] . '&profile_id=' . (int)$profile['profile_id'], true);
        }

        $data['export_filter_defaults'] = array(
            'store_id' => 0,
            'language_id' => (int)$this->config->get('config_language_id'),
            'status' => '',
            'manufacturer_id' => 0,
            'category_id' => 0,
            'keyword' => '',
            'quantity_min' => '',
            'quantity_max' => '',
            'price_min' => '',
            'price_max' => '',
            'file_format' => 'xlsx'
        );

        if (isset($this->request->post['module_dockercart_import_export_excel_license_key'])) {
            $data['module_dockercart_import_export_excel_license_key'] = $this->request->post['module_dockercart_import_export_excel_license_key'];
        } else {
            $data['module_dockercart_import_export_excel_license_key'] = (string)$this->config->get('module_dockercart_import_export_excel_license_key');
        }

        if (isset($this->request->post['module_dockercart_import_export_excel_public_key'])) {
            $data['module_dockercart_import_export_excel_public_key'] = $this->request->post['module_dockercart_import_export_excel_public_key'];
        } else {
            $data['module_dockercart_import_export_excel_public_key'] = (string)$this->config->get('module_dockercart_import_export_excel_public_key');
        }

        $data['license_domain'] = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'unknown';
        $data['license_valid'] = false;
        $data['license_message'] = '';

        try {
            if (file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                require_once(DIR_SYSTEM . 'library/dockercart_license.php');

                $license_key = (string)$data['module_dockercart_import_export_excel_license_key'];
                $public_key = (string)$data['module_dockercart_import_export_excel_public_key'];

                if ($license_key !== '' && class_exists('DockercartLicense')) {
                    $license = new DockercartLicense($this->registry);
                    if ($public_key !== '') {
                        $res = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_import_export_excel', true);
                    } else {
                        $res = $license->verify($license_key, 'dockercart_import_export_excel', true);
                    }

                    $data['license_valid'] = !empty($res['valid']);
                    $data['license_message'] = isset($res['error']) ? (string)$res['error'] : '';
                }
            }
        } catch (Exception $e) {
            $data['license_valid'] = false;
            $data['license_message'] = 'License check error: ' . $e->getMessage();
        }

        $data['cron_base_path'] = '/var/www/html/cron/dockercart_import_export_excel.php';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_import_export_excel', $data));
    }

    public function profile() {
        $this->load->language('extension/module/dockercart_import_export_excel');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/dockercart_import_export_excel');

        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
        $profile = array();

        if ($profile_id > 0) {
            $profile = $this->model_extension_module_dockercart_import_export_excel->getProfile($profile_id);
            if (!$profile) {
                $this->session->data['error_warning'] = $this->language->get('error_profile_not_found');
                $this->response->redirect($this->url->link('extension/module/dockercart_import_export_excel', 'user_token=' . $this->session->data['user_token'], true));
                return;
            }
        }

        $data['error_warning'] = isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '';
        unset($this->session->data['error_warning']);

        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_import_export_excel', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $profile_id > 0 ? $this->language->get('text_edit_profile') : $this->language->get('text_add_profile'),
            'href' => $this->url->link('extension/module/dockercart_import_export_excel/profile', 'user_token=' . $this->session->data['user_token'] . ($profile_id > 0 ? '&profile_id=' . $profile_id : ''), true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_import_export_excel/saveProfile', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/module/dockercart_import_export_excel', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_upload_source'] = $this->url->link('extension/module/dockercart_import_export_excel/uploadSourceAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_preview_source'] = $this->url->link('extension/module/dockercart_import_export_excel/previewSourceAjax', 'user_token=' . $this->session->data['user_token'], true);

        $data['profile'] = $this->buildProfileDefaults($profile);

        $this->load->model('setting/store');
        $data['stores'] = array();
        $data['stores'][] = array('store_id' => 0, 'name' => $this->config->get('config_name') . ' (Default)');
        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = $store;
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model('localisation/currency');
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();

        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(array());

        $this->load->model('catalog/attribute');
        $data['attributes'] = $this->model_catalog_attribute->getAttributes(array('start' => 0, 'limit' => 10000));

        $this->load->model('catalog/option');
        $data['options'] = $this->model_catalog_option->getOptions(array('start' => 0, 'limit' => 10000));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_import_export_excel_profile', $data));
    }

    public function saveProfile() {
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
                throw new Exception($this->language->get('error_permission'));
            }

            if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
                throw new Exception($this->language->get('error_invalid_request'));
            }

            $data = $this->request->post;
            if (empty($data['name'])) {
                throw new Exception($this->language->get('error_profile_name_required'));
            }

            $data['field_map'] = array(
                'external_id' => (int)$this->getPostInt('map_external_id', 0),
                'sku' => (int)$this->getPostInt('map_sku', 0),
                'model' => (int)$this->getPostInt('map_model', 0),
                'name' => (int)$this->getPostInt('map_name', 0),
                'description' => (int)$this->getPostInt('map_description', 0),
                'price' => (int)$this->getPostInt('map_price', 0),
                'quantity' => (int)$this->getPostInt('map_quantity', 0),
                'manufacturer' => (int)$this->getPostInt('map_manufacturer', 0),
                'category' => (int)$this->getPostInt('map_category', 0),
                'image' => (int)$this->getPostInt('map_image', 0),
                'images' => $this->normalizeColumnList(isset($this->request->post['map_images']) ? (string)$this->request->post['map_images'] : ''),
                'specials' => (int)$this->getPostInt('map_specials', 0)
            );

            $category_rules = $this->decodeJsonArray(isset($this->request->post['category_rules_json']) ? $this->request->post['category_rules_json'] : '[]');
            $attribute_rules = $this->decodeJsonArray(isset($this->request->post['attribute_rules_json']) ? $this->request->post['attribute_rules_json'] : '[]');
            $option_rules = $this->decodeJsonArray(isset($this->request->post['option_rules_json']) ? $this->request->post['option_rules_json'] : '[]');

            $data['extra_settings'] = array(
                'category_map_text' => isset($this->request->post['category_map_text']) ? trim((string)$this->request->post['category_map_text']) : '',
                'category_rules' => $this->normalizeCategoryRules($category_rules),
                'attribute_rules' => $this->normalizeAttributeRules($attribute_rules),
                'option_rules' => $this->normalizeOptionRules($option_rules)
            );

            $this->load->model('extension/module/dockercart_import_export_excel');

            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            if ($profile_id > 0) {
                $this->model_extension_module_dockercart_import_export_excel->updateProfile($profile_id, $data);
            } else {
                $profile_id = $this->model_extension_module_dockercart_import_export_excel->addProfile($data);
            }

            $this->session->data['success'] = $this->language->get('text_profile_saved');
            $this->response->redirect($this->url->link('extension/module/dockercart_import_export_excel/profile', 'user_token=' . $this->session->data['user_token'] . '&profile_id=' . (int)$profile_id, true));
        } catch (Exception $e) {
            $this->session->data['error_warning'] = $e->getMessage();
            $redirect = $this->url->link('extension/module/dockercart_import_export_excel/profile', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->post['profile_id']) && (int)$this->request->post['profile_id'] > 0 ? '&profile_id=' . (int)$this->request->post['profile_id'] : ''), true);
            $this->response->redirect($redirect);
        }
    }

    public function previewSourceAjax() {
        $json = array('success' => false);
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!is_array($data)) {
                throw new Exception($this->language->get('error_invalid_request'));
            }

            $profile = $this->buildPreviewProfile($data);

            $field_map = array(
                'external_id' => max(0, (int)$this->getArrayInt($data, 'map_external_id', 0)),
                'sku' => max(0, (int)$this->getArrayInt($data, 'map_sku', 0)),
                'model' => max(0, (int)$this->getArrayInt($data, 'map_model', 0)),
                'name' => max(0, (int)$this->getArrayInt($data, 'map_name', 0)),
                'description' => max(0, (int)$this->getArrayInt($data, 'map_description', 0)),
                'price' => max(0, (int)$this->getArrayInt($data, 'map_price', 0)),
                'quantity' => max(0, (int)$this->getArrayInt($data, 'map_quantity', 0)),
                'manufacturer' => max(0, (int)$this->getArrayInt($data, 'map_manufacturer', 0)),
                'category' => max(0, (int)$this->getArrayInt($data, 'map_category', 0)),
                'image' => max(0, (int)$this->getArrayInt($data, 'map_image', 0)),
                'images' => $this->normalizeColumnList(isset($data['map_images']) ? (string)$data['map_images'] : ''),
                'specials' => max(0, (int)$this->getArrayInt($data, 'map_specials', 0))
            );

            $this->load->model('extension/module/dockercart_import_export_excel');
            $rows = $this->model_extension_module_dockercart_import_export_excel->previewSourceRows($profile, 10);

            $mapped = array();
            foreach ($rows as $row) {
                $mapped[] = array(
                    'external_id' => $this->rowByColumnIndex($row, $field_map['external_id']),
                    'sku' => $this->rowByColumnIndex($row, $field_map['sku']),
                    'model' => $this->rowByColumnIndex($row, $field_map['model']),
                    'name' => $this->rowByColumnIndex($row, $field_map['name']),
                    'description' => $this->rowByColumnIndex($row, $field_map['description']),
                    'price' => $this->rowByColumnIndex($row, $field_map['price']),
                    'quantity' => $this->rowByColumnIndex($row, $field_map['quantity']),
                    'manufacturer' => $this->rowByColumnIndex($row, $field_map['manufacturer']),
                    'category' => $this->rowByColumnIndex($row, $field_map['category']),
                    'image' => $this->rowByColumnIndex($row, $field_map['image']),
                    'images' => $this->rowByColumnIndexes($row, $field_map['images']),
                    'specials' => $this->rowByColumnIndex($row, $field_map['specials'])
                );
            }

            $json['success'] = true;
            $json['rows'] = $rows;
            $json['mapped'] = $mapped;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function uploadSourceAjax() {
        $json = array();

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
                throw new Exception($this->language->get('error_permission'));
            }

            if (empty($this->request->files['source_file']['name'])) {
                throw new Exception($this->language->get('error_file_required'));
            }

            $file = $this->request->files['source_file'];
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new Exception($this->language->get('error_upload_failed'));
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('csv', 'xlsx'))) {
                throw new Exception($this->language->get('error_invalid_file_format'));
            }

            $dir = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $filename = 'supplier_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target = $dir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception($this->language->get('error_upload_failed'));
            }

            $json['success'] = true;
            $json['source_file'] = $target;
            $json['source_format'] = $ext;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function saveProfileAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
                throw new Exception($this->language->get('error_permission'));
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (empty($data['name'])) {
                throw new Exception($this->language->get('error_profile_name_required'));
            }

            if (empty($data['field_map']) || !is_array($data['field_map'])) {
                throw new Exception($this->language->get('error_field_map_required'));
            }

            foreach ($data['field_map'] as $key => $value) {
                if ($key === 'images') {
                    $data['field_map'][$key] = $this->normalizeColumnList((string)$value);
                } else {
                    $data['field_map'][$key] = max(0, (int)$value);
                }
            }

            $this->load->model('extension/module/dockercart_import_export_excel');

            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            if ($profile_id > 0) {
                $this->model_extension_module_dockercart_import_export_excel->updateProfile($profile_id, $data);
            } else {
                $profile_id = $this->model_extension_module_dockercart_import_export_excel->addProfile($data);
            }

            $json['success'] = true;
            $json['profile_id'] = $profile_id;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function getProfileAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_export_excel');
            $profile = $this->model_extension_module_dockercart_import_export_excel->getProfile($profile_id);
            if (!$profile) {
                throw new Exception($this->language->get('error_profile_not_found'));
            }

            $json['success'] = true;
            $json['profile'] = $profile;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function deleteProfileAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
                throw new Exception($this->language->get('error_permission'));
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_export_excel');
            $this->model_extension_module_dockercart_import_export_excel->deleteProfile($profile_id);

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function runImportAjax() {
        $this->runRemoteAction('import');
    }

    public function runExportAjax() {
        $this->runRemoteAction('export');
    }

    public function runFilteredExportAjax() {
        $json = array('success' => false);
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
                throw new Exception($this->language->get('error_permission'));
            }

            if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
                throw new Exception($this->language->get('error_invalid_request'));
            }

            $file_format = isset($this->request->post['file_format']) ? (string)$this->request->post['file_format'] : 'xlsx';
            if (!in_array($file_format, array('xlsx', 'csv'))) {
                $file_format = 'xlsx';
            }

            $filters = array(
                'store_id' => isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0,
                'language_id' => isset($this->request->post['language_id']) ? (int)$this->request->post['language_id'] : (int)$this->config->get('config_language_id'),
                'status' => isset($this->request->post['status']) ? (string)$this->request->post['status'] : '',
                'manufacturer_id' => isset($this->request->post['manufacturer_id']) ? (int)$this->request->post['manufacturer_id'] : 0,
                'category_id' => isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0,
                'keyword' => isset($this->request->post['keyword']) ? trim((string)$this->request->post['keyword']) : '',
                'quantity_min' => isset($this->request->post['quantity_min']) ? trim((string)$this->request->post['quantity_min']) : '',
                'quantity_max' => isset($this->request->post['quantity_max']) ? trim((string)$this->request->post['quantity_max']) : '',
                'price_min' => isset($this->request->post['price_min']) ? trim((string)$this->request->post['price_min']) : '',
                'price_max' => isset($this->request->post['price_max']) ? trim((string)$this->request->post['price_max']) : ''
            );

            $this->load->model('extension/module/dockercart_import_export_excel');
            $summary = $this->model_extension_module_dockercart_import_export_excel->runFilteredExport($filters, $file_format);

            $json['success'] = true;
            $json['summary'] = $summary;
            $json['download_url'] = $this->url->link(
                'extension/module/dockercart_import_export_excel/downloadExportAjax',
                'user_token=' . $this->session->data['user_token'] . '&file=' . urlencode((string)$summary['filename']),
                true
            );
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function verifyLicenseAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? (string)$data['license_key'] : '';
        $public_key = isset($data['public_key']) ? (string)$data['public_key'] : '';

        if ($license_key === '') {
            $json['valid'] = false;
            $json['error'] = 'License key is empty';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $json['valid'] = false;
            $json['error'] = 'License library not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $json['valid'] = false;
            $json['error'] = 'DockercartLicense class not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);
            if ($public_key !== '') {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_import_export_excel', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_import_export_excel', true);
            }

            $json = $result;
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function saveLicenseKeyAjax() {
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
            $json['success'] = false;
            $json['error'] = 'No permission';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? (string)$data['license_key'] : '';
        $public_key = isset($data['public_key']) ? (string)$data['public_key'] : '';

        try {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSettingValue('module_dockercart_import_export_excel', 'module_dockercart_import_export_excel_license_key', $license_key);
            $this->model_setting_setting->editSettingValue('module_dockercart_import_export_excel', 'module_dockercart_import_export_excel_public_key', $public_key);

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function downloadExportAjax() {
        if (!$this->user->hasPermission('access', 'extension/module/dockercart_import_export_excel')) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->setOutput('Access denied');
            return;
        }

        $filename = isset($this->request->get['file']) ? (string)$this->request->get['file'] : '';

        $this->load->model('extension/module/dockercart_import_export_excel');
        $path = $this->model_extension_module_dockercart_import_export_excel->getExportFileByName($filename);

        if (!$path || !is_file($path)) {
            $this->response->addHeader('HTTP/1.1 404 Not Found');
            $this->response->setOutput('File not found');
            return;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $content_type = ($ext === 'csv')
            ? 'text/csv; charset=utf-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $this->response->addHeader('Content-Type: ' . $content_type);
        $this->response->addHeader('Content-Disposition: attachment; filename="' . basename($path) . '"');
        $this->response->addHeader('Content-Length: ' . filesize($path));
        $this->response->setOutput(file_get_contents($path));
    }

    private function runRemoteAction($action) {
        $json = array();
        $this->load->language('extension/module/dockercart_import_export_excel');

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_export_excel');
            $profile = $this->model_extension_module_dockercart_import_export_excel->getProfile($profile_id);
            if (!$profile) {
                throw new Exception($this->language->get('error_profile_not_found'));
            }

            $file_format = isset($this->request->get['file_format']) ? (string)$this->request->get['file_format'] : 'xlsx';
            if (!in_array($file_format, array('xlsx', 'csv'))) {
                $file_format = 'xlsx';
            }

            $url = HTTP_CATALOG . 'index.php?route=extension/module/dockercart_import_export_excel/cron'
                . '&profile_id=' . $profile_id
                . '&cron_key=' . urlencode($profile['cron_key'])
                . '&action=' . urlencode($action)
                . '&file_format=' . urlencode($file_format)
                . '&format=json';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            $response = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curl_errno) {
                throw new Exception($this->language->get('error_curl') . ': [' . (int)$curl_errno . '] ' . $curl_error . '. URL: ' . $url);
            }

            if ((int)$http_code !== 200) {
                $body_preview = $this->buildResponsePreview($response);
                throw new Exception($this->language->get('error_http') . ': ' . $http_code . '. URL: ' . $url . ($body_preview !== '' ? '. Response preview: ' . $body_preview : ''));
            }

            $decoded = json_decode((string)$response, true);
            if (!is_array($decoded)) {
                $json_error = function_exists('json_last_error_msg') ? json_last_error_msg() : 'json decode error';
                $body_preview = $this->buildResponsePreview($response);
                throw new Exception($this->language->get('error_invalid_response') . '. JSON decode error: ' . $json_error . '. URL: ' . $url . ($body_preview !== '' ? '. Response preview: ' . $body_preview : ''));
            }

            if (isset($decoded['success']) && !$decoded['success']) {
                $remote_error = isset($decoded['error']) ? trim((string)$decoded['error']) : '';
                if ($remote_error === '') {
                    $body_preview = $this->buildResponsePreview($response);
                    $remote_error = $this->language->get('error_invalid_response') . ($body_preview !== '' ? '. Response preview: ' . $body_preview : '');
                }

                throw new Exception('Endpoint error: ' . $remote_error . '. URL: ' . $url);
            }

            $json = $decoded;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function buildResponsePreview($response, $max_length = 500) {
        $response = trim((string)$response);
        if ($response === '') {
            return '';
        }

        $response = preg_replace('/\s+/', ' ', $response);
        if (!is_string($response)) {
            return '';
        }

        $max_length = max(50, (int)$max_length);
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($response, 'UTF-8') > $max_length) {
                return mb_substr($response, 0, $max_length, 'UTF-8') . '...';
            }

            return $response;
        }

        if (strlen($response) > $max_length) {
            return substr($response, 0, $max_length) . '...';
        }

        return $response;
    }

    public function install() {
        $this->load->model('extension/module/dockercart_import_export_excel');
        $this->load->model('setting/setting');

        $this->model_extension_module_dockercart_import_export_excel->install();
        $this->model_setting_setting->editSettingValue('module_dockercart_import_export_excel', 'module_dockercart_import_export_excel_status', 0);
    }

    public function uninstall() {
        $this->load->model('extension/module/dockercart_import_export_excel');
        $this->model_extension_module_dockercart_import_export_excel->uninstall();
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_export_excel')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function buildProfileDefaults($profile) {
        $defaults = array(
            'profile_id' => 0,
            'name' => '',
            'supplier_code' => '',
            'source_type' => 'url',
            'source_url' => '',
            'source_file' => '',
            'source_format' => 'auto',
            'sheet_index' => 0,
            'delimiter' => ';',
            'has_header' => 1,
            'start_row' => 2,
            'import_mode' => 'update',
            'store_id' => 0,
            'language_id' => (int)$this->config->get('config_language_id'),
            'currency_code' => (string)$this->config->get('config_currency'),
            'default_category_id' => 0,
            'status' => 1,
            'extra_settings' => array(
                'category_map_text' => '',
                'category_rules' => array(),
                'attribute_rules' => array(),
                'option_rules' => array()
            ),
            'field_map' => array(
                'external_id' => 1,
                'sku' => 2,
                'model' => 3,
                'name' => 4,
                'description' => 5,
                'price' => 6,
                'quantity' => 7,
                'manufacturer' => 8,
                'category' => 9,
                'image' => 10,
                'images' => '11',
                'specials' => 13
            )
        );

        if (!is_array($profile)) {
            return $defaults;
        }

        $result = array_merge($defaults, $profile);
        if (!isset($result['field_map']) || !is_array($result['field_map'])) {
            $result['field_map'] = $defaults['field_map'];
        } else {
            $result['field_map'] = array_merge($defaults['field_map'], $result['field_map']);
        }

        if (!isset($result['extra_settings']) || !is_array($result['extra_settings'])) {
            $result['extra_settings'] = $defaults['extra_settings'];
        } else {
            $result['extra_settings'] = array_merge($defaults['extra_settings'], $result['extra_settings']);
        }

        return $result;
    }

    private function buildPreviewProfile($data) {
        $source_type = isset($data['source_type']) ? (string)$data['source_type'] : 'url';
        if (!in_array($source_type, array('url', 'file'))) {
            $source_type = 'url';
        }

        $source_format = isset($data['source_format']) ? (string)$data['source_format'] : 'auto';
        if (!in_array($source_format, array('auto', 'csv', 'xlsx'))) {
            $source_format = 'auto';
        }

        return array(
            'source_type' => $source_type,
            'source_url' => isset($data['source_url']) ? (string)$data['source_url'] : '',
            'source_file' => isset($data['source_file']) ? (string)$data['source_file'] : '',
            'source_format' => $source_format,
            'sheet_index' => max(0, (int)$this->getArrayInt($data, 'sheet_index', 0)),
            'delimiter' => isset($data['delimiter']) ? (string)$data['delimiter'] : ';',
            'has_header' => (int)$this->getArrayInt($data, 'has_header', 1) ? 1 : 0,
            'start_row' => max(1, (int)$this->getArrayInt($data, 'start_row', 2))
        );
    }

    private function getPostInt($key, $default = 0) {
        return isset($this->request->post[$key]) ? (int)$this->request->post[$key] : (int)$default;
    }

    private function getArrayInt($data, $key, $default = 0) {
        return isset($data[$key]) ? (int)$data[$key] : (int)$default;
    }

    private function rowByColumnIndex($row, $index) {
        $index = (int)$index;
        if ($index <= 0) {
            return '';
        }

        return isset($row[$index]) ? (string)$row[$index] : '';
    }

    private function rowByColumnIndexes($row, $indexes_string) {
        $indexes = $this->parseColumnIndexes($indexes_string);
        if (!$indexes) {
            return '';
        }

        $values = array();
        foreach ($indexes as $index) {
            if (isset($row[$index])) {
                $value = trim((string)$row[$index]);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return implode(' | ', $values);
    }

    private function normalizeColumnList($value) {
        $indexes = $this->parseColumnIndexes($value);
        if (!$indexes) {
            return '';
        }

        return implode(',', $indexes);
    }

    private function parseColumnIndexes($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return array();
        }

        $parts = preg_split('/[^0-9]+/', $value);
        $result = array();

        foreach ($parts as $part) {
            $index = (int)$part;
            if ($index > 0) {
                $result[] = $index;
            }
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    private function decodeJsonArray($json) {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function normalizeCategoryRules($rules) {
        $result = array();
        if (!is_array($rules)) {
            return $result;
        }

        foreach ($rules as $row) {
            if (!is_array($row)) {
                continue;
            }

            $source = isset($row['source']) ? trim((string)$row['source']) : '';
            $category_id = isset($row['category_id']) ? (int)$row['category_id'] : 0;
            if ($source === '' || $category_id <= 0) {
                continue;
            }

            $result[] = array(
                'source' => $source,
                'category_id' => $category_id
            );
        }

        return $result;
    }

    private function normalizeAttributeRules($rules) {
        $result = array();
        if (!is_array($rules)) {
            return $result;
        }

        foreach ($rules as $row) {
            if (!is_array($row)) {
                continue;
            }

            $attribute_id = isset($row['attribute_id']) ? (int)$row['attribute_id'] : 0;
            $column = isset($row['column']) ? (int)$row['column'] : 0;
            if ($attribute_id <= 0 || $column <= 0) {
                continue;
            }

            $result[] = array(
                'attribute_id' => $attribute_id,
                'column' => $column
            );
        }

        return $result;
    }

    private function normalizeOptionRules($rules) {
        $result = array();
        if (!is_array($rules)) {
            return $result;
        }

        foreach ($rules as $row) {
            if (!is_array($row)) {
                continue;
            }

            $option_id = isset($row['option_id']) ? (int)$row['option_id'] : 0;
            $column = isset($row['column']) ? (int)$row['column'] : 0;
            if ($option_id <= 0 || $column <= 0) {
                continue;
            }

            $result[] = array(
                'option_id' => $option_id,
                'column' => $column
            );
        }

        return $result;
    }
}
