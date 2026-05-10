<?php
/**
 * DockerCart NovaPost Shipping Module - Admin Controller
 */
class ControllerExtensionShippingDockercartNovapost extends Controller {

	private $error = [];

	public function index() {
		$this->load->language('extension/shipping/dockercart_novapost');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/shipping/dockercart_novapost');
		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('shipping_dockercart_novapost', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['error_warning'] = $this->error['warning'] ?? '';

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['action'] = $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
		$data['user_token'] = $this->session->data['user_token'];
		$data['sync_url'] = $this->url->link('extension/shipping/dockercart_novapost/sync', 'user_token=' . $this->session->data['user_token'], true);

		// Statistics
		$data['total_divisions'] = $this->model_extension_shipping_dockercart_novapost->getTotalDivisions();
		$data['divisions_by_country'] = $this->model_extension_shipping_dockercart_novapost->getDivisionsByCountry();
		$data['divisions_by_category'] = $this->model_extension_shipping_dockercart_novapost->getDivisionsByCategory();
		$data['last_sync'] = $this->model_extension_shipping_dockercart_novapost->getLastSync();

		// Settings
		$data['shipping_dockercart_novapost_api_key'] = $this->config->get('shipping_dockercart_novapost_api_key');
		$data['shipping_dockercart_novapost_status'] = $this->config->get('shipping_dockercart_novapost_status');
		$data['shipping_dockercart_novapost_sandbox'] = $this->config->get('shipping_dockercart_novapost_sandbox');
		$data['shipping_dockercart_novapost_sort_order'] = $this->config->get('shipping_dockercart_novapost_sort_order');

		$selectedCountries = $this->config->get('shipping_dockercart_novapost_country_codes');
		$data['shipping_dockercart_novapost_country_codes'] = is_array($selectedCountries) ? $selectedCountries : ['PL', 'UA'];

		$selectedCategories = $this->config->get('shipping_dockercart_novapost_division_categories');
		$data['shipping_dockercart_novapost_division_categories'] = is_array($selectedCategories) ? $selectedCategories : ['CargoBranch', 'PostBranch', 'Postomat', 'PUDO'];

		$data['supported_countries'] = \ModelExtensionShippingDockercartNovapost::SUPPORTED_COUNTRIES;
		$data['division_categories'] = \ModelExtensionShippingDockercartNovapost::DIVISION_CATEGORIES;

		$data['tab'] = $this->request->get['tab'] ?? 'dashboard';

		// Sync log
		$page = max(1, isset($this->request->get['page_sync']) ? (int)$this->request->get['page_sync'] : 1);
		$limit = 10;
		$start = ($page - 1) * $limit;
		$data['sync_logs'] = $this->model_extension_shipping_dockercart_novapost->getSyncLogs($start, $limit);
		$totalSyncLogs = $this->model_extension_shipping_dockercart_novapost->getTotalSyncLogs();

		$pagination = new Pagination();
		$pagination->total = $totalSyncLogs;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'] . '&page_sync={page}&tab=sync-log', true);
		$data['pagination_sync'] = $pagination->render();

		$data['results_sync'] = sprintf($this->language->get('text_pagination'), ($totalSyncLogs) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($totalSyncLogs - $limit)) ? $totalSyncLogs : ((($page - 1) * $limit) + $limit), $totalSyncLogs, ceil($totalSyncLogs / $limit));

		// Divisions list
		$pageDiv = max(1, isset($this->request->get['page_div']) ? (int)$this->request->get['page_div'] : 1);
		$limitDiv = 20;
		$startDiv = ($pageDiv - 1) * $limitDiv;

		$filterData = [
			'sort'  => $this->request->get['sort'] ?? 'name',
			'order' => $this->request->get['order'] ?? 'ASC',
			'start' => $startDiv,
			'limit' => $limitDiv,
		];

		if (!empty($this->request->get['filter_country'])) {
			$filterData['filter_country'] = $this->request->get['filter_country'];
		}
		if (!empty($this->request->get['filter_category'])) {
			$filterData['filter_category'] = $this->request->get['filter_category'];
		}
		if (!empty($this->request->get['filter_search'])) {
			$filterData['filter_search'] = $this->request->get['filter_search'];
		}

		$data['divisions'] = $this->model_extension_shipping_dockercart_novapost->getDivisions($filterData);
		$totalDivisionsFiltered = $this->model_extension_shipping_dockercart_novapost->getTotalDivisionsFiltered($filterData);

		$paginationDiv = new Pagination();
		$paginationDiv->total = $totalDivisionsFiltered;
		$paginationDiv->page = $pageDiv;
		$paginationDiv->limit = $limitDiv;
		$paginationDiv->url = $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'] . '&page_div={page}&tab=divisions' . (!empty($filterData['filter_country']) ? '&filter_country=' . $filterData['filter_country'] : '') . (!empty($filterData['filter_category']) ? '&filter_category=' . $filterData['filter_category'] : '') . (!empty($filterData['filter_search']) ? '&filter_search=' . urlencode($filterData['filter_search']) : ''), true);
		$data['pagination_divisions'] = $paginationDiv->render();

		$data['results_divisions'] = sprintf($this->language->get('text_pagination'), ($totalDivisionsFiltered) ? (($pageDiv - 1) * $limitDiv) + 1 : 0, ((($pageDiv - 1) * $limitDiv) > ($totalDivisionsFiltered - $limitDiv)) ? $totalDivisionsFiltered : ((($pageDiv - 1) * $limitDiv) + $limitDiv), $totalDivisionsFiltered, ceil($totalDivisionsFiltered / $limitDiv));

		$data['filter_country'] = $this->request->get['filter_country'] ?? '';
		$data['filter_category'] = $this->request->get['filter_category'] ?? '';
		$data['filter_search'] = $this->request->get['filter_search'] ?? '';
		$data['sort'] = $filterData['sort'];
		$data['order'] = $filterData['order'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/shipping/dockercart_novapost', $data));
	}

	public function sync() {
		$this->load->language('extension/shipping/dockercart_novapost');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$apiKey = $this->config->get('shipping_dockercart_novapost_api_key');
			$sandbox = (bool)$this->config->get('shipping_dockercart_novapost_sandbox');
			$countryCodes = $this->config->get('shipping_dockercart_novapost_country_codes');
			$categories = $this->config->get('shipping_dockercart_novapost_division_categories');

			if (empty($apiKey)) {
				$json['error'] = $this->language->get('error_api_key');
			} else {
				if (!is_array($countryCodes) || empty($countryCodes)) {
					$countryCodes = ['PL'];
				}
				if (!is_array($categories) || empty($categories)) {
					$categories = ['CargoBranch', 'PostBranch', 'Postomat', 'PUDO'];
				}

				$this->load->model('extension/shipping/dockercart_novapost');
				$result = $this->model_extension_shipping_dockercart_novapost->syncDivisions($apiKey, $sandbox, $countryCodes, $categories);

				if ($result['total_errors'] > 0 && $result['total_loaded'] === 0) {
					$json['error'] = sprintf($this->language->get('error_sync'), implode('; ', $result['errors']));
				} else {
					$json['success'] = sprintf(
						'Loaded: %d, Errors: %d',
						$result['total_loaded'],
						$result['total_errors']
					);
					$json['total_loaded'] = $result['total_loaded'];
					$json['total_errors'] = $result['total_errors'];
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->model('extension/shipping/dockercart_novapost');
		$this->load->model('setting/setting');
		$this->load->model('user/user_group');

		$this->model_extension_shipping_dockercart_novapost->install();

		$this->model_setting_setting->editSetting('shipping_dockercart_novapost', [
			'shipping_dockercart_novapost_status'            => 0,
			'shipping_dockercart_novapost_sandbox'           => 1,
			'shipping_dockercart_novapost_country_codes'     => ['PL', 'UA'],
			'shipping_dockercart_novapost_division_categories' => ['CargoBranch', 'PostBranch', 'Postomat', 'PUDO'],
			'shipping_dockercart_novapost_sort_order'        => 0,
		]);

		$groupId = (int)$this->user->getGroupId();
		$this->model_user_user_group->addPermission($groupId, 'access', 'extension/shipping/dockercart_novapost');
		$this->model_user_user_group->addPermission($groupId, 'modify', 'extension/shipping/dockercart_novapost');
	}

	public function uninstall() {
		$this->load->model('extension/shipping/dockercart_novapost');
		$this->load->model('setting/setting');

		$this->model_extension_shipping_dockercart_novapost->uninstall();
		$this->model_setting_setting->deleteSetting('shipping_dockercart_novapost');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
