<?php
/**
 * DockerCart admin — webhook subscriptions (System → API → Webhooks).
 *
 * Creates, edits, enables/disables, rotates and deletes webhook subscriptions,
 * sends test events and stores the global config_webhook_* limits. Validation
 * (URL/SSRF, events, filters) lives in ModelSettingWebhook::validate() so the
 * panel and the REST API controller share one rule set.
 *
 * The secret is encrypted at rest and shown exactly once (create / rotate),
 * mirroring the API key flow.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

use Dockercart\Webhook\Events;

class ControllerSettingWebhook extends Controller {
	private array $error = [];

	public function index() {
		$this->load->language('setting/webhook');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/webhook');

		$this->getList();
	}

	public function add() {
		$this->load->language('setting/webhook');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/webhook');

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateForm()) {
			$data = $this->model_setting_webhook->normalizeData($this->request->post);
			$data['created_by_user_id'] = (int)$this->user->getId();

			$created = $this->model_setting_webhook->addSubscription($data);

			// The plain secret is shown exactly once, right after creation.
			$this->session->data['webhook_new_secret'] = $created['secret'];
			$this->session->data['webhook_new_secret_id'] = $created['subscription_id'];
			$this->session->data['success'] = sprintf($this->language->get('text_created_message'), $data['name']);

			$this->response->redirect($this->url->link('setting/webhook/edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $created['subscription_id'], true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('setting/webhook');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/webhook');

		$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validateForm()) {
			$this->model_setting_webhook->editSubscription($subscription_id, $this->model_setting_webhook->normalizeData($this->request->post));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->getForm();
	}

	public function enable() {
		$this->setStatus(1, 'text_enabled_success');
	}

	public function disable() {
		$this->setStatus(0, 'text_disabled_success');
	}

	public function rotate() {
		$this->load->language('setting/webhook');

		if (!$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/webhook');

		$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;
		$subscription = $this->model_setting_webhook->getSubscription($subscription_id);

		if (!$subscription) {
			$this->session->data['error_warning'] = $this->language->get('error_subscription');
			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->session->data['webhook_new_secret'] = $this->model_setting_webhook->rotateSecret($subscription_id);
		$this->session->data['webhook_new_secret_id'] = $subscription_id;
		$this->session->data['success'] = sprintf($this->language->get('text_rotated'), $subscription['name']);

		$this->response->redirect($this->url->link('setting/webhook/edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true));
	}

	/**
	 * Queues a synthetic webhook.test delivery (sent by the scheduler worker).
	 */
	public function test() {
		$this->load->language('setting/webhook');

		if (!$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/webhook');

		$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;

		if ($this->model_setting_webhook->enqueueTest($subscription_id, $this->language->get('text_test_message')) === null) {
			$this->session->data['error_warning'] = $this->language->get('error_test');
		} else {
			$this->session->data['success'] = $this->language->get('text_test_sent');
		}

		$this->response->redirect($this->url->link('setting/webhook/edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true));
	}

	public function delete() {
		$this->load->language('setting/webhook');

		if (!$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/webhook');

			$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;
			$subscription = $this->model_setting_webhook->getSubscription($subscription_id);

			if ($subscription) {
				$this->model_setting_webhook->deleteSubscription($subscription_id);

				$this->session->data['success'] = sprintf($this->language->get('text_deleted'), $subscription['name']);
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_subscription');
			}
		}

		$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Saves the five global config_webhook_* limits. updateSetting() upserts only
	 * these keys — editSetting('config', …) would wipe every other store setting.
	 */
	public function settings() {
		$this->load->language('setting/webhook');

		if (!$this->user->hasPermission('modify', 'setting/webhook') || $this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->session->data['error_warning'] = $this->language->get('error_permission');

			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/setting');

		$this->model_setting_setting->updateSetting('config', [
			'config_webhook_status'             => (int)($this->request->post['config_webhook_status'] ?? 1) === 1 ? 1 : 0,
			'config_webhook_timeout'            => max(1, min(60, (int)($this->request->post['config_webhook_timeout'] ?? 10))),
			'config_webhook_max_attempts'       => max(1, min(20, (int)($this->request->post['config_webhook_max_attempts'] ?? 6))),
			'config_webhook_disable_after'      => max(1, min(100, (int)($this->request->post['config_webhook_disable_after'] ?? 4))),
			'config_webhook_log_retention_days' => max(0, (int)($this->request->post['config_webhook_log_retention_days'] ?? 30)),
		]);

		$this->session->data['success'] = $this->language->get('text_settings_saved');

		$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function setStatus(int $status, string $success_key): void {
		$this->load->language('setting/webhook');

		if (!$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/webhook');

			$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;
			$subscription = $this->model_setting_webhook->getSubscription($subscription_id);

			if ($subscription) {
				$this->model_setting_webhook->setStatus($subscription_id, $status);

				$this->session->data['success'] = sprintf($this->language->get($success_key), $subscription['name']);
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_subscription');
			}
		}

		$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));
	}

	private function getList(): void {
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 25;

		$filters = [
			'filter_name'   => isset($this->request->get['filter_name']) ? trim((string)$this->request->get['filter_name']) : '',
			'filter_url'    => isset($this->request->get['filter_url']) ? trim((string)$this->request->get['filter_url']) : '',
			'filter_event'  => isset($this->request->get['filter_event']) ? trim((string)$this->request->get['filter_event']) : '',
			'filter_status' => isset($this->request->get['filter_status']) && $this->request->get['filter_status'] !== '' ? (int)$this->request->get['filter_status'] : '',
			'start'         => ($page - 1) * $limit,
			'limit'         => $limit,
		];

		$data['subscriptions'] = [];

		foreach ($this->model_setting_webhook->getSubscriptions($filters) as $subscription) {
			$data['subscriptions'][] = [
				'subscription_id' => (int)$subscription['subscription_id'],
				'name'            => (string)$subscription['name'],
				'url'             => (string)$subscription['url'],
				'events'          => implode(', ', (array)$subscription['events_list']),
				'status'          => (int)$subscription['status'],
				'status_text'     => (int)$subscription['status'] === 1 ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'disabled_reason' => (string)$subscription['disabled_reason'],
				'disabled_text'   => $this->disabledReasonText((string)$subscription['disabled_reason']),
				'failures'        => (int)$subscription['consecutive_failures'],
				'pending'         => (int)$subscription['deliveries_pending'],
				'failed'          => (int)$subscription['deliveries_failed'],
				'last_success_at' => $this->formatDate((string)($subscription['last_success_at'] ?? '')),
				'last_failure_at' => $this->formatDate((string)($subscription['last_failure_at'] ?? '')),
				'edit'            => $this->url->link('setting/webhook/edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
				'log'             => $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
				'test'            => $this->url->link('setting/webhook/test', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
				'enable'          => $this->url->link('setting/webhook/enable', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
				'disable'         => $this->url->link('setting/webhook/disable', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
				'delete'          => $this->url->link('setting/webhook/delete', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$subscription['subscription_id'], true),
			];
		}

		$total = $this->model_setting_webhook->getTotalSubscriptions($filters);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'] . '&page={page}' . $this->filterQuery(), true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), $total ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit), $total, ceil($total / $limit));

		$data['filter_name'] = $filters['filter_name'];
		$data['filter_url'] = $filters['filter_url'];
		$data['filter_event'] = $filters['filter_event'];
		$data['filter_status'] = $filters['filter_status'];

		$data['total'] = $total;
		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/webhook');
		$data['kill_switch_on'] = !$this->config->has('config_webhook_status') || (int)$this->config->get('config_webhook_status') === 1;

		$data['config_webhook_status'] = $data['kill_switch_on'] ? 1 : 0;
		$data['config_webhook_timeout'] = $this->config->has('config_webhook_timeout') ? (int)$this->config->get('config_webhook_timeout') : 10;
		$data['config_webhook_max_attempts'] = $this->config->has('config_webhook_max_attempts') ? (int)$this->config->get('config_webhook_max_attempts') : 6;
		$data['config_webhook_disable_after'] = $this->config->has('config_webhook_disable_after') ? (int)$this->config->get('config_webhook_disable_after') : 4;
		$data['config_webhook_log_retention_days'] = $this->config->has('config_webhook_log_retention_days') ? (int)$this->config->get('config_webhook_log_retention_days') : 30;

		$data['events'] = $this->eventCatalogue();

		$data['add'] = $this->url->link('setting/webhook/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['filter'] = $this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true);
		$data['settings_action'] = $this->url->link('setting/webhook/settings', 'user_token=' . $this->session->data['user_token'], true);
		$data['log'] = $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'], true);
		$data['api_keys'] = $this->url->link('setting/api_key', 'user_token=' . $this->session->data['user_token'], true);
		$data['docs'] = $this->url->link('setting/api_docs', 'user_token=' . $this->session->data['user_token'], true);
		$data['api_base_url'] = rtrim((string)HTTP_CATALOG, '/') . '/api/v1';

		$data['success'] = (string)($this->session->data['success'] ?? '');

		unset($this->session->data['success']);

		$data['error_warning'] = (string)($this->session->data['error_warning'] ?? '');

		unset($this->session->data['error_warning']);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/webhook_list', $data));
	}

	private function getForm(): void {
		$subscription_id = isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0;
		$subscription = $subscription_id ? $this->model_setting_webhook->getSubscription($subscription_id) : [];

		if ($subscription_id && !$subscription) {
			$this->session->data['error_warning'] = $this->language->get('error_subscription');

			$this->response->redirect($this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$data['text_form'] = $subscription_id ? $this->language->get('text_edit') : $this->language->get('text_add');
		$data['subscription_id'] = $subscription_id;

		$data['error_warning'] = (string)($this->error['warning'] ?? '');
		$data['success'] = (string)($this->session->data['success'] ?? '');

		unset($this->session->data['success']);

		// Newly issued secret — shown once, never stored in plain text.
		if (!empty($this->session->data['webhook_new_secret']) && (int)($this->session->data['webhook_new_secret_id'] ?? 0) === $subscription_id) {
			$data['new_secret'] = (string)$this->session->data['webhook_new_secret'];

			unset($this->session->data['webhook_new_secret'], $this->session->data['webhook_new_secret_id']);
		} else {
			$data['new_secret'] = '';
		}

		$posted_events = isset($this->request->post['events']) ? (array)$this->request->post['events'] : null;

		if ($posted_events !== null) {
			$data['events_selected'] = Events::normalize($posted_events);
		} else {
			$data['events_selected'] = (array)($subscription['events_list'] ?? []);
		}

		$data['name'] = isset($this->request->post['name']) ? (string)$this->request->post['name'] : (string)($subscription['name'] ?? '');
		$data['url'] = isset($this->request->post['url']) ? (string)$this->request->post['url'] : (string)($subscription['url'] ?? '');

		if (isset($this->request->post['filters'])) {
			$data['filters'] = is_array($this->request->post['filters']) ? (string)json_encode($this->request->post['filters']) : (string)$this->request->post['filters'];
		} else {
			$data['filters'] = json_encode((array)($subscription['filters_array'] ?? []));
		}

		if ($data['filters'] === '[]' || $data['filters'] === '') {
			$data['filters'] = '{}';
		}

		$data['full_payload'] = isset($this->request->post['full_payload']) ? (int)!empty($this->request->post['full_payload']) : (int)($subscription['full_payload'] ?? 0);
		$data['status'] = isset($this->request->post['status']) ? (int)$this->request->post['status'] : (int)($subscription['status'] ?? 1);

		$data['events_catalogue'] = $this->eventCatalogue();
		$data['event_options'] = [];

		foreach ($data['events_catalogue'] as $event) {
			if (!$event['implemented']) {
				continue;
			}

			$data['event_options'][] = [
				'type'        => $event['type'],
				'description' => $event['description'],
				'selected'    => in_array($event['type'], $data['events_selected'], true),
			];
		}

		$data['planned_events'] = [];

		foreach ($data['events_catalogue'] as $event) {
			if ($event['implemented']) {
				continue;
			}

			$data['planned_events'][] = [
				'type'        => $event['type'],
				'description' => $event['description'],
			];
		}

		$data['secret_hint'] = (string)($subscription['secret_hint'] ?? '');
		$data['date_added'] = $this->formatDate((string)($subscription['date_added'] ?? ''));
		$data['last_success_at'] = $this->formatDate((string)($subscription['last_success_at'] ?? ''));
		$data['last_failure_at'] = $this->formatDate((string)($subscription['last_failure_at'] ?? ''));
		$data['consecutive_failures'] = (int)($subscription['consecutive_failures'] ?? 0);
		$data['disabled_reason'] = $this->disabledReasonText((string)($subscription['disabled_reason'] ?? ''));

		$data['error_name'] = (string)($this->error['name'] ?? '');
		$data['error_url'] = (string)($this->error['url'] ?? '');
		$data['error_events'] = (string)($this->error['events'] ?? '');
		$data['error_filters'] = (string)($this->error['filters'] ?? '');

		$data['action'] = $this->url->link('setting/webhook/' . ($subscription_id ? 'edit' : 'add'), 'user_token=' . $this->session->data['user_token'] . ($subscription_id ? '&subscription_id=' . $subscription_id : ''), true);
		$data['cancel'] = $this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true);
		$data['rotate'] = $subscription_id ? $this->url->link('setting/webhook/rotate', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true) : '';
		$data['test'] = $subscription_id ? $this->url->link('setting/webhook/test', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true) : '';
		$data['log'] = $subscription_id ? $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true) : $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'], true);
		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/webhook');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/webhook_form', $data));
	}

	private function validateForm(): bool {
		if (!$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$filters_raw = $this->request->post['filters'] ?? '';

		$errors = $this->model_setting_webhook->validate([
			'name'     => $this->request->post['name'] ?? '',
			'url'      => $this->request->post['url'] ?? '',
			'events'   => $this->request->post['events'] ?? [],
			'filters'  => $filters_raw,
		]);

		if (isset($errors['name'])) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (isset($errors['url'])) {
			$this->error['url'] = $this->language->get($this->urlErrorKey((string)$errors['url']));
		}

		if (isset($errors['events'])) {
			$this->error['events'] = strpos($errors['events'], 'unknown_event:') === 0
				? sprintf($this->language->get('error_events_unknown'), substr($errors['events'], strlen('unknown_event:')))
				: $this->language->get('error_events');
		}

		if (isset($errors['filters'])) {
			$this->error['filters'] = strpos($errors['filters'], 'unsupported_filter:') === 0
				? sprintf($this->language->get('error_filters_unsupported'), substr($errors['filters'], strlen('unsupported_filter:')))
				: $this->language->get('error_filters');
		}

		return !$this->error;
	}

	/**
	 * Event catalogue with descriptions translated into the operator's language
	 * (the English text in the library is the API contract, not panel copy).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function eventCatalogue(): array {
		return Events::catalogueTranslated(function (string $key): string {
			return (string)$this->language->get($key);
		});
	}

	private function urlErrorKey(string $code): string {
		$known = [
			'invalid_url',
			'invalid_scheme',
			'invalid_port',
			'invalid_host',
			'credentials_not_allowed',
			'ssrf_blocked',
		];

		if (in_array($code, $known, true)) {
			return 'error_url_' . $code;
		}

		return 'error_url';
	}

	private function disabledReasonText(string $reason): string {
		if ($reason === 'gone') {
			return $this->language->get('text_disabled_gone');
		}

		if ($reason === 'repeated_failures') {
			return $this->language->get('text_disabled_repeated');
		}

		return '';
	}

	private function formatDate(string $value): string {
		$value = trim($value);

		if ($value === '' || $value === '0000-00-00 00:00:00') {
			return '';
		}

		$timestamp = strtotime($value);

		return $timestamp === false ? '' : date($this->language->get('datetime_format'), $timestamp);
	}

	private function filterQuery(): string {
		$query = '';

		foreach (['filter_name', 'filter_url', 'filter_event'] as $key) {
			if (isset($this->request->get[$key]) && (string)$this->request->get[$key] !== '') {
				$query .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		if (isset($this->request->get['filter_status']) && $this->request->get['filter_status'] !== '') {
			$query .= '&filter_status=' . (int)$this->request->get['filter_status'];
		}

		return $query;
	}
}
