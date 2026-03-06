<?php
// Error Reporting
error_reporting(E_ALL);

// Check Version
if (version_compare(phpversion(), '8.1.0', '<') == true) {
	exit('PHP8.1+ Required');
}

if (!ini_get('date.timezone')) {
	date_default_timezone_set('UTC');
}

// Windows IIS Compatibility
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['SCRIPT_FILENAME'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['DOCUMENT_ROOT'])) {
	if (isset($_SERVER['PATH_TRANSLATED'])) {
		$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
	}
}

if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);

	if (isset($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

if (!isset($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = getenv('HTTP_HOST');
}

// Check if SSL
if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || (isset($_SERVER['HTTPS']) && (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443))) {
	$_SERVER['HTTPS'] = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
	$_SERVER['HTTPS'] = true;
} else {
	$_SERVER['HTTPS'] = false;
}

// Normalize URL: remove multiple consecutive slashes
// Example: ///desktops → /desktops, /path//to//page → /path/to/page
if (isset($_SERVER['REQUEST_URI'])) {
	// Skip normalization for non-GET requests or XHR (AJAX) requests to avoid breaking API/AJAX flows
	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
	$is_xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

	if ($method !== 'GET' || $is_xhr) {
		// Do not perform URL normalization or redirects for non-GET/XHR requests
	} else {
		$request_targets = array();

		// Standard request URI from PHP
		$request_targets[] = $_SERVER['REQUEST_URI'];

		// Raw request target from web server when available
		if (!empty($_SERVER['THE_REQUEST']) && preg_match('#^[A-Z]+\s+(\S+)#', $_SERVER['THE_REQUEST'], $matches)) {
			$request_targets[] = $matches[1];
		}

		// Proxies/load balancers may pass original URI in headers
		$proxy_uri_keys = array('HTTP_X_ORIGINAL_URI', 'HTTP_X_FORWARDED_URI', 'HTTP_X_REWRITE_URL', 'UNENCODED_URL');
		foreach ($proxy_uri_keys as $proxy_uri_key) {
			if (!empty($_SERVER[$proxy_uri_key]) && is_string($_SERVER[$proxy_uri_key])) {
				$request_targets[] = $_SERVER[$proxy_uri_key];
			}
		}

		$path = '';
		$query = '';

		foreach ($request_targets as $request_target) {
			if (!is_string($request_target) || $request_target === '') {
				continue;
			}

			if (strpos($request_target, '://') !== false) {
				$parsed_target = parse_url($request_target);
				$candidate_path = isset($parsed_target['path']) ? $parsed_target['path'] : '';
				$candidate_query = isset($parsed_target['query']) ? '?' . $parsed_target['query'] : '';
			} else {
				$parts = explode('?', $request_target, 2);
				$candidate_path = $parts[0];
				$candidate_query = isset($parts[1]) ? '?' . $parts[1] : '';
			}

			if ($candidate_path !== '' && preg_match('#//+#', $candidate_path)) {
				$path = $candidate_path;
				$query = $candidate_query;
				break;
			}
		}

		if ($path === '') {
			$parts = explode('?', $_SERVER['REQUEST_URI'], 2);
			$path = $parts[0];
			$query = isset($parts[1]) ? '?' . $parts[1] : '';
		}
	
		// Replace multiple consecutive slashes with single slash
		$normalized_path = preg_replace('#/+#', '/', $path);

		// If path was changed (had multiple slashes), redirect to normalized URL
		if ($normalized_path !== $path) {
			$protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
			$host = $_SERVER['HTTP_HOST'];
			$redirect_url = $protocol . '://' . $host . $normalized_path . $query;

			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $redirect_url);
			exit;
		}
	}
}

// Modification Override
function modification($filename) {
	if (defined('DIR_CATALOG')) {
		$file = DIR_MODIFICATION . 'admin/' .  substr($filename, strlen(DIR_APPLICATION));
	} elseif (defined('DIR_OPENCART')) {
		$file = DIR_MODIFICATION . 'install/' .  substr($filename, strlen(DIR_APPLICATION));
	} else {
		$file = DIR_MODIFICATION . 'catalog/' . substr($filename, strlen(DIR_APPLICATION));
	}

	if (substr($filename, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
		$file = DIR_MODIFICATION . 'system/' . substr($filename, strlen(DIR_SYSTEM));
	}

	if (is_file($file)) {
		return $file;
	}

	return $filename;
}

// Autoloader
if (defined('DIR_STORAGE') && is_file(DIR_STORAGE . 'vendor/autoload.php')) {
	require_once(DIR_STORAGE . 'vendor/autoload.php');
}

function library($class) {
	$file = DIR_SYSTEM . 'library/' . str_replace('\\', '/', strtolower($class)) . '.php';

	if (is_file($file)) {
		include_once(modification($file));

		return true;
	} else {
		return false;
	}
}

spl_autoload_register('library');
spl_autoload_extensions('.php');

// Engine
require_once(modification(DIR_SYSTEM . 'engine/action.php'));
require_once(modification(DIR_SYSTEM . 'engine/controller.php'));
require_once(modification(DIR_SYSTEM . 'engine/event.php'));
require_once(modification(DIR_SYSTEM . 'engine/router.php'));
require_once(modification(DIR_SYSTEM . 'engine/loader.php'));
require_once(modification(DIR_SYSTEM . 'engine/model.php'));
require_once(modification(DIR_SYSTEM . 'engine/registry.php'));
require_once(modification(DIR_SYSTEM . 'engine/proxy.php'));

// Helper
require_once(DIR_SYSTEM . 'helper/general.php');
require_once(DIR_SYSTEM . 'helper/utf8.php');

function start($application_config) {
	require_once(DIR_SYSTEM . 'framework.php');	
}