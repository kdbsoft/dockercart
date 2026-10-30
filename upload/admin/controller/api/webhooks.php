<?php
/**
 * DockerCart admin REST API — webhooks.
 *
 *   GET    /v1/webhooks                              list (filters + pagination)
 *   POST   /v1/webhooks                              create (secret returned once)
 *   GET    /v1/webhooks/events                       event catalogue
 *   GET    /v1/webhooks/{id}                         single subscription
 *   PUT    /v1/webhooks/{id}                         full replace
 *   PATCH  /v1/webhooks/{id}                         partial update
 *   DELETE /v1/webhooks/{id}                         delete (+ its journal)
 *   POST   /v1/webhooks/{id}/rotate                  new secret (returned once)
 *   POST   /v1/webhooks/{id}/test                    queue a webhook.test event
 *   GET    /v1/webhooks/{id}/deliveries              delivery journal of one subscription
 *   GET    /v1/webhook-deliveries/{id}               one delivery (payload + response)
 *   POST   /v1/webhook-deliveries/{id}/retry          re-queue a failed delivery
 *
 * The "webhooks:read|write" scope of an API key is expressed with the panel's
 * permission model: reads need the group's "access" permission on
 * setting/webhook, writes its "modify" permission. Nothing is stored twice.
 *
 * The HMAC secret is encrypted at rest and only ever returned by create and
 * rotate. Deliveries are never sent from a request: the event is queued and the
 * scheduler worker delivers it (202 Accepted).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;
use Dockercart\Webhook\Events;

class ControllerApiWebhooks extends \Dockercart\Api\Controller {
	/**
	 * GET /v1/webhooks
	 */
	public function index() {
		$this->load->model('setting/webhook');

		$pagination = $this->pagination();

		$filters = [
			'filter_name'   => $this->queryString('q'),
			'filter_url'    => $this->queryString('url'),
			'filter_event'  => $this->queryString('event'),
			'start'         => $pagination['start'],
			'limit'         => $pagination['limit'],
		];

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$rows = [];

		foreach ($this->model_setting_webhook->getSubscriptions($filters) as $subscription) {
			$rows[] = $this->subscriptionResponse($subscription);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $this->model_setting_webhook->getTotalSubscriptions($filters)));
	}

	/**
	 * GET /v1/webhooks/events — the catalogue (implemented + planned).
	 */
	public function events() {
		$rows = [];

		foreach (Events::catalogue() as $event) {
			$rows[] = [
				'type'          => $event['type'],
				'domain'        => $event['domain'],
				'description'   => $event['description'],
				'implemented'   => $event['implemented'],
				'data_fields'   => $event['data_fields'],
				'filter_fields' => $event['filter_fields'],
			];
		}

		$this->respondCollection($rows, $this->meta(1, count($rows), count($rows)));
	}

	/**
	 * GET /v1/webhooks/{webhook_id}
	 */
	public function get() {
		$this->respondResource($this->subscriptionResponse($this->requireSubscription($this->param('webhook_id'))));
	}

	/**
	 * POST /v1/webhooks — the plain secret is returned exactly once.
	 */
	public function add() {
		$this->load->model('setting/webhook');

		$data = $this->model_setting_webhook->normalizeData($this->subscriptionInput($this->body(), []));

		$this->assertValid($data);

		$data['created_by_api_key_id'] = $this->user instanceof \Dockercart\Api\Principal ? (int)$this->user->getApiKeyId() : 0;
		$data['created_by_user_id'] = (int)$this->user->getId();

		$created = $this->model_setting_webhook->addSubscription($data);

		$response = $this->subscriptionResponse($this->requireSubscription((int)$created['subscription_id']));
		$response['secret'] = $created['secret'];

		$this->context()->etag = $this->etagFor($response);

		$this->respondCreated($response, '/api/v1/webhooks/' . (int)$created['subscription_id']);
	}

	/**
	 * PUT /v1/webhooks/{webhook_id} — full replace.
	 */
	public function edit() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');
		$current = $this->requireSubscription($subscription_id);

		$this->assertPreconditions($this->subscriptionResponse($current));

		$data = $this->model_setting_webhook->normalizeData($this->subscriptionInput($this->body(), $current));

		$this->assertValid($data);

		$this->model_setting_webhook->editSubscription($subscription_id, $data);

		$this->respondResource($this->subscriptionResponse($this->requireSubscription($subscription_id)));
	}

	/**
	 * PATCH /v1/webhooks/{webhook_id} — merge into the current document.
	 */
	public function patch() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');
		$current = $this->requireSubscription($subscription_id);

		$this->assertPreconditions($this->subscriptionResponse($current));

		$body = $this->body();

		if (!$body) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = $this->subscriptionInput($body, $current);

		$data = $this->model_setting_webhook->normalizeData($merged);

		$this->assertValid($data);

		$this->model_setting_webhook->editSubscription($subscription_id, $data);

		$this->respondResource($this->subscriptionResponse($this->requireSubscription($subscription_id)));
	}

	/**
	 * DELETE /v1/webhooks/{webhook_id} — removes the subscription and its journal.
	 */
	public function delete() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');

		$this->assertPreconditions($this->subscriptionResponse($this->requireSubscription($subscription_id)));

		$this->model_setting_webhook->deleteSubscription($subscription_id);

		$this->respondNoContent();
	}

	/**
	 * POST /v1/webhooks/{webhook_id}/rotate — new secret, returned once.
	 */
	public function rotate() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');

		$this->requireSubscription($subscription_id);

		$secret = $this->model_setting_webhook->rotateSecret($subscription_id);

		$response = $this->subscriptionResponse($this->requireSubscription($subscription_id));
		$response['secret'] = $secret;

		$this->context()->etag = $this->etagFor($response);

		$this->respond($response, 201);
	}

	/**
	 * POST /v1/webhooks/{webhook_id}/test — queues a synthetic webhook.test.
	 */
	public function test() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');
		$subscription = $this->requireSubscription($subscription_id);

		if ((int)$subscription['status'] !== 1) {
			throw new HttpException(Error::CONFLICT, ['status' => ['the subscription is disabled; enable it before sending a test event']]);
		}

		$message = trim((string)$this->input('message', 'API test event'));

		$queued = $this->model_setting_webhook->enqueueTest($subscription_id, $message);

		if ($queued === null) {
			throw new HttpException(Error::CONFLICT);
		}

		$this->respond([
			'delivery_id' => $queued['delivery_id'],
			'event_id'    => $queued['event_id'],
			'event_type'  => 'webhook.test',
			'status'      => 'pending',
		], 202);
	}

	/**
	 * GET /v1/webhooks/{webhook_id}/deliveries
	 */
	public function deliveries() {
		$this->load->model('setting/webhook');

		$subscription_id = $this->param('webhook_id');

		$this->requireSubscription($subscription_id);

		$pagination = $this->pagination();

		$filters = [
			'filter_subscription_id' => $subscription_id,
			'filter_event_type'      => $this->queryString('event_type'),
			'filter_status'          => $this->queryString('status'),
			'filter_event_id'        => $this->queryString('event_id'),
			'filter_date_from'       => $this->queryString('date_from'),
			'filter_date_to'         => $this->queryString('date_to'),
			'start'                  => $pagination['start'],
			'limit'                  => $pagination['limit'],
		];

		$rows = [];

		foreach ($this->model_setting_webhook->getDeliveries($filters) as $delivery) {
			$rows[] = $this->deliveryResponse($delivery, false);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $this->model_setting_webhook->getTotalDeliveries($filters)));
	}

	/**
	 * GET /v1/webhook-deliveries/{delivery_id}
	 */
	public function delivery() {
		$delivery = $this->requireDelivery($this->param('delivery_id'));

		$this->respondResource($this->deliveryResponse($delivery, true));
	}

	/**
	 * POST /v1/webhook-deliveries/{delivery_id}/retry — only failed rows.
	 */
	public function retry() {
		$this->load->model('setting/webhook');

		$delivery = $this->requireDelivery($this->param('delivery_id'));

		if ($delivery['status'] !== 'failed') {
			throw new HttpException(Error::CONFLICT, ['status' => ['only failed deliveries can be retried']]);
		}

		if ((int)($delivery['subscription_status'] ?? 0) !== 1) {
			throw new HttpException(Error::CONFLICT, ['status' => ['the subscription is disabled; enable it before retrying']]);
		}

		if (!$this->model_setting_webhook->retryDelivery((int)$delivery['delivery_id'])) {
			throw new HttpException(Error::CONFLICT);
		}

		$this->respond([
			'delivery_id' => (int)$delivery['delivery_id'],
			'event_id'    => (string)$delivery['event_id'],
			'status'      => 'pending',
		], 202);
	}

	/**
	 * Merges the request body over the current subscription (PUT semantics keep
	 * all fields required; PATCH only sends what changed).
	 *
	 * @param array $body
	 * @param array $current existing subscription (empty on create)
	 * @return array name, url, events, filters, full_payload, status
	 */
	private function subscriptionInput(array $body, array $current): array {
		$data = [
			// The name is rendered by the panel list and form, so it is escaped on
			// write like Request::clean() does for the panel form (an omitted field
			// keeps its stored, already escaped value).
			'name'         => array_key_exists('name', $body) ? Serializer::encodeTextWithin('name', (string)$body['name'], 128) : (string)($current['name'] ?? ''),
			'url'          => array_key_exists('url', $body) ? (string)$body['url'] : (string)($current['url'] ?? ''),
			'events'       => array_key_exists('events', $body) ? (array)$body['events'] : (array)($current['events_list'] ?? []),
			'filters'      => array_key_exists('filters', $body) ? $body['filters'] : (array)($current['filters_array'] ?? []),
			'full_payload' => array_key_exists('full_payload', $body) ? (int)!empty($body['full_payload']) : (int)($current['full_payload'] ?? 0),
			'status'       => array_key_exists('status', $body) ? (int)!empty($body['status']) : (int)($current['status'] ?? 1),
		];

		if (!is_array($data['events'])) {
			$data['events'] = [];
		}

		// A plain string is accepted for convenience (single event).
		if (isset($body['events']) && is_string($body['events'])) {
			$data['events'] = [$body['events']];
		}

		if (isset($body['filters']) && is_string($body['filters'])) {
			$decoded = json_decode($body['filters'], true);

			$data['filters'] = is_array($decoded) ? $decoded : $body['filters'];
		}

		return $data;
	}

	private function assertValid(array $data): void {
		$this->load->model('setting/webhook');

		$errors = $this->model_setting_webhook->validate($data);

		if (!$errors) {
			return;
		}

		// The API reports per-field message lists (RFC 9457 `fields`), the model
		// returns one machine code per field.
		$fields = [];

		foreach ($errors as $field => $message) {
			$fields[(string)$field] = [(string)$message];
		}

		throw new HttpException(Error::VALIDATION_FAILED, $fields);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function requireSubscription(int $subscription_id): array {
		$this->load->model('setting/webhook');

		$subscription = $this->model_setting_webhook->getSubscription($subscription_id);

		if (!$subscription) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $subscription;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function requireDelivery(int $delivery_id): array {
		$this->load->model('setting/webhook');

		$delivery = $this->model_setting_webhook->getDelivery($delivery_id);

		if (!$delivery) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $delivery;
	}

	/**
	 * API representation of a subscription — never contains the plain secret.
	 *
	 * @param array<string, mixed> $subscription
	 * @return array<string, mixed>
	 */
	private function subscriptionResponse(array $subscription): array {
		return [
			'webhook_id'      => (int)$subscription['subscription_id'],
			'name'            => Serializer::decodeText($subscription['name'] ?? ''),
			'url'             => (string)$subscription['url'],
			'events'          => array_values((array)$subscription['events_list']),
			'filters'         => (object)(array)$subscription['filters_array'],
			'full_payload'    => (bool)(int)$subscription['full_payload'],
			'status'          => (int)$subscription['status'],
			'enabled'         => (int)$subscription['status'] === 1,
			'secret_hint'     => (string)$subscription['secret_hint'],
			'consecutive_failures' => (int)$subscription['consecutive_failures'],
			'disabled_reason' => (string)$subscription['disabled_reason'] === '' ? null : (string)$subscription['disabled_reason'],
			'disabled_at'     => Serializer::datetime($subscription['disabled_at'] ?? ''),
			'last_success_at' => Serializer::datetime($subscription['last_success_at'] ?? ''),
			'last_failure_at' => Serializer::datetime($subscription['last_failure_at'] ?? ''),
			'deliveries'      => [
				'total'   => (int)($subscription['deliveries_total'] ?? 0),
				'pending' => (int)($subscription['deliveries_pending'] ?? 0),
				'failed'  => (int)($subscription['deliveries_failed'] ?? 0),
			],
			'created_by'      => [
				'api_key_id' => (int)($subscription['created_by_api_key_id'] ?? 0),
				'user_id'    => (int)($subscription['created_by_user_id'] ?? 0),
			],
			'date_added'      => Serializer::datetime($subscription['date_added'] ?? ''),
			'date_modified'   => Serializer::datetime($subscription['date_modified'] ?? ''),
		];
	}

	/**
	 * `occurred_at` is written in UTC (the payload carries an ISO-8601 `Z`
	 * instant), while every other column uses the store clock — so it must not be
	 * formatted through Serializer::datetime(), which assumes local time.
	 */
	private function utcDatetime($value): ?string {
		$value = trim((string)$value);

		if ($value === '' || $value === '0000-00-00 00:00:00') {
			return null;
		}

		$timestamp = strtotime($value . ' UTC');

		return $timestamp === false ? null : gmdate('c', $timestamp);
	}

	/**
	 * @param array<string, mixed> $delivery
	 * @return array<string, mixed>
	 */
	private function deliveryResponse(array $delivery, bool $with_payload): array {
		$payload = json_decode((string)$delivery['payload'], true);

		$response = [
			'delivery_id'     => (int)$delivery['delivery_id'],
			'webhook_id'      => (int)$delivery['subscription_id'],
			'event_type'      => (string)$delivery['event_type'],
			'event_id'        => (string)$delivery['event_id'],
			'status'          => (string)$delivery['status'],
			'is_test'         => (bool)(int)$delivery['is_test'],
			'attempts'        => (int)$delivery['attempts'],
			'response_code'   => (int)$delivery['response_code'] === 0 ? null : (int)$delivery['response_code'],
			'last_error'      => (string)$delivery['last_error'] === '' ? null : (string)$delivery['last_error'],
			'duration_ms'     => (int)$delivery['duration_ms'],
			'occurred_at'     => $this->utcDatetime($delivery['occurred_at'] ?? ''),
			'next_attempt_at' => Serializer::datetime($delivery['next_attempt_at'] ?? ''),
			'date_added'      => Serializer::datetime($delivery['date_added'] ?? ''),
		];

		if ($with_payload) {
			$response['payload'] = is_array($payload) ? $payload : (object)[];
			$response['webhook'] = [
				'webhook_id' => (int)$delivery['subscription_id'],
				'name'       => (string)($delivery['subscription_name'] ?? ''),
				'url'        => (string)($delivery['subscription_url'] ?? ''),
				'status'     => (int)($delivery['subscription_status'] ?? 0),
			];
			$response['response_body'] = (string)($delivery['response_body'] ?? '') === '' ? null : (string)$delivery['response_body'];
		}

		return $response;
	}
}
