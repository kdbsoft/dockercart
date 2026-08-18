<?php
declare(strict_types=1);

class ControllerToolUpdate extends Controller {
	private function validateModify(): bool {
		return $this->user->hasPermission('modify', 'tool/update');
	}

	private function validateAccess(): bool {
		return $this->user->hasPermission('access', 'tool/update');
	}

	public function index(): void {
		$this->load->language('tool/update');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('tool/update');

		$this->error = [];

		if (!$this->validateAccess()) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validateModify()) {
			$remote = trim((string)($this->request->post['remote'] ?? ''));
			$branch = trim((string)($this->request->post['branch'] ?? ''));

			if ($remote === '' || $branch === '') {
				$this->error['warning'] = $this->language->get('error_required');
			} elseif (!preg_match('#^https://#i', $remote)) {
				$this->error['warning'] = $this->language->get('error_remote');
			} elseif (!preg_match('#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $branch)) {
				$this->error['warning'] = $this->language->get('error_branch');
			} else {
				$this->model_tool_update->saveConfig($remote, $branch);
				$this->session->data['success'] = $this->language->get('text_success');
				$this->response->redirect($this->url->link('tool/update', 'user_token=' . $this->session->data['user_token'], true));
				return;
			}
		}

		$config = $this->model_tool_update->getConfig();
		// Only serve cached data on page load; a network-backed, potentially
		// blocking check runs client-side via the AJAX 'check' action so the
		// admin page renders immediately instead of waiting on the remote repo.
		$info = $this->model_tool_update->getCachedUpdateInfo();

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_current_version'] = $this->language->get('text_current_version');
		$data['text_changelog'] = $this->language->get('text_changelog');
		$data['text_no_changelog'] = $this->language->get('text_no_changelog');
		$data['text_remote_version'] = $this->language->get('text_remote_version');
		$data['text_up_to_date'] = $this->language->get('text_up_to_date');
		$data['text_update_available'] = $this->language->get('text_update_available');
		$data['text_checking'] = $this->language->get('text_checking');
		$data['text_running'] = $this->language->get('text_running');
		$data['text_progress'] = $this->language->get('text_progress');
		$data['text_maintenance_on'] = $this->language->get('text_maintenance_on');
		$data['text_trust_warning'] = $this->language->get('text_trust_warning');
		$data['text_limitations'] = $this->language->get('text_limitations');
		$data['text_stale'] = $this->language->get('text_stale');
		$data['text_request_failed'] = $this->language->get('text_request_failed');
		$data['text_update_failed'] = $this->language->get('text_update_failed');
		$data['text_update_complete'] = $this->language->get('text_update_complete');
		$data['text_reconnecting'] = $this->language->get('text_reconnecting');
		$data['text_reload_hint'] = $this->language->get('text_reload_hint');
		$data['text_warnings'] = $this->language->get('text_warnings');

		$data['entry_remote'] = $this->language->get('entry_remote');
		$data['entry_branch'] = $this->language->get('entry_branch');
		$data['help_remote'] = $this->language->get('help_remote');
		$data['help_branch'] = $this->language->get('help_branch');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_check'] = $this->language->get('button_check');
		$data['button_update'] = $this->language->get('button_update');
		$data['button_reset'] = $this->language->get('button_reset');

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['success'] = $this->session->data['success'] ?? '';
		unset($this->session->data['success']);

		$data['local_version'] = $this->model_tool_update->getLocalVersion();
		$data['config_remote'] = $config['remote'];
		$data['config_branch'] = $config['branch'];

		$data['update_available'] = $info['update_available'];
		$data['remote_version'] = $info['remote'];
		$data['changelog'] = $info['changelog'];

		$data['user_token'] = $this->session->data['user_token'];
		$data['action'] = $this->url->link('tool/update', 'user_token=' . $this->session->data['user_token'], true);
		$data['check_url'] = $this->url->link('tool/update/check', 'user_token=' . $this->session->data['user_token'], true);
		$data['start_url'] = $this->url->link('tool/update/start', 'user_token=' . $this->session->data['user_token'], true);
		$data['status_url'] = $this->url->link('tool/update/status', 'user_token=' . $this->session->data['user_token'], true);
		$data['reset_url'] = $this->url->link('tool/update/reset', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/update', $data));
	}

	public function check(): void {
		$this->load->language('tool/update');
		$this->load->model('tool/update');

		$json = ['success' => false];

		if (!$this->validateAccess()) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$local = $this->model_tool_update->getLocalVersion();
			// Manual "Check" (force=1) hits the network for fresh data; page load
			// and auto-check serve the cached snapshot so views never block.
			$info = empty($this->request->get['force'])
				? $this->model_tool_update->getCachedUpdateInfo()
				: $this->model_tool_update->refreshUpdateInfo();

			if ($info['fetch_failed']) {
				$json['error'] = $this->language->get('error_fetch');
			} else {
				$json['success'] = true;
				$json['local_version'] = $local;
				$json['remote_version'] = $info['remote'];
				$json['update_available'] = $info['update_available'];
				$json['changelog'] = $info['changelog'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function start(): void {
		$this->load->language('tool/update');
		$this->load->model('tool/update');

		$json = ['success' => false];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_method');
		} elseif (!$this->validateModify()) {
			$json['error'] = $this->language->get('error_permission');
		} elseif ($this->model_tool_update->isRunning()) {
			$json['error'] = $this->language->get('error_running');
		} else {
			$config = $this->model_tool_update->getConfig();
			$remote = $config['remote'];
			$branch = $config['branch'];

			$script = DIR_APPLICATION . 'cli/dockercart_update.php';
			$log = rtrim(DIR_STORAGE, '/') . '/dockercart_update/worker.out';
			$statusFile = rtrim(DIR_STORAGE, '/') . '/dockercart_update/status.json';

			@mkdir(dirname($log), 0775, true);

			// Seed a "queued" status so the first poll does not read a stale
			// previous run's "done" state (which would trigger a page reload).
			file_put_contents($statusFile, json_encode([
				'step'       => 'queued',
				'percent'    => 0,
				'message'    => $this->language->get('text_running'),
				'log'        => [],
				'warning'    => [],
				'done'       => false,
				'error'      => null,
				'pid'        => null,
				'started_at' => date('c')
			]));

			$detach = trim((string)@shell_exec('command -v setsid')) !== '' ? 'setsid' : 'nohup';

			$cmd = $detach . ' php ' . escapeshellarg($script)
				. ' ' . escapeshellarg($remote)
				. ' ' . escapeshellarg($branch)
				. ' > ' . escapeshellarg($log) . ' 2>&1 < /dev/null &';

			exec($cmd);

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function status(): void {
		$this->load->language('tool/update');
		$this->load->model('tool/update');

		$json = ['success' => false];

		if (!$this->validateAccess()) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$json = $this->model_tool_update->getStatus();
			$json['success'] = true;
			$json['local_version'] = $this->model_tool_update->getLocalVersion();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Clears a dead (stale) update run: restores maintenance mode from the
	 * value saved by the worker, removes leftover status/tmp files so a new
	 * update can be started cleanly.
	 */
	public function reset(): void {
		$this->load->language('tool/update');
		$this->load->model('tool/update');

		$json = ['success' => false];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['error'] = $this->language->get('error_method');
		} elseif (!$this->validateModify()) {
			$json['error'] = $this->language->get('error_permission');
		} elseif ($this->model_tool_update->isRunning()) {
			$json['error'] = $this->language->get('error_running');
		} else {
			$this->model_tool_update->reset();
			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
