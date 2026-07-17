<?php
class ControllerStartupError extends Controller {
	public function index() {
		$this->registry->set('log', new Log($this->config->get('config_error_filename')));
		
		error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
		set_error_handler(array($this, 'handler'));

		register_shutdown_function(function() {
			$last_error = error_get_last();

			if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
				if ($this->config->get('config_error_display')) {
					echo '<b>Fatal Error</b>: ' . $last_error['message'] . ' in <b>' . $last_error['file'] . '</b> on line <b>' . $last_error['line'] . '</b>';
				}

				if ($this->config->get('config_error_log')) {
					$this->log->write('PHP Fatal Error: ' . $last_error['message'] . ' in ' . $last_error['file'] . ' on line ' . $last_error['line']);
				}
			}
		});
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