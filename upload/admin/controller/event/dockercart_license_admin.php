<?php

declare(strict_types=1);

class ControllerEventDockercartLicenseAdmin extends Controller {

	public function index(&$route, &$data) {
		$module_code = $this->extractModuleCode($route);

		if (!$module_code) {
			return null;
		}

		if (!is_file(DIR_SYSTEM . 'library/dockercart/licensing.php')) {
			return null;
		}

		require_once DIR_SYSTEM . 'library/dockercart/licensing.php';

		$licensing = new DockercartLicensing($this->registry);

		$license = $licensing->getLicense($module_code);
		$meta = $this->getMeta($module_code);

		$needs_license = false;

		if ($license) {
			if ($licensing->check($module_code)) {
				return null;
			}

			$needs_license = true;
		} elseif ($meta && !empty($meta['license_type']) && $meta['license_type'] === 'Proprietary') {
			$needs_license = true;
		}

		if (!$needs_license) {
			return null;
		}

		$this->load->language('extension/dockercart_about');

		$is_ajax = !empty($this->request->server['HTTP_X_REQUESTED_WITH'])
			&& strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

		if ($is_ajax) {
			$this->response->addHeader('Content-Type: application/json');

			return json_encode(array('error' => $this->language->get('error_license_required')));
		}

		$this->session->data['error'] = $this->language->get('error_license_required');
		$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));

		return null;
	}

	private function getMeta(string $module_code): ?array {
		$query = $this->db->query(
			"SELECT `license_type` FROM `" . DB_PREFIX . "dockercart_extension_meta`
			 WHERE `code` = '" . $this->db->escape($module_code) . "'"
		);

		return $query->num_rows ? $query->row : null;
	}

	private function extractModuleCode(string $route): ?string {
		if (strpos($route, 'extension/') !== 0) {
			return null;
		}

		$parts = explode('/', $route);

		return $parts[2] ?? null;
	}
}
