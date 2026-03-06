<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Config class
*/
class Config {
	private $data = array();
    
	/**
     * Get config value with multilingual support
     *
     * @param	string	$key
	 * 
	 * @return	mixed
     */
	public function get($key) {
		// First check for _i18n version of the key (multilingual)
		$i18n_key = $key . '_i18n';
		if (isset($this->data[$i18n_key])) {
			$i18n_value = $this->data[$i18n_key];
			
			// If it's an array (JSON stored in DB), get value for current language
			if (is_array($i18n_value)) {
				// Get current language ID from config
				$lang_id = isset($this->data['config_language_id']) ? $this->data['config_language_id'] : 1;
				
				// Return value for current language if exists, otherwise return first value
				if (isset($i18n_value[$lang_id])) {
					return $i18n_value[$lang_id];
				} elseif (!empty($i18n_value)) {
					return reset($i18n_value);
				}
			}
		}
		
		// Fall back to original key
		return (isset($this->data[$key]) ? $this->data[$key] : null);
	}
	
    /**
     * 
     *
     * @param	string	$key
	 * @param	mixed	$value
     */
	public function set($key, $value) {
		$this->data[$key] = $value;
	}

    /**
     * 
     *
     * @param	string	$key
	 *
	 * @return	bool
     */
	public function has($key) {
		return isset($this->data[$key]);
	}
	
    /**
     * 
     *
     * @param	string	$filename
     */
	public function load($filename) {
		$file = DIR_CONFIG . $filename . '.php';

		if (file_exists($file)) {
			$_ = array();

			require($file);

			$this->data = array_merge($this->data, $_);
		} else {
			trigger_error('Error: Could not load config ' . $filename . '!');
			exit();
		}
	}
}