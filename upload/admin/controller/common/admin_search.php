<?php
/**
 * DockerCart Admin Search Controller
 *
 * Provides global admin search functionality via Manticore
 *
 * @package    DockerCart
 * @subpackage Controller
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.0
 */

class ControllerCommonAdminSearch extends Controller {
    public function index() {
        $this->load->language('common/admin_search');
        $this->load->model('common/admin_search');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('common/admin_search', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $search = isset($this->request->get['search']) ? trim($this->request->get['search']) : '';
        $type = isset($this->request->get['type']) ? trim($this->request->get['type']) : 'all';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        $limit = 20;

        $data['search'] = $search;
        $data['active_type'] = $type;
        $data['user_token'] = $this->session->data['user_token'];
        $data['action'] = $this->url->link('common/admin_search', '', true);

        $data['types'] = $this->getAvailableTypes();

        if ($search !== '') {
            $result = $this->model_common_admin_search->search($search, $type, $page, $limit);

            $data['results'] = $result['results'];
            $data['total'] = $result['total'];

            if (isset($result['type_totals'])) {
                $data['type_totals'] = $result['type_totals'];
            } else {
                $data['type_totals'] = $this->model_common_admin_search->getTypeTotals($search);
            }

            $url = '';

            if ($search) {
                $url .= '&search=' . urlencode($search);
            }

            if ($type !== 'all') {
                $url .= '&type=' . $type;
            }

            $pagination = new Pagination();
            $pagination->total = $data['total'];
            $pagination->page = $page;
            $pagination->limit = $limit;
            $pagination->url = $this->url->link('common/admin_search', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

            $data['pagination'] = $pagination->render();

            $data['results_start'] = ($page - 1) * $limit + 1;
            $data['results_end'] = min($page * $limit, $data['total']);
        } else {
            $data['results'] = [];
            $data['total'] = 0;
            $data['type_totals'] = [];
            $data['pagination'] = '';
            $data['results_start'] = 0;
            $data['results_end'] = 0;
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_search_placeholder'] = $this->language->get('text_search_placeholder');
        $data['text_all'] = $this->language->get('text_all');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_results'] = $this->language->get('text_results');
        $data['text_enter_search'] = $this->language->get('text_enter_search');
        $data['button_search'] = $this->language->get('button_search');

        $data['text_product'] = $this->language->get('text_product');
        $data['text_category'] = $this->language->get('text_category');
        $data['text_manufacturer'] = $this->language->get('text_manufacturer');
        $data['text_information'] = $this->language->get('text_information');
        $data['text_order'] = $this->language->get('text_order');
        $data['text_customer'] = $this->language->get('text_customer');
        $data['text_showing'] = $this->language->get('text_showing');
        $data['text_to'] = $this->language->get('text_to');
        $data['text_of'] = $this->language->get('text_of');
        $data['column_type'] = $this->language->get('column_type');
        $data['column_name'] = $this->language->get('column_name');
        $data['column_details'] = $this->language->get('column_details');
        $data['column_action'] = $this->language->get('column_action');
        $data['button_edit'] = $this->language->get('button_edit');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/admin_search', $data));
    }

    public function suggest() {
        $this->load->language('common/admin_search');
        $this->load->model('common/admin_search');
        $this->load->model('tool/image');

        $search = isset($this->request->get['search']) ? trim($this->request->get['search']) : '';

        $json = [];

        if ($search !== '' && mb_strlen($search) >= 2) {
            $results = $this->model_common_admin_search->suggest($search);

            foreach ($results as $result) {
                $image = '';

                if (!empty($result['image']) && in_array($result['type'], ['product', 'category', 'manufacturer'])) {
                    if (is_file(DIR_IMAGE . $result['image'])) {
                        $image = $this->model_tool_image->resize($result['image'], 44, 44);
                    }
                }

                $json[] = [
                    'type' => $result['type'],
                    'type_label' => $result['type_label'],
                    'entity_id' => $result['entity_id'],
                    'name' => $result['name'],
                    'subtitle' => $result['subtitle'],
                    'image' => $image,
                    'href' => $result['href'],
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getAvailableTypes() {
        $types = [];

        $permissions = [
            'product'      => 'catalog/product',
            'category'     => 'catalog/category',
            'manufacturer' => 'catalog/manufacturer',
            'information'  => 'catalog/information',
            'order'        => 'sale/order',
            'customer'     => 'customer/customer',
        ];

        foreach ($permissions as $type => $route) {
            if ($this->user->hasPermission('access', $route)) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
