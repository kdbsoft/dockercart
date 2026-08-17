<?php
class ControllerStartupSession extends Controller {
	public function index() {
		if (isset($_COOKIE[$this->config->get('session_name')])) {
			$session_id = $_COOKIE[$this->config->get('session_name')];
		} else {
			$session_id = '';
		}

		$this->session->start($session_id);

		$secure = (!empty($this->request->server['HTTPS']) && ($this->request->server['HTTPS'] == 'on' || $this->request->server['HTTPS'] == '1')) || (isset($this->request->server['HTTP_X_FORWARDED_PROTO']) && $this->request->server['HTTP_X_FORWARDED_PROTO'] == 'https');

		setcookie($this->config->get('session_name'), $this->session->getId(), [
			'expires'  => ini_get('session.cookie_lifetime') ? time() + ini_get('session.cookie_lifetime') : 0,
			'path'     => ini_get('session.cookie_path'),
			'domain'   => ini_get('session.cookie_domain'),
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}
}
