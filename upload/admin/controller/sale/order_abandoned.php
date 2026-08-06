<?php
/**
 * Abandoned Carts - Admin Controller
 * Lists checkout carts that were started but never completed.
 */

class ControllerSaleOrderAbandoned extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('sale/order_abandoned');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/dockercart_checkout');

		$this->getList();
	}

	public function markRecovered() {
		$this->load->language('sale/order_abandoned');

		$this->load->model('extension/module/dockercart_checkout');

		if (isset($this->request->post['abandoned_id']) && $this->validateModify()) {
			$this->model_extension_module_dockercart_checkout->markRecovered((int)$this->request->post['abandoned_id']);

			$this->session->data['success'] = $this->language->get('text_success_mark_recovered');
		}

		$this->response->redirect($this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function restore() {
		$this->load->language('sale/order_abandoned');

		$this->load->model('extension/module/dockercart_checkout');

		if (isset($this->request->post['abandoned_id']) && $this->validateModify()) {
			$abandoned_id = (int)$this->request->post['abandoned_id'];

			$token = $this->model_extension_module_dockercart_checkout->createRestoreToken($abandoned_id, 7);

			if ($token) {
				$this->session->data['success'] = sprintf(
					$this->language->get('text_success_restore_link'),
					htmlspecialchars($this->buildRestoreUrl($token), ENT_QUOTES, 'UTF-8')
				);
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_restore_link');
			}
		}

		$this->response->redirect($this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Build the storefront restore URL using the catalog URL rewriter so the
	 * link is a clean SEO URL (e.g. /restore-cart?token=...) instead of
	 * /index.php?route=checkout/dockercart_checkout/restore&token=...
	 */
	private function buildRestoreUrl($token) {
		$base_url = rtrim((string)$this->config->get('config_url'), '/');
		$ssl_url = rtrim((string)$this->config->get('config_ssl'), '/');

		if (!$base_url) {
			$base_url = defined('HTTP_CATALOG') ? rtrim(HTTP_CATALOG, '/') : (defined('HTTP_SERVER') ? rtrim(HTTP_SERVER, '/') : '');
		}

		if (!$ssl_url) {
			$ssl_url = defined('HTTPS_CATALOG') ? rtrim(HTTPS_CATALOG, '/') : $base_url;
		}

		$url = new Url($base_url . '/', $ssl_url . '/', $this->registry);

		if ($this->config->get('config_seo_url')) {
			require_once DIR_CATALOG . 'controller/startup/seo_url.php';
			$seo_url = new ControllerStartupSeoUrl($this->registry);
			$seo_url->initializeRequestState();
			$url->addRewrite($seo_url);
		}

		return $url->link('checkout/dockercart_checkout/restore', 'token=' . $token);
	}

	protected function getList() {
		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = '';
		}

		if (isset($this->request->get['filter_date_start'])) {
			$filter_date_start = $this->request->get['filter_date_start'];
		} else {
			$filter_date_start = '';
		}

		if (isset($this->request->get['filter_date_end'])) {
			$filter_date_end = $this->request->get['filter_date_end'];
		} else {
			$filter_date_end = '';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode($this->request->get['filter_email']);
		}

		if (isset($this->request->get['filter_date_start'])) {
			$url .= '&filter_date_start=' . $this->request->get['filter_date_start'];
		}

		if (isset($this->request->get['filter_date_end'])) {
			$url .= '&filter_date_end=' . $this->request->get['filter_date_end'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['mark_recovered'] = $this->url->link('sale/order_abandoned/markRecovered', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['restore'] = $this->url->link('sale/order_abandoned/restore', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['export_url'] = str_replace('&amp;', '&', $this->url->link('sale/order_abandoned/exportCsv', 'user_token=' . $this->session->data['user_token'], true));

		// Per-admin saved filters (Shopify-style tabs)
		$active_filter = $this->getActiveUserFilter('abandoned_cart');

		$this->load->model('user/user_filter');

		$user_id = (int)$this->user->getId();
		$saved_filters = $this->model_user_user_filter->getFilters($user_id, 'abandoned_cart');

		$tab_counts = array(
			'all' => $this->model_extension_module_dockercart_checkout->getTotalAbandonedCarts(array())
		);

		foreach ($saved_filters as $saved) {
			$tab_counts['custom_' . $saved['filter_id']] = $this->model_extension_module_dockercart_checkout->getTotalAbandonedCarts($this->buildFilterData($saved['conditions']));
		}

		$data['user_filter'] = $this->renderUserFilter('abandoned_cart', 'sale/order_abandoned', array(
			array('key' => 'email', 'label' => $this->language->get('entry_email'), 'type' => 'text'),
			array('key' => 'date_added', 'label' => $this->language->get('entry_date_added'), 'type' => 'text')
		), $tab_counts);

		$data['active_filter'] = $active_filter;

		$filter_data = array(
			'email'      => $filter_email,
			'date_start' => $filter_date_start,
			'date_end'   => $filter_date_end,
			'start'      => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'      => $this->config->get('config_limit_admin')
		);

		if ($active_filter) {
			foreach ($this->buildFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$results = $this->model_extension_module_dockercart_checkout->getAbandonedCarts($filter_data);
		$abandoned_total = $this->model_extension_module_dockercart_checkout->getTotalAbandonedCarts($filter_data);

		$data['abandoned_carts'] = array();

		foreach ($results as $result) {
			$items = array();

			$cart_data = json_decode($result['cart_data'], true);
			if (is_array($cart_data)) {
				foreach ($cart_data as $item) {
					if (!isset($item['name'], $item['quantity'])) {
						continue;
					}

					$items[] = $item['name'] . ' &times; ' . $item['quantity'];
				}
			}

			$data['abandoned_carts'][] = array(
				'abandoned_id' => $result['abandoned_id'],
				'customer'     => $result['customer_id'] ? $this->language->get('text_customer_account') : $this->language->get('text_guest'),
				'email'        => $result['email'],
				'phone'        => $result['phone'],
				'items'        => implode('<br />', array_slice($items, 0, 5)),
				'items_count'  => count($items),
				'last_step'    => $this->language->get('text_step_' . $result['last_step']),
				'date_added'   => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'age'          => $this->formatAge($result['date_added'])
			);
		}

		$data['filter_email'] = $filter_email;
		$data['filter_date_start'] = $filter_date_start;
		$data['filter_date_end'] = $filter_date_end;

		$data['filter_url'] = $this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true);

		$data['error_warning'] = '';
		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['success'] = '';
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}

		$pagination = new Pagination();
		$pagination->total = $abandoned_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($abandoned_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($abandoned_total - $this->config->get('config_limit_admin'))) ? $abandoned_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $abandoned_total, ceil($abandoned_total / $this->config->get('config_limit_admin')));

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('sale/order_abandoned_list', $data));
	}

	protected function validateModify() {
		if (!$this->user->hasPermission('modify', 'sale/order')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Export abandoned carts to CSV (respects the active filters)
	 */
	public function exportCsv(): void {
		$this->load->language('sale/order_abandoned');

		if (!$this->user->hasPermission('access', 'sale/order')) {
			$this->response->redirect($this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$this->load->model('extension/module/dockercart_checkout');

		$filter_data = array(
			'email'      => isset($this->request->get['filter_email']) ? (string)$this->request->get['filter_email'] : '',
			'date_start' => isset($this->request->get['filter_date_start']) ? (string)$this->request->get['filter_date_start'] : '',
			'date_end'   => isset($this->request->get['filter_date_end']) ? (string)$this->request->get['filter_date_end'] : ''
		);

		// Apply the active saved filter conditions
		$active_filter = $this->getActiveUserFilter('abandoned_cart');

		if ($active_filter) {
			foreach ($this->buildFilterData($active_filter['conditions']) as $key => $value) {
				$filter_data[$key] = $value;
			}
		}

		$results = $this->model_extension_module_dockercart_checkout->getAbandonedCarts($filter_data);

		if (!$results) {
			$this->response->redirect($this->url->link('sale/order_abandoned', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$headers = array(
			$this->language->get('column_id'),
			$this->language->get('column_date_added'),
			$this->language->get('entry_email'),
			$this->language->get('entry_telephone'),
			$this->language->get('column_customer'),
			$this->language->get('column_items'),
			$this->language->get('column_last_step'),
			$this->language->get('text_date_modified'),
			$this->language->get('column_age')
		);

		$rows = array();

		foreach ($results as $result) {
			$items = array();

			$cart_data = json_decode($result['cart_data'], true);
			if (is_array($cart_data)) {
				foreach ($cart_data as $item) {
					if (isset($item['name'], $item['quantity'])) {
						$items[] = $item['name'] . ' x' . $item['quantity'];
					}
				}
			}

			$rows[] = array(
				$result['abandoned_id'],
				date($this->language->get('datetime_format'), strtotime($result['date_added'])),
				$result['email'],
				$result['phone'],
				$result['customer_id'] ? $this->language->get('text_customer_account') : $this->language->get('text_guest'),
				implode('; ', $items),
				$this->language->get('text_step_' . $result['last_step']),
				date($this->language->get('datetime_format'), strtotime($result['date_modified'])),
				$this->formatAge($result['date_added'])
			);
		}

		$output = "\xEF\xBB\xBF" . $this->csvLine($headers);

		foreach ($rows as $row) {
			$output .= $this->csvLine($row);
		}

		$filename = 'abandoned-carts-' . date('Ymd-His') . '.csv';

		$this->response->addHeader('Content-Type: text/csv; charset=UTF-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($output);
	}

	private function csvLine(array $fields): string {
		$quoted = array();

		foreach ($fields as $field) {
			$quoted[] = '"' . str_replace('"', '""', (string)$field) . '"';
		}

		return implode(',', $quoted) . "\r\n";
	}

	protected function formatAge($date_added) {
		$seconds = time() - strtotime($date_added);

		if ($seconds < 3600) {
			return sprintf($this->language->get('text_age_minutes'), max(1, (int)floor($seconds / 60)));
		}

		if ($seconds < 86400) {
			return sprintf($this->language->get('text_age_hours'), (int)floor($seconds / 3600));
		}

		return sprintf($this->language->get('text_age_days'), (int)floor($seconds / 86400));
	}

	/**
	 * Convert saved filter conditions into abandoned cart model filter_data.
	 */
	private function buildFilterData(array $conditions): array {
		$data = array();

		foreach ($conditions as $condition) {
			$field = (string)($condition['field'] ?? '');
			$value = $condition['value'] ?? '';

			switch ($field) {
				case 'email':
					$data['email'] = (string)$value;
					break;
				case 'date_added':
					$data['date_start'] = (string)$value;
					break;
			}
		}

		return $data;
	}
}
