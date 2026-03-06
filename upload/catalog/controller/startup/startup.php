<?php
class ControllerStartupStartup extends Controller {

	public function __isset($key) {
		// To make sure that calls to isset also support dynamic properties from the registry
		// See https://www.php.net/manual/en/language.oop5.overloading.php#object.isset
		if ($this->registry) {
			if ($this->registry->get($key)!==null) {
				return true;
			}
		}
		return false;
	}

	public function index() {
		// Store
		if ($this->request->server['HTTPS']) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`ssl`, 'www.', '') = '" . $this->db->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
		} else {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $this->db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
		}
		
		if (isset($this->request->get['store_id'])) {
			$this->config->set('config_store_id', (int)$this->request->get['store_id']);
		} else if ($query->num_rows) {
			$this->config->set('config_store_id', $query->row['store_id']);
		} else {
			$this->config->set('config_store_id', 0);
		}
		
		if (!$query->num_rows) {
			$this->config->set('config_url', HTTP_SERVER);
			$this->config->set('config_ssl', HTTPS_SERVER);
		}
		
		// Settings
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' OR store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY store_id ASC");
		
		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$this->config->set($result['key'], $result['value']);
			} else {
				$this->config->set($result['key'], json_decode($result['value'], true));
			}
		}

		// Set time zone
		if ($this->config->get('config_timezone')) {
			date_default_timezone_set($this->config->get('config_timezone'));

			// Sync PHP and DB time zones.
			$this->db->query("SET time_zone = '" . $this->db->escape(date('P')) . "'");
		}

		// Theme
		$this->config->set('template_cache', $this->config->get('developer_theme'));
		
		// Url
		$url = new Url($this->config->get('config_url'), $this->config->get('config_ssl'));
		$url->setRegistry($this->registry);
		$this->registry->set('url', $url);

		// Detect request method and XHR to avoid performing redirects for non-GET/AJAX requests
		$method = isset($this->request->server['REQUEST_METHOD']) ? strtoupper($this->request->server['REQUEST_METHOD']) : 'GET';
		$is_xhr = isset($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
		
		// Language
		$code = '';
		
		$this->load->model('localisation/language');
		
		$languages = $this->model_localisation_language->getLanguages();
		
		// Handle language switching via URL parameter (only for GET non-AJAX requests)
		if ($method === 'GET' && !$is_xhr && isset($this->request->get['switch_lang'])) {
			$switch_to = strtolower($this->request->get['switch_lang']);
			if (array_key_exists($switch_to, $languages) && $languages[$switch_to]['status']) {
				// Update session and cookie with new language
				$this->session->data['language'] = $switch_to;
				setcookie('language', $switch_to, time() + 60 * 60 * 24 * 30, '/');
				$this->config->set('config_language_id', $languages[$switch_to]['language_id']);
				$code = $switch_to;
				
				// Redirect without switch_lang parameter - rebuild URL from scratch
				$path = isset($this->request->server['REQUEST_URI']) ? parse_url($this->request->server['REQUEST_URI'], PHP_URL_PATH) : '/';
				
				// Build query string from $_GET, excluding switch_lang
				$query_params = array();
				foreach ($this->request->get as $key => $value) {
					if ($key !== 'switch_lang' && $key !== '_route_') {
						$query_params[$key] = $value;
					}
				}
				
				// Rebuild URL
				$clean_url = $path;
				if (!empty($query_params)) {
					$clean_url .= '?' . urldecode(http_build_query($query_params, '', '&'));
				}
				
				$protocol = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? 'https://' : 'http://';
				$host = $this->request->server['HTTP_HOST'];
				$this->response->redirect($protocol . $host . $clean_url, 302);
				exit;
			}
		}
		
		// Check URL for language prefix first (highest priority)
		$has_language_prefix = false;
		$has_any_params = false;
		$seo_url_language = null; // Language detected from SEO URL
		
		if (isset($this->request->get['_route_'])) {
			$route = $this->request->get['_route_'];
			$parts = explode('/', trim($route, '/'));
			
			if (!empty($parts[0])) {
				$has_any_params = true;
				$potential_lang = $parts[0];
				if (array_key_exists($potential_lang, $languages) && $languages[$potential_lang]['status']) {
					$code = $potential_lang;
					$has_language_prefix = true;
				} else {
					// First part is not a language - it's a SEO URL keyword
					// Check which language this SEO URL belongs to
					$seo_keyword = $parts[0];
					$store_id = (int)$this->config->get('config_store_id');
				
					// Prefer the store default language if the same keyword exists for multiple languages
					$default_language_code = $this->config->get('config_language');
					$default_language_id = 0;
					if (isset($languages[$default_language_code]) && isset($languages[$default_language_code]['language_id'])) {
						$default_language_id = (int)$languages[$default_language_code]['language_id'];
					}
				
					$seo_query = $this->db->query(
						"SELECT language_id FROM " . DB_PREFIX . "seo_url 
						WHERE keyword = '" . $this->db->escape($seo_keyword) . "' 
						AND store_id = '" . $store_id . "' 
						ORDER BY (language_id = '" . $default_language_id . "') DESC
						LIMIT 1"
					);
					
					if ($seo_query->num_rows) {
						// Found SEO URL - get its language
						$seo_language_id = $seo_query->row['language_id'];
						// Find language code for this language_id
						foreach ($languages as $lang_code => $lang_data) {
							if ($lang_data['language_id'] == $seo_language_id) {
								$seo_url_language = $lang_code;
								break;
							}
						}
					}
				}
			}
		}
		
		// Check if there are any other query parameters (path, product_id, route, etc)
		// Note: lang_switch is a system parameter and doesn't count as a "real" parameter
		if (!$has_any_params && (
		    isset($this->request->get['path']) || isset($this->request->get['product_id']) || 
		    isset($this->request->get['manufacturer_id']) || isset($this->request->get['information_id']) ||
		    isset($this->request->get['route'])
		)) {
			$has_any_params = true;
		}
		
		// Language detection priority:
		// 1. URL prefix (highest - explicit in URL)
		// 2. Session (user's selected language via switch_lang)
		// 3. Cookie (persistent user preference)
		// 4. SEO URL language (inferred from DB)
		// 5. Default (config)
		
		if ($has_language_prefix) {
			// URL has explicit language prefix - use it
			// Code is already set from URL prefix detection above
		} else if (isset($this->session->data['language']) && array_key_exists($this->session->data['language'], $languages)) {
			// User has selected language via switch_lang - highest priority after URL prefix
			$code = $this->session->data['language'];
		} else if (isset($this->request->cookie['language']) && array_key_exists($this->request->cookie['language'], $languages)) {
			// User has persistent cookie - use it
			$code = $this->request->cookie['language'];
		} else if ($seo_url_language) {
			// SEO URL found - infer language from DB
			$code = $seo_url_language;
		} else {
			// No language detected - use default
			$code = $this->config->get('config_language');
		}
		
		// Language Detection
		if (!empty($this->request->server['HTTP_ACCEPT_LANGUAGE']) && !array_key_exists($code, $languages)) {
			$detect = '';
			
			$browser_languages = explode(',', $this->request->server['HTTP_ACCEPT_LANGUAGE']);
			
			// Try using local to detect the language
			foreach ($browser_languages as $browser_language) {
				foreach ($languages as $key => $value) {
					if ($value['status']) {
						$locale = explode(',', $value['locale']);
						
						if (in_array($browser_language, $locale)) {
							$detect = $key;
							break 2;
						}
					}
				}	
			}			
			
			if (!$detect) { 
				// Try using language folder to detect the language
				foreach ($browser_languages as $browser_language) {
					if (array_key_exists(strtolower($browser_language), $languages)) {
						$detect = strtolower($browser_language);
						
						break;
					}
				}
			}
			
			$code = $detect ? $detect : '';
		}
		
		if (!array_key_exists($code, $languages)) {
			$code = $this->config->get('config_language');
		}
		
		// Redirect home page to language-specific version if user has selected non-default language
		$default_language = $this->config->get('config_language');
		
		if ($code !== $default_language && !$has_language_prefix && !$has_any_params && !$seo_url_language && $method === 'GET' && !$is_xhr) {
			// User has selected non-default language and is on pure home page (no params, no prefix)
			// Redirect to language-specific home (e.g., /uk-ua/)
			$protocol = (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$host = $this->request->server['HTTP_HOST'];
			$redirect_url = $protocol . $host . '/' . $code . '/';
			
			$this->response->redirect($redirect_url, 302);
			exit;
		}
		
		// Update session and cookie with detected language
		if (!isset($this->session->data['language']) || $this->session->data['language'] != $code) {
			$this->session->data['language'] = $code;
		}
				
		if (!isset($this->request->cookie['language']) || $this->request->cookie['language'] != $code) {
			setcookie('language', $code, time() + 60 * 60 * 24 * 30, '/');
		}
				
		// Overwrite the default language object
		$language = new Language($code);
		$language->load($code);
		
		$this->registry->set('language', $language);
		
		// Set the config language_id
		$this->config->set('config_language_id', $languages[$code]['language_id']);	

		// Customer
		$customer = new Cart\Customer($this->registry);
		$this->registry->set('customer', $customer);
		
		// Customer Group
		if (isset($this->session->data['customer']) && isset($this->session->data['customer']['customer_group_id'])) {
			// For API calls
			$this->config->set('config_customer_group_id', $this->session->data['customer']['customer_group_id']);
		} elseif ($this->customer->isLogged()) {
			// Logged in customers
			$this->config->set('config_customer_group_id', $this->customer->getGroupId());
		} elseif (isset($this->session->data['guest']) && isset($this->session->data['guest']['customer_group_id'])) {
			$this->config->set('config_customer_group_id', $this->session->data['guest']['customer_group_id']);
		} else {
			$this->config->set('config_customer_group_id', $this->config->get('config_customer_group_id'));
		}
		
		// Tracking Code
		if (isset($this->request->get['tracking'])) {
			setcookie('tracking', $this->request->get['tracking'], time() + 3600 * 24 * 1000, '/');
		
			$this->db->query("UPDATE `" . DB_PREFIX . "marketing` SET clicks = (clicks + 1) WHERE code = '" . $this->db->escape($this->request->get['tracking']) . "'");
		}		
		
		// Currency
		$code = '';
		
		$this->load->model('localisation/currency');
		
		$currencies = $this->model_localisation_currency->getCurrencies();
		
		if (isset($this->session->data['currency'])) {
			$code = $this->session->data['currency'];
		}
		
		if (isset($this->request->cookie['currency']) && !array_key_exists($code, $currencies)) {
			$code = $this->request->cookie['currency'];
		}
		
		if (!array_key_exists($code, $currencies)) {
			$code = $this->config->get('config_currency');
		}
		
		if (!isset($this->session->data['currency']) || $this->session->data['currency'] != $code) {
			$this->session->data['currency'] = $code;
		}
		
		if (!isset($this->request->cookie['currency']) || $this->request->cookie['currency'] != $code) {
			setcookie('currency', $code, time() + 60 * 60 * 24 * 30, '/');
		}		
		
		$this->registry->set('currency', new Cart\Currency($this->registry));
		
		// Tax
		$this->registry->set('tax', new Cart\Tax($this->registry));
		
		// PHP v7.4+ validation compatibility.
		if (isset($this->session->data['shipping_address']['country_id']) && isset($this->session->data['shipping_address']['zone_id'])) {
			$this->tax->setShippingAddress($this->session->data['shipping_address']['country_id'], $this->session->data['shipping_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'shipping') {
			$this->tax->setShippingAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		}

		if (isset($this->session->data['payment_address']['country_id']) && isset($this->session->data['payment_address']['zone_id'])) {
			$this->tax->setPaymentAddress($this->session->data['payment_address']['country_id'], $this->session->data['payment_address']['zone_id']);
		} elseif ($this->config->get('config_tax_default') == 'payment') {
			$this->tax->setPaymentAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		}

		$this->tax->setStoreAddress($this->config->get('config_country_id'), $this->config->get('config_zone_id'));
		
		// Weight
		$this->registry->set('weight', new Cart\Weight($this->registry));
		
		// Length
		$this->registry->set('length', new Cart\Length($this->registry));
		
		// Cart
		$this->registry->set('cart', new Cart\Cart($this->registry));
		
		// Encryption
		$this->registry->set('encryption', new Encryption());
	}
}
