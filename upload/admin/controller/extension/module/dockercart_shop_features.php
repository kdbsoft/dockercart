<?php
class ControllerExtensionModuleDockercartShopFeatures extends Controller {
    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/dockercart_shop_features');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('localisation/language');

        $selected_module_id = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['icon_options'] = $this->getLucideIconOptions();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $module_data = $this->request->post;
            $module_data['features'] = $this->normalizeFeatures(isset($module_data['features']) ? $module_data['features'] : array(), $data['languages']);

            if ($selected_module_id > 0) {
                $this->model_setting_module->editModule($selected_module_id, $module_data);
                $saved_module_id = $selected_module_id;
            } else {
                $this->model_setting_module->addModule('dockercart_shop_features', $module_data);
                $saved_module_id = (int)$this->db->getLastId();
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $saved_module_id, true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';

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
            'href' => $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['new_widget'] = $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'], true);

        if ($selected_module_id > 0 && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $module_info = $this->model_setting_module->getModule($selected_module_id);
        } else {
            $module_info = array();
        }

        $defaults = array(
            'name' => $this->language->get('text_default_module_name'),
            'status' => 1
        );

        foreach ($defaults as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } elseif (!empty($module_info) && isset($module_info[$key])) {
                $data[$key] = $module_info[$key];
            } else {
                $data[$key] = $default;
            }
        }

        if (isset($this->request->post['features'])) {
            $data['features'] = $this->normalizeFeatures($this->request->post['features'], $data['languages']);
        } elseif (!empty($module_info) && isset($module_info['features']) && is_array($module_info['features'])) {
            $data['features'] = $this->normalizeFeatures($module_info['features'], $data['languages']);
        } else {
            $data['features'] = $this->getDefaultFeatures($data['languages']);
        }

        $modules = $this->model_setting_module->getModulesByCode('dockercart_shop_features');
        $data['widgets'] = array();

        foreach ($modules as $module) {
            $module_id = isset($module['module_id']) ? (int)$module['module_id'] : 0;

            $data['widgets'][] = array(
                'module_id' => $module_id,
                'name' => !empty($module['name']) ? $module['name'] : $this->language->get('text_default_module_name') . ' #' . $module_id,
                'href' => $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module_id, true),
                'active' => ($selected_module_id === $module_id)
            );
        }

        $data['current_module_id'] = $selected_module_id;

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_shop_features', $data));
    }

    public function install() {
        $this->load->model('user/user_group');

        $group_id = (int)$this->user->getGroupId();
        $this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_shop_features');
        $this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_shop_features');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_shop_features')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    private function normalizeFeatures($features, $languages) {
        $normalized = array();
        $icon_options = $this->getLucideIconOptions();
        $allowed_icons = array_flip($icon_options);

        if (!is_array($features)) {
            return $this->getDefaultFeatures($languages);
        }

        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $icon = isset($feature['icon']) ? trim((string)$feature['icon']) : 'truck';
            if (!isset($allowed_icons[$icon])) {
                $icon = 'truck';
            }

            $sort_order = isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0;
            $item = array(
                'icon' => $icon,
                'sort_order' => $sort_order,
                'title' => array(),
                'text' => array()
            );

            $has_content = false;

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];

                $title = '';
                if (isset($feature['title'][$language_id])) {
                    $title = trim((string)$feature['title'][$language_id]);
                }

                $text = '';
                if (isset($feature['text'][$language_id])) {
                    $text = trim((string)$feature['text'][$language_id]);
                }

                if ($title !== '' || $text !== '') {
                    $has_content = true;
                }

                $item['title'][$language_id] = $title;
                $item['text'][$language_id] = $text;
            }

            if ($has_content) {
                $normalized[] = $item;
            }
        }

        usort($normalized, function($a, $b) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        });

        if (!$normalized) {
            return $this->getDefaultFeatures($languages);
        }

        return array_values($normalized);
    }

    private function getDefaultFeatures($languages) {
        $defaults = array(
            array('icon' => 'truck', 'title_key' => 'text_default_feature_1_title', 'text_key' => 'text_default_feature_1_text'),
            array('icon' => 'shield-check', 'title_key' => 'text_default_feature_2_title', 'text_key' => 'text_default_feature_2_text'),
            array('icon' => 'refresh-ccw', 'title_key' => 'text_default_feature_3_title', 'text_key' => 'text_default_feature_3_text'),
            array('icon' => 'headset', 'title_key' => 'text_default_feature_4_title', 'text_key' => 'text_default_feature_4_text')
        );

        $features = array();

        foreach ($defaults as $index => $default) {
            $feature = array(
                'icon' => $default['icon'],
                'sort_order' => $index,
                'title' => array(),
                'text' => array()
            );

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $feature['title'][$language_id] = $this->language->get($default['title_key']);
                $feature['text'][$language_id] = $this->language->get($default['text_key']);
            }

            $features[] = $feature;
        }

        return $features;
    }

    private function getLucideIconOptions() {
        return array(
            'truck',
            'shield-check',
            'refresh-ccw',
            'headset',
            'badge-check',
            'gift',
            'sparkles',
            'package',
            'clock-3',
            'zap',
            'credit-card',
            'wallet',
            'leaf',
            'award',
            'thumbs-up',
            'smile',
            'check-circle-2',
            'star',
            'globe',
            'lock'
        );
    }
}
