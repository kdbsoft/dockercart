<?php
/**
 * DockerCart Theme Settings
 * Stores global visual settings for the dockercart storefront theme.
 *
 * Settings key group: dockercart_theme
 * Available settings:
 *   dockercart_theme_status         int  1
 *   dockercart_theme_logo_dark      str  path relative to DIR_IMAGE
 *   dockercart_theme_logo_light     str  path relative to DIR_IMAGE
 *   dockercart_theme_menu_type      str  horizontal|vertical
 *   dockercart_theme_social_N_image str  social icon image path (relative to DIR_IMAGE)
 *   dockercart_theme_social_N_link  str  social link URL
 *   dockercart_theme_payment_N_image str  payment icon image path
 *   dockercart_theme_payment_N_link  str  payment link URL
 *
 * Social and payment items support up to 10 rows each.
 * POST uses array inputs: dockercart_theme_social_image[], dockercart_theme_payment_image[], etc.
 *
 * Custom header/footer links also support up to 10 rows each:
 *   dockercart_theme_header_link_N_title  str  link title
 *   dockercart_theme_header_link_N_url    str  link URL
 *   dockercart_theme_footer_link_N_title  str  link title
 *   dockercart_theme_footer_link_N_url    str  link URL
 */
class ControllerExtensionModuleDockerCartTheme extends Controller {

    private $error = [];

    public function index() {
        $this->load->language('extension/module/dockercart_theme');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('tool/image');
        $this->load->model('localisation/language');

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['icon_options'] = $this->getLucideIconOptions();

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->_saveSettings();
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'extension/module/dockercart_theme',
                'user_token=' . $this->session->data['user_token'],
                true
            ));
        }

        /* ── Breadcrumbs ── */
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_theme', 'user_token=' . $this->session->data['user_token'], true),
        ];

        /* ── Errors ── */
        $data['error_warning'] = $this->error['warning'] ?? '';

        /* ── Flash success ── */
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        /* ── URLs ── */
        $data['action']      = $this->url->link('extension/module/dockercart_theme', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']      = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token']  = $this->session->data['user_token'];

        /* ── Module Status ── */
        $data['dockercart_theme_status'] = (int)$this->config->get('dockercart_theme_status');

        /* ── Dark Logo ── */
        $logo_dark = (string)$this->config->get('dockercart_theme_logo_dark');
        $data['dockercart_theme_logo_dark'] = $logo_dark;

        if ($logo_dark && is_file(DIR_IMAGE . $logo_dark)) {
            $data['logo_dark_thumb'] = $this->model_tool_image->resize($logo_dark, 200, 80);
        } else {
            $data['logo_dark_thumb'] = $this->model_tool_image->resize('no_image.png', 200, 80);
        }

        $data['placeholder']         = $this->model_tool_image->resize('no_image.png', 200, 80);
        $data['placeholder_payment'] = $this->model_tool_image->resize('no_image.png', 120, 40);
        $data['placeholder_social']  = $this->model_tool_image->resize('no_image.png', 40, 40);

        /* ── Light Logo ── */
        $logo_light = (string)$this->config->get('dockercart_theme_logo_light');
        $data['dockercart_theme_logo_light'] = $logo_light;

        if ($logo_light && is_file(DIR_IMAGE . $logo_light)) {
            $data['logo_light_thumb'] = $this->model_tool_image->resize($logo_light, 200, 80);
        } else {
            $data['logo_light_thumb'] = $this->model_tool_image->resize('no_image.png', 200, 80);
        }

        /* ── Favicon master ── */
        $favicon_master = (string)$this->config->get('dockercart_theme_favicon_master');
        $data['dockercart_theme_favicon_master'] = $favicon_master;

        if ($favicon_master && is_file(DIR_IMAGE . $favicon_master)) {
            $data['favicon_master_thumb'] = $this->model_tool_image->resize($favicon_master, 100, 100);
        } else {
            $data['favicon_master_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        }

        $data['placeholder_favicon'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        /* ── Category menu type ── */
        $menu_type = (string)$this->config->get('dockercart_theme_menu_type');
        $data['dockercart_theme_menu_type'] = ($menu_type === 'vertical') ? 'vertical' : 'horizontal';

        /* ── Call for Price status (default: enabled) ── */
        $call_for_price_raw = $this->config->get('dockercart_theme_call_for_price_status');
        $data['dockercart_theme_call_for_price_status'] = ($call_for_price_raw === null) ? 1 : (int)$call_for_price_raw;

        /* ── Messenger FAB status (default: enabled) ── */
        $fab_raw = $this->config->get('dockercart_theme_messenger_fab_status');
        $data['dockercart_theme_messenger_fab_status'] = ($fab_raw === null) ? 0 : (int)$fab_raw;

        /* ── Custom CSS/JS ── */
        $data['dockercart_theme_custom_css'] = (string)$this->config->get('dockercart_theme_custom_css');
        $data['dockercart_theme_custom_js'] = (string)$this->config->get('dockercart_theme_custom_js');

        /* ── Social icons/images (dynamic, up to 10) ── */
        $social_items = [];
        for ($i = 1; $i <= 10; $i++) {
            $image = $this->config->get('dockercart_theme_social_' . $i . '_image');
            $link  = $this->config->get('dockercart_theme_social_' . $i . '_link');
            if (($image === null || (string)$image === '') && ($link === null || (string)$link === '')) {
                break;
            }
            $image_str = (string)$image;
            if ($image_str && is_file(DIR_IMAGE . $image_str)) {
                $thumb = $this->model_tool_image->resize($image_str, 40, 40);
            } else {
                $thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
            }
            $social_items[] = [
                'image' => $image_str,
                'link'  => (string)$link,
                'thumb' => $thumb,
            ];
        }
        $data['social_items'] = $social_items;

        /* ── Messenger icons/images (dynamic, up to 10) ── */
        $messenger_items = [];
        for ($i = 1; $i <= 10; $i++) {
            $image = $this->config->get('dockercart_theme_messenger_' . $i . '_image');
            $link  = $this->config->get('dockercart_theme_messenger_' . $i . '_link');
            $name  = $this->config->get('dockercart_theme_messenger_' . $i . '_name');
            if (($image === null || (string)$image === '') && ($link === null || (string)$link === '')) {
                break;
            }
            $image_str = (string)$image;
            if ($image_str && is_file(DIR_IMAGE . $image_str)) {
                $thumb = $this->model_tool_image->resize($image_str, 40, 40);
            } else {
                $thumb = $this->model_tool_image->resize('no_image.png', 40, 40);
            }
            $messenger_items[] = [
                'image' => $image_str,
                'link'  => (string)$link,
                'name'  => (string)$name,
                'thumb' => $thumb,
            ];
        }
        $data['messenger_items'] = $messenger_items;

        /* ── Payment icons/images (dynamic, up to 10) ── */
        $payment_items = [];
        for ($i = 1; $i <= 10; $i++) {
            $image = $this->config->get('dockercart_theme_payment_' . $i . '_image');
            $link  = $this->config->get('dockercart_theme_payment_' . $i . '_link');
            // Stop at blanked-out or never-written slots
            if (($image === null || (string)$image === '') && ($link === null || (string)$link === '')) {
                break;
            }
            $image_str = (string)$image;
            if ($image_str && is_file(DIR_IMAGE . $image_str)) {
                $thumb = $this->model_tool_image->resize($image_str, 120, 40);
            } else {
                $thumb = $this->model_tool_image->resize('no_image.png', 120, 40);
            }
            $payment_items[] = [
                'image' => $image_str,
                'link'  => (string)$link,
                'thumb' => $thumb,
            ];
        }
        $data['payment_items'] = $payment_items;

        /* ── Custom links (multilingual, JSON arrays) ── */
        if (isset($this->request->post['dockercart_theme_header_links'])) {
            $data['dockercart_theme_header_links'] = $this->normalizeLinks($this->request->post['dockercart_theme_header_links'], $data['languages']);
        } else {
            $data['dockercart_theme_header_links'] = $this->getLinksFromConfig('dockercart_theme_header_links', $data['languages']);
        }

        if (isset($this->request->post['dockercart_theme_footer_links'])) {
            $data['dockercart_theme_footer_links'] = $this->normalizeLinks($this->request->post['dockercart_theme_footer_links'], $data['languages']);
        } else {
            $data['dockercart_theme_footer_links'] = $this->getLinksFromConfig('dockercart_theme_footer_links', $data['languages']);
        }

        /* ── Theme features (multilingual + lucide icon) ── */
        if (isset($this->request->post['dockercart_theme_product_features'])) {
            $data['dockercart_theme_product_features'] = $this->normalizeThemeFeatures($this->request->post['dockercart_theme_product_features'], $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'product'));
        } else {
            $data['dockercart_theme_product_features'] = $this->getThemeFeaturesFromConfig('dockercart_theme_product_features', $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'product'));
        }

        if (isset($this->request->post['dockercart_theme_category_features'])) {
            $data['dockercart_theme_category_features'] = $this->normalizeThemeFeatures($this->request->post['dockercart_theme_category_features'], $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'category'));
        } else {
            $data['dockercart_theme_category_features'] = $this->getThemeFeaturesFromConfig('dockercart_theme_category_features', $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'category'));
        }

        if (isset($this->request->post['dockercart_theme_quickview_features'])) {
            $data['dockercart_theme_quickview_features'] = $this->normalizeThemeFeatures($this->request->post['dockercart_theme_quickview_features'], $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'quickview'));
        } else {
            $data['dockercart_theme_quickview_features'] = $this->getThemeFeaturesFromConfig('dockercart_theme_quickview_features', $data['languages'], $this->getDefaultThemeFeatures($data['languages'], 'quickview'));
        }

        /* ── Layout ── */
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_theme', $data));
    }

    /**
     * Build and persist the full settings array from POST data.
     * Social & payment items are submitted as arrays and saved using flat _N_ key format.
     */
    private function _saveSettings() {
        $p = $this->request->post;
        $languages = $this->model_localisation_language->getLanguages();

        $product_features = $this->normalizeThemeFeatures(
            isset($p['dockercart_theme_product_features']) ? $p['dockercart_theme_product_features'] : array(),
            $languages,
            $this->getDefaultThemeFeatures($languages, 'product')
        );

        $category_features = $this->normalizeThemeFeatures(
            isset($p['dockercart_theme_category_features']) ? $p['dockercart_theme_category_features'] : array(),
            $languages,
            $this->getDefaultThemeFeatures($languages, 'category')
        );

        $quickview_features = $this->normalizeThemeFeatures(
            isset($p['dockercart_theme_quickview_features']) ? $p['dockercart_theme_quickview_features'] : array(),
            $languages,
            $this->getDefaultThemeFeatures($languages, 'quickview')
        );

        $settings = [
            'dockercart_theme_status'              => (int)($p['dockercart_theme_status'] ?? 0),
            'dockercart_theme_logo_dark'           => trim((string)($p['dockercart_theme_logo_dark'] ?? '')),
            'dockercart_theme_logo_light'          => trim((string)($p['dockercart_theme_logo_light'] ?? '')),
            'dockercart_theme_favicon_master'      => trim((string)($p['dockercart_theme_favicon_master'] ?? '')),
            'dockercart_theme_menu_type'           => ($p['dockercart_theme_menu_type'] ?? '') === 'vertical' ? 'vertical' : 'horizontal',
            'dockercart_theme_call_for_price_status' => (int)($p['dockercart_theme_call_for_price_status'] ?? 1),
            'dockercart_theme_messenger_fab_status' => (int)($p['dockercart_theme_messenger_fab_status'] ?? 0),
            'dockercart_theme_product_features' => json_encode($product_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_category_features' => json_encode($category_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_quickview_features' => json_encode($quickview_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_custom_css' => trim((string)($p['dockercart_theme_custom_css'] ?? '')),
            'dockercart_theme_custom_js'  => trim((string)($p['dockercart_theme_custom_js'] ?? '')),
        ];

        // Blank out all 10 slots first (ensures removed rows are cleared)
        for ($n = 1; $n <= 10; $n++) {
            $settings['dockercart_theme_social_' . $n . '_image'] = '';
            $settings['dockercart_theme_social_' . $n . '_link']  = '';
            $settings['dockercart_theme_messenger_' . $n . '_image'] = '';
            $settings['dockercart_theme_messenger_' . $n . '_link']  = '';
            $settings['dockercart_theme_messenger_' . $n . '_name']  = '';
            $settings['dockercart_theme_payment_' . $n . '_image'] = '';
            $settings['dockercart_theme_payment_' . $n . '_link']  = '';
        }

        // Social items (array POST fields)
        $social_images = array_values((array)($p['dockercart_theme_social_image'] ?? []));
        $social_links  = array_values((array)($p['dockercart_theme_social_link']  ?? []));
        foreach ($social_images as $idx => $image) {
            $n = $idx + 1;
            if ($n > 10) break;
            $settings['dockercart_theme_social_' . $n . '_image'] = trim((string)$image);
            $settings['dockercart_theme_social_' . $n . '_link']  = trim((string)($social_links[$idx] ?? ''));
        }

        // Messenger items (array POST fields)
        $messenger_images = array_values((array)($p['dockercart_theme_messenger_image'] ?? []));
        $messenger_links  = array_values((array)($p['dockercart_theme_messenger_link']  ?? []));
        $messenger_names  = array_values((array)($p['dockercart_theme_messenger_name']  ?? []));
        foreach ($messenger_images as $idx => $image) {
            $n = $idx + 1;
            if ($n > 10) break;
            $settings['dockercart_theme_messenger_' . $n . '_image'] = trim((string)$image);
            $settings['dockercart_theme_messenger_' . $n . '_link']  = trim((string)($messenger_links[$idx] ?? ''));
            $settings['dockercart_theme_messenger_' . $n . '_name']  = trim((string)($messenger_names[$idx] ?? ''));
        }

        // Payment items (array POST fields)
        $payment_images = array_values((array)($p['dockercart_theme_payment_image'] ?? []));
        $payment_links  = array_values((array)($p['dockercart_theme_payment_link']  ?? []));
        foreach ($payment_images as $idx => $image) {
            $n = $idx + 1;
            if ($n > 10) break;
            $settings['dockercart_theme_payment_' . $n . '_image'] = trim((string)$image);
            $settings['dockercart_theme_payment_' . $n . '_link']  = trim((string)($payment_links[$idx] ?? ''));
        }

        // Custom header/footer links (multilingual JSON arrays)
        $header_links = $this->normalizeLinks(
            isset($p['dockercart_theme_header_links']) ? $p['dockercart_theme_header_links'] : array(),
            $languages
        );
        $footer_links = $this->normalizeLinks(
            isset($p['dockercart_theme_footer_links']) ? $p['dockercart_theme_footer_links'] : array(),
            $languages
        );

        $settings['dockercart_theme_header_links'] = json_encode($header_links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $settings['dockercart_theme_footer_links'] = json_encode($footer_links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->model_setting_setting->editSetting('dockercart_theme', $settings);
    }

    private function getThemeFeaturesFromConfig($setting_key, $languages, $defaults) {
        $raw_value = $this->config->get($setting_key);

        if (!is_string($raw_value) || $raw_value === '') {
            return $defaults;
        }

        $decoded = json_decode($raw_value, true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        return $this->normalizeThemeFeatures($decoded, $languages, $defaults);
    }

    private function normalizeThemeFeatures($features, $languages, $fallback = array()) {
        if (!is_array($features)) {
            return $fallback;
        }

        $normalized = array();
        $icon_options = $this->getLucideIconOptions();
        $allowed_icons = array_flip($icon_options);

        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $icon = isset($feature['icon']) ? trim((string)$feature['icon']) : 'truck';
            if (!isset($allowed_icons[$icon])) {
                $icon = 'truck';
            }

            $item = array(
                'icon' => $icon,
                'sort_order' => isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0,
                'title' => array(),
                'text' => array()
            );

            $has_content = false;

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];

                $title = '';
                if (isset($feature['title']) && is_array($feature['title']) && isset($feature['title'][$language_id])) {
                    $title = trim((string)$feature['title'][$language_id]);
                }

                $text = '';
                if (isset($feature['text']) && is_array($feature['text']) && isset($feature['text'][$language_id])) {
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
            return $fallback;
        }

        return array_values($normalized);
    }

    private function getLinksFromConfig($setting_key, $languages) {
        $raw_value = $this->config->get($setting_key);

        if (!is_string($raw_value) || $raw_value === '') {
            return [];
        }

        $decoded = json_decode($raw_value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeLinks($decoded, $languages);
    }

    private function normalizeLinks($links, $languages) {
        if (!is_array($links)) {
            return [];
        }

        $normalized = [];

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $item = [
                'title' => [],
                'url' => [],
                'sort_order' => isset($link['sort_order']) ? (int)$link['sort_order'] : 0,
            ];

            $has_content = false;

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];

                $title = '';
                if (isset($link['title']) && is_array($link['title']) && isset($link['title'][$language_id])) {
                    $title = trim((string)$link['title'][$language_id]);
                }

                $url = '';
                if (isset($link['url']) && is_array($link['url']) && isset($link['url'][$language_id])) {
                    $url = trim((string)$link['url'][$language_id]);
                }

                if ($title !== '' || $url !== '') {
                    $has_content = true;
                }

                $item['title'][$language_id] = $title;
                $item['url'][$language_id] = $url;
            }

            if ($has_content) {
                $normalized[] = $item;
            }
        }

        usort($normalized, function ($a, $b) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        });

        return $normalized;
    }

    private function getDefaultThemeFeatures($languages, $group) {
        $map = array(
            'product' => array(
                array('icon' => 'truck', 'title_key' => 'text_default_product_feature_1_title', 'text_key' => 'text_default_product_feature_1_text'),
                array('icon' => 'shield-check', 'title_key' => 'text_default_product_feature_2_title', 'text_key' => 'text_default_product_feature_2_text'),
                array('icon' => 'refresh-ccw', 'title_key' => 'text_default_product_feature_3_title', 'text_key' => 'text_default_product_feature_3_text')
            ),
            'category' => array(
                array('icon' => 'layers-3', 'title_key' => 'text_default_category_feature_1_title', 'text_key' => 'text_default_category_feature_1_text'),
                array('icon' => 'badge-check', 'title_key' => 'text_default_category_feature_2_title', 'text_key' => 'text_default_category_feature_2_text'),
                array('icon' => 'headset', 'title_key' => 'text_default_category_feature_3_title', 'text_key' => 'text_default_category_feature_3_text')
            ),
            'quickview' => array(
                array('icon' => 'truck', 'title_key' => 'text_default_quickview_feature_1_title', 'text_key' => 'text_default_quickview_feature_1_text'),
                array('icon' => 'shield-check', 'title_key' => 'text_default_quickview_feature_2_title', 'text_key' => 'text_default_quickview_feature_2_text'),
                array('icon' => 'refresh-ccw', 'title_key' => 'text_default_quickview_feature_3_title', 'text_key' => 'text_default_quickview_feature_3_text')
            )
        );

        $defaults = array();
        $group_defaults = isset($map[$group]) ? $map[$group] : array();

        foreach ($group_defaults as $index => $default) {
            $item = array(
                'icon' => $default['icon'],
                'sort_order' => $index,
                'title' => array(),
                'text' => array()
            );

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $item['title'][$language_id] = $this->language->get($default['title_key']);
                $item['text'][$language_id] = $this->language->get($default['text_key']);
            }

            $defaults[] = $item;
        }

        return $defaults;
    }

    private function getLucideIconOptions() {
        return array(
            // E-commerce & Shopping
            'shopping-cart', 'shopping-bag', 'shopping-basket', 'bag', 'barcode',
            'tag', 'ticket', 'percent', 'badge-percent', 'badge-dollar-sign',
            'badge-check', 'badge-info', 'badge-x', 'dollar-sign', 'euro',
            'pound-sterling', 'yen', 'wallet', 'credit-card', 'receipt',
            'receipt-text', 'scale', 'package', 'package-check', 'package-x',
            'gift', 'truck', 'warehouse', 'boxes',

            // Shipping & Transport
            'car', 'car-front', 'bus', 'bus-front', 'train', 'train-front',
            'plane', 'plane-landing', 'plane-takeoff', 'ship', 'bike',
            'fuel', 'map-pin', 'map', 'map-pinned', 'compass', 'navigation',
            'navigation-2', 'route', 'signpost', 'locate', 'locate-fixed',
            'waypoints',

            // Communication & Support
            'headset', 'phone', 'phone-call', 'phone-forwarded',
            'phone-incoming', 'phone-missed', 'phone-off', 'phone-outgoing',
            'mail', 'mail-open', 'mail-check', 'mail-x', 'mail-search',
            'mail-question', 'message-square', 'message-circle',
            'message-square-text', 'message-circle-text', 'chat',
            'chat-text', 'send', 'reply', 'reply-all', 'forward',
            'at-sign', 'contact', 'contact-round',

            // Media & Entertainment
            'camera', 'camera-off', 'video', 'video-off', 'image',
            'image-plus', 'image-minus', 'film', 'clapperboard',
            'music', 'music-3', 'mic', 'mic-2', 'mic-off',
            'radio', 'headphones', 'speaker', 'volume-1', 'volume-2',
            'volume-x', 'monitor-speaker', 'tv', 'monitor', 'tablet',
            'smartphone', 'gamepad-2', 'puzzle',

            // Technology & Devices
            'laptop', 'laptop-2', 'computer', 'hard-drive', 'database',
            'cpu', 'memory-stick', 'printer', 'scanner', 'keyboard',
            'mouse', 'monitor-check', 'monitor-dot', 'monitor-pause',
            'monitor-stop', 'monitor-x', 'watch', 'smartwatch',
            'battery-charging', 'battery-full', 'battery-low',
            'battery-medium', 'battery-warning', 'plug', 'plug-zap',
            'power', 'power-off', 'usb',

            // Files & Documents
            'file-text', 'file', 'file-plus', 'file-minus', 'file-search',
            'file-check', 'file-x', 'file-image', 'file-video',
            'file-audio', 'file-archive', 'file-code', 'file-spreadsheet',
            'file-type', 'folder', 'folder-plus', 'folder-minus',
            'folder-open', 'folder-search', 'folder-check', 'folder-x',
            'folder-root', 'folder-tree', 'clipboard', 'clipboard-list',
            'clipboard-check', 'clipboard-x', 'copy', 'copy-check',
            'scissors', 'pen', 'pen-tool', 'pencil', 'pencil-line',
            'pencil-ruler', 'eraser', 'highlighter', 'type', 'sticky-note',
            'book-open', 'book', 'bookmark', 'bookmark-check', 'bookmark-x',
            'notebook', 'notebook-pen', 'notebook-text', 'notepad-text',

            // Arrows & Direction
            'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right',
            'arrow-up-down', 'arrow-left-right',
            'arrow-up-circle', 'arrow-down-circle',
            'arrow-left-circle', 'arrow-right-circle',
            'chevron-up', 'chevron-down', 'chevron-left', 'chevron-right',
            'chevrons-up-down', 'chevrons-left-right',
            'chevron-up-circle', 'chevron-down-circle',
            'move-up', 'move-down', 'move-left', 'move-right',
            'up-down', 'corner-up-left', 'corner-up-right',
            'corner-left-down', 'corner-left-up',
            'corner-right-down', 'corner-right-up',
            'maximize', 'minimize', 'maximize-2', 'minimize-2',
            'fullscreen', 'expand', 'shrink', 'zoom-in', 'zoom-out',

            // UI & Navigation
            'menu', 'menu-square', 'home', 'search', 'search-x',
            'search-check', 'x', 'x-circle', 'check', 'check-circle',
            'check-circle-2', 'check-square', 'plus', 'plus-circle',
            'plus-square', 'minus', 'minus-circle', 'minus-square',
            'trash', 'trash-2', 'edit', 'settings', 'cog',
            'sliders-horizontal', 'sliders-vertical', 'toggle-left',
            'toggle-right', 'filter', 'list', 'list-checks',
            'list-ordered', 'grid-3x3', 'columns', 'rows', 'layout-grid',
            'layout-list', 'layout-dashboard', 'panel-left',
            'panel-right', 'panel-top', 'panel-bottom',
            'sidebar', 'sidebar-close', 'sidebar-open',

            // Social & Users
            'user', 'users', 'user-plus', 'user-minus', 'user-check',
            'user-x', 'user-cog', 'user-round', 'users-round',
            'user-round-plus', 'user-round-minus', 'user-round-check',
            'user-round-x', 'user-round-cog', 'profile', 'fingerprint',
            'shield', 'shield-check', 'shield-off', 'shield-alert',
            'shield-ban', 'shield-plus', 'lock', 'unlock',
            'key', 'key-round', 'eye', 'eye-off', 'scan',

            // Weather & Nature
            'sun', 'sunrise', 'sunset', 'moon', 'cloud',
            'cloud-sun', 'cloud-moon', 'cloud-lightning', 'cloud-rain',
            'cloud-snow', 'cloud-drizzle', 'cloud-fog', 'cloud-hail',
            'cloudy', 'haze', 'snowflake', 'wind', 'umbrella',
            'thermometer', 'droplets', 'leaf', 'tree-deciduous',
            'tree-pine', 'flower-2', 'sprout', 'seedling',

            // Shapes & Symbols
            'circle', 'circle-dot', 'circle-ellipsis', 'square',
            'square-dot', 'triangle', 'triangle-right', 'diamond',
            'hexagon', 'octagon', 'pentagon', 'infinity',
            'heart', 'star', 'sparkles', 'flame', 'fire',
            'crown', 'award', 'trophy', 'medal', 'ribbon',
            'gem', 'dice-5', 'dices', 'target', 'crosshair',

            // Time & Status
            'clock', 'clock-1', 'clock-2', 'clock-3', 'clock-4',
            'clock-5', 'clock-6', 'clock-7', 'clock-8', 'clock-9',
            'clock-10', 'clock-11', 'clock-12', 'alarm-clock',
            'alarm-clock-check', 'alarm-clock-off', 'timer',
            'timer-off', 'hourglass', 'calendar', 'calendar-check',
            'calendar-plus', 'calendar-minus', 'calendar-x',
            'calendar-days', 'calendar-range', 'calendar-arrow-up',
            'calendar-arrow-down', 'calendar-clock',
            'activity', 'pulse', 'waves', 'signal', 'signal-high',
            'signal-low', 'signal-medium', 'signal-zero', 'wifi',

            // Alerts & Notifications
            'bell', 'bell-plus', 'bell-minus', 'bell-off',
            'bell-ring', 'bell-dot', 'alert-circle', 'alert-triangle',
            'alert-octagon', 'info', 'help-circle', 'question-mark',
            'message-circle-warning', 'message-square-warning',

            // Misc
            'anchor', 'atom', 'axe', 'bean', 'beer', 'bomb',
            'bone', 'book-open-text', 'brick-wall', 'brush',
            'bug', 'building', 'building-2', 'cable-car',
            'cake', 'calculator', 'candy', 'candy-cane',
            'carrot', 'cat', 'cherry', 'chef-hat', 'church',
            'cigarette-off', 'citrus', 'clover', 'cocktail',
            'coffee', 'coins', 'combine', 'cone', 'concierge-bell',
            'cookie', 'cooking-pot', 'cookie', 'copyleft', 'copyright',
            'crab', 'crop', 'cross', 'curly-braces', 'disc-3',
            'donut', 'door-open', 'drama', 'drill', 'drop',
            'droplet', 'ear', 'ear-off', 'egg', 'egg-fried',
            'egg-off', 'electric-car', 'electric-vehicle',
            'factory', 'fan', 'fast-forward', 'feather', 'fence',
            'ferris-wheel', 'figma', 'flag', 'flag-off', 'flashlight',
            'flask-conical', 'flask-round', 'flip-horizontal',
            'flip-vertical', 'flower', 'flying-saucer', 'focus',
            'fold-horizontal', 'fold-vertical', 'food', 'footprints',
            'forklift', 'form-input', 'frame', 'framer', 'frown',
            'funnel', 'gallery-horizontal', 'gallery-vertical',
            'gamepad', 'gantt-chart', 'gauge', 'ghost', 'gir',
            'glass-water', 'glasses', 'grab', 'grape', 'grip',
            'grip-horizontal', 'grip-vertical', 'group',
            'ham', 'hammer', 'hand', 'hand-coins', 'hand-heart',
            'hand-helping', 'hand-metal', 'hand-platter',
            'handshake', 'hard-hat', 'hash', 'hat',
            'heading-1', 'heading-2', 'heading-3', 'heading-4',
            'heading-5', 'heading-6', 'headphones', 'hearing',
            'heater', 'helping-hand', 'hexagon', 'hiking',
            'history', 'hop', 'hopping', 'hospital', 'hotel',
            'ice-cream', 'ice-cream-bowl', 'ice-cream-cone',
            'igloo', 'inbox', 'indent', 'indian-rupee',
            'inspection-panel', 'instagram', 'italic', 'japan',
            'joystick', 'kanban', 'kanban-square', 'kanban-square-dashed',
            'kip', 'knife', 'lamp', 'lamp-ceiling', 'lamp-desk',
            'lamp-floor', 'lamp-wall-down', 'lamp-wall-up',
            'land-plot', 'languages', 'lasso', 'lasso-select',
            'laugh', 'layers', 'layers-2', 'layers-3',
            'layout-panel-left', 'layout-panel-top',
            'lectern', 'library', 'life-buoy', 'ligature',
            'lightbulb', 'lightbulb-off', 'link', 'link-2',
            'list-collapse', 'list-end', 'list-filter',
            'list-music', 'list-ordered', 'list-plus',
            'list-restart', 'list-start', 'list-tree',
            'list-video', 'list-x', 'loader', 'loader-2',
            'loader-pinwheel', 'locate', 'log-in', 'log-out',
            'luggage', 'magnet', 'mailbox', 'mars', 'mars-stroke',
            'martini', 'masonry', 'massage', 'math-hand',
            'maximize-2', 'meat', 'megaphone', 'megaphone-off',
            'memory-stick', 'menu', 'menu-square', 'merge',
            'message-square', 'message-square-diff',
            'message-square-dot', 'message-square-lock',
            'message-square-plus', 'message-square-quote',
            'message-square-share', 'message-square-text',
            'message-square-warning', 'message-square-x',
            'messages-square', 'metronome', 'microwave',
            'milk', 'milk-off', 'minimize-2', 'minus',
            'monitor', 'monitor-check', 'monitor-cog',
            'monitor-dot', 'monitor-down', 'monitor-off',
            'monitor-pause', 'monitor-play', 'monitor-smartphone',
            'monitor-speaker', 'monitor-stop', 'monitor-up',
            'monitor-x', 'moon-star', 'more-horizontal',
            'more-vertical', 'mountain', 'mountain-snow',
            'mouse-pointer', 'mouse-pointer-2', 'mouse-pointer-bolt',
            'mouse-pointer-click', 'move', 'move-3d',
            'move-diagonal', 'move-diagonal-2', 'move-horizontal',
            'move-vertical', 'moves', 'museum', 'music-2',
            'music-4', 'navigation-2-off', 'navigation-off',
            'necktie', 'newspaper', 'nfc', 'non-binary',
            'notebook', 'notebook-pen', 'notebook-text',
            'notepad-text', 'nut', 'nut-off', 'octagon-alert',
            'octagon-minus', 'octagon-pause', 'octagon-x',
            'omega', 'option', 'orbit', 'origami',
            'outdent', 'oven', 'package-minus', 'package-plus',
            'package-search', 'paintbrush', 'paintbrush-2',
            'paintbrush-vertical', 'palette', 'palmtree',
            'panel-bottom', 'panel-bottom-close',
            'panel-bottom-dashed', 'panel-bottom-open',
            'panel-left', 'panel-left-close', 'panel-left-dashed',
            'panel-left-open', 'panel-right', 'panel-right-close',
            'panel-right-dashed', 'panel-right-open',
            'panel-top', 'panel-top-close', 'panel-top-dashed',
            'panel-top-open', 'panels-left-bottom',
            'panels-right-bottom', 'panels-top-left',
            'panels-top-right', 'paperclip', 'parentheses',
            'parking-meter', 'party-popper', 'pause',
            'paw-print', 'paypal', 'peace', 'pen-line',
            'pen-off', 'pencil-ruler', 'penguin', 'pentagon',
            'percent-diamond', 'person-standing', 'philippine-peso',
            'phone-off', 'pi', 'piano', 'pickaxe', 'picture-in-picture',
            'picture-in-picture-2', 'piggy-bank', 'pilcrow',
            'pilcrow-left', 'pilcrow-right', 'pill', 'pill-bottle',
            'pin', 'pin-off', 'pipette', 'pizza', 'plane-landing',
            'plane-takeoff', 'play', 'plug-2', 'plug-zap-2',
            'plus', 'pocket', 'pocket-knife', 'podcast',
            'pointer', 'pointer-off', 'popcorn', 'popsicle',
            'pound-sterling', 'power-circle', 'power-square',
            'presentation', 'printer-check', 'printer-x',
            'projector', 'proportions', 'puzzle', 'qr-code',
            'quote', 'radar', 'radio-receiver', 'radish',
            'rainbow', 'rat', 'rattle', 'recycle',
            'redo', 'redo-2', 'refresh-ccw', 'refresh-cw',
            'refrigerator', 'regex', 'remove-formatting',
            'repeat', 'repeat-1', 'repeat-2',
            'replace', 'replace-all', 'reply-all', 'rewind',
            'ribbon', 'rocket', 'rocking-chair', 'roller-coaster',
            'rotate-3d', 'rotate-ccw', 'rotate-ccw-square',
            'rotate-cw', 'rotate-cw-square', 'route-off',
            'router', 'rows', 'rows-2', 'rows-3', 'rows-4',
            'rss', 'ruler', 'ruler', 'russian-ruble', 'sailboat',
            'salad', 'sandwich', 'satellite', 'satellite-dish',
            'saudi-riyal', 'save-all', 'save-off', 'scale-3d',
            'scaling', 'scan-barcode', 'scan-eye', 'scan-face',
            'scan-line', 'scan-qr-code', 'scan-search', 'scan-text',
            'scatter-chart', 'school', 'school-2', 'scissors',
            'screencast', 'screen-share', 'screen-share-off',
            'scroll', 'scroll-text', 'search-code', 'search-slash',
            'section', 'send-horizonal', 'send-to-back',
            'separator-horizonal', 'separator-vertical',
            'server', 'server-cog', 'server-crash', 'server-off',
            'settings-2', 'shapes', 'share', 'share-2',
            'sheet', 'shelves', 'shirt', 'shopping-bag',
            'shopping-cart', 'shower-head', 'shrink', 'shrub',
            'shuffle', 'sidebar', 'sidebar-close', 'sidebar-open',
            'sigma', 'signal-high', 'signal-low', 'signal-medium',
            'signal-zero', 'signpost-big', 'siren', 'skateboard',
            'skip-back', 'skip-forward', 'skull', 'slash',
            'slash', 'slice', 'sliders-horizontal',
            'sliders-vertical', 'smartphone-charging', 'smartphone-nfc',
            'smile-plus', 'snail', 'snowflake', 'sofa', 'solar-panel',
            'soup', 'space', 'spade', 'sparkle', 'sparkles',
            'speaker', 'speech', 'spell-check', 'spell-check-2',
            'spline', 'split', 'split-square-horizontal',
            'split-square-vertical', 'spray-can', 'sprout',
            'square-activity', 'square-arrow-left', 'square-arrow-out-down-left',
            'square-arrow-out-down-right', 'square-arrow-out-up-left',
            'square-arrow-out-up-right', 'square-arrow-right',
            'square-arrow-up', 'square-asterisk', 'square-bottom-dashed-scroll',
            'square-check', 'square-check-big', 'square-code',
            'square-dashed', 'square-dashed-bottom', 'square-dashed-mouse-pointer',
            'square-divide', 'square-dot', 'square-equal',
            'square-function', 'square-gantt-chart', 'square-kanban',
            'square-library', 'square-m', 'square-menu',
            'square-minus', 'square-mouse-pointer', 'square-parking',
            'square-parking-off', 'square-pen', 'square-percent',
            'square-pi', 'square-pilcrow', 'square-play',
            'square-plus', 'square-power', 'square-radical',
            'square-scroll', 'square-sigma', 'square-slash',
            'square-split-horizontal', 'square-split-vertical',
            'square-user', 'square-user-round', 'square-x',
            'squircle', 'squirrel', 'stamp', 'star-half',
            'star-off', 'step-back', 'step-forward',
            'stethoscope', 'sticker', 'sticky-note',
            'store', 'stretch-horizontal', 'stretch-vertical',
            'strikethrough', 'subscript', 'sugar',
            'superscript', 'swatch-book', 'swiss-franc',
            'switch-camera', 'sword', 'swords', 'syringe',
            'table', 'table-2', 'table-cells-merge', 'table-cells-split',
            'table-columns-split', 'table-of-contents',
            'table-properties', 'table-rows-split', 'tablet-smartphone',
            'tablets', 'tag', 'tags', 'tally-1', 'tally-2',
            'tally-3', 'tally-4', 'tally-5', 'tangent',
            'target', 'telescope', 'tent', 'terminal',
            'terminal-square', 'test-tube-2', 'test-tubes',
            'text', 'text-cursor', 'text-cursor-input',
            'text-quote', 'text-search', 'text-select',
            'theater', 'thermometer-snowflake', 'thermometer-sun',
            'thumbs-down', 'thumbs-up', 'ticket',
            'ticket-check', 'ticket-minus', 'ticket-percent',
            'ticket-plus', 'ticket-slash', 'ticket-x',
            'tickets', 'tickets-plane', 'timer-off',
            'timer-reset', 'toggle-left', 'toggle-right',
            'tornado', 'torus', 'touchpad', 'touchpad-off',
            'tower-control', 'toy-brick', 'tractor',
            'traffic-cone', 'train-front-tunnel', 'train-track',
            'tram-front', 'transgender', 'trash',
            'tree-deciduous', 'tree-pine', 'trees',
            'trello', 'triangle-alert', 'triangle-right',
            'triange', 'trophy', 'truck', 'trash-2',
            'turkey', 'turtle', 'tv-minimal', 'tv-minimal-play',
            'twitch', 'twitter', 'type', 'umbrella-off',
            'underline', 'undo', 'undo-2', 'unfold-horizontal',
            'unfold-vertical', 'ungroup', 'unlink', 'unlink-2',
            'unplug', 'upload', 'usb', 'user-check',
            'user-cog', 'user-minus', 'user-plus', 'user-round-check',
            'user-round-cog', 'user-round-minus', 'user-round-plus',
            'user-round-search', 'user-round-x', 'user-search',
            'user-x', 'utensils', 'utensils-crossed',
            'utility-pole', 'vacuum-cleaner', 'variable',
            'vault', 'vegan', 'venetian-mask', 'venus',
            'venus-and-mars', 'venus-mars', 'verified',
            'vibrate', 'vibrate-off', 'video', 'video-off',
            'videotape', 'view', 'voicemail', 'volleyball',
            'volume', 'volume-1', 'volume-2', 'volume-off',
            'volume-x', 'vote', 'wallet-cards', 'wallet-minimal',
            'wallpaper', 'wand', 'wand-sparkles', 'warehouse',
            'washing-machine', 'watch', 'waves', 'waypoints',
            'webcam', 'webhook', 'weight', 'wheat',
            'wheat-off', 'whole-word', 'wifi', 'wifi-off',
            'wind', 'wine', 'wine-off', 'workflow',
            'worm', 'wrap-text', 'wrench', 'x',
            'youtube', 'zap', 'zap-off', 'zoom-in',
            'zoom-out'
        );
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_theme')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    /* Called by marketplace installer */
    public function install() {
        $this->load->model('setting/setting');
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();
        $product_defaults = $this->getDefaultThemeFeatures($languages, 'product');
        $category_defaults = $this->getDefaultThemeFeatures($languages, 'category');
        $quickview_defaults = $this->getDefaultThemeFeatures($languages, 'quickview');
        $this->model_setting_setting->editSetting('dockercart_theme', [
            'dockercart_theme_status'    => 1,
            'dockercart_theme_logo_dark' => '',
            'dockercart_theme_logo_light' => '',
            'dockercart_theme_favicon_master' => '',
            'dockercart_theme_menu_type' => 'horizontal',
            'dockercart_theme_call_for_price_status' => 1,
            'dockercart_theme_product_features' => json_encode($product_defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_category_features' => json_encode($category_defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_quickview_features' => json_encode($quickview_defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dockercart_theme_social_1_image' => '',
            'dockercart_theme_social_1_link'  => '',
            'dockercart_theme_social_2_image' => '',
            'dockercart_theme_social_2_link'  => '',
            'dockercart_theme_social_3_image' => '',
            'dockercart_theme_social_3_link'  => '',
            'dockercart_theme_social_4_image' => '',
            'dockercart_theme_social_4_link'  => '',
            'dockercart_theme_payment_1_image' => '',
            'dockercart_theme_payment_1_link' => '',
            'dockercart_theme_payment_2_image' => '',
            'dockercart_theme_payment_2_link' => '',
            'dockercart_theme_payment_3_image' => '',
            'dockercart_theme_payment_3_link' => '',
            'dockercart_theme_payment_4_image' => '',
            'dockercart_theme_payment_4_link' => '',
            'dockercart_theme_header_links' => '[]',
            'dockercart_theme_footer_links' => '[]',
        ]);
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('dockercart_theme');
    }
}
