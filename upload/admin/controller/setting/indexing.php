<?php
declare(strict_types=1);

class ControllerSettingIndexing extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('setting/indexing');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->save();
		}

		$this->getForm();
	}

	protected function getForm() {
		$data['text_form'] = $this->language->get('heading_title');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['action'] = $this->url->link('setting/indexing', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('setting/store', 'user_token=' . $this->session->data['user_token'], true);

		// Only two settings are owned by this page: a master on/off switch and
		// the robots.txt body. updateSetting() upserts just these keys, leaving
		// the rest of the `config` code untouched (see AGENTS.md save semantics).
		if (isset($this->request->post['config_indexing_status'])) {
			$data['config_indexing_status'] = (int)$this->request->post['config_indexing_status'];
		} elseif ($this->config->has('config_indexing_status')) {
			$data['config_indexing_status'] = (int)$this->config->get('config_indexing_status');
		} else {
			// Indexing is enabled by default when the setting was never saved.
			$data['config_indexing_status'] = 1;
		}

		if (isset($this->request->post['config_indexing_robots'])) {
			$data['config_indexing_robots'] = (string)$this->request->post['config_indexing_robots'];
		} elseif ($this->config->has('config_indexing_robots')) {
			$data['config_indexing_robots'] = (string)$this->config->get('config_indexing_robots');
		} else {
			$data['config_indexing_robots'] = '';
		}

		$default = $this->defaultRobotsTxt();

		// Never show an empty editor on first visit.
		if (trim($data['config_indexing_robots']) === '') {
			$data['config_indexing_robots'] = $default;
		}

		$data['default_robots'] = $default;

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('setting/indexing', $data));
	}

	protected function save() {
		$status = isset($this->request->post['config_indexing_status']) ? (int)$this->request->post['config_indexing_status'] : 0;
		$robots = isset($this->request->post['config_indexing_robots']) ? (string)$this->request->post['config_indexing_robots'] : '';

		$content = $this->renderRobotsTxt($status, $robots);

		if (!$this->writeRobotsTxt($content)) {
			$this->error['warning'] = $this->language->get('error_write');

			return;
		}

		$this->model_setting_setting->updateSetting('config', array(
			'config_indexing_status' => $status,
			'config_indexing_robots' => $robots,
		));

		$this->session->data['success'] = $this->language->get('text_success');

		$this->response->redirect($this->url->link('setting/indexing', 'user_token=' . $this->session->data['user_token'], true));
	}

	/**
	 * Build the final robots.txt contents from the admin settings.
	 *
	 * @param	int		$status
	 * @param	string	$robots
	 * @return	string
	 */
	protected function renderRobotsTxt($status, $robots) {
		if (!$status) {
			return "User-agent: *\nDisallow: /\n";
		}

		$robots = trim((string)$robots);

		if ($robots === '') {
			$robots = $this->defaultRobotsTxt();
		}

		return rtrim($robots) . "\n";
	}

	/**
	 * Default robots.txt template. Keep in sync with ensure_robots_txt() in
	 * docker/entrypoint.sh (which only seeds this on first boot).
	 *
	 * @return	string
	 */
	protected function defaultRobotsTxt() {
		$url = rtrim((string)$this->config->get('config_url'), '/');

		if ($url === '' && defined('HTTP_CATALOG')) {
			$url = rtrim((string)HTTP_CATALOG, '/');
		}

		$lines = array(
			'User-agent: *',
			'Allow: /catalog/view/javascript/',
			'Allow: /catalog/view/theme/',
			'Allow: /image/',
			'',
			'Disallow: /admin/',
			'Disallow: /system/',
			'Disallow: /storage/',
			'Disallow: /tool-',
			'Disallow: /*/tool-',
			'Disallow: /account-',
			'Disallow: /*/account-',
			'Disallow: /checkout-',
			'Disallow: /*/checkout-',
			'Disallow: /affiliate-',
			'Disallow: /*/affiliate-',
			'Disallow: /product-search',
			'Disallow: /*/product-search',
			'Disallow: /product-compare',
			'Disallow: /*/product-compare',
			'Disallow: /*?*sort=',
			'Disallow: /*?*order=',
			'Disallow: /*?*limit=',
			'Disallow: /*?*page=',
			'Disallow: /*?*tracking=',
			'Disallow: /*?*utm_',
			'',
			'Sitemap: ' . $url . '/sitemap.xml',
		);

		return implode("\n", $lines) . "\n";
	}

	/**
	 * Write robots.txt in place (same inode) so the host-side ownership
	 * (host user : staff group) set by the container entrypoint is preserved
	 * across admin saves. The file is only a few hundred bytes and is written
	 * under an exclusive lock, so a torn read is not a practical concern.
	 *
	 * @param	string	$content
	 * @return	bool
	 */
	protected function writeRobotsTxt($content) {
		$file = DIR_SYSTEM . '../robots.txt';

		if (is_file($file)) {
			if (!is_writable($file)) {
				return false;
			}
		} elseif (!is_writable(dirname($file))) {
			return false;
		}

		if (@file_put_contents($file, $content, LOCK_EX) === false) {
			return false;
		}

		@chmod($file, 0664);

		// Only relevant when the file had to be created: match the web root's
		// group so the host user and FTP keep group access. On an existing file
		// the group is already staff, and a non-owner chgrp is a no-op anyway.
		$dir_group = @filegroup(dirname($file));

		if ($dir_group !== false) {
			@chgrp($file, $dir_group);
		}

		return true;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'setting/indexing')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
