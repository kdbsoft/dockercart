<?php
class ControllerExtensionAnalyticsFacebook extends Controller {
    public function index() {
		return html_entity_decode($this->config->get('analytics_facebook_code'), ENT_QUOTES, 'UTF-8');
	}
}
