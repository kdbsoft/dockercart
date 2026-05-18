<?php
class ControllerStartupError extends Controller {
	public function index() {
		$this->registry->set('log', new Log($this->config->get('config_error_filename')));
		
		error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
		set_error_handler(array($this, 'handler'));	
	}
	
	public function handler($code, $message, $file, $line) {
		// error suppressed with @
		if (!(error_reporting() & $code)) {
			return false;
		}
	
		switch ($code) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$error = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$error = 'Warning';
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return false;
			case E_ERROR:
			case E_USER_ERROR:
				$error = 'Fatal Error';
				break;
			default:
				$error = 'Unknown';
				break;
		}
	
		if ($this->config->get('config_error_display')) {
			echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
		}
	
		if ($this->config->get('config_error_log') && in_array($code, [E_WARNING, E_USER_WARNING, E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
			$this->log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
		}
	
		return true;
	} 
} 