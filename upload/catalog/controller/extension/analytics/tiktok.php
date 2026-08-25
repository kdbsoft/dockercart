<?php
declare(strict_types=1);

class ControllerExtensionAnalyticsTiktok extends Controller {
	public function index() {
		return html_entity_decode($this->config->get('analytics_tiktok_code'), ENT_QUOTES, 'UTF-8');
	}
}
