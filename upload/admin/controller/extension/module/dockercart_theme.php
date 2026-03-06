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
 */
class ControllerExtensionModuleDockerCartTheme extends Controller {

    private $error = [];

    public function index() {
        $this->load->language('extension/module/dockercart_theme');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('tool/image');

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

        $settings = [
            'dockercart_theme_status'     => (int)($p['dockercart_theme_status'] ?? 0),
            'dockercart_theme_logo_dark'  => trim((string)($p['dockercart_theme_logo_dark'] ?? '')),
            'dockercart_theme_logo_light' => trim((string)($p['dockercart_theme_logo_light'] ?? '')),
            'dockercart_theme_favicon_master' => trim((string)($p['dockercart_theme_favicon_master'] ?? '')),
            'dockercart_theme_menu_type'  => ($p['dockercart_theme_menu_type'] ?? '') === 'vertical' ? 'vertical' : 'horizontal',
        ];

        // Blank out all 10 slots first (ensures removed rows are cleared)
        for ($n = 1; $n <= 10; $n++) {
            $settings['dockercart_theme_social_' . $n . '_image'] = '';
            $settings['dockercart_theme_social_' . $n . '_link']  = '';
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

        // Payment items (array POST fields)
        $payment_images = array_values((array)($p['dockercart_theme_payment_image'] ?? []));
        $payment_links  = array_values((array)($p['dockercart_theme_payment_link']  ?? []));
        foreach ($payment_images as $idx => $image) {
            $n = $idx + 1;
            if ($n > 10) break;
            $settings['dockercart_theme_payment_' . $n . '_image'] = trim((string)$image);
            $settings['dockercart_theme_payment_' . $n . '_link']  = trim((string)($payment_links[$idx] ?? ''));
        }

        $this->model_setting_setting->editSetting('dockercart_theme', $settings);
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
        $this->model_setting_setting->editSetting('dockercart_theme', [
            'dockercart_theme_status'    => 1,
            'dockercart_theme_logo_dark' => '',
            'dockercart_theme_logo_light' => '',
            'dockercart_theme_favicon_master' => '',
            'dockercart_theme_menu_type' => 'horizontal',
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
        ]);
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('dockercart_theme');
    }
}
