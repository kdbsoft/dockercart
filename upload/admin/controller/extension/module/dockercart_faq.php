<?php
class ControllerExtensionModuleDockercartFaq extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/dockercart_faq');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/dockercart_faq');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateModule()) {
            $this->model_setting_setting->editSetting('module_dockercart_faq', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data = $this->getCommonData();

        if (isset($this->request->post['module_dockercart_faq_status'])) {
            $data['module_dockercart_faq_status'] = (int)$this->request->post['module_dockercart_faq_status'];
        } else {
            $data['module_dockercart_faq_status'] = (int)$this->config->get('module_dockercart_faq_status');
        }

        $data['add_faq_link'] = $this->url->link('extension/module/dockercart_faq/form', 'user_token=' . $this->session->data['user_token'], true);
        $data['faqs'] = $this->model_extension_module_dockercart_faq->getFaqs(array('sort' => 'fd.question', 'order' => 'ASC'));

        foreach ($data['faqs'] as &$faq) {
            $faq['edit_link'] = $this->url->link('extension/module/dockercart_faq/form', 'user_token=' . $this->session->data['user_token'] . '&faq_id=' . (int)$faq['faq_id'], true);
            $faq['delete_link'] = $this->url->link('extension/module/dockercart_faq/delete', 'user_token=' . $this->session->data['user_token'] . '&faq_id=' . (int)$faq['faq_id'], true);
        }

        $data['context_type_map'] = $this->getContextTypeMap();

        $this->response->setOutput($this->load->view('extension/module/dockercart_faq', $data));
    }

    public function form() {
        $this->load->language('extension/module/dockercart_faq');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/dockercart_faq');
        $this->load->model('localisation/language');
        $this->load->model('setting/store');

        $faq_id = isset($this->request->get['faq_id']) ? (int)$this->request->get['faq_id'] : 0;
        $faq = array();

        if ($faq_id > 0) {
            $faq = $this->model_extension_module_dockercart_faq->getFaq($faq_id);

            if (!$faq) {
                $this->session->data['error_warning'] = $this->language->get('error_faq_not_found');
                $this->response->redirect($this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true));
                return;
            }
        }

        $data = $this->getCommonData();
        $data['faq_id'] = $faq_id;
        $data['action'] = $this->url->link('extension/module/dockercart_faq/save', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true);

        $defaults = array(
            'faq_id' => 0,
            'code' => '',
            'context_type' => 'all',
            'context_value' => '',
            'show_widget' => 1,
            'show_json_ld' => 1,
            'sort_order' => 0,
            'status' => 1
        );

        $data['faq'] = array_merge($defaults, is_array($faq) ? $faq : array());
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['faq_descriptions'] = ($faq_id > 0) ? $this->model_extension_module_dockercart_faq->getFaqDescriptions($faq_id) : array();

        if (!is_array($data['faq_descriptions'])) {
            $data['faq_descriptions'] = array();
        }

        $data['stores'] = array();
        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name') . ' (' . $this->language->get('text_default') . ')'
        );

        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = $store;
        }

        $data['faq_stores'] = ($faq_id > 0) ? $this->model_extension_module_dockercart_faq->getFaqStores($faq_id) : array(0);
        $data['context_types'] = $this->getContextTypeMap();

        $this->response->setOutput($this->load->view('extension/module/dockercart_faq_form', $data));
    }

    public function save() {
        $this->load->language('extension/module/dockercart_faq');
        $this->load->model('extension/module/dockercart_faq');

        if (!$this->validateFaqForm()) {
            $this->session->data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_form');

            $redirect = $this->url->link('extension/module/dockercart_faq/form', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->post['faq_id']) ? '&faq_id=' . (int)$this->request->post['faq_id'] : ''), true);
            $this->response->redirect($redirect);
            return;
        }

        $data = $this->request->post;
        $faq_id = isset($data['faq_id']) ? (int)$data['faq_id'] : 0;

        if ($faq_id > 0) {
            $this->model_extension_module_dockercart_faq->editFaq($faq_id, $data);
        } else {
            $faq_id = $this->model_extension_module_dockercart_faq->addFaq($data);
        }

        $this->session->data['success'] = $this->language->get('text_faq_saved');
        $this->response->redirect($this->url->link('extension/module/dockercart_faq/form', 'user_token=' . $this->session->data['user_token'] . '&faq_id=' . (int)$faq_id, true));
    }

    public function delete() {
        $this->load->language('extension/module/dockercart_faq');
        $this->load->model('extension/module/dockercart_faq');

        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_faq')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $faq_id = isset($this->request->get['faq_id']) ? (int)$this->request->get['faq_id'] : 0;
        if ($faq_id > 0) {
            $this->model_extension_module_dockercart_faq->deleteFaq($faq_id);
            $this->session->data['success'] = $this->language->get('text_faq_deleted');
        }

        $this->response->redirect($this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function install() {
        $this->load->model('extension/module/dockercart_faq');
        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        $this->load->model('user/user_group');

        $this->model_extension_module_dockercart_faq->install();
        $this->registerEvents();

        $this->model_setting_setting->editSetting('module_dockercart_faq', array(
            'module_dockercart_faq_status' => 1
        ));

        $group_id = (int)$this->user->getGroupId();
        $this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_faq');
        $this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_faq');
    }

    public function uninstall() {
        $this->load->model('extension/module/dockercart_faq');
        $this->unregisterEvents();
        $this->model_extension_module_dockercart_faq->uninstall();
    }

    private function registerEvents() {
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('dockercart_faq_admin_menu');
        $this->model_setting_event->deleteEventByCode('dockercart_faq_placeholder_replace');

        $this->model_setting_event->addEvent(
            'dockercart_faq_admin_menu',
            'admin/view/common/column_left/before',
            'extension/module/dockercart_faq/eventAdminMenu',
            1,
            0
        );

        $this->model_setting_event->addEvent(
            'dockercart_faq_placeholder_replace',
            'catalog/controller/*/after',
            'extension/module/dockercart_faq/eventReplacePlaceholders',
            1,
            0
        );
    }

    private function unregisterEvents() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('dockercart_faq_admin_menu');
        $this->model_setting_event->deleteEventByCode('dockercart_faq_placeholder_replace');
    }

    public function eventAdminMenu(&$route, &$data, &$output) {
        $this->load->language('extension/module/dockercart_faq');

        if (!$this->user->hasPermission('access', 'extension/module/dockercart_faq')) {
            return;
        }

        $menu = array(
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true),
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
            'id' => 'menu-dockercart-faq',
            'icon' => 'fa-question-circle',
            'name' => $this->language->get('heading_title_menu'),
            'href' => $this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true),
            'children' => array()
        );
    }

    private function getCommonData() {
        $data = array();

        $data['heading_title'] = $this->language->get('heading_title');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_add'] = $this->language->get('button_add');
        $data['button_edit'] = $this->language->get('button_edit');
        $data['button_delete'] = $this->language->get('button_delete');

        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_home'] = $this->language->get('text_home');
        $data['text_extension'] = $this->language->get('text_extension');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_add_faq'] = $this->language->get('text_add_faq');
        $data['text_edit_faq'] = $this->language->get('text_edit_faq');
        $data['text_placeholders_title'] = $this->language->get('text_placeholders_title');
        $data['text_placeholders_scope'] = $this->language->get('text_placeholders_scope');
        $data['text_default'] = $this->language->get('text_default');

        $data['button_add_faq'] = $this->language->get('button_add_faq');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_code'] = $this->language->get('entry_code');
        $data['entry_context_type'] = $this->language->get('entry_context_type');
        $data['entry_context_value'] = $this->language->get('entry_context_value');
        $data['entry_question'] = $this->language->get('entry_question');
        $data['entry_answer'] = $this->language->get('entry_answer');
        $data['entry_show_widget'] = $this->language->get('entry_show_widget');
        $data['entry_show_json_ld'] = $this->language->get('entry_show_json_ld');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_store'] = $this->language->get('entry_store');
        $data['entry_faq_status'] = $this->language->get('entry_faq_status');

        $data['column_question'] = $this->language->get('column_question');
        $data['column_code'] = $this->language->get('column_code');
        $data['column_context'] = $this->language->get('column_context');
        $data['column_status'] = $this->language->get('column_status');
        $data['column_sort_order'] = $this->language->get('column_sort_order');
        $data['column_action'] = $this->language->get('column_action');

        $data['help_code'] = $this->language->get('help_code');
        $data['help_context_type'] = $this->language->get('help_context_type');
        $data['help_context_value'] = $this->language->get('help_context_value');
        $data['help_question_answer_placeholders'] = $this->language->get('help_question_answer_placeholders');

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        if (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = $this->session->data['error_warning'];
            unset($this->session->data['error_warning']);
        }

        $data['success'] = '';
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
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
            'href' => $this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_faq', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $data;
    }

    private function getContextTypeMap() {
        return array(
            'all' => $this->language->get('text_context_all'),
            'home' => $this->language->get('text_context_home'),
            'route' => $this->language->get('text_context_route'),
            'category' => $this->language->get('text_context_category'),
            'product' => $this->language->get('text_context_product'),
            'manufacturer' => $this->language->get('text_context_manufacturer'),
            'information' => $this->language->get('text_context_information'),
            'search' => $this->language->get('text_context_search')
        );
    }

    private function validateModule() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_faq')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function validateFaqForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_faq')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }

        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->error['warning'] = $this->language->get('error_form');
            return false;
        }

        $code = isset($this->request->post['code']) ? trim((string)$this->request->post['code']) : '';
        if ($code === '') {
            $this->error['warning'] = $this->language->get('error_code_required');
            return false;
        }

        if (!preg_match('/^[a-z0-9_\-\.]+$/i', $code)) {
            $this->error['warning'] = $this->language->get('error_code_format');
            return false;
        }

        $faq_descriptions = isset($this->request->post['faq_description']) && is_array($this->request->post['faq_description'])
            ? $this->request->post['faq_description']
            : array();

        $has_question = false;
        $has_answer = false;

        foreach ($faq_descriptions as $description) {
            $question = isset($description['question']) ? trim((string)$description['question']) : '';
            $answer = isset($description['answer']) ? trim((string)$description['answer']) : '';

            if ($question !== '') {
                $has_question = true;
            }

            if ($answer !== '') {
                $has_answer = true;
            }
        }

        if (!$has_question || !$has_answer) {
            $this->error['warning'] = $this->language->get('error_question_answer_required');
            return false;
        }

        return true;
    }
}
