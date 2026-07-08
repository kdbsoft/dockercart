<?php

class ControllerExtensionModuleDockercartGoogleTranslation extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/dockercart_google_translation');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/dockercart_google_translation');
        $this->load->model('localisation/language');
        $this->load->model('setting/event');

        $this->ensureCacheFlushEventsRegistered();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_google_translation', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/dockercart_google_translation', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data = array();

        foreach ($this->language->all() as $key => $value) {
            $data[$key] = $value;
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
            'href' => $this->url->link('extension/module/dockercart_google_translation', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['action'] = $this->url->link('extension/module/dockercart_google_translation', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token'] = $this->session->data['user_token'];

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['default_language_id'] = $this->model_extension_module_dockercart_google_translation->getDefaultLanguageId();

        $keys = array(
            'module_dockercart_google_translation_status' => 0,
            'module_dockercart_google_translation_api_key' => getenv('GOOGLE_TRANSLATE_API_KEY') ?: '',
            'module_dockercart_google_translation_match_threshold' => 90,
            'module_dockercart_google_translation_force_overwrite' => 0,
            'module_dockercart_google_translation_price_per_million' => 20,
        );

        foreach ($keys as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $val = $this->config->get($key);
                $data[$key] = ($val !== null && $val !== '') ? $val : $default;
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_google_translation', $data));
    }

    public function install() {
        $this->load->model('extension/module/dockercart_google_translation');
        $this->model_extension_module_dockercart_google_translation->install();

        $this->registerCacheFlushEvents();
        $this->registerMenuEvent();
        $this->registerHeaderEvents();
    }

    public function uninstall() {
        $this->unregisterCacheFlushEvents();
        $this->unregisterMenuEvent();
    }

    public function scan() {
        $this->load->language('extension/module/dockercart_google_translation');
        $this->load->model('extension/module/dockercart_google_translation');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_google_translation')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                $input = $this->getJsonBody();

                $source_language_id = isset($input['source_language_id']) ? (int)$input['source_language_id'] : 0;
                $target_language_id = isset($input['target_language_id']) ? (int)$input['target_language_id'] : 0;
                $include_db = !empty($input['include_db']);
                $include_files = !empty($input['include_files']);

                $match_threshold = isset($input['match_threshold']) ? (float)$input['match_threshold'] : (float)$this->config->get('module_dockercart_google_translation_match_threshold');

                if ($source_language_id <= 0 || $target_language_id <= 0 || $source_language_id === $target_language_id) {
                    throw new Exception($this->language->get('error_language_pair'));
                }

                $json['report'] = $this->model_extension_module_dockercart_google_translation->buildScanReport(
                    $source_language_id,
                    $target_language_id,
                    $match_threshold,
                    $include_db,
                    $include_files
                );
                $json['success'] = true;
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function translate() {
        $this->load->language('extension/module/dockercart_google_translation');
        $this->load->model('extension/module/dockercart_google_translation');

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_google_translation')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                $input = $this->getJsonBody();

                $source_language_id = isset($input['source_language_id']) ? (int)$input['source_language_id'] : 0;
                $target_language_id = isset($input['target_language_id']) ? (int)$input['target_language_id'] : 0;
                $translate_db = !empty($input['translate_db']);
                $translate_files = !empty($input['translate_files']);
                $selected_tables = isset($input['selected_tables']) && is_array($input['selected_tables']) ? $input['selected_tables'] : array();
                $force_overwrite = !empty($input['force_overwrite']);

                $match_threshold = isset($input['match_threshold']) ? (float)$input['match_threshold'] : (float)$this->config->get('module_dockercart_google_translation_match_threshold');

                if ($source_language_id <= 0 || $target_language_id <= 0 || $source_language_id === $target_language_id) {
                    throw new Exception($this->language->get('error_language_pair'));
                }

                $json['result'] = $this->model_extension_module_dockercart_google_translation->executeTranslation(
                    $source_language_id,
                    $target_language_id,
                    $match_threshold,
                    $force_overwrite,
                    $translate_db,
                    $translate_files,
                    $selected_tables
                );
                $json['success'] = true;
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function quickTranslate() {
        $this->load->language('extension/module/dockercart_google_translation');
        $this->load->model('extension/module/dockercart_google_translation');

        $json = array();

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_google_translation')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                $input = $this->getJsonBody();

                $source_language_id = isset($input['source_language_id']) ? (int)$input['source_language_id'] : 0;
                $target_language_id = isset($input['target_language_id']) ? (int)$input['target_language_id'] : 0;
                $text = isset($input['text']) ? (string)$input['text'] : '';

                if ($source_language_id <= 0 || $target_language_id <= 0 || $source_language_id === $target_language_id) {
                    throw new Exception($this->language->get('error_language_pair'));
                }

                $json['translated_text'] = $this->model_extension_module_dockercart_google_translation->translateSingleText(
                    $text,
                    $source_language_id,
                    $target_language_id
                );

                $json['success'] = true;
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_google_translation')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function getJsonBody() {
        $raw = file_get_contents('php://input');

        if (!$raw) {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    public function eventProductCacheFlushAfter($route, $args = array()) {
        $this->flushSystemCacheSafe();
    }

    public function eventCategoryCacheFlushAfter($route, $args = array()) {
        $this->flushSystemCacheSafe();
    }

    public function eventManufacturerCacheFlushAfter($route, $args = array()) {
        $this->flushSystemCacheSafe();
    }

    private function registerCacheFlushEvents() {
        $this->load->model('setting/event');

        $events = array(
            'dockercart_google_translation_product_add_after' => array('admin/model/catalog/product/addProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_product_edit_after' => array('admin/model/catalog/product/editProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_product_delete_after' => array('admin/model/catalog/product/deleteProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_category_add_after' => array('admin/model/catalog/category/addCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_category_edit_after' => array('admin/model/catalog/category/editCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_category_delete_after' => array('admin/model/catalog/category/deleteCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_add_after' => array('admin/model/catalog/manufacturer/addManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_edit_after' => array('admin/model/catalog/manufacturer/editManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_delete_after' => array('admin/model/catalog/manufacturer/deleteManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter')
        );

        foreach ($events as $code => $event) {
            $this->model_setting_event->addEvent($code, $event[0], $event[1]);
        }
    }

    private function ensureCacheFlushEventsRegistered() {
        $events = array(
            'dockercart_google_translation_product_add_after' => array('admin/model/catalog/product/addProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_product_edit_after' => array('admin/model/catalog/product/editProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_product_delete_after' => array('admin/model/catalog/product/deleteProduct/after', 'extension/module/dockercart_google_translation/eventProductCacheFlushAfter'),
            'dockercart_google_translation_category_add_after' => array('admin/model/catalog/category/addCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_category_edit_after' => array('admin/model/catalog/category/editCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_category_delete_after' => array('admin/model/catalog/category/deleteCategory/after', 'extension/module/dockercart_google_translation/eventCategoryCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_add_after' => array('admin/model/catalog/manufacturer/addManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_edit_after' => array('admin/model/catalog/manufacturer/editManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter'),
            'dockercart_google_translation_manufacturer_delete_after' => array('admin/model/catalog/manufacturer/deleteManufacturer/after', 'extension/module/dockercart_google_translation/eventManufacturerCacheFlushAfter')
        );

        foreach ($events as $code => $event) {
            $exists = $this->db->query("SELECT event_id FROM " . DB_PREFIX . "event WHERE code = '" . $this->db->escape($code) . "' LIMIT 1");

            if (!$exists->num_rows) {
                $this->model_setting_event->addEvent($code, $event[0], $event[1]);
            }
        }
    }

    private function unregisterCacheFlushEvents() {
        $this->db->query("DELETE FROM " . DB_PREFIX . "event WHERE code LIKE 'dockercart_google_translation_%'");
    }

    private function registerMenuEvent() {
        $this->load->model('setting/event');
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'dockercart_google_translation_admin_menu'");
        $this->model_setting_event->addEvent(
            'dockercart_google_translation_admin_menu',
            'admin/view/common/column_left/before',
            'extension/module/dockercart_google_translation/eventAdminMenu',
            1,
            0
        );
    }

    private function unregisterMenuEvent() {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'dockercart_google_translation_admin_menu'");
    }

    public function eventAdminMenu(&$route, &$data, &$output) {
        $this->load->language('extension/module/dockercart_google_translation');

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_google_translation')) {
            return;
        }

        $menu = array(
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_google_translation', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );

        if (!isset($data['menus']) || !is_array($data['menus'])) {
            return;
        }

        foreach ($data['menus'] as &$item) {
            if (isset($item['id']) && $item['id'] === 'menu-extension' && isset($item['children']) && is_array($item['children'])) {
                $item['children'][] = $menu;
                return;
            }
        }

        $data['menus'][] = array(
            'id' => 'menu-dockercart-google-translation',
            'icon' => 'fa-language',
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_google_translation', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );
    }

    private function registerHeaderEvents() {
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent(
            'dockercart_google_translation_header_after',
            'admin/view/common/header/after',
            'extension/module/dockercart_google_translation/eventHeaderAfter'
        );
    }

    public function getConfig() {
        $this->load->model('extension/module/dockercart_google_translation');
        $this->load->model('localisation/language');

        $json = array(
            'enabled' => (int)$this->config->get('module_dockercart_google_translation_status'),
            'default_language_id' => $this->model_extension_module_dockercart_google_translation->getDefaultLanguageId()
        );

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function eventHeaderAfter(&$route, &$data, &$output) {
        if ((int)$this->config->get('module_dockercart_google_translation_status')) {
            $output .= '<script src="view/javascript/dockercart_google_translation_inline.js" type="text/javascript"></script>';
        }
    }

    private function flushSystemCacheSafe() {
        try {
            $this->cache->flush();
        } catch (Throwable $e) {
            // Avoid interrupting save/delete flows due to cache backend issues.
        }
    }
}
