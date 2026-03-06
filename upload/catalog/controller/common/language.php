<?php
class ControllerCommonLanguage extends Controller {
	public function index() {
		$this->load->language('common/language');

		$data['action'] = $this->url->link('common/language/language', '', $this->request->server['HTTPS']);

		$data['code'] = $this->session->data['language'];

		$this->load->model('localisation/language');

		$data['languages'] = array();

		$results = $this->model_localisation_language->getLanguages();

		// Get current route and parameters for language switching
		$current_route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
		$current_params = $this->request->get;
		
		// Remove system parameters
		unset($current_params['_route_']);
		unset($current_params['route']);

		foreach ($results as $result) {
			if ($result['status']) {
				// Generate URL for this language version of current page
				$language_url = $this->getLanguageUrl($result['code'], $current_route, $current_params);
				
				// Add switch_lang parameter to trigger language update in startup.php
				if (strpos($language_url, '?') !== false) {
					$language_url .= '&switch_lang=' . $result['code'];
				} else {
					$language_url .= '?switch_lang=' . $result['code'];
				}
				
				$data['languages'][] = array(
					'name' => $result['name'],
					'code' => $result['code'],
					'url' => $language_url
				);
			}
		}

		if (!isset($this->request->get['route'])) {
			$data['redirect'] = '/';
		} else {
			$url_data = $this->request->get;

			unset($url_data['_route_']);

			$route = $url_data['route'];

			unset($url_data['route']);

			$url = '';

			if ($url_data) {
				$url = '&' . urldecode(http_build_query($url_data, '', '&'));
			}

			$data['redirect'] = $this->url->link($route, $url, $this->request->server['HTTPS']);
		}

		return $this->load->view('common/language', $data);
	}

	public function language() {
		if (isset($this->request->post['code'])) {
			// Set the session language code (store lowercase form)
			$language_code = strtolower($this->request->post['code']);
			
			// Load language model
			$this->load->model('localisation/language');
			$languages = $this->model_localisation_language->getLanguages();
			
			// Check if language is valid and active
			if (isset($languages[$language_code]) && $languages[$language_code]['status']) {
				// Update session
				$this->session->data['language'] = $language_code;
				
				// Update cookie
				setcookie('language', $language_code, time() + 60 * 60 * 24 * 30, '/');
				
				// Update config
				$this->config->set('config_language_id', $languages[$language_code]['language_id']);
				
				// Reload language object
				$language_obj = new Language($language_code);
				$language_obj->load($language_code);
				$this->registry->set('language', $language_obj);
			}
		}

		if (isset($this->request->post['redirect']) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) === 0 || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) === 0)) {
			$this->response->redirect($this->request->post['redirect']);
		} else {
			$this->response->redirect($this->url->link('common/home'));
		}
	}

	/**
	 * Generate URL for specific language version of current page
	 * 
	 * @param string $language_code Language code (e.g., 'en-gb', 'uk-ua')
	 * @param string $route Current route
	 * @param array $params Current URL parameters
	 * @return string Full URL with language prefix
	 */
	private function getLanguageUrl($language_code, $route, $params) {
		$default_language = $this->config->get('config_language');
		
		// Save current language
		$current_language = isset($this->session->data['language']) ? $this->session->data['language'] : $default_language;
		$current_language_id = $this->config->get('config_language_id');
		
		// Get language data
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		
		if (!isset($languages[$language_code])) {
			return $this->config->get('config_url');
		}
		
		// Temporarily set the target language to generate correct URL
		$this->session->data['language'] = $language_code;
		$this->config->set('config_language_id', $languages[$language_code]['language_id']);
		
		// Build URL parameters
		$url_params = '';
		if (!empty($params)) {
			$url_params = '&' . urldecode(http_build_query($params, '', '&'));
		}
		
		// Generate URL - use link method which will add language prefix
		$url = $this->url->link($route, $url_params, $this->request->server['HTTPS']);
		
		// Restore original state
		$this->session->data['language'] = $current_language;
		$this->config->set('config_language_id', $current_language_id);
		
		return $url;
	}
}