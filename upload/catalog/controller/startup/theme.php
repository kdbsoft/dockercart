<?php
declare(strict_types=1);

class ControllerStartupTheme extends Controller {
	public function index(): void {
		if ($this->config->get('config_theme')) {
			$directory = $this->config->get('theme_' . $this->config->get('config_theme') . '_directory');

			if ($directory) {
				$this->config->set('template_directory', $directory . '/template/');
			}
		}
	}
}
