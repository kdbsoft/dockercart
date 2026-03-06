<?php
class ControllerExtensionModuleDockercartImportYml extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/dockercart_import_yml');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->validateLicense();

        $this->load->model('setting/setting');
        $this->load->model('extension/module/dockercart_import_yml');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_import_yml', $this->request->post);

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
            'href' => $this->url->link('extension/module/dockercart_import_yml', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_import_yml', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['ajax_save_profile'] = $this->url->link('extension/module/dockercart_import_yml/saveProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_get_profile'] = $this->url->link('extension/module/dockercart_import_yml/getProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_delete_profile'] = $this->url->link('extension/module/dockercart_import_yml/deleteProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_run_profile'] = $this->url->link('extension/module/dockercart_import_yml/runProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_verify_license'] = $this->url->link('extension/module/dockercart_import_yml/verifyLicenseAjax', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->post['module_dockercart_import_yml_status'])) {
            $data['module_dockercart_import_yml_status'] = (int)$this->request->post['module_dockercart_import_yml_status'];
        } else {
            $data['module_dockercart_import_yml_status'] = (int)$this->config->get('module_dockercart_import_yml_status');
        }

        if (isset($this->request->post['module_dockercart_import_yml_license_key'])) {
            $data['module_dockercart_import_yml_license_key'] = $this->request->post['module_dockercart_import_yml_license_key'];
        } else {
            $data['module_dockercart_import_yml_license_key'] = $this->config->get('module_dockercart_import_yml_license_key');
        }

        if (isset($this->request->post['module_dockercart_import_yml_public_key'])) {
            $data['module_dockercart_import_yml_public_key'] = $this->request->post['module_dockercart_import_yml_public_key'];
        } else {
            $data['module_dockercart_import_yml_public_key'] = $this->config->get('module_dockercart_import_yml_public_key');
        }

        $this->load->model('setting/store');
        $data['stores'] = array();
        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name') . ' (Default)'
        );
        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = $store;
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $this->load->model('localisation/currency');
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();

        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(array());

        $data['profiles'] = $this->model_extension_module_dockercart_import_yml->getProfiles();
        $data['cron_base_path'] = '/var/www/html/cron/dockercart_import_yml.php';
        $data['module_version'] = '1.0.0';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_import_yml', $data));
    }

    public function saveProfileAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_yml');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_yml')) {
                throw new Exception($this->language->get('error_permission'));
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (empty($data['name'])) {
                throw new Exception($this->language->get('error_profile_name_required'));
            }
            if (empty($data['feed_url'])) {
                throw new Exception($this->language->get('error_feed_url_required'));
            }

            $this->load->model('extension/module/dockercart_import_yml');

            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            if ($profile_id > 0) {
                $this->model_extension_module_dockercart_import_yml->updateProfile($profile_id, $data);
            } else {
                $profile_id = $this->model_extension_module_dockercart_import_yml->addProfile($data);
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
        $this->load->language('extension/module/dockercart_import_yml');

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_yml');
            $profile = $this->model_extension_module_dockercart_import_yml->getProfile($profile_id);
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
        $this->load->language('extension/module/dockercart_import_yml');

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_yml')) {
                throw new Exception($this->language->get('error_permission'));
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_yml');
            $this->model_extension_module_dockercart_import_yml->deleteProfile($profile_id);

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function runProfileAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_yml');

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            $offset = isset($this->request->get['offset']) ? (int)$this->request->get['offset'] : 0;
            $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 0;

            if ($offset < 0) {
                $offset = 0;
            }

            if ($limit < 0) {
                $limit = 0;
            }

            if ($profile_id <= 0) {
                throw new Exception($this->language->get('error_profile_id_invalid'));
            }

            $this->load->model('extension/module/dockercart_import_yml');
            $profile = $this->model_extension_module_dockercart_import_yml->getProfile($profile_id);
            if (!$profile) {
                throw new Exception($this->language->get('error_profile_not_found'));
            }

            $url = HTTP_CATALOG . 'index.php?route=extension/module/dockercart_import_yml/cron&profile_id=' . $profile_id . '&cron_key=' . urlencode($profile['cron_key']) . '&format=json';

            if ($limit > 0) {
                $url .= '&offset=' . (int)$offset . '&limit=' . (int)$limit;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curl_errno) {
                throw new Exception($this->language->get('error_curl') . ': ' . $curl_error);
            }
            if ($http_code !== 200) {
                throw new Exception($this->language->get('error_http') . ': ' . $http_code . '. ' . $this->language->get('text_response') . ': ' . $response);
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                $start = strpos((string)$response, '{');
                $end = strrpos((string)$response, '}');

                if ($start !== false && $end !== false && $end > $start) {
                    $json_fragment = substr((string)$response, $start, $end - $start + 1);
                    $decoded = json_decode($json_fragment, true);
                }

                if (!is_array($decoded)) {
                    throw new Exception($this->language->get('error_invalid_response'));
                }
            }

            $json = $decoded;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function verifyLicenseAjax() {
        $json = array();
        $this->load->language('extension/module/dockercart_import_yml');

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (empty($license_key)) {
            $json['valid'] = false;
            $json['error'] = $this->language->get('error_license_key_empty');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $json['valid'] = false;
            $json['error'] = $this->language->get('error_license_library_not_found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $json['valid'] = false;
            $json['error'] = $this->language->get('error_license_class_not_found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);

            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_import_yml', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_import_yml', true);
            }

            $json = $result;
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = $this->language->get('error_prefix') . ': ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install() {
        $this->load->model('extension/module/dockercart_import_yml');
        $this->load->model('setting/setting');

        $this->model_extension_module_dockercart_import_yml->install();
        $this->model_setting_setting->editSettingValue('module_dockercart_import_yml', 'module_dockercart_import_yml_status', 0);
    }

    public function uninstall() {
        $this->load->model('extension/module/dockercart_import_yml');
        $this->model_extension_module_dockercart_import_yml->uninstall();
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_import_yml')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function validateLicense() {
        $license_key = $this->config->get('module_dockercart_import_yml_license_key');

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false || strpos($domain, '.docker.localhost') !== false) {
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
            $license->verify($license_key, 'dockercart_import_yml');
        } catch (Exception $e) {
            // silent fail in admin
        }

        return true;
    }
}
