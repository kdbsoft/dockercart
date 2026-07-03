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
		$data['set_schedule_url'] = $this->url->link('extension/shipping/dockercart_novapost/setSchedule', 'user_token=' . $this->session->data['user_token'], true);

		// Scheduler task
		$tasks = $this->dockercart_scheduler->getTasksByType('novapost_sync');
		$schedulerTask = !empty($tasks) ? $tasks[0] : null;
		$data['sync_schedule'] = $schedulerTask ? ($schedulerTask['cron_schedule'] ?? '') : '';
		$data['schedule_options'] = [
			''          => $this->language->get('text_cron_disabled'),
			'every_15m' => $this->language->get('text_every_15m'),
			'every_30m' => $this->language->get('text_every_30m'),
			'hourly'    => $this->language->get('text_hourly'),
			'every_6h'  => $this->language->get('text_every_6h'),
			'every_12h' => $this->language->get('text_every_12h'),
			'daily'     => $this->language->get('text_daily'),
		];

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
		$data['shipping_dockercart_novapost_calculation_method'] = $this->config->get('shipping_dockercart_novapost_calculation_method') ?: 'tariff';

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

		// Tariffs list
		$pageTariff = max(1, isset($this->request->get['page_tariff']) ? (int)$this->request->get['page_tariff'] : 1);
		$limitTariff = 20;
		$startTariff = ($pageTariff - 1) * $limitTariff;

		$tariffFilter = [
			'sort'  => $this->request->get['sort_tariff'] ?? 'country_code',
			'order' => $this->request->get['order_tariff'] ?? 'ASC',
			'start' => $startTariff,
			'limit' => $limitTariff,
		];

		if (!empty($this->request->get['filter_tariff_country'])) {
			$tariffFilter['filter_country'] = $this->request->get['filter_tariff_country'];
		}
		if (!empty($this->request->get['filter_tariff_delivery_type'])) {
			$tariffFilter['filter_delivery_type'] = $this->request->get['filter_tariff_delivery_type'];
		}

		$data['tariffs'] = $this->model_extension_shipping_dockercart_novapost->getTariffs($tariffFilter);
		$totalTariffs = $this->model_extension_shipping_dockercart_novapost->getTotalTariffs($tariffFilter);

		$paginationTariff = new Pagination();
		$paginationTariff->total = $totalTariffs;
		$paginationTariff->page = $pageTariff;
		$paginationTariff->limit = $limitTariff;
		$paginationTariff->url = $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'] . '&page_tariff={page}&tab=tariffs' . (!empty($tariffFilter['filter_country']) ? '&filter_tariff_country=' . $tariffFilter['filter_country'] : '') . (!empty($tariffFilter['filter_delivery_type']) ? '&filter_tariff_delivery_type=' . $tariffFilter['filter_delivery_type'] : ''), true);
		$data['pagination_tariffs'] = $paginationTariff->render();

		$data['results_tariffs'] = sprintf($this->language->get('text_pagination'), ($totalTariffs) ? (($pageTariff - 1) * $limitTariff) + 1 : 0, ((($pageTariff - 1) * $limitTariff) > ($totalTariffs - $limitTariff)) ? $totalTariffs : ((($pageTariff - 1) * $limitTariff) + $limitTariff), $totalTariffs, ceil($totalTariffs / $limitTariff));

		$data['filter_tariff_country'] = $this->request->get['filter_tariff_country'] ?? '';
		$data['filter_tariff_delivery_type'] = $this->request->get['filter_tariff_delivery_type'] ?? '';
		$data['sort_tariff'] = $tariffFilter['sort'];
		$data['order_tariff'] = $tariffFilter['order'];

		$data['add_tariff_url'] = $this->url->link('extension/shipping/dockercart_novapost/addTariff', 'user_token=' . $this->session->data['user_token'], true);
		$data['edit_tariff_url'] = $this->url->link('extension/shipping/dockercart_novapost/editTariff', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete_tariff_url'] = $this->url->link('extension/shipping/dockercart_novapost/deleteTariff', 'user_token=' . $this->session->data['user_token'], true);

		$data['delivery_types'] = \ModelExtensionShippingDockercartNovapost::DELIVERY_TYPES;

		// Region maps list
		$pageRegMap = max(1, isset($this->request->get['page_region_map']) ? (int)$this->request->get['page_region_map'] : 1);
		$limitRegMap = 20;
		$startRegMap = ($pageRegMap - 1) * $limitRegMap;

		$regionMapFilter = [
			'sort'  => $this->request->get['sort_region_map'] ?? 'country_code',
			'order' => $this->request->get['order_region_map'] ?? 'ASC',
			'start' => $startRegMap,
			'limit' => $limitRegMap,
		];

		if (!empty($this->request->get['filter_region_country'])) {
			$regionMapFilter['filter_country'] = $this->request->get['filter_region_country'];
		}
		if (isset($this->request->get['filter_region_mapped']) && $this->request->get['filter_region_mapped'] !== '') {
			$regionMapFilter['filter_mapped'] = $this->request->get['filter_region_mapped'];
		}

		$data['region_maps'] = $this->model_extension_shipping_dockercart_novapost->getRegionMaps($regionMapFilter);
		$totalRegionMaps = $this->model_extension_shipping_dockercart_novapost->getTotalRegionMaps($regionMapFilter);

		$paginationRegMap = new Pagination();
		$paginationRegMap->total = $totalRegionMaps;
		$paginationRegMap->page = $pageRegMap;
		$paginationRegMap->limit = $limitRegMap;
		$paginationRegMap->url = $this->url->link('extension/shipping/dockercart_novapost', 'user_token=' . $this->session->data['user_token'] . '&page_region_map={page}&tab=region-mapping' . (!empty($regionMapFilter['filter_country']) ? '&filter_region_country=' . $regionMapFilter['filter_country'] : '') . (isset($regionMapFilter['filter_mapped']) ? '&filter_region_mapped=' . $regionMapFilter['filter_mapped'] : ''), true);
		$data['pagination_region_maps'] = $paginationRegMap->render();

		$data['results_region_maps'] = sprintf($this->language->get('text_pagination'), ($totalRegionMaps) ? (($pageRegMap - 1) * $limitRegMap) + 1 : 0, ((($pageRegMap - 1) * $limitRegMap) > ($totalRegionMaps - $limitRegMap)) ? $totalRegionMaps : ((($pageRegMap - 1) * $limitRegMap) + $limitRegMap), $totalRegionMaps, ceil($totalRegionMaps / $limitRegMap));

		$data['filter_region_country'] = $this->request->get['filter_region_country'] ?? '';
		$data['filter_region_mapped'] = $this->request->get['filter_region_mapped'] ?? '';
		$data['sort_region_map'] = $regionMapFilter['sort'];
		$data['order_region_map'] = $regionMapFilter['order'];

		$data['update_region_map_url'] = $this->url->link('extension/shipping/dockercart_novapost/updateRegionMap', 'user_token=' . $this->session->data['user_token'], true);
		$data['get_zones_url'] = $this->url->link('extension/shipping/dockercart_novapost/getZones', 'user_token=' . $this->session->data['user_token'], true);

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

	public function setSchedule() {
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$schedule = $this->request->post['schedule'] ?? '';

			$tasks = $this->dockercart_scheduler->getTasksByType('novapost_sync');
			if (!empty($tasks)) {
				$task = $tasks[0];
				$this->dockercart_scheduler->setSchedule((int)$task['task_id'], $schedule);
				$json['success'] = true;
			} else {
				$json['error'] = 'Scheduler task not found';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install() {
		$this->load->model('extension/shipping/dockercart_novapost');
		$this->load->model('setting/setting');
		$this->load->model('setting/event');
		$this->load->model('user/user_group');

		$this->model_extension_shipping_dockercart_novapost->install();

		$this->model_setting_setting->editSetting('shipping_dockercart_novapost', [
			'shipping_dockercart_novapost_status'            => 0,
			'shipping_dockercart_novapost_sandbox'           => 1,
			'shipping_dockercart_novapost_calculation_method' => 'tariff',
			'shipping_dockercart_novapost_country_codes'     => ['PL', 'UA'],
			'shipping_dockercart_novapost_division_categories' => ['CargoBranch', 'PostBranch', 'Postomat', 'PUDO'],
			'shipping_dockercart_novapost_sort_order'        => 0,
		]);

		// Register events for frontend checkout integration
		$this->model_setting_event->addEvent('dockercart_novapost_header', 'catalog/view/checkout/dockercart_checkout/after', 'extension/shipping/dockercart_novapost/eventHeaderAfter', 1, 90);
		$this->model_setting_event->addEvent('dockercart_novapost_shipping_address', 'catalog/controller/checkout/dockercart_checkout/shipping_address/after', 'extension/shipping/dockercart_novapost/eventShippingAddressAfter', 1, 10);

		$groupId = (int)$this->user->getGroupId();
		$this->model_user_user_group->addPermission($groupId, 'access', 'extension/shipping/dockercart_novapost');
		$this->model_user_user_group->addPermission($groupId, 'modify', 'extension/shipping/dockercart_novapost');

		// Register with universal scheduler
		$this->dockercart_scheduler->registerTask(
			'novapost_sync',
			'NovaPost Sync',
			'php /var/www/html/bin/novapost-sync.php',
			'daily',
			true
		);
	}

	public function uninstall() {
		$this->load->model('extension/shipping/dockercart_novapost');
		$this->load->model('setting/setting');
		$this->load->model('setting/event');

		$this->model_extension_shipping_dockercart_novapost->uninstall();
		$this->model_setting_setting->deleteSetting('shipping_dockercart_novapost');

		$this->model_setting_event->deleteEventByCode('dockercart_novapost_header');
		$this->model_setting_event->deleteEventByCode('dockercart_novapost_shipping_address');

		// Remove from universal scheduler
		$this->dockercart_scheduler->unregisterTask('novapost_sync');
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function addTariff() {
		$this->load->language('extension/shipping/dockercart_novapost');
		$this->load->model('extension/shipping/dockercart_novapost');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$data = $this->request->post;
			$errors = $this->validateTariff($data, 0);

			if ($errors) {
				$json['error'] = $errors;
			} else {
				$this->model_extension_shipping_dockercart_novapost->addTariff($data);
				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function editTariff() {
		$this->load->language('extension/shipping/dockercart_novapost');
		$this->load->model('extension/shipping/dockercart_novapost');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$tariffId = isset($this->request->post['tariff_id']) ? (int)$this->request->post['tariff_id'] : 0;

			if (!$tariffId) {
				$json['error'] = $this->language->get('error_tariff_not_found');
			} else {
				$data = $this->request->post;
				$errors = $this->validateTariff($data, $tariffId);

				if ($errors) {
					$json['error'] = $errors;
				} else {
					$this->model_extension_shipping_dockercart_novapost->editTariff($tariffId, $data);
					$json['success'] = $this->language->get('text_success');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteTariff() {
		$this->load->language('extension/shipping/dockercart_novapost');
		$this->load->model('extension/shipping/dockercart_novapost');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$tariffId = isset($this->request->post['tariff_id']) ? (int)$this->request->post['tariff_id'] : 0;

			if (!$tariffId) {
				$json['error'] = $this->language->get('error_tariff_not_found');
			} else {
				$this->model_extension_shipping_dockercart_novapost->deleteTariff($tariffId);
				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function validateTariff(array $data, int $excludeId = 0): string {
		$supportedCountries = \ModelExtensionShippingDockercartNovapost::SUPPORTED_COUNTRIES;
		$deliveryTypes = \ModelExtensionShippingDockercartNovapost::DELIVERY_TYPES;

		if (empty($data['country_code']) || !array_key_exists($data['country_code'], $supportedCountries)) {
			return $this->language->get('error_tariff_country');
		}

		if (empty($data['delivery_type']) || !array_key_exists($data['delivery_type'], $deliveryTypes)) {
			return $this->language->get('error_tariff_delivery');
		}

		$weightFrom = (float)($data['weight_from'] ?? -1);
		$weightTo = (float)($data['weight_to'] ?? -1);
		$cost = (float)($data['cost'] ?? -1);

		if ($weightFrom < 0) {
			return $this->language->get('error_tariff_weight_from');
		}

		if ($weightTo <= $weightFrom) {
			return $this->language->get('error_tariff_weight_to');
		}

		if ($cost < 0) {
			return $this->language->get('error_tariff_cost');
		}

		// Check for overlapping weight ranges for same country + delivery_type
		$sql = "SELECT tariff_id FROM `" . DB_PREFIX . "dockercart_novapost_tariff`
			WHERE country_code = '" . $this->db->escape($data['country_code']) . "'
			AND delivery_type = '" . $this->db->escape($data['delivery_type']) . "'
			AND (weight_from < " . (float)$weightTo . " AND weight_to > " . (float)$weightFrom . ")";
		if ($excludeId > 0) {
			$sql .= " AND tariff_id != '" . (int)$excludeId . "'";
		}
		$sql .= " LIMIT 1";
		$query = $this->db->query($sql);

		if ($query->num_rows) {
			return $this->language->get('error_tariff_overlap');
		}

		return '';
	}

	public function updateRegionMap() {
		$this->load->language('extension/shipping/dockercart_novapost');
		$this->load->model('extension/shipping/dockercart_novapost');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/shipping/dockercart_novapost')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$regionMapId = isset($this->request->post['region_map_id']) ? (int)$this->request->post['region_map_id'] : 0;
			$ocZoneId = isset($this->request->post['oc_zone_id']) ? (int)$this->request->post['oc_zone_id'] : 0;
			$cityName = isset($this->request->post['city_name']) ? trim($this->request->post['city_name']) : '';

			if (!$regionMapId) {
				$json['error'] = 'Invalid region map ID';
			} else {
				$this->model_extension_shipping_dockercart_novapost->updateRegionMap($regionMapId, $ocZoneId, $cityName);
				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getZones() {
		$this->load->model('extension/shipping/dockercart_novapost');

		$json = [];

		$countryCode = $this->request->get['country_code'] ?? '';

		if ($countryCode) {
			$zones = $this->model_extension_shipping_dockercart_novapost->getZonesByCountryCode($countryCode);
			$json = $zones;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
