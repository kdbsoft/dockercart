<?php
/**
 * DockerCart Multilanguage Startup Controller
 * Automatically adds hreflang tags to all pages for SEO
 *
 * @author Mykyta Tkachenko
 * @license MIT
 */
class ControllerStartupMultilanguage extends Controller {
	public function index() {
		// Only add hreflang on GET requests, not on AJAX or POST
		$method = isset($this->request->server['REQUEST_METHOD']) ? strtoupper($this->request->server['REQUEST_METHOD']) : 'GET';
		$is_xhr = isset($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		
		if ($method !== 'GET' || $is_xhr) {
			return;
		}

		// Add noindex,follow for non-SEO URLs (those with index.php?route=)
		$this->addRobotsMetaTags();
		
		// Add hreflang tags (called during startup)
		$this->addHreflangTags();
	}

	/**
	 * Add robots meta tag for non-SEO URLs
	 */
	private function addRobotsMetaTags() {
		$current_route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
		
		// Skip robots meta tag for home page
		if ($current_route === 'common/home') {
			return;
		}
		
		// Check if this is a non-SEO URL by checking REQUEST_URI
		$request_uri = isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '';
		
		// If URL contains "index.php?route=" pattern, add noindex,follow header
		if (strpos($request_uri, 'index.php?route=') !== false) {
			$this->response->addHeader('X-Robots-Tag: noindex,follow');
		}
	}

	/**
	 * Add hreflang tags to document head
	 */
	private function addHreflangTags() {
		// Get current URL without language prefix
		$current_route = isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home';
		$current_params = $this->request->get;
		
		// Remove system parameters
		unset($current_params['_route_']);
		unset($current_params['route']);

		// Don't add hreflang tags for non-SEO URLs (those with index.php?route=)
		// These should only be used for clean SEO URLs (e.g., /product-name or /category-name)
		if ($current_route !== 'common/home') {
			// Check if this is a non-SEO URL by checking REQUEST_URI
			$request_uri = isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '';
			
			// If URL contains "index.php?route=" pattern, skip hreflang tags
			if (strpos($request_uri, 'index.php?route=') !== false) {
				return;
			}
		}

		// Get all active languages
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		$default_language = $this->config->get('config_language');
		$current_language = isset($this->session->data['language']) ? $this->session->data['language'] : $default_language;

		// Store current state
		$original_language = $this->session->data['language'];
		$original_language_id = $this->config->get('config_language_id');

		// Add hreflang for each language
		foreach ($languages as $language) {
			if (!$language['status']) {
				continue;
			}

			// Temporarily switch to target language to generate correct URL
			$this->session->data['language'] = $language['code'];
			$this->config->set('config_language_id', $language['language_id']);

			// Build URL parameters
			$url_params = '';
			if (!empty($current_params)) {
				$url_params = '&' . urldecode(http_build_query($current_params, '', '&'));
			}

			// Generate URL for this language
			$language_url = $this->url->link($current_route, $url_params, $this->request->server['HTTPS']);

			// Convert language code to hreflang format (en-gb -> en-GB, uk-ua -> uk-UA)
			$hreflang = $this->convertToHreflang($language['code']);

			// Add hreflang tag
			$this->document->addHreflang($language_url, $hreflang);
		}

		// Add x-default for default language
		$this->session->data['language'] = $default_language;
		if (isset($languages[$default_language])) {
			$this->config->set('config_language_id', $languages[$default_language]['language_id']);
		}

		$url_params = '';
		if (!empty($current_params)) {
			$url_params = '&' . urldecode(http_build_query($current_params, '', '&'));
		}

		$default_url = $this->url->link($current_route, $url_params, $this->request->server['HTTPS']);
		$this->document->addHreflang($default_url, 'x-default');

		// Restore original state
		$this->session->data['language'] = $original_language;
		$this->config->set('config_language_id', $original_language_id);
	}

	/**
	 * Convert language code to hreflang format
	 * en-gb -> en-GB, uk-ua -> uk-UA
	 * 
	 * @param string $code Language code
	 * @return string Hreflang format
	 */
	private function convertToHreflang($code) {
		$parts = explode('-', $code);
		if (count($parts) === 2) {
			return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
		}
		return strtolower($code);
	}
}
