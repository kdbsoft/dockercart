<?php
class ControllerExtensionCurrencyEcb extends Controller {

	public function refresh() {
		// This function can be called as a CRON task

		if (!$this->config->get('currency_ecb_status')) {
			return false;
		}

		$config_currency_engine = $this->config->get('config_currency_engine');

		if (!$config_currency_engine) {
			return false;
		}

		if ($config_currency_engine != 'ecb') {
			return false;
		}

		$this->load->model('extension/currency/ecb');
		$this->model_extension_currency_ecb->refresh();

		return true;
	}
}

