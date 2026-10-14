<?php
class ControllerExtensionModuleDockercartShopFeatures extends Controller {
    public function index($setting) {
        if (!isset($setting['status']) || !(int)$setting['status']) {
            return '';
        }

        $this->load->language('extension/module/dockercart_shop_features');

        $language_id = (int)$this->config->get('config_language_id');
        $features = isset($setting['features']) && is_array($setting['features']) ? $setting['features'] : array();

        $data['features'] = array();

        $section_title = '';
        if (isset($setting['section_title'])) {
            if (is_array($setting['section_title'])) {
                if (isset($setting['section_title'][$language_id]) && $setting['section_title'][$language_id] !== '') {
                    $section_title = (string)$setting['section_title'][$language_id];
                } else {
                    foreach ($setting['section_title'] as $candidate) {
                        if ($candidate !== '') {
                            $section_title = (string)$candidate;
                            break;
                        }
                    }
                }
            } elseif (is_string($setting['section_title']) && $setting['section_title'] !== '') {
                $section_title = $setting['section_title'];
            }
        }
        $data['section_title'] = $section_title;

        $section_subtitle = '';
        if (isset($setting['section_subtitle'])) {
            if (is_array($setting['section_subtitle'])) {
                if (isset($setting['section_subtitle'][$language_id]) && $setting['section_subtitle'][$language_id] !== '') {
                    $section_subtitle = (string)$setting['section_subtitle'][$language_id];
                } else {
                    foreach ($setting['section_subtitle'] as $candidate) {
                        if ($candidate !== '') {
                            $section_subtitle = (string)$candidate;
                            break;
                        }
                    }
                }
            } elseif (is_string($setting['section_subtitle']) && $setting['section_subtitle'] !== '') {
                $section_subtitle = $setting['section_subtitle'];
            }
        }
        $data['section_subtitle'] = $section_subtitle;

        foreach ($features as $feature) {
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

            $data['features'][] = array(
                'icon' => isset($feature['icon']) && $feature['icon'] !== '' ? (string)$feature['icon'] : 'truck',
                'title' => $title,
                'text' => $text,
                'sort_order' => isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0
            );
        }

        usort($data['features'], function($a, $b) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        });

        if (!$data['features']) {
            $data['features'][] = array('icon' => 'truck', 'title' => $this->language->get('text_default_feature_1_title'), 'text' => $this->language->get('text_default_feature_1_text'));
            $data['features'][] = array('icon' => 'shield-check', 'title' => $this->language->get('text_default_feature_2_title'), 'text' => $this->language->get('text_default_feature_2_text'));
            $data['features'][] = array('icon' => 'refresh-ccw', 'title' => $this->language->get('text_default_feature_3_title'), 'text' => $this->language->get('text_default_feature_3_text'));
            $data['features'][] = array('icon' => 'headset', 'title' => $this->language->get('text_default_feature_4_title'), 'text' => $this->language->get('text_default_feature_4_text'));
        }

        return $this->load->view('extension/module/dockercart_shop_features', $data);
    }
}
