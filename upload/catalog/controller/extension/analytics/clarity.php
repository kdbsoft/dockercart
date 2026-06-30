<?php
class ControllerExtensionAnalyticsClarity extends Controller {
	public function index() {
		return html_entity_decode($this->config->get('analytics_clarity_code'), ENT_QUOTES, 'UTF-8');
	}
}
