<?php
/**
 * DockerCart admin — webhook delivery journal (System → API → Webhooks → Log).
 *
 * Read-only view on oc_dockercart_webhook_delivery with the filters an operator
 * needs after an integration breaks: subscription, event, status, event id and
 * date. Failed deliveries can be re-queued (same row, same event id), which the
 * scheduler worker picks up on its next run. The receivers' response body is
 * shown inline so a broken endpoint can be diagnosed without server access.
 *
 * @package    DockerCart
 * @subpackage Admin
 */

declare(strict_types=1);

class ControllerSettingWebhookLog extends Controller {
	public function index() {
		$this->load->language('setting/webhook_log');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/webhook');

		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 50;

		$filters = [
			'filter_subscription_id' => isset($this->request->get['subscription_id']) ? (int)$this->request->get['subscription_id'] : 0,
			'filter_event_type'      => isset($this->request->get['event_type']) ? trim((string)$this->request->get['event_type']) : '',
			'filter_status'          => isset($this->request->get['status']) ? trim((string)$this->request->get['status']) : '',
			'filter_event_id'        => isset($this->request->get['event_id']) ? trim((string)$this->request->get['event_id']) : '',
			'filter_date_from'       => isset($this->request->get['date_from']) ? trim((string)$this->request->get['date_from']) : '',
			'filter_date_to'         => isset($this->request->get['date_to']) ? trim((string)$this->request->get['date_to']) : '',
			'start'                  => ($page - 1) * $limit,
			'limit'                  => $limit,
		];

		$data['deliveries'] = [];

		foreach ($this->model_setting_webhook->getDeliveries($filters) as $delivery) {
			$data['deliveries'][] = [
				'delivery_id'       => (int)$delivery['delivery_id'],
				'subscription_id'   => (int)$delivery['subscription_id'],
				'subscription_name' => (string)($delivery['subscription_name'] ?? ''),
				'url'               => (string)($delivery['subscription_url'] ?? ''),
				'event_type'        => (string)$delivery['event_type'],
				'event_id'          => (string)$delivery['event_id'],
				'status'            => (string)$delivery['status'],
				'status_text'       => $this->statusText((string)$delivery['status']),
				'is_test'           => (int)$delivery['is_test'],
				'attempts'          => (int)$delivery['attempts'],
				'response_code'     => (int)$delivery['response_code'],
				'response_body'     => $this->truncate((string)($delivery['response_body'] ?? ''), 400),
				'last_error'        => (string)($delivery['last_error'] ?? ''),
				'duration_ms'       => (int)$delivery['duration_ms'],
				'date_added'        => $this->formatDate((string)$delivery['date_added']),
				'next_attempt_at'   => $this->formatDate((string)($delivery['next_attempt_at'] ?? '')),
				'payload'           => (string)$delivery['payload'],
				'view'              => $this->url->link('setting/webhook_log/info', 'user_token=' . $this->session->data['user_token'] . '&delivery_id=' . (int)$delivery['delivery_id'], true),
				'retry'             => $this->url->link('setting/webhook_log/retry', 'user_token=' . $this->session->data['user_token'] . '&delivery_id=' . (int)$delivery['delivery_id'], true),
			];
		}

		$total = $this->model_setting_webhook->getTotalDeliveries($filters);
		$summary = $this->model_setting_webhook->getDeliverySummary($filters);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'] . '&page={page}' . $this->filterQuery(), true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), $total ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit), $total, ceil($total / $limit));
		$data['summary'] = sprintf($this->language->get('text_summary'), $summary['total'], $summary['delivered'], $summary['failed'], $summary['pending']);

		$data['filter_subscription_id'] = $filters['filter_subscription_id'];
		$data['filter_event_type'] = $filters['filter_event_type'];
		$data['filter_status'] = $filters['filter_status'];
		$data['filter_event_id'] = $filters['filter_event_id'];
		$data['filter_date_from'] = $filters['filter_date_from'];
		$data['filter_date_to'] = $filters['filter_date_to'];

		$data['subscriptions'] = [];

		foreach ($this->model_setting_webhook->getSubscriptions() as $subscription) {
			$data['subscriptions'][] = [
				'subscription_id' => (int)$subscription['subscription_id'],
				'name'            => (string)$subscription['name'],
				'status'          => (int)$subscription['status'],
			];
		}

		$data['event_types'] = [];

		foreach ($this->model_setting_webhook->getDeliveries(['limit' => 200]) as $delivery) {
			$data['event_types'][(string)$delivery['event_type']] = true;
		}

		$data['event_types'] = array_keys($data['event_types']);

		sort($data['event_types']);

		$data['statuses'] = [
			'pending'   => $this->language->get('text_pending'),
			'sending'   => $this->language->get('text_sending'),
			'delivered' => $this->language->get('text_delivered'),
			'failed'    => $this->language->get('text_failed'),
			'cancelled' => $this->language->get('text_cancelled'),
		];

		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/webhook_log') || $this->user->hasPermission('modify', 'setting/webhook');

		$data['success'] = (string)($this->session->data['success'] ?? '');

		unset($this->session->data['success']);

		$data['error_warning'] = (string)($this->session->data['error_warning'] ?? '');

		unset($this->session->data['error_warning']);

		$data['filter'] = $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('setting/webhook', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/webhook_log', $data));
	}

	/**
	 * Single delivery: payload, receiver response and retry control.
	 */
	public function info() {
		$this->load->language('setting/webhook_log');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/webhook');

		$delivery_id = isset($this->request->get['delivery_id']) ? (int)$this->request->get['delivery_id'] : 0;
		$delivery = $this->model_setting_webhook->getDelivery($delivery_id);

		if (!$delivery) {
			$this->session->data['error_warning'] = $this->language->get('error_delivery');

			$this->response->redirect($this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'], true));

			return;
		}

		$payload = (string)$delivery['payload'];
		$decoded = json_decode($payload, true);

		$data['delivery'] = [
			'delivery_id'       => (int)$delivery['delivery_id'],
			'subscription_id'   => (int)$delivery['subscription_id'],
			'subscription_name' => (string)($delivery['subscription_name'] ?? ''),
			'url'               => (string)($delivery['subscription_url'] ?? ''),
			'subscription_status' => (int)($delivery['subscription_status'] ?? 0),
			'event_type'        => (string)$delivery['event_type'],
			'event_id'          => (string)$delivery['event_id'],
			'status'            => (string)$delivery['status'],
			'status_text'       => $this->statusText((string)$delivery['status']),
			'is_test'           => (int)$delivery['is_test'],
			'attempts'          => (int)$delivery['attempts'],
			'response_code'     => (int)$delivery['response_code'],
			'response_body'     => (string)($delivery['response_body'] ?? ''),
			'last_error'        => (string)($delivery['last_error'] ?? ''),
			'duration_ms'       => (int)$delivery['duration_ms'],
			'occurred_at'       => $this->formatUtcDate((string)$delivery['occurred_at']),
			'date_added'        => $this->formatDate((string)$delivery['date_added']),
			'next_attempt_at'   => $this->formatDate((string)($delivery['next_attempt_at'] ?? '')),
			'locked_until'      => $this->formatDate((string)($delivery['locked_until'] ?? '')),
			'payload_raw'       => $payload,
			'payload_pretty'    => $decoded === null ? $payload : (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
		];

		$data['can_edit'] = $this->user->hasPermission('modify', 'setting/webhook_log') || $this->user->hasPermission('modify', 'setting/webhook');
		$data['can_retry'] = $data['can_edit'] && (string)$delivery['status'] === 'failed';
		$data['retry'] = $this->url->link('setting/webhook_log/retry', 'user_token=' . $this->session->data['user_token'] . '&delivery_id=' . (int)$delivery['delivery_id'], true);
		$data['back'] = $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$delivery['subscription_id'], true);
		$data['subscription_link'] = $this->url->link('setting/webhook/edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . (int)$delivery['subscription_id'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['success'] = (string)($this->session->data['success'] ?? '');

		unset($this->session->data['success']);

		$data['error_warning'] = (string)($this->session->data['error_warning'] ?? '');

		unset($this->session->data['error_warning']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/webhook_log_info', $data));
	}

	/**
	 * Re-queues a failed delivery (same row and event id).
	 */
	public function retry() {
		$this->load->language('setting/webhook_log');

		if (!$this->user->hasPermission('modify', 'setting/webhook_log') && !$this->user->hasPermission('modify', 'setting/webhook')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->load->model('setting/webhook');

		$delivery_id = isset($this->request->get['delivery_id']) ? (int)$this->request->get['delivery_id'] : 0;
		$delivery = $this->model_setting_webhook->getDelivery($delivery_id);

		if (!$delivery) {
			$this->session->data['error_warning'] = $this->language->get('error_delivery');
		} elseif ((string)$delivery['status'] !== 'failed') {
			$this->session->data['error_warning'] = $this->language->get('error_not_retryable');
		} elseif ($this->model_setting_webhook->retryDelivery($delivery_id)) {
			$this->session->data['success'] = $this->language->get('text_retry_success');
		} else {
			$this->session->data['error_warning'] = $this->language->get('error_not_retryable');
		}

		$back = $this->url->link('setting/webhook_log', 'user_token=' . $this->session->data['user_token'] . (!empty($delivery['subscription_id']) ? '&subscription_id=' . (int)$delivery['subscription_id'] : ''), true);

		$this->response->redirect($back);
	}

	private function statusText(string $status): string {
		$key = 'text_' . $status;

		$text = $this->language->get($key);

		return $text !== $key ? $text : $status;
	}

	private function truncate(string $value, int $limit): string {
		if (mb_strlen($value) <= $limit) {
			return $value;
		}

		return mb_substr($value, 0, $limit) . '…';
	}

	/**
	 * occurred_at is stored in UTC (the signed payload carries a UTC instant), so
	 * it is rendered in the operator's time zone instead of being read as local.
	 */
	private function formatUtcDate(string $value): string {
		$value = trim($value);

		if ($value === '' || $value === '0000-00-00 00:00:00') {
			return '';
		}

		$timestamp = strtotime($value . ' UTC');

		return $timestamp === false ? '' : date($this->language->get('datetime_format'), $timestamp);
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

		foreach (['event_type', 'status', 'event_id', 'date_from', 'date_to'] as $key) {
			if (isset($this->request->get[$key]) && (string)$this->request->get[$key] !== '') {
				$query .= '&' . $key . '=' . urlencode((string)$this->request->get[$key]);
			}
		}

		if (isset($this->request->get['subscription_id']) && (int)$this->request->get['subscription_id'] > 0) {
			$query .= '&subscription_id=' . (int)$this->request->get['subscription_id'];
		}

		return $query;
	}
}
