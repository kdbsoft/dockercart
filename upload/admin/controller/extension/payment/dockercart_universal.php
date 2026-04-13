<?php
/**
 * DockerCart Universal Payment Module
 * Flexible payment configuration with multiple methods,
 * geo zones, order total conditions and multilingual descriptions.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ControllerExtensionPaymentDockercartUniversal extends Controller {
    private $error = [];

    /**
     * Main admin page - displays list of payment methods
     */
    public function index() {
        $this->load->language('extension/payment/dockercart_universal');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/payment/dockercart_universal');
        $this->load->model('setting/setting');

        // Handle POST for general settings
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_dockercart_universal', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true)
        ];

        // Errors
        $data['error_warning'] = $this->error['warning'] ?? '';

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // URLs
        $data['action'] = $this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['add'] = $this->url->link('extension/payment/dockercart_universal/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];

        // Get payment methods
        $methods = $this->model_extension_payment_dockercart_universal->getMethods();
        foreach ($methods as &$method) {
            $method['edit'] = $this->url->link('extension/payment/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . '&method_id=' . $method['method_id'], true);
        }
        unset($method);
        $data['payment_methods'] = $methods;

        // Languages for display
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // Settings
        if (isset($this->request->post['payment_dockercart_universal_status'])) {
            $data['payment_dockercart_universal_status'] = $this->request->post['payment_dockercart_universal_status'];
        } else {
            $data['payment_dockercart_universal_status'] = $this->config->get('payment_dockercart_universal_status');
        }

        if (isset($this->request->post['payment_dockercart_universal_order_status_id'])) {
            $data['payment_dockercart_universal_order_status_id'] = $this->request->post['payment_dockercart_universal_order_status_id'];
        } else {
            $data['payment_dockercart_universal_order_status_id'] = $this->config->get('payment_dockercart_universal_order_status_id');

            if (!$data['payment_dockercart_universal_order_status_id']) {
                $data['payment_dockercart_universal_order_status_id'] = $this->config->get('config_order_status_id');
            }
        }

        if (isset($this->request->post['payment_dockercart_universal_sort_order'])) {
            $data['payment_dockercart_universal_sort_order'] = $this->request->post['payment_dockercart_universal_sort_order'];
        } else {
            $data['payment_dockercart_universal_sort_order'] = $this->config->get('payment_dockercart_universal_sort_order');
        }

        // Order statuses
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Geo zones for display
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // Layout
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/dockercart_universal', $data));
    }

    /**
     * Add or edit payment method form
     */
    public function form() {
        $this->load->language('extension/payment/dockercart_universal');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/payment/dockercart_universal');
        $this->load->model('setting/extension');
        $this->load->model('localisation/language');
        $this->load->model('localisation/geo_zone');

        // Determine if editing
        $method_id = isset($this->request->get['method_id']) ? (int)$this->request->get['method_id'] : 0;

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
            if ($method_id) {
                $this->model_extension_payment_dockercart_universal->editMethod($method_id, $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success_edit');
            } else {
                $this->model_extension_payment_dockercart_universal->addMethod($this->request->post);
                $this->session->data['success'] = $this->language->get('text_success_add');
            }

            $this->response->redirect($this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $method_id ? $this->language->get('text_edit_method') : $this->language->get('text_add_method'),
            'href' => $this->url->link('extension/payment/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . ($method_id ? '&method_id=' . $method_id : ''), true)
        ];

        // Errors
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['error_name'] = $this->error['name'] ?? [];

        // URLs
        $data['action'] = $this->url->link('extension/payment/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'] . ($method_id ? '&method_id=' . $method_id : ''), true);
        $data['cancel'] = $this->url->link('extension/payment/dockercart_universal', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];

        // Languages
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // Geo zones
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // Available shipping methods for dependency condition
        $data['available_shipping_methods'] = $this->getAvailableShippingMethodOptions();

        // Load existing method data or defaults
        if ($method_id && ($method_info = $this->model_extension_payment_dockercart_universal->getMethod($method_id))) {
            $data['method_id'] = $method_id;
            $data['method_description'] = $this->model_extension_payment_dockercart_universal->getMethodDescriptions($method_id);

            $data['geo_zone_id'] = $method_info['geo_zone_id'];
            $data['min_total'] = $method_info['min_total'];
            $data['max_total'] = $method_info['max_total'];
            $data['shipping_methods'] = !empty($method_info['shipping_methods']) ? (json_decode($method_info['shipping_methods'], true) ?: []) : [];
            $data['status'] = $method_info['status'];
            $data['sort_order'] = $method_info['sort_order'];
        } else {
            $data['method_id'] = 0;
            $data['method_description'] = [];

            $data['geo_zone_id'] = 0;
            $data['min_total'] = '';
            $data['max_total'] = '';
            $data['shipping_methods'] = [];
            $data['status'] = 1;
            $data['sort_order'] = 0;
        }

        if (isset($this->request->post['shipping_methods']) && is_array($this->request->post['shipping_methods'])) {
            $data['shipping_methods'] = $this->request->post['shipping_methods'];
        }

        // Layout
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/dockercart_universal_form', $data));
    }

    /**
     * Add method - redirects to form
     */
    public function add() {
        $this->response->redirect($this->url->link('extension/payment/dockercart_universal/form', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Delete payment method via AJAX
     */
    public function delete() {
        $this->load->language('extension/payment/dockercart_universal');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/payment/dockercart_universal')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->post['method_id'])) {
                $this->load->model('extension/payment/dockercart_universal');
                $this->model_extension_payment_dockercart_universal->deleteMethod((int)$this->request->post['method_id']);
                $json['success'] = $this->language->get('text_success_delete');
            } else {
                $json['error'] = $this->language->get('error_method_id');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Install - creates database tables
     */
    public function install() {
        $this->load->model('extension/payment/dockercart_universal');
        $this->model_extension_payment_dockercart_universal->install();
    }

    /**
     * Uninstall - removes database tables
     */
    public function uninstall() {
        $this->load->model('extension/payment/dockercart_universal');
        $this->model_extension_payment_dockercart_universal->uninstall();
    }

    /**
     * Validate main settings
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/dockercart_universal')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Validate payment method form
     */
    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/payment/dockercart_universal')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Validate name for every language
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            if (empty($this->request->post['method_description'][$language['language_id']]['name'])) {
                $this->error['name'][$language['language_id']] = $this->language->get('error_name');
            }
        }

        return !$this->error;
    }

    /**
     * Build list of available shipping method codes for dependency selection.
     * Includes module-level code and known quote-level codes where available.
     */
    protected function getAvailableShippingMethodOptions(): array {
        $options = [];

        $extensions = $this->model_setting_extension->getInstalled('shipping');

        foreach ($extensions as $code) {
            if (!$this->config->get('shipping_' . $code . '_status')) {
                continue;
            }

            $this->load->language('extension/shipping/' . $code);

            $title = $this->language->get('heading_title');
            if (empty($title) || $title === 'heading_title') {
                $title = ucfirst(str_replace('_', ' ', $code));
            }

            // Module-level code means "all methods of this shipping extension"
            $options[] = [
                'code'  => $code,
                'title' => $title,
                'label' => sprintf('%s — %s', $title, $this->language->get('text_all_methods_of_module'))
            ];

            // Common single-quote code pattern in OpenCart (e.g. flat.flat, pickup.pickup)
            $options[] = [
                'code'  => $code . '.' . $code,
                'title' => $title,
                'label' => sprintf('%s — %s.%s', $title, $code, $code)
            ];

            // DockerCart Universal Shipping has multiple quote-level methods.
            if ($code === 'dockercart_universal') {
                $this->load->model('extension/shipping/dockercart_universal');
                $shipping_methods = $this->model_extension_shipping_dockercart_universal->getMethods();

                foreach ($shipping_methods as $shipping_method) {
                    $method_code = 'dockercart_universal.dockercart_universal_' . (int)$shipping_method['method_id'];
                    $method_name = !empty($shipping_method['name']) ? $shipping_method['name'] : $this->language->get('text_unnamed');

                    $options[] = [
                        'code'  => $method_code,
                        'title' => $title,
                        'label' => sprintf('%s — %s', $title, $method_name)
                    ];
                }
            }
        }

        // Remove accidental duplicates while keeping initial order
        $unique = [];
        $seen = [];

        foreach ($options as $option) {
            if (isset($seen[$option['code']])) {
                continue;
            }

            $seen[$option['code']] = true;
            $unique[] = $option;
        }

        return $unique;
    }
}
