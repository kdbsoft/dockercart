<?php
declare(strict_types=1);

class ControllerToolDockercartScheduler extends Controller {
	public function toggleTask(): void {
		$this->load->language('tool/dockercart_scheduler');
		$this->load->model('tool/dockercart_scheduler');

		$json = ['success' => false, 'error' => ''];

		$taskId  = isset($this->request->post['task_id']) ? (int)$this->request->post['task_id'] : 0;
		$enabled = isset($this->request->post['enabled']) ? (int)$this->request->post['enabled'] : -1;

		if ($taskId <= 0 || !in_array($enabled, [0, 1], true)) {
			$json['error'] = 'Missing task_id or enabled';
		} else {
			$result = $this->model_tool_dockercart_scheduler->toggleTask($taskId, $enabled);
			$json = $result ? ['success' => true] : ['success' => false, 'error' => 'Toggle failed'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateSchedule(): void {
		$this->load->language('tool/dockercart_scheduler');
		$this->load->model('tool/dockercart_scheduler');

		$json = ['success' => false, 'error' => ''];

		$taskId   = isset($this->request->post['task_id']) ? (int)$this->request->post['task_id'] : 0;
		$schedule = isset($this->request->post['schedule']) ? (string)$this->request->post['schedule'] : null;

		if ($taskId <= 0 || $schedule === null) {
			$json['error'] = 'Missing task_id or schedule';
		} else {
			$result = $this->model_tool_dockercart_scheduler->updateSchedule($taskId, $schedule);
			$json = $result ? ['success' => true] : ['success' => false, 'error' => 'Update failed'];
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function updateName(): void {
		$this->load->language('tool/dockercart_scheduler');
		$this->load->model('tool/dockercart_scheduler');

		if (!$this->user->hasPermission('modify', 'tool/dockercart_scheduler')) {
			$json = ['success' => false, 'error' => $this->language->get('error_permission')];
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$json = ['success' => false, 'error' => ''];

		$taskId      = isset($this->request->post['task_id']) ? (int)$this->request->post['task_id'] : 0;
		$translations = isset($this->request->post['translations']) && is_array($this->request->post['translations'])
			? $this->request->post['translations'] : [];

		if ($taskId <= 0 || !$translations) {
			$json['error'] = 'Missing task_id or translations';
		} else {
			$task = $this->model_tool_dockercart_scheduler->getTask($taskId);

			if ($task === null) {
				$json['error'] = 'Task not found';
			} elseif (!empty($task['is_system'])) {
				$json['error'] = 'System tasks cannot be renamed';
			} else {
				$result = $this->model_tool_dockercart_scheduler->saveTaskNames($taskId, $translations);
				$json = $result ? ['success' => true] : ['success' => false, 'error' => 'Update failed'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function index(): void {
		$this->load->language('tool/dockercart_scheduler');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('tool/dockercart_scheduler');

		if (!$this->user->hasPermission('access', 'tool/dockercart_scheduler')) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();

		$currentLanguageCode = isset($this->session->data['language']) ? $this->session->data['language'] : $this->config->get('config_admin_language');
		$currentLanguageId = 0;

		foreach ($languages as $language) {
			if ($language['code'] === $currentLanguageCode) {
				$currentLanguageId = (int)$language['language_id'];
				break;
			}
		}

		$tasks = $this->model_tool_dockercart_scheduler->getAllScheduledTasks();

		foreach ($tasks as &$task) {
			$translations = $this->model_tool_dockercart_scheduler->getTaskNames((int)$task['task_id']);
			$task['translations'] = $translations;
			$task['task_name'] = isset($translations[$currentLanguageId]) && $translations[$currentLanguageId] !== ''
				? $translations[$currentLanguageId]
				: $task['task_name_fallback'];
		}

		unset($task);

		$data['tasks'] = $tasks;

		$data['languages'] = [];

		foreach ($languages as $language) {
			$data['languages'][] = [
				'language_id' => $language['language_id'],
				'name'        => $language['name'],
				'code'        => $language['code'],
			];
		}

		$data['schedule_labels'] = [
			''             => $this->language->get('text_cron_disabled'),
			'every_15m'    => $this->language->get('text_every_15m'),
			'every_30m'    => $this->language->get('text_every_30m'),
			'hourly'       => $this->language->get('text_hourly'),
			'every_6h'     => $this->language->get('text_every_6h'),
			'every_12h'    => $this->language->get('text_every_12h'),
			'daily'        => $this->language->get('text_daily'),
			'weekly'       => $this->language->get('text_every_week'),
			'monthly'      => $this->language->get('text_every_month'),
			'custom'       => $this->language->get('text_custom'),
		];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/dockercart_scheduler', $data));
	}

}
