<?php
/**
 * DockerCart admin — API request journal (System > API Log).
 *
 * Read-only view on oc_api_log with the filters an operator needs after an
 * integration breaks: key, status code, error code, path, date.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ControllerSettingApiLog extends Controller {
	public function index() {
		$this->load->language('setting/api_log');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/api_log');
		$this->load->model('setting/api_key');

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;

		$limit = 50;

		$filters = [
			'filter_api_key_id' => isset($this->request->get['api_key_id']) ? (int)$this->request->get['api_key_id'] : 0,
			'filter_status'     => isset($this->request->get['status']) && $this->request->get['status'] !== '' ? (int)$this->request->get['status'] : 0,
			'filter_error_code' => isset($this->request->get['error_code']) ? trim((string)$this->request->get['error_code']) : '',
			'filter_path'       => isset($this->request->get['path']) ? trim((string)$this->request->get['path']) : '',
			'filter_date_added' => isset($this->request->get['date_added']) ? trim((string)$this->request->get['date_added']) : '',
			'start'             => ($page - 1) * $limit,
			'limit'             => $limit,
		];

		$data['logs'] = [];

		foreach ($this->model_setting_api_log->getLogs($filters) as $log) {
			$data['logs'][] = [
				'api_log_id'  => (int)$log['api_log_id'],
				'api_key_id'  => (int)$log['api_key_id'],
				'key_name'    => (string)$log['key_name'],
				'method'      => (string)$log['method'],
				'path'        => (string)$log['path'],
				'route'       => (string)$log['route'],
				'status_code' => (int)$log['status_code'],
				'is_error'    => (int)$log['status_code'] >= 400,
				'duration_ms' => (int)$log['duration_ms'],
				'ip'          => (string)$log['ip'],
				'request_id'  => (string)$log['request_id'],
				'error_code'  => (string)$log['error_code'],
				'date_added'  => date($this->language->get('datetime_format'), strtotime((string)$log['date_added'])),
				'key_link'    => $this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'] . '&api_key_id=' . (int)$log['api_key_id'], true),
			];
		}

		$total = $this->model_setting_api_log->getTotalLogs($filters);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'] . $this->filterQuery() . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit), $total, ceil($total / $limit));

		$data['filter_api_key_id'] = $filters['filter_api_key_id'];
		$data['filter_status'] = $filters['filter_status'];
		$data['filter_error_code'] = $filters['filter_error_code'];
		$data['filter_path'] = $filters['filter_path'];
		$data['filter_date_added'] = $filters['filter_date_added'];

		$data['keys'] = [];

		foreach ($this->model_setting_api_key->getKeys() as $key) {
			$data['keys'][] = [
				'api_key_id' => (int)$key['api_key_id'],
				'name'       => (string)$key['name'],
			];
		}

		$data['clear'] = $this->url->link('setting/api_log/clear', 'user_token=' . $this->session->data['user_token'], true);
		$data['filter'] = $this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true);

		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/api_log');

		if (isset($this->session->data['success'])) {
			$data['success'] = (string)$this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_warning'] = isset($this->session->data['error_warning']) ? (string)$this->session->data['error_warning'] : '';

		unset($this->session->data['error_warning']);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/api_log', $data));
	}

	public function clear() {
		$this->load->language('setting/api_log');

		if (!$this->user->hasPermission('modify', 'setting/api_log')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/api_log');

			$this->model_setting_api_log->clear();

			$this->session->data['success'] = $this->language->get('text_cleared');
		}

		$this->response->redirect($this->url->link('setting/api_log', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Query string of the active filters (kept when paging).
	 */
	private function filterQuery(): string {
		$query = '';

		foreach (['api_key_id', 'status', 'error_code', 'path', 'date_added'] as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$query .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		return $query;
	}
}
