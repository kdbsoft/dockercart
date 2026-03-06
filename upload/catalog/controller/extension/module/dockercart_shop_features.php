<?php
class ControllerExtensionModuleDockercartShopFeatures extends Controller {
    public function index($setting) {
        if (!isset($setting['status']) || !(int)$setting['status']) {
            return '';
        }

        $this->load->language('extension/module/dockercart_shop_features');

        $language_id = (int)$this->config->get('config_language_id');
        $features = isset($setting['features']) && is_array($setting['features']) ? $setting['features'] : array();

        $palette = array(
            array('icon_bg' => 'bg-blue-100', 'icon_hover_bg' => 'group-hover:bg-blue-600', 'icon_text' => 'text-blue-600'),
            array('icon_bg' => 'bg-teal-100', 'icon_hover_bg' => 'group-hover:bg-teal-500', 'icon_text' => 'text-teal-600'),
            array('icon_bg' => 'bg-rose-100', 'icon_hover_bg' => 'group-hover:bg-rose-500', 'icon_text' => 'text-rose-500'),
            array('icon_bg' => 'bg-indigo-100', 'icon_hover_bg' => 'group-hover:bg-indigo-600', 'icon_text' => 'text-indigo-600')
        );

        $data['features'] = array();

        foreach ($features as $index => $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $title = '';
            if (isset($feature['title']) && is_array($feature['title'])) {
                if (isset($feature['title'][$language_id]) && $feature['title'][$language_id] !== '') {
                    $title = (string)$feature['title'][$language_id];
                } else {
                    foreach ($feature['title'] as $candidate) {
                        if ($candidate !== '') {
                            $title = (string)$candidate;
                            break;
                        }
                    }
                }
            }

            $text = '';
            if (isset($feature['text']) && is_array($feature['text'])) {
                if (isset($feature['text'][$language_id]) && $feature['text'][$language_id] !== '') {
                    $text = (string)$feature['text'][$language_id];
                } else {
                    foreach ($feature['text'] as $candidate) {
                        if ($candidate !== '') {
                            $text = (string)$candidate;
                            break;
                        }
                    }
                }
            }

            if ($title === '' && $text === '') {
                continue;
            }

            $color = $palette[$index % count($palette)];

            $data['features'][] = array(
                'icon' => isset($feature['icon']) && $feature['icon'] !== '' ? (string)$feature['icon'] : 'truck',
                'title' => $title,
                'text' => $text,
                'sort_order' => isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0,
                'icon_bg' => $color['icon_bg'],
                'icon_hover_bg' => $color['icon_hover_bg'],
                'icon_text' => $color['icon_text']
            );
        }

        usort($data['features'], function($a, $b) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        });

        if (!$data['features']) {
            $data['features'] = array(
                array('icon' => 'truck', 'title' => $this->language->get('text_default_feature_1_title'), 'text' => $this->language->get('text_default_feature_1_text'), 'icon_bg' => 'bg-blue-100', 'icon_hover_bg' => 'group-hover:bg-blue-600', 'icon_text' => 'text-blue-600'),
                array('icon' => 'shield-check', 'title' => $this->language->get('text_default_feature_2_title'), 'text' => $this->language->get('text_default_feature_2_text'), 'icon_bg' => 'bg-teal-100', 'icon_hover_bg' => 'group-hover:bg-teal-500', 'icon_text' => 'text-teal-600'),
                array('icon' => 'refresh-ccw', 'title' => $this->language->get('text_default_feature_3_title'), 'text' => $this->language->get('text_default_feature_3_text'), 'icon_bg' => 'bg-rose-100', 'icon_hover_bg' => 'group-hover:bg-rose-500', 'icon_text' => 'text-rose-500'),
                array('icon' => 'headset', 'title' => $this->language->get('text_default_feature_4_title'), 'text' => $this->language->get('text_default_feature_4_text'), 'icon_bg' => 'bg-indigo-100', 'icon_hover_bg' => 'group-hover:bg-indigo-600', 'icon_text' => 'text-indigo-600')
            );
        }

        return $this->load->view('extension/module/dockercart_shop_features', $data);
    }
}
