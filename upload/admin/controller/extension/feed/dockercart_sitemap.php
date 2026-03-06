<?php
class ControllerExtensionFeedDockercartSitemap extends Controller {
    private $logger;
    private $error = array();
    // Module version — update this when releasing new versions
    private $module_version = '1.0.1';

     /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'googlebase');
    }

    public function index() {
        $this->load->language('extension/feed/dockercart_sitemap');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {


            $this->model_setting_setting->editSetting('dockercart_sitemap', $this->request->post);

            // Keep native feed status in sync so Extensions > Feeds shows the correct state
            if (isset($this->request->post['feed_dockercart_sitemap_status'])) {
                $feed_status = (int)$this->request->post['feed_dockercart_sitemap_status'];
            } elseif (isset($this->request->post['dockercart_sitemap_status'])) {
                $feed_status = (int)$this->request->post['dockercart_sitemap_status'];
            } elseif (isset($this->request->post['module_dockercart_sitemap_status'])) {
                $feed_status = (int)$this->request->post['module_dockercart_sitemap_status'];
            } else {
                $feed_status = 0;
            }
            $this->model_setting_setting->editSettingValue('feed_dockercart_sitemap', 'feed_dockercart_sitemap_status', $feed_status);


            $module_data = array();
            foreach ($this->request->post as $key => $value) {
                if (strpos($key, 'module_') === 0) {
                    $module_data[$key] = $value;
                } else {
                    $module_data['module_' . $key] = $value;
                }
            }
            $this->model_setting_setting->editSetting('module_dockercart_sitemap', $module_data);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
        }


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
            'href' => $this->url->link('extension/feed/dockercart_sitemap', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/feed/dockercart_sitemap', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);
        $data['generate'] = $this->url->link('extension/feed/dockercart_sitemap', 'user_token=' . $this->session->data['user_token'] . '&generate=1', true);


        $settings = array(
            'dockercart_sitemap_status',
            'dockercart_sitemap_products',
            'dockercart_sitemap_categories',
            'dockercart_sitemap_manufacturers',
            'dockercart_sitemap_information',
            'dockercart_sitemap_product_priority',
            'dockercart_sitemap_product_changefreq',
            'dockercart_sitemap_category_priority',
            'dockercart_sitemap_category_changefreq',
            'dockercart_sitemap_manufacturer_priority',
            'dockercart_sitemap_manufacturer_changefreq',
            'dockercart_sitemap_information_priority',
            'dockercart_sitemap_information_changefreq',
            'dockercart_sitemap_max_urls',
            'dockercart_sitemap_max_file_size_mb'
            ,'dockercart_sitemap_cache_seconds'
            ,'dockercart_sitemap_create_gzip'
        );

        foreach ($settings as $setting) {
            if (isset($this->request->post[$setting])) {
                $data[$setting] = $this->request->post[$setting];
            } else {
                $data[$setting] = $this->config->get($setting);
            }
        }

        if (!isset($data['dockercart_sitemap_product_priority'])) {
            $data['dockercart_sitemap_product_priority'] = '0.8';
        }
        if (!isset($data['dockercart_sitemap_product_changefreq'])) {
            $data['dockercart_sitemap_product_changefreq'] = 'weekly';
        }
        if (!isset($data['dockercart_sitemap_category_priority'])) {
            $data['dockercart_sitemap_category_priority'] = '0.9';
        }
        if (!isset($data['dockercart_sitemap_category_changefreq'])) {
            $data['dockercart_sitemap_category_changefreq'] = 'weekly';
        }
        if (!isset($data['dockercart_sitemap_manufacturer_priority'])) {
            $data['dockercart_sitemap_manufacturer_priority'] = '0.7';
        }
        if (!isset($data['dockercart_sitemap_manufacturer_changefreq'])) {
            $data['dockercart_sitemap_manufacturer_changefreq'] = 'monthly';
        }
        if (!isset($data['dockercart_sitemap_information_priority'])) {
            $data['dockercart_sitemap_information_priority'] = '0.5';
        }
        if (!isset($data['dockercart_sitemap_information_changefreq'])) {
            $data['dockercart_sitemap_information_changefreq'] = 'monthly';
        }
        if (!isset($data['dockercart_sitemap_max_urls'])) {
            $data['dockercart_sitemap_max_urls'] = '50000';
        }
        if (!isset($data['dockercart_sitemap_max_file_size_mb'])) {
            $data['dockercart_sitemap_max_file_size_mb'] = '50';
        }
        if (!isset($data['dockercart_sitemap_cache_seconds'])) {
            $data['dockercart_sitemap_cache_seconds'] = '86400';
        }
        if (!isset($data['dockercart_sitemap_create_gzip'])) {
            $data['dockercart_sitemap_create_gzip'] = 0;
        }

        $data['changefreq_options'] = array(
            'always'  => $this->language->get('text_always'),
            'hourly'  => $this->language->get('text_hourly'),
            'daily'   => $this->language->get('text_daily'),
            'weekly'  => $this->language->get('text_weekly'),
            'monthly' => $this->language->get('text_monthly'),
            'yearly'  => $this->language->get('text_yearly'),
            'never'   => $this->language->get('text_never')
        );


        $data['sitemap_url'] = HTTP_CATALOG . 'sitemap.xml';

        $data['last_generated'] = $this->config->get('dockercart_sitemap_last_generated');
        $data['sitemap_file_count'] = (int)$this->config->get('dockercart_sitemap_file_count');

        $data['generate_ajax'] = $this->url->link('extension/feed/dockercart_sitemap/ajaxGenerate', 'user_token=' . $this->session->data['user_token'], true);



        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_sitemap');

        if (isset($this->request->post['module_dockercart_sitemap_license_key'])) {
            $data['module_dockercart_sitemap_license_key'] = $this->request->post['module_dockercart_sitemap_license_key'];
        } else {
            $data['module_dockercart_sitemap_license_key'] = isset($module_settings['module_dockercart_sitemap_license_key']) ? $module_settings['module_dockercart_sitemap_license_key'] : '';
        }

        if (isset($this->request->post['module_dockercart_sitemap_public_key'])) {
            $data['module_dockercart_sitemap_public_key'] = $this->request->post['module_dockercart_sitemap_public_key'];
        } else {
            $data['module_dockercart_sitemap_public_key'] = isset($module_settings['module_dockercart_sitemap_public_key']) ? $module_settings['module_dockercart_sitemap_public_key'] : '';
        }


        $data['license_verify_ajax'] = $this->url->link('extension/feed/dockercart_sitemap/verifyLicenseAjax', 'user_token=' . $this->session->data['user_token'], true);
        $data['license_save_ajax'] = $this->url->link('extension/feed/dockercart_sitemap/saveLicenseKeyAjax', 'user_token=' . $this->session->data['user_token'], true);


    $data['license_domain'] = $_SERVER['HTTP_HOST'] ?? 'unknown';


        $data['license_valid'] = false;
        $data['license_message'] = '';
        try {
            if (file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                require_once(DIR_SYSTEM . 'library/dockercart_license.php');

                $license_key = $data['module_dockercart_sitemap_license_key'] ?? '';
                $public_key = $data['module_dockercart_sitemap_public_key'] ?? '';
                
                if (!empty($license_key)) {
                    $license = new DockercartLicense($this->registry);
                    if (!empty($public_key)) {
                        $res = $license->verifyWithPublicKey((string)$license_key, (string)$public_key, 'dockercart_sitemap', true);
                    } else {
                        $res = $license->verify((string)$license_key, 'dockercart_sitemap', true);
                    }
                    $data['license_valid'] = !empty($res['valid']);
                    $data['license_message'] = $res['error'] ?? '';
                }
            }
        } catch (Throwable $e) {
            $data['license_valid'] = false;
            $data['license_message'] = 'License check error: ' . $e->getMessage();
        }


    $data['entry_limits_heading'] = $this->language->get('entry_limits_heading');
    $data['entry_max_urls'] = $this->language->get('entry_max_urls');
    $data['help_max_urls'] = $this->language->get('help_max_urls');
    $data['entry_max_file_size_mb'] = $this->language->get('entry_max_file_size_mb');
    $data['help_max_file_size_mb'] = $this->language->get('help_max_file_size_mb');
    $data['entry_cache_seconds'] = $this->language->get('entry_cache_seconds');
    $data['help_cache_seconds'] = $this->language->get('help_cache_seconds');

    // Simple inline labels (no lang file required for backward compatibility)
    $data['entry_create_gzip'] = 'Create gzipped sitemap files';
    $data['help_create_gzip'] = 'When enabled, the generator will create .xml.gz versions of sitemap fragments and update the sitemap index to reference the compressed files. XML is generated by default.';

        // Expose module version to template. Prefer a global DOCKERCART_VERSION constant if defined.
        $data['module_version'] = defined('DOCKERCART_VERSION') ? DOCKERCART_VERSION : $this->module_version;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/feed/dockercart_sitemap', $data));
    }

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
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_sitemap', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_sitemap', true);
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

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_sitemap')) {
            $json['success'] = false;
            $json['error'] = 'No permission';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->model('setting/setting');


            $this->model_setting_setting->editSettingValue('module_dockercart_sitemap', 'module_dockercart_sitemap_license_key', $license_key);

            if (!empty($public_key)) {
                $this->model_setting_setting->editSettingValue('module_dockercart_sitemap', 'module_dockercart_sitemap_public_key', $public_key);
            }

            $json['success'] = true;
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }



    public function ajaxGenerate() {
        $this->load->language('extension/feed/dockercart_sitemap');

        $json = array();


        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_sitemap')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {

            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });



            $catalog_url = (defined('HTTP_CATALOG') ? rtrim(HTTP_CATALOG, '/') : rtrim($this->config->get('config_url'), '/')) . '/index.php?route=extension/feed/dockercart_sitemap';

            $catalog_url .= '&regenerate=1&admin_request=1';


            @array_map('unlink', glob(DIR_APPLICATION . '../sitemap*.xml'));


            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $catalog_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);

                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $response = curl_exec($ch);
                $curl_errno = curl_errno($ch);
                $curl_error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curl_errno || $http_code >= 400) {
                    throw new \RuntimeException('Catalog generation failed: ' . ($curl_error ?: 'HTTP ' . $http_code));
                }
            } else {

                $context = stream_context_create(array('http' => array('timeout' => 120)));
                $response = @file_get_contents($catalog_url, false, $context);
                if ($response === false) {
                    throw new \RuntimeException('Catalog generation failed: file_get_contents error');
                }
            }


            restore_error_handler();


            $files = glob(DIR_APPLICATION . '../sitemap*.xml');
            $gzfiles = glob(DIR_APPLICATION . '../sitemap*.xml.gz');
            $files = $files ?: array();
            $gzfiles = $gzfiles ?: array();
            // Merge and keep unique
            $files = array_values(array_unique(array_merge($files, $gzfiles)));


            $this->load->model('setting/setting');
            $last_generated = date('c');
            $file_count = count($files);


            $current_settings = $this->model_setting_setting->getSetting('dockercart_sitemap');


            $current_settings['dockercart_sitemap_last_generated'] = $last_generated;
            $current_settings['dockercart_sitemap_file_count'] = $file_count;


            $this->model_setting_setting->editSetting('dockercart_sitemap', $current_settings);

            $json['success'] = $this->language->get('text_generated');
            $json['files'] = array();
            foreach ($files as $f) {
                $json['files'][] = array(
                    'name' => basename($f),
                    'size' => is_file($f) ? filesize($f) : 0,
                    'lastmod' => is_file($f) ? date('c', filemtime($f)) : null
                );
            }
            $json['last_generated'] = $last_generated;
            $json['file_count'] = $file_count;
        } catch (\Throwable $e) {

            restore_error_handler();
            $json['error'] = $e->getMessage();
            $this->logger->info('DockerCart Sitemap generation error: ' . $e->__toString());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }



    public function install() {
        $this->load->model('setting/setting');


        $defaults = array(
            'dockercart_sitemap_status' => 0,
            'dockercart_sitemap_products' => 1,
            'dockercart_sitemap_categories' => 1,
            'dockercart_sitemap_manufacturers' => 1,
            'dockercart_sitemap_information' => 1,
            'dockercart_sitemap_product_priority' => '0.8',
            'dockercart_sitemap_product_changefreq' => 'weekly',
            'dockercart_sitemap_category_priority' => '0.9',
            'dockercart_sitemap_manufacturer_priority' => '0.7',
            'dockercart_sitemap_manufacturer_changefreq' => 'monthly',
            'dockercart_sitemap_information_priority' => '0.5',
            'dockercart_sitemap_information_changefreq' => 'monthly',
            'dockercart_sitemap_max_urls' => '50000',
            'dockercart_sitemap_max_file_size_mb' => '50',
            'dockercart_sitemap_cache_seconds' => '86400',
            'dockercart_sitemap_create_gzip' => 0,
            'module_dockercart_sitemap_license_key' => '',
            'module_dockercart_sitemap_public_key' => ''
        );

        $this->model_setting_setting->editSetting('dockercart_sitemap', $defaults);
        $this->model_setting_setting->editSetting('module_dockercart_sitemap', $defaults);
        $this->model_setting_setting->editSettingValue('feed_dockercart_sitemap', 'feed_dockercart_sitemap_status', 0);



        $webroot = DIR_APPLICATION . '../';
        $htaccess = $webroot . '.htaccess';

        $marker_start = "# DockerCart Sitemap - BEGIN\n";
        $marker_end = "# DockerCart Sitemap - END\n";

        $snippet = $marker_start
            . "<IfModule mod_rewrite.c>\n"
            . "RewriteEngine On\n"
            . "RewriteRule ^sitemap\\.xml$ index.php?route=extension/feed/dockercart_sitemap [L,QSA]\n"
            . "</IfModule>\n"
            . $marker_end;

        try {
            if (file_exists($htaccess)) {
                $content = @file_get_contents($htaccess);
                if ($content !== false) {

                    if (strpos($content, 'DockerCart Sitemap - BEGIN') === false) {
                        @copy($htaccess, $htaccess . '.bak.' . time());

                        $content = $snippet . "\n" . $content;
                        @file_put_contents($htaccess, $content, LOCK_EX);
                    }
                }
            } else {

                @file_put_contents($htaccess, $snippet, LOCK_EX);
                @chmod($htaccess, 0644);
            }
        } catch (\Throwable $e) {

            $this->logger->info('DockerCart Sitemap: failed to update .htaccess: ' . $e->getMessage());
        }
    }



    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('dockercart_sitemap');


        $files = glob(DIR_APPLICATION . '../sitemap*.xml');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }


        $webroot = DIR_APPLICATION . '../';
        $htaccess = $webroot . '.htaccess';
        if (file_exists($htaccess)) {
            $content = @file_get_contents($htaccess);
            if ($content !== false) {

                $new = preg_replace('/# DockerCart Sitemap - BEGIN[\s\S]*?# DockerCart Sitemap - END\n?/i', '', $content);
                if ($new !== null && $new !== $content) {
                    @file_put_contents($htaccess, $new, LOCK_EX);
                }
            }
        }


        $this->model_setting_setting->deleteSetting('module_dockercart_sitemap');
    }



    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/feed/dockercart_sitemap')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }


        $priorities = array(
            'dockercart_sitemap_product_priority',
            'dockercart_sitemap_category_priority',
            'dockercart_sitemap_manufacturer_priority',
            'dockercart_sitemap_information_priority'
        );

        foreach ($priorities as $priority) {
            if (isset($this->request->post[$priority])) {
                $value = (float)$this->request->post[$priority];
                if ($value < 0 || $value > 1) {
                    $this->error['warning'] = $this->language->get('error_priority');
                    break;
                }
            }
        }


        if (isset($this->request->post['dockercart_sitemap_max_urls'])) {
            $max_urls = $this->request->post['dockercart_sitemap_max_urls'];
            if (!ctype_digit((string)$max_urls) || (int)$max_urls < 1000 || (int)$max_urls > 1000000) {
                $this->error['warning'] = $this->language->get('error_max_urls');
            }
        }

        if (isset($this->request->post['dockercart_sitemap_max_file_size_mb'])) {
            $max_mb = $this->request->post['dockercart_sitemap_max_file_size_mb'];
            if (!ctype_digit((string)$max_mb) || (int)$max_mb < 1 || (int)$max_mb > 1024) {
                $this->error['warning'] = $this->language->get('error_max_file_size_mb');
            }
        }

        if (isset($this->request->post['dockercart_sitemap_cache_seconds'])) {
            $cache = $this->request->post['dockercart_sitemap_cache_seconds'];
            if (!ctype_digit((string)$cache) || (int)$cache < 60 || (int)$cache > 31536000) {
                $this->error['warning'] = $this->language->get('error_cache_seconds');
            }
        }

        return !$this->error;
    }
}
?>
