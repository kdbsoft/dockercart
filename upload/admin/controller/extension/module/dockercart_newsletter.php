<?php
class ControllerExtensionModuleDockercartNewsletter extends Controller {
    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/dockercart_newsletter');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('extension/module/dockercart_newsletter');
        $this->load->model('localisation/language');

        $selected_module_id = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : $this->getDefaultModuleId();

        if (!isset($this->request->get['module_id']) && $selected_module_id > 0) {
            $this->request->get['module_id'] = $selected_module_id;
        }

        $data['languages'] = $this->model_localisation_language->getLanguages();

        $multilingual_fields = array(
            'title',
            'subtitle',
            'placeholder',
            'button_text',
            'privacy_text',
            'success_text',
            'already_text'
        );

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $module_data = $this->request->post;

            if (isset($module_data['module_settings']) && is_array($module_data['module_settings'])) {
                foreach ($multilingual_fields as $field) {
                    $module_data[$field] = array();

                    foreach ($module_data['module_settings'] as $language_id => $language_settings) {
                        $module_data[$field][(int)$language_id] = isset($language_settings[$field])
                            ? trim((string)$language_settings[$field])
                            : '';
                    }
                }
            }

            unset($module_data['module_settings']);

            if ($selected_module_id <= 0 && isset($this->request->get['module_id'])) {
                $selected_module_id = (int)$this->request->get['module_id'];
            }

            if ($selected_module_id <= 0) {
                $this->model_setting_module->addModule('dockercart_newsletter', $module_data);
            } else {
                $this->model_setting_module->editModule($selected_module_id, $module_data);
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['name'])) {
            $data['error_name'] = $this->error['name'];
        } else {
            $data['error_name'] = '';
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

        if ($selected_module_id <= 0) {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'], true)
            );
        } else {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $selected_module_id, true)
            );
        }

        if ($selected_module_id <= 0) {
            $data['action'] = $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['action'] = $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $selected_module_id, true);
        }

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['import'] = $this->url->link('extension/module/dockercart_newsletter/import', 'user_token=' . $this->session->data['user_token'], true);
        $data['export'] = $this->url->link('extension/module/dockercart_newsletter/export', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];

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

        $default_module_settings = array(
            'title' => $this->language->get('text_default_title'),
            'subtitle' => $this->language->get('text_default_subtitle'),
            'placeholder' => $this->language->get('text_default_placeholder'),
            'button_text' => $this->language->get('text_default_button'),
            'privacy_text' => $this->language->get('text_default_privacy'),
            'success_text' => $this->language->get('text_default_success'),
            'already_text' => $this->language->get('text_default_already')
        );

        $data['module_settings'] = array();

        foreach ($data['languages'] as $language) {
            $language_id = (int)$language['language_id'];
            $data['module_settings'][$language_id] = array();

            foreach ($multilingual_fields as $field) {
                $value = '';

                if (isset($this->request->post['module_settings'][$language_id][$field])) {
                    $value = (string)$this->request->post['module_settings'][$language_id][$field];
                } elseif (!empty($module_info) && isset($module_info['module_settings'][$language_id][$field])) {
                    $value = (string)$module_info['module_settings'][$language_id][$field];
                } elseif (!empty($module_info) && isset($module_info[$field])) {
                    if (is_array($module_info[$field])) {
                        $value = isset($module_info[$field][$language_id]) ? (string)$module_info[$field][$language_id] : '';
                    } else {
                        $value = (string)$module_info[$field];
                    }
                }

                if ($value === '') {
                    $value = $default_module_settings[$field];
                }

                $data['module_settings'][$language_id][$field] = $value;
            }
        }

        $filter_email = isset($this->request->get['filter_email']) ? trim((string)$this->request->get['filter_email']) : '';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        $limit = 20;

        $filter_data = array(
            'filter_email' => $filter_email,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        );

        $subscriber_total = $this->model_extension_module_dockercart_newsletter->getTotalSubscribers($filter_data);
        $results = $this->model_extension_module_dockercart_newsletter->getSubscribers($filter_data);
        $stats = $this->model_extension_module_dockercart_newsletter->getStats();

        $data['subscribers'] = array();

        foreach ($results as $result) {
            $is_customer = $result['subscriber_type'] === 'customer';

            $delete_link = $this->url->link(
                'extension/module/dockercart_newsletter/deleteSubscriber',
                'user_token=' . $this->session->data['user_token'] . '&type=' . ($is_customer ? 'customer' : 'guest') .
                ($is_customer ? '&customer_id=' . (int)$result['customer_id'] : '&subscriber_id=' . (int)$result['subscriber_id']),
                true
            );

            $data['subscribers'][] = array(
                'subscriber_id' => (int)$result['subscriber_id'],
                'customer_id' => (int)$result['customer_id'],
                'email' => $result['email'],
                'source' => $result['source'],
                'subscriber_type' => $result['subscriber_type'],
                'status' => (int)$result['status'],
                'date_added' => $result['date_added'],
                'delete' => $delete_link
            );
        }

        $data['stats_total'] = $stats['total'];
        $data['stats_guest'] = $stats['guest_total'];
        $data['stats_customer'] = $stats['customer_total'];

        $pagination = new Pagination();
        $pagination->total = $subscriber_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . '&filter_email=' . urlencode($filter_email) . '&page={page}' . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($subscriber_total) ? (($page - 1) * $limit) + 1 : 0,
            ((($page - 1) * $limit) > ($subscriber_total - $limit)) ? $subscriber_total : ((($page - 1) * $limit) + $limit),
            $subscriber_total,
            ceil($subscriber_total / $limit)
        );

        $data['filter_email'] = $filter_email;
        $data['filter_action'] = $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true);

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->session->data['warning'])) {
            $data['warning'] = $this->session->data['warning'];
            unset($this->session->data['warning']);
        } else {
            $data['warning'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_newsletter', $data));
    }

    public function install() {
        $this->load->model('extension/module/dockercart_newsletter');
        $this->load->model('user/user_group');

        $this->model_extension_module_dockercart_newsletter->install();

        $group_id = (int)$this->user->getGroupId();
        $this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_newsletter');
        $this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_newsletter');

        // Register admin menu event to add Subscribers link under Marketing
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('dockercart_newsletter_admin_menu');
        $this->model_setting_event->addEvent(
            'dockercart_newsletter_admin_menu',
            'admin/view/common/column_left/before',
            'extension/module/dockercart_newsletter/eventAdminMenu',
            1,
            0
        );
    }

    public function uninstall() {
        $this->load->model('extension/module/dockercart_newsletter');
        // Remove admin menu event
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('dockercart_newsletter_admin_menu');

        $this->model_extension_module_dockercart_newsletter->uninstall();
    }

    public function eventAdminMenu(&$route, &$data, &$output) {
        $this->load->language('extension/module/dockercart_newsletter');

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_newsletter')) {
            return;
        }

        $menu = array(
            'name' => $this->language->get('text_subscribers'),
            'href' => $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . ($this->getDefaultModuleId() > 0 ? '&module_id=' . $this->getDefaultModuleId() : ''), true),
            'children' => array()
        );

        if (!isset($data['menus']) || !is_array($data['menus'])) {
            return;
        }

        foreach ($data['menus'] as &$item) {
            if (isset($item['id']) && $item['id'] === 'menu-marketing' && isset($item['children']) && is_array($item['children'])) {
                $item['children'][] = $menu;
                return;
            }
        }

        // If marketing menu not found, add standalone menu entry
        $data['menus'][] = array(
            'id' => 'menu-dockercart-newsletter',
            'icon' => 'fa-envelope',
            'name' => $this->language->get('text_subscribers'),
            'href' => $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . ($this->getDefaultModuleId() > 0 ? '&module_id=' . $this->getDefaultModuleId() : ''), true),
            'children' => array()
        );
    }

    public function deleteSubscriber() {
        $this->load->language('extension/module/dockercart_newsletter');
        $this->load->model('extension/module/dockercart_newsletter');

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_newsletter')) {
            $this->session->data['warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $type = isset($this->request->get['type']) ? (string)$this->request->get['type'] : '';

        if ($type === 'customer' && isset($this->request->get['customer_id'])) {
            $this->model_extension_module_dockercart_newsletter->unsubscribeCustomer((int)$this->request->get['customer_id']);
            $this->session->data['success'] = $this->language->get('text_success_unsubscribe_customer');
        } elseif ($type === 'guest' && isset($this->request->get['subscriber_id'])) {
            $this->model_extension_module_dockercart_newsletter->deleteGuestSubscriber((int)$this->request->get['subscriber_id']);
            $this->session->data['success'] = $this->language->get('text_success_delete_subscriber');
        }

        $redirect = $this->url->link('extension/module/dockercart_newsletter', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : ''), true);
        $this->response->redirect($redirect);
    }

    public function import() {
        $this->load->language('extension/module/dockercart_newsletter');
        $this->load->model('extension/module/dockercart_newsletter');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_newsletter')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif (!isset($this->request->files['file']) || !is_uploaded_file($this->request->files['file']['tmp_name'])) {
            $json['error'] = $this->language->get('error_upload');
        } else {
            $file = $this->request->files['file'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($extension !== 'csv') {
                $json['error'] = $this->language->get('error_file_type');
            } else {
                $import_result = $this->processImportFile($file['tmp_name']);

                if ($import_result['processed'] > 0) {
                    $json['success'] = sprintf(
                        $this->language->get('text_success_import'),
                        $import_result['added'],
                        $import_result['updated'],
                        $import_result['already']
                    );
                } else {
                    $json['error'] = $this->language->get('error_import_empty');
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function export() {
        $this->load->language('extension/module/dockercart_newsletter');
        $this->load->model('extension/module/dockercart_newsletter');

        $rows = $this->model_extension_module_dockercart_newsletter->getSubscribersForExport();

        $filename = 'newsletter_subscribers_' . date('Y-m-d_H-i-s') . '.csv';

        $fp = fopen('php://temp', 'r+');

        fputcsv($fp, array('email', 'type', 'source', 'customer_id', 'status', 'date_added'));

        foreach ($rows as $row) {
            fputcsv($fp, array(
                $row['email'],
                $row['subscriber_type'],
                $row['source'],
                (int)$row['customer_id'],
                (int)$row['status'],
                $row['date_added']
            ));
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        $this->response->addHeader('Content-Type: text/csv; charset=utf-8');
        $this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->response->setOutput($csv);
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_newsletter')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    private function processImportFile($file_path) {
        $result = array(
            'processed' => 0,
            'added' => 0,
            'updated' => 0,
            'already' => 0
        );

        if (!is_readable($file_path)) {
            return $result;
        }

        $contents = file_get_contents($file_path);

        if ($contents === false || trim($contents) === '') {
            return $result;
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents);

        if (!$lines) {
            return $result;
        }

        $delimiter = ',';
        if (isset($lines[0]) && substr_count($lines[0], ';') > substr_count($lines[0], ',')) {
            $delimiter = ';';
        }

        $first_line = true;

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line, $delimiter);
            if (!$columns || !isset($columns[0])) {
                continue;
            }

            $email = strtolower(trim((string)$columns[0]));

            if ($first_line && ($email === 'email' || $email === 'e-mail')) {
                $first_line = false;
                continue;
            }

            $first_line = false;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $result['processed']++;
            $import_status = $this->model_extension_module_dockercart_newsletter->importEmail($email);

            if ($import_status === 'subscribed_guest') {
                $result['added']++;
            } elseif ($import_status === 'subscribed_customer' || $import_status === 'reactivated') {
                $result['updated']++;
            } elseif ($import_status === 'already') {
                $result['already']++;
            }
        }

        return $result;
    }

    private function getDefaultModuleId() {
        $this->load->model('setting/module');

        $layout_query = $this->db->query("SELECT code FROM `" . DB_PREFIX . "layout_module` WHERE code LIKE 'dockercart_newsletter.%' ORDER BY layout_module_id ASC LIMIT 1");

        if ($layout_query->num_rows && !empty($layout_query->row['code'])) {
            $parts = explode('.', (string)$layout_query->row['code']);

            if (isset($parts[1]) && (int)$parts[1] > 0) {
                return (int)$parts[1];
            }
        }

        $modules = $this->model_setting_module->getModulesByCode('dockercart_newsletter');

        if (!$modules) {
            return 0;
        }

        $module_ids = array();

        foreach ($modules as $module) {
            if (isset($module['module_id'])) {
                $module_ids[] = (int)$module['module_id'];
            }
        }

        if (!$module_ids) {
            return 0;
        }

        sort($module_ids);

        return (int)$module_ids[0];
    }
}
