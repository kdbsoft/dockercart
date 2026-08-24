<?php

declare(strict_types=1);

class ControllerToolRecycleBin extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('tool/recycle_bin');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('tool/recycle_bin');

        $this->getList();
    }

    /**
     * Captures an entity delete into the trash. Called via the
     * admin model delete-before events registered by the migration.
     */
    public function eventCapture(&$route, &$args)
    {
        $this->load->model('tool/recycle_bin');

        $entity_type = $this->model_tool_recycle_bin->entityTypeFromRoute((string) $route);

        if ($entity_type === '') {
            return;
        }

        if (!isset($args[0])) {
            return;
        }

        $deleted_by = $this->registry->has('user') ? (int) $this->user->getId() : 0;

        // Route may be 'extension/module/dockercart_blog_post/deletePost'.
        $this->model_tool_recycle_bin->capture($entity_type, (int) $args[0], $deleted_by);
    }

    public function restore()
    {
        $this->load->language('tool/recycle_bin');

        if (!$this->validateModify()) {
            $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));

            return;
        }

        $this->load->model('tool/recycle_bin');

        $trash_ids = array();

        if (isset($this->request->get['trash_id'])) {
            $trash_ids[] = (int) $this->request->get['trash_id'];
        }

        if (isset($this->request->post['selected'])) {
            foreach ((array) $this->request->post['selected'] as $trash_id) {
                $trash_ids[] = (int) $trash_id;
            }
        }

        $trash_ids = array_unique(array_filter($trash_ids));

        $restored = 0;

        foreach ($trash_ids as $trash_id) {
            if ($this->model_tool_recycle_bin->restore($trash_id)) {
                ++$restored;
            }
        }

        if ($trash_ids) {
            if ($restored > 0) {
                $this->session->data['success'] = sprintf($this->language->get('text_success_restore'), $restored);
            } else {
                $this->error['warning'] = $this->language->get('error_restore');
            }
        }

        if (isset($this->error['warning'])) {
            $this->session->data['error_warning'] = $this->error['warning'];
        }

        $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function purge()
    {
        $this->load->language('tool/recycle_bin');

        if (!$this->validateModify()) {
            $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));

            return;
        }

        $this->load->model('tool/recycle_bin');

        $trash_ids = array();

        if (isset($this->request->get['trash_id'])) {
            $trash_ids[] = (int) $this->request->get['trash_id'];
        }

        if (isset($this->request->post['selected'])) {
            foreach ((array) $this->request->post['selected'] as $trash_id) {
                $trash_ids[] = (int) $trash_id;
            }
        }

        $trash_ids = array_unique(array_filter($trash_ids));

        foreach ($trash_ids as $trash_id) {
            $this->model_tool_recycle_bin->purge($trash_id);
        }

        if ($trash_ids) {
            $this->session->data['success'] = sprintf($this->language->get('text_success_purge'), count($trash_ids));
        }

        $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function clear()
    {
        $this->load->language('tool/recycle_bin');

        if (!$this->validateModify()) {
            $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));

            return;
        }

        $this->load->model('tool/recycle_bin');

        $count = $this->model_tool_recycle_bin->clearAll();

        if ($count > 0) {
            $this->session->data['success'] = sprintf($this->language->get('text_success_clear'), $count);
        }

        $this->response->redirect($this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function getList()
    {
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'deleted_at';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page = (int) $this->request->get['page'];
        } else {
            $page = 1;
        }

        if (isset($this->request->get['filter_entity_type'])) {
            $filter_entity_type = $this->request->get['filter_entity_type'];
        } else {
            $filter_entity_type = '';
        }

        if (isset($this->request->get['filter_name'])) {
            $filter_name = $this->request->get['filter_name'];
        } else {
            $filter_name = '';
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        if ($filter_entity_type !== '') {
            $url .= '&filter_entity_type=' . urlencode(html_entity_decode($filter_entity_type, ENT_QUOTES, 'UTF-8'));
        }

        if ($filter_name !== '') {
            $url .= '&filter_name=' . urlencode(html_entity_decode($filter_name, ENT_QUOTES, 'UTF-8'));
        }

        $data['restore'] = $this->url->link('tool/recycle_bin/restore', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['purge'] = $this->url->link('tool/recycle_bin/purge', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['clear'] = $this->url->link('tool/recycle_bin/clear', 'user_token=' . $this->session->data['user_token'], true);
        $data['action'] = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'], true);

        $data['recycle_bin'] = array();

        $filter_data = array(
            'filter_entity_type' => $filter_entity_type,
            'filter_name'        => $filter_name,
            'sort'               => $sort,
            'order'              => $order,
            'start'              => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit'              => $this->config->get('config_limit_admin'),
        );

        $trash_total = $this->model_tool_recycle_bin->getTotalTrash($filter_data);
        $results = $this->model_tool_recycle_bin->getTrash($filter_data);

        $descriptors = $this->model_tool_recycle_bin->getEntityDescriptors();

        foreach ($results as $result) {
            $label = isset($descriptors[$result['entity_type']]) ? $descriptors[$result['entity_type']]['label'] : $result['entity_type'];

            $data['recycle_bin'][] = array(
                'trash_id'      => $result['trash_id'],
                'entity_type'   => $result['entity_type'],
                'entity_label'  => $this->language->get($label),
                'name'          => $result['name'],
                'deleted_by'    => $result['deleted_username'] !== null ? $result['deleted_username'] : $this->language->get('text_unknown_user'),
                'deleted_at'    => $result['deleted_at'] ? date($this->language->get('datetime_format'), strtotime($result['deleted_at'])) : '',
                'restore'       => $this->url->link('tool/recycle_bin/restore', 'user_token=' . $this->session->data['user_token'] . '&trash_id=' . $result['trash_id'], true),
                'purge'         => $this->url->link('tool/recycle_bin/purge', 'user_token=' . $this->session->data['user_token'] . '&trash_id=' . $result['trash_id'], true),
            );
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } elseif (isset($this->session->data['error_warning'])) {
            $data['error_warning'] = $this->session->data['error_warning'];

            unset($this->session->data['error_warning']);
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array) $this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        $url = '';

        if (isset($this->request->get['filter_entity_type'])) {
            $url .= '&filter_entity_type=' . urlencode(html_entity_decode($this->request->get['filter_entity_type'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if ($order === 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_name'] = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);
        $data['sort_entity_type'] = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'] . '&sort=entity_type' . $url, true);
        $data['sort_deleted_at'] = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'] . '&sort=deleted_at' . $url, true);
        $data['sort_deleted_by'] = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'] . '&sort=deleted_username' . $url, true);

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['filter_entity_type'])) {
            $url .= '&filter_entity_type=' . urlencode(html_entity_decode($this->request->get['filter_entity_type'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        $pagination = new Pagination();
        $pagination->total = $trash_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('tool/recycle_bin', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = $pagination->renderResults($this->language->get('text_pagination'));

        $data['filter_entity_type'] = $filter_entity_type;
        $data['filter_name'] = $filter_name;
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['user_token'] = $this->session->data['user_token'];

        // Entity types for the filter dropdown.
        $data['entity_types'] = array();

        foreach ($descriptors as $entity_type => $descriptor) {
            $data['entity_types'][] = array(
                'value' => $entity_type,
                'label' => $this->language->get($descriptor['label']),
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tool/recycle_bin_list', $data));
    }

    protected function validateModify()
    {
        if (!$this->user->hasPermission('modify', 'tool/recycle_bin')) {
            $this->error['warning'] = $this->language->get('error_permission');

            return false;
        }

        return true;
    }
}