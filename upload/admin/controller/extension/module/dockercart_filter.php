<?php
/**
 * DockerCart Filter — Admin controller
 *
 * Module settings UI and license management in the admin area.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 *
 * This module is distributed under a commercial/proprietary license.
 * Use, copying, modification, and distribution are permitted only under
 * the terms of a valid commercial license agreement with the copyright owner.
 *
 * For licensing inquiries contact: licensing@mathflow-bit.example
 */

class ControllerExtensionModuleDockerCartFilter extends Controller {
    private $error = array();
    private $logger;

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'filter');
    }

    public function index() {
        $this->logger->info('INDEX: Entered index() method');

        $this->load->language('extension/module/dockercart_filter');
        $this->logger->info('INDEX: Language loaded');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->logger->info('INDEX: Title set');

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_filter', $this->request->post);

            $this->clearModuleCache();

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

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
            'href' => $this->url->link('extension/module/dockercart_filter', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_filter', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        if (isset($this->request->post['module_dockercart_filter_status'])) {
            $data['module_dockercart_filter_status'] = $this->request->post['module_dockercart_filter_status'];
        } else {
            $data['module_dockercart_filter_status'] = $this->config->get('module_dockercart_filter_status');
        }

        if (isset($this->request->post['module_dockercart_filter_cache_time'])) {
            $data['module_dockercart_filter_cache_time'] = $this->request->post['module_dockercart_filter_cache_time'];
        } else {
            $data['module_dockercart_filter_cache_time'] = $this->config->get('module_dockercart_filter_cache_time') ? $this->config->get('module_dockercart_filter_cache_time') : 3600;
        }

        if (isset($this->request->post['module_dockercart_filter_mode'])) {
            $data['module_dockercart_filter_mode'] = $this->request->post['module_dockercart_filter_mode'];
        } else {
            $data['module_dockercart_filter_mode'] = $this->config->get('module_dockercart_filter_mode') ? $this->config->get('module_dockercart_filter_mode') : 'instant';
        }

        if (isset($this->request->post['module_dockercart_filter_seo_mode'])) {
            $data['module_dockercart_filter_seo_mode'] = $this->request->post['module_dockercart_filter_seo_mode'];
        } else {
            $data['module_dockercart_filter_seo_mode'] = $this->config->get('module_dockercart_filter_seo_mode') ? $this->config->get('module_dockercart_filter_seo_mode') : 1;
        }

        if (isset($this->request->post['module_dockercart_filter_items_limit'])) {
            $data['module_dockercart_filter_items_limit'] = $this->request->post['module_dockercart_filter_items_limit'];
        } else {
            $data['module_dockercart_filter_items_limit'] = $this->config->get('module_dockercart_filter_items_limit') ? $this->config->get('module_dockercart_filter_items_limit') : 5;
        }

        if (isset($this->request->post['module_dockercart_filter_attribute_separators'])) {
            $data['module_dockercart_filter_attribute_separators'] = $this->request->post['module_dockercart_filter_attribute_separators'];
        } else {
            $data['module_dockercart_filter_attribute_separators'] = $this->config->get('module_dockercart_filter_attribute_separators') ? $this->config->get('module_dockercart_filter_attribute_separators') : '';
        }

        if (isset($this->request->post['module_dockercart_filter_debug'])) {
            $data['module_dockercart_filter_debug'] = $this->request->post['module_dockercart_filter_debug'];
        } else {
            $data['module_dockercart_filter_debug'] = $this->config->get('module_dockercart_filter_debug') ? $this->config->get('module_dockercart_filter_debug') : 0;
        }

        if (isset($this->request->post['module_dockercart_filter_theme'])) {
            $data['module_dockercart_filter_theme'] = $this->request->post['module_dockercart_filter_theme'];
        } else {
            $data['module_dockercart_filter_theme'] = $this->config->get('module_dockercart_filter_theme') ? $this->config->get('module_dockercart_filter_theme') : 'light';
        }

        // Primary color (hex) for filter UI
        if (isset($this->request->post['module_dockercart_filter_primary_color'])) {
            $data['module_dockercart_filter_primary_color'] = $this->request->post['module_dockercart_filter_primary_color'];
        } else {
            $data['module_dockercart_filter_primary_color'] = $this->config->get('module_dockercart_filter_primary_color') ? $this->config->get('module_dockercart_filter_primary_color') : '#007bff';
        }

        // Note: primary opacity removed — only solid primary color is stored

        if (isset($this->request->post['module_dockercart_filter_custom_css'])) {
            $data['module_dockercart_filter_custom_css'] = $this->request->post['module_dockercart_filter_custom_css'];
        } else {
            $data['module_dockercart_filter_custom_css'] = $this->config->get('module_dockercart_filter_custom_css') ? $this->config->get('module_dockercart_filter_custom_css') : '';
        }

        if (isset($this->request->post['module_dockercart_filter_mobile_breakpoint'])) {
            $data['module_dockercart_filter_mobile_breakpoint'] = $this->request->post['module_dockercart_filter_mobile_breakpoint'];
        } else {
            $data['module_dockercart_filter_mobile_breakpoint'] = $this->config->get('module_dockercart_filter_mobile_breakpoint') ? $this->config->get('module_dockercart_filter_mobile_breakpoint') : 768;
        }

        $data['user_token'] = $this->session->data['user_token'];

        if (isset($this->request->post['module_dockercart_filter_license_key'])) {
            $data['module_dockercart_filter_license_key'] = $this->request->post['module_dockercart_filter_license_key'];
        } else {
            $data['module_dockercart_filter_license_key'] = $this->config->get('module_dockercart_filter_license_key');
        }

        if (isset($this->request->post['module_dockercart_filter_public_key'])) {
            $data['module_dockercart_filter_public_key'] = $this->request->post['module_dockercart_filter_public_key'];
        } else {
            $data['module_dockercart_filter_public_key'] = $this->config->get('module_dockercart_filter_public_key');
        }

        $data['license_domain'] = $_SERVER['HTTP_HOST'] ?? 'unknown';

        $this->load->model('catalog/attribute');
        $this->load->model('catalog/option');

        $disabledAttributesRaw = $this->config->get('module_dockercart_filter_disabled_attributes');
        $disabledOptionsRaw = $this->config->get('module_dockercart_filter_disabled_options');

        $disabledAttributes = array();
        if ($disabledAttributesRaw) {
            if (is_string($disabledAttributesRaw)) {
                $disabledAttributes = @unserialize($disabledAttributesRaw);
                if ($disabledAttributes === false) {
                    $disabledAttributes = array();
                }
            } elseif (is_array($disabledAttributesRaw)) {
                $disabledAttributes = $disabledAttributesRaw;
            }
        }

        $disabledOptions = array();
        if ($disabledOptionsRaw) {
            if (is_string($disabledOptionsRaw)) {
                $disabledOptions = @unserialize($disabledOptionsRaw);
                if ($disabledOptions === false) {
                    $disabledOptions = array();
                }
            } elseif (is_array($disabledOptionsRaw)) {
                $disabledOptions = $disabledOptionsRaw;
            }
        }

        $data['attributes'] = array();
        $attributes = $this->model_catalog_attribute->getAttributes();
        foreach ($attributes as $attribute) {
            $attrId = 'attr_' . $attribute['attribute_id'];
            $isDisabled = isset($this->request->post[$attrId]) ?
                          ($this->request->post[$attrId] != '1') :
                          in_array($attribute['attribute_id'], $disabledAttributes);

            $data['attributes'][] = array(
                'attribute_id' => $attribute['attribute_id'],
                'name' => $attribute['name'],
                'enabled' => !$isDisabled,
                'key' => $attrId
            );
        }

        $data['options'] = array();
        $options = $this->model_catalog_option->getOptions();
        foreach ($options as $option) {
            $optId = 'opt_' . $option['option_id'];
            $isDisabled = isset($this->request->post[$optId]) ?
                          ($this->request->post[$optId] != '1') :
                          in_array($option['option_id'], $disabledOptions);

            $data['options'][] = array(
                'option_id' => $option['option_id'],
                'name' => $option['name'],
                'enabled' => !$isDisabled,
                'key' => $optId
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_filter', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_filter')) {
            $this->error['warning'] = $this->language->get('error_permission');
        } else {

            $this->validateLicense();


            $this->logger->info('VALIDATE: Starting validation...');
            $this->logger->info('VALIDATE: POST keys related to filters: ' . json_encode(array_filter(array_keys($this->request->post), function($k) {
                return strpos($k, 'attr_') === 0 || strpos($k, 'opt_') === 0;
            })));

            $disabledAttributes = array();
            $disabledOptions = array();

            $this->load->model('catalog/attribute');
            $this->load->model('catalog/option');

            $allAttributes = $this->model_catalog_attribute->getAttributes();
            $allOptions = $this->model_catalog_option->getOptions();

            $this->logger->info('VALIDATE: Found ' . count($allAttributes) . ' attributes in DB');

            foreach ($allAttributes as $attribute) {
                $key = 'attr_' . $attribute['attribute_id'];
                $value = isset($this->request->post[$key]) ? $this->request->post[$key] : 'NOT_SET';
                $this->logger->info('VALIDATE: Attribute ' . $attribute['attribute_id'] . ' (' . $attribute['name'] . '): key=' . $key . ', value=' . $value);

                if (!isset($this->request->post[$key]) || $this->request->post[$key] != '1') {
                    $disabledAttributes[] = (int)$attribute['attribute_id'];
                    $this->logger->info('VALIDATE: -> DISABLED');
                } else {
                    $this->logger->info('VALIDATE: -> ENABLED');
                }
            }

            foreach ($allOptions as $option) {
                $key = 'opt_' . $option['option_id'];
                if (!isset($this->request->post[$key]) || $this->request->post[$key] != '1') {
                    $disabledOptions[] = (int)$option['option_id'];
                }
            }

            $this->logger->info('VALIDATE: Disabled attributes: ' . json_encode($disabledAttributes));
            $this->logger->info('VALIDATE: Disabled options: ' . json_encode($disabledOptions));

            $serializedAttrs = serialize($disabledAttributes);
            $serializedOpts = serialize($disabledOptions);

            $this->logger->info('VALIDATE: Serialized attributes: ' . $serializedAttrs);
            $this->logger->info('VALIDATE: Serialized options: ' . $serializedOpts);

            $this->request->post['module_dockercart_filter_disabled_attributes'] = $serializedAttrs;
            $this->request->post['module_dockercart_filter_disabled_options'] = $serializedOpts;

                    // Validate primary color (hex) if provided
                    if (isset($this->request->post['module_dockercart_filter_primary_color'])) {
                        $color = trim($this->request->post['module_dockercart_filter_primary_color']);
                        if (!preg_match('/^#?[0-9a-fA-F]{6}$|^#?[0-9a-fA-F]{3}$/', $color)) {
                            $this->error['warning'] = $this->language->get('error_invalid_color') ? $this->language->get('error_invalid_color') : 'Primary color must be a valid hex value.';
                        } else {
                            if (strpos($color, '#') !== 0) {
                                $color = '#' . $color;
                            }
                            $this->request->post['module_dockercart_filter_primary_color'] = strtolower($color);
                        }
                    }

                    // Primary opacity validation removed (opacity not used)
        }

        return !$this->error;
    }

    private function validateLicense() {
        $license_key = $this->config->get('module_dockercart_filter_license_key');

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
            $result = $license->verify($license_key, 'dockercart_filter');

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

                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_filter', true);
            } else {

                $this->logger->info('AJAX: Using saved public key from database');

                $result = $license->verify($license_key, 'dockercart_filter', true);
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

    public function saveLicenseKeyAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';


        $this->logger->info('AJAX: saveLicenseKeyAjax() called');
        $this->logger->info('AJAX: License key length: ' . strlen($license_key));
        $this->logger->info('AJAX: Public key length: ' . strlen($public_key));
        $this->logger->info('AJAX: Public key first 50 chars: ' . substr($public_key, 0, 50));
        $this->logger->info('AJAX: Public key last 50 chars: ' . substr($public_key, -50));

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_filter')) {
            $json['success'] = false;
            $json['error'] = 'No permission';
            $this->logger->info('AJAX: No permission to save');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!empty($public_key)) {

            require_once(DIR_SYSTEM . 'library/dockercart_license.php');
            $lic = new DockercartLicense($this->registry);

            $this->logger->info('AJAX: Cache will be cleared when license is verified with new public key');
        }

        try {

            $this->load->model('setting/setting');

            $this->model_setting_setting->editSettingValue('module_dockercart_filter', 'module_dockercart_filter_license_key', $license_key);
            $this->logger->info('AJAX: License key saved to database');

            if (!empty($public_key)) {
                $this->model_setting_setting->editSettingValue('module_dockercart_filter', 'module_dockercart_filter_public_key', $public_key);
                $this->logger->info('AJAX: Public key saved to database with length: ' . strlen((string)$public_key));

                $saved_key = $this->config->get('module_dockercart_filter_public_key');
                $saved_key_str = (string)$saved_key;
                $this->logger->info('AJAX: Verification - saved key length in config: ' . strlen($saved_key_str));
                $this->logger->info('AJAX: Verification - saved key first 50 chars: ' . substr($saved_key_str, 0, 50));
            } else {
                $this->logger->info('AJAX: Public key is empty, not saved');
            }

            $json['success'] = true;
            $this->logger->info('AJAX: Save operation completed successfully');
        } catch (Exception $e) {
            $json['success'] = false;
            $json['error'] = $e->getMessage();
            $this->logger->info('AJAX: Error saving - ' . $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install() {
        $this->load->model('extension/module/dockercart_filter');
        $this->model_extension_module_dockercart_filter->install();
    }

    public function uninstall() {
        $this->load->model('extension/module/dockercart_filter');
        $this->model_extension_module_dockercart_filter->uninstall();
    }

    public function clearCache() {
        $this->load->language('extension/module/dockercart_filter');

        $json = array();

        $this->clearModuleCache();

        $json['success'] = $this->language->get('text_cache_cleared');

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function clearModuleCache() {

        $cache_keys = array(
            'dockercart_filter',
            'dockercart_filter.manufacturers',
            'dockercart_filter.attributes',
            'dockercart_filter.options',
            'dockercart_filter.price_range'
        );

        foreach ($cache_keys as $key) {
            $this->cache->delete($key);
        }

        for ($i = 1; $i <= 100; $i++) {
            $this->cache->delete('dockercart_filter.manufacturers.' . $i);
            $this->cache->delete('dockercart_filter.attributes.' . $i);
            $this->cache->delete('dockercart_filter.options.' . $i);
            $this->cache->delete('dockercart_filter.price_range.' . $i);
        }

        $cache_dir = defined('DIR_CACHE') ? DIR_CACHE : DIR_STORAGE . 'cache/';
        $files = glob($cache_dir . 'cache.dockercart_filter.*');
        if ($files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        $storage_cache = DIR_STORAGE . 'cache/';
        if (is_dir($storage_cache)) {
            $files = glob($storage_cache . '*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file) && strpos($file, 'dockercart_filter') !== false) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}
