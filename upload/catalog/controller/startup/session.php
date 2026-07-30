<?php
class ControllerStartupSession extends Controller {
	public function index() {
		if (isset($_COOKIE[$this->config->get('session_name')])) {
			$session_id = $_COOKIE[$this->config->get('session_name')];
		} else {
			$session_id = '';
		}

		$this->session->start($session_id);

		setcookie($this->config->get('session_name'), $this->session->getId(), (ini_get('session.cookie_lifetime') ? time() + ini_get('session.cookie_lifetime') : 0), ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
	}
}
