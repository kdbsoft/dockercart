<?php
/**
 * DockerCart Export YML Module
 * 
 * Generates Yandex Market Language (YML) price lists for integration with Yandex Market
 * and other marketplaces supporting YML format.
 * 
 * Features:
 * - Multiple export profiles with independent settings
 * - Streaming XML generation via XMLWriter for memory efficiency
 * - Automatic file splitting by limits
 * - Atomic writes (tmp files + rename)
 * - File locking to prevent concurrent generation
 * - License verification
 * 
 * @package DockerCart
 * @subpackage Export YML
 * @version 1.0.0
 */

class ControllerExtensionFeedDockercartExportYml extends Controller {
    private $logger;
    private $error = array();

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'export_yml');
    }

    /**
     * Main admin page
     */
    public function index() {
        $this->load->language('extension/feed/dockercart_export_yml');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/feed/dockercart_export_yml');

        // Save settings
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('dockercart_export_yml', $this->request->post);

            // Keep native feed status in sync so Extensions > Feeds shows the correct state
            if (isset($this->request->post['feed_dockercart_export_yml_status'])) {
                $feed_status = (int)$this->request->post['feed_dockercart_export_yml_status'];
            } elseif (isset($this->request->post['dockercart_export_yml_status'])) {
                $feed_status = (int)$this->request->post['dockercart_export_yml_status'];
            } elseif (isset($this->request->post['module_dockercart_export_yml_status'])) {
                $feed_status = (int)$this->request->post['module_dockercart_export_yml_status'];
            } else {
                $feed_status = 0;
            }
            $this->model_setting_setting->editSettingValue('feed_dockercart_export_yml', 'feed_dockercart_export_yml_status', $feed_status);

            // Also save with module_ prefix for compatibility
            $module_data = array();
            foreach ($this->request->post as $key => $value) {
                if (strpos($key, 'module_') === 0) {
                    $module_data[$key] = $value;
                } else {
                    $module_data['module_' . $key] = $value;
                }
            }
            $this->model_setting_setting->editSetting('module_dockercart_export_yml', $module_data);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
        }

        // Error and success messages
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

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
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/feed/dockercart_export_yml', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/feed/dockercart_export_yml', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);

        // AJAX endpoints
        $data['ajax_generate'] = $this->url->link('extension/feed/dockercart_export_yml/ajaxGenerate', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_verify_license'] = $this->url->link('extension/feed/dockercart_export_yml/verifyLicenseAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_save_license'] = $this->url->link('extension/feed/dockercart_export_yml/saveLicenseKeyAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_save_profile'] = $this->url->link('extension/feed/dockercart_export_yml/saveProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_delete_profile'] = $this->url->link('extension/feed/dockercart_export_yml/deleteProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_get_profile'] = $this->url->link('extension/feed/dockercart_export_yml/getProfileAjax', 'user_token=' . $this->session->data['user_token'], true);
        
        // Catalog URL for frontend
        $data['catalog_url'] = HTTP_CATALOG;
        $data['default_language_code'] = (string)$this->config->get('config_language');

        // Load module settings (license keys are stored in module_* namespace)
        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_export_yml');

        // Load settings
        $settings = array(
            'dockercart_export_yml_status',
            'dockercart_export_yml_license_key',
            'dockercart_export_yml_public_key',
            'dockercart_export_yml_shop_name',
            'dockercart_export_yml_company',
            'dockercart_export_yml_max_products',
            'dockercart_export_yml_cache_ttl',
            'dockercart_export_yml_split_files',
            'dockercart_export_yml_products_per_file',
        );

        foreach ($settings as $setting) {
            $value = $this->config->get($setting);
            if (isset($this->request->post[$setting])) {
                $data[$setting] = $this->request->post[$setting];
            } elseif (!is_null($value)) {
                $data[$setting] = $value;
            } else {
                $data[$setting] = '';
            }
        }

        // License fields: keep visible after activation/save
        if (isset($this->request->post['module_dockercart_export_yml_license_key'])) {
            $data['dockercart_export_yml_license_key'] = $this->request->post['module_dockercart_export_yml_license_key'];
        } else {
            $data['dockercart_export_yml_license_key'] = isset($module_settings['module_dockercart_export_yml_license_key']) ? $module_settings['module_dockercart_export_yml_license_key'] : '';
        }

        if (isset($this->request->post['module_dockercart_export_yml_public_key'])) {
            $data['dockercart_export_yml_public_key'] = $this->request->post['module_dockercart_export_yml_public_key'];
        } else {
            $data['dockercart_export_yml_public_key'] = isset($module_settings['module_dockercart_export_yml_public_key']) ? $module_settings['module_dockercart_export_yml_public_key'] : '';
        }

        // Optional aliases used by some templates/scripts
        $data['module_dockercart_export_yml_license_key'] = $data['dockercart_export_yml_license_key'];
        $data['module_dockercart_export_yml_public_key'] = $data['dockercart_export_yml_public_key'];

        // Defaults
        if (empty($data['dockercart_export_yml_max_products'])) {
            $data['dockercart_export_yml_max_products'] = 50000;
        }
        if (empty($data['dockercart_export_yml_cache_ttl'])) {
            $data['dockercart_export_yml_cache_ttl'] = 3600;
        }
        if (empty($data['dockercart_export_yml_products_per_file'])) {
            $data['dockercart_export_yml_products_per_file'] = 10000;
        }

        // Load profiles
        $data['profiles'] = $this->model_extension_feed_dockercart_export_yml->getProfiles();

        // Load stores for profile configuration
        $this->load->model('setting/store');
        $data['stores'] = array();
        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name') . ' (Default)'
        );
        $stores = $this->model_setting_store->getStores();
        foreach ($stores as $store) {
            $data['stores'][] = $store;
        }

        // Load currencies
        $this->load->model('localisation/currency');
        $data['currencies'] = $this->model_localisation_currency->getCurrencies();

        // Load languages
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // Load categories for profile filters
        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(array());

        // Load manufacturers
        $this->load->model('catalog/manufacturer');
        $data['manufacturers'] = $this->model_catalog_manufacturer->getManufacturers(array());

        // License verification
        $data['license_valid'] = false;
        $data['license_message'] = '';
        
        try {
            if (file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                require_once(DIR_SYSTEM . 'library/dockercart_license.php');
                
                $license_key = $data['dockercart_export_yml_license_key'];
                $public_key = $data['dockercart_export_yml_public_key'];
                
                if (!empty($license_key)) {
                    $license = new DockercartLicense($this->registry);
                    if (!empty($public_key)) {
                        $res = $license->verifyWithPublicKey((string)$license_key, (string)$public_key, 'dockercart_export_yml', true);
                    } else {
                        $res = $license->verify((string)$license_key, 'dockercart_export_yml', true);
                    }
                    $data['license_valid'] = !empty($res['valid']);
                    $data['license_message'] = $res['error'] ?? '';
                }
            }
        } catch (Throwable $e) {
            $data['license_valid'] = false;
            $data['license_message'] = 'License check error: ' . $e->getMessage();
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/feed/dockercart_export_yml', $data));
    }

    /**
     * AJAX: Verify license
     */
    public function verifyLicenseAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

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

            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_export_yml', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_export_yml', true);
            }

            $json = $result;
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Save license key
     */
    public function saveLicenseKeyAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (empty($license_key)) {
            $json['success'] = false;
            $json['error'] = 'License key is empty';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->model('setting/setting');
            
            $settings = array(
                'module_dockercart_export_yml_license_key' => $license_key,
                'module_dockercart_export_yml_public_key' => $public_key
            );
            
            $this->model_setting_setting->editSetting('module_dockercart_export_yml', $settings);
            
            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Generate YML feed
     */
    public function ajaxGenerate() {
        $json = array();

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile ID');
            }

            // Trigger generation via catalog controller
            $catalog_url = HTTP_CATALOG . 'index.php?route=extension/feed/dockercart_export_yml&profile_id=' . $profile_id . '&regenerate=1&admin_request=1';
            
            $ch = curl_init($catalog_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curl_errno) {
                throw new Exception('cURL error: ' . $curl_error);
            }

            if ($http_code !== 200) {
                throw new Exception('HTTP error: ' . $http_code);
            }

            $json['success'] = true;
            $json['message'] = 'YML feed generated successfully';
            $json['url'] = HTTP_CATALOG . 'export-yml-' . $profile_id . '-' . (string)$this->config->get('config_language') . '.xml';
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Save profile
     */
    public function saveProfileAjax() {
        $json = array();

        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (empty($data['name'])) {
                throw new Exception('Profile name is required');
            }

            $this->load->model('extension/feed/dockercart_export_yml');
            
            $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
            
            if ($profile_id > 0) {
                $this->model_extension_feed_dockercart_export_yml->updateProfile($profile_id, $data);
            } else {
                $profile_id = $this->model_extension_feed_dockercart_export_yml->addProfile($data);
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

    /**
     * AJAX: Get profile data
     */
    public function getProfileAjax() {
        $json = array();

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile ID');
            }

            $this->load->model('extension/feed/dockercart_export_yml');
            $profile = $this->model_extension_feed_dockercart_export_yml->getProfile($profile_id);

            if (!$profile) {
                throw new Exception('Profile not found');
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

    /**
     * AJAX: Delete profile
     */
    public function deleteProfileAjax() {
        $json = array();

        try {
            $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
            
            if ($profile_id <= 0) {
                throw new Exception('Invalid profile ID');
            }

            $this->load->model('extension/feed/dockercart_export_yml');
            $this->model_extension_feed_dockercart_export_yml->deleteProfile($profile_id);

            // Remove generated files for this profile
            $webroot = DIR_APPLICATION . '../';
            @unlink($webroot . 'export-yml-' . $profile_id . '.xml');
            @unlink($webroot . 'export-yml-' . $profile_id . '.xml.gz');

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Install module
     */
    public function install() {
        $this->load->model('extension/feed/dockercart_export_yml');
        $this->load->model('setting/setting');
        $this->model_extension_feed_dockercart_export_yml->install();

        // Ensure feed list has a dedicated status key
        $this->model_setting_setting->editSettingValue('feed_dockercart_export_yml', 'feed_dockercart_export_yml_status', 0);

        $this->installHtaccessRewrite();
        
        $this->logger->info('DockerCart Export YML installed');
    }

    /**
     * Uninstall module
     */
    public function uninstall() {
        $this->load->model('extension/feed/dockercart_export_yml');
        $this->model_extension_feed_dockercart_export_yml->uninstall();
        
        // Remove all generated files
        $webroot = DIR_APPLICATION . '../';
        $files = glob($webroot . 'export-yml-*.xml');
        foreach ($files as $file) {
            @unlink($file);
        }
        $gzfiles = glob($webroot . 'export-yml-*.xml.gz');
        foreach ($gzfiles as $file) {
            @unlink($file);
        }

        $this->removeHtaccessRewrite();
        
        $this->logger->info('DockerCart Export YML uninstalled');
    }

    /**
     * Install .htaccess rewrite snippet for export-yml XML links.
     */
    private function installHtaccessRewrite() {
        $webroot = DIR_APPLICATION . '../';
        $htaccess = $webroot . '.htaccess';

        $marker_start = "# DockerCart Export YML - BEGIN\n";
        $marker_end = "# DockerCart Export YML - END\n";

        $snippet = $marker_start
            . "<IfModule mod_rewrite.c>\n"
            . "RewriteEngine On\n"
            . "RewriteRule ^export-yml-([0-9]+)(-[a-z]{2}-[a-z]{2})?(-part-[0-9]+)?\\.xml$ index.php?route=extension/feed/dockercart_export_yml&file=$0 [L,QSA]\n"
            . "</IfModule>\n"
            . $marker_end;

        try {
            if (file_exists($htaccess)) {
                $content = @file_get_contents($htaccess);
                if ($content !== false && strpos($content, 'DockerCart Export YML - BEGIN') === false) {
                    @copy($htaccess, $htaccess . '.bak.' . time());
                    $content = $snippet . "\n" . $content;
                    @file_put_contents($htaccess, $content, LOCK_EX);
                }
            } else {
                @file_put_contents($htaccess, $snippet, LOCK_EX);
                @chmod($htaccess, 0644);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('DockerCart Export YML: failed to update .htaccess: ' . $e->getMessage());
        }
    }

    /**
     * Remove .htaccess rewrite snippet for export-yml XML links.
     */
    private function removeHtaccessRewrite() {
        $webroot = DIR_APPLICATION . '../';
        $htaccess = $webroot . '.htaccess';

        if (!file_exists($htaccess)) {
            return;
        }

        $content = @file_get_contents($htaccess);
        if ($content === false) {
            return;
        }

        $new = preg_replace('/# DockerCart Export YML - BEGIN[\s\S]*?# DockerCart Export YML - END\n?/i', '', $content);
        if ($new !== null && $new !== $content) {
            @file_put_contents($htaccess, $new, LOCK_EX);
        }
    }

    /**
     * Validate form submission
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_export_yml')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
