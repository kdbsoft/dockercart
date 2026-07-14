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

	public function index(): void {
		$this->load->language('tool/dockercart_scheduler');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('tool/dockercart_scheduler');

		if (!$this->user->hasPermission('access', 'tool/dockercart_scheduler')) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/dockercart_scheduler', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['user_token'] = $this->session->data['user_token'];

		$data['tasks'] = $this->model_tool_dockercart_scheduler->getAllScheduledTasks();

		$data['schedule_labels'] = [
			''             => $this->language->get('text_cron_disabled'),
			'every_15m'    => $this->language->get('text_every_15m'),
			'every_30m'    => $this->language->get('text_every_30m'),
			'hourly'       => $this->language->get('text_hourly'),
			'every_6h'     => $this->language->get('text_every_6h'),
			'every_12h'    => $this->language->get('text_every_12h'),
			'daily'        => $this->language->get('text_daily'),
			'custom'       => $this->language->get('text_custom'),
		];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/dockercart_scheduler', $data));
	}

}
