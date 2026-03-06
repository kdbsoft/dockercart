<?php
/**
 * DockerCart Redirects - Catalog Controller (renamed)
 */

class ControllerExtensionModuleDockercartRedirects extends Controller {
    private $logger;
    private $redirect_limit = 5; // Maximum redirect chain to prevent loops

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'redirects');
    }

    public function checkRedirect() {
        $method = isset($this->request->server['REQUEST_METHOD']) ? strtoupper($this->request->server['REQUEST_METHOD']) : 'GET';
        $is_xhr = isset($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($method !== 'GET' || $is_xhr) {
            return;
        }

        if (!$this->config->get('module_dockercart_redirects_status')) {
            return;
        }

        // Runtime license enforcement:
        // - On local development (localhost/127.0.0.1/::1) redirects are allowed without a license.
        // - On any other host, a license key must be configured and validated. If missing or invalid,
        //   redirects are disabled to prevent unlicensed use on production systems.
        $license_key = trim((string)$this->config->get('module_dockercart_redirects_license_key'));
        $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $is_local = false;
        if ($domain !== '') {
            $lower = strtolower($domain);
            if (strpos($lower, 'localhost') !== false || strpos($lower, '127.0.0.1') !== false || strpos($lower, '::1') !== false) {
                $is_local = true;
            }
        }

        if (!$is_local) {
            // On non-local hosts require a license key to be present
            if (empty($license_key)) {
                $this->logger->info('ERROR: License key not configured; redirects disabled on production host (' . $domain . ')');
                return;
            }

            if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                $this->logger->info('ERROR: License library not found at ' . DIR_SYSTEM . 'library/dockercart_license.php');
                return;
            }

            require_once(DIR_SYSTEM . 'library/dockercart_license.php');

            if (class_exists('DockercartLicense')) {
                try {
                    $license = new DockercartLicense($this->registry);
                    $result = $license->verify($license_key, 'dockercart_redirects');

                    if (empty($result) || !$result['valid']) {
                        $this->logger->info('ERROR: Invalid or missing license - ' . (isset($result['error']) ? $result['error'] : 'No verification result'));
                        return;
                    }

                    $this->logger->info('LICENSE: Valid license verified for dockercart_redirects');
                } catch (Exception $e) {
                    $this->logger->info('ERROR: Exception during license verification - ' . $e->getMessage());
                    return;
                }
            } else {
                $this->logger->info('ERROR: DockercartLicense class not found after including license library');
                return;
            }
        }

        if ($this->registry->get('dockercart_redirects_checked')) {
            return;
        }
        $this->registry->set('dockercart_redirects_checked', true);

        $this->load->model('extension/module/dockercart_redirects');

        $current_url = $this->getCurrentUrl();
        $normalized_url = $this->normalizeUrl($current_url);

        $this->logger->info('checkRedirect: request_uri=' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . ' normalized=' . $normalized_url);

        $redirect = $this->findRedirect($normalized_url, $current_url);

        if ($redirect) {
            $this->performRedirect($redirect, $current_url);
        } else {
            $this->logger->info('checkRedirect: no redirect found for ' . $normalized_url);
        }
    }

    public function handle404() {
        // Enforce license for 404 handling as well (same rules as checkRedirect)
        $license_key = trim((string)$this->config->get('module_dockercart_redirects_license_key'));
        $domain = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        $is_local = false;
        if ($domain !== '') {
            $lower = strtolower($domain);
            if (strpos($lower, 'localhost') !== false || strpos($lower, '127.0.0.1') !== false || strpos($lower, '::1') !== false) {
                $is_local = true;
            }
        }

        if (!$is_local) {
            if (empty($license_key)) {
                $this->logger->info('ERROR: License key not configured; 404 redirect handling disabled on production host (' . $domain . ')');
                return;
            }

            if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
                $this->logger->info('ERROR: License library not found at ' . DIR_SYSTEM . 'library/dockercart_license.php');
                return;
            }

            require_once(DIR_SYSTEM . 'library/dockercart_license.php');

            if (class_exists('DockercartLicense')) {
                try {
                    $license = new DockercartLicense($this->registry);
                    $result = $license->verify($license_key, 'dockercart_redirects');

                    if (empty($result) || !$result['valid']) {
                        $this->logger->info('ERROR: Invalid or missing license during 404 handling - ' . (isset($result['error']) ? $result['error'] : 'No verification result'));
                        return;
                    }
                } catch (Exception $e) {
                    $this->logger->info('ERROR: Exception during license verification (404 handler) - ' . $e->getMessage());
                    return;
                }
            } else {
                $this->logger->info('ERROR: DockercartLicense class not found after including license library (404 handler)');
                return;
            }
        }

        $this->load->model('extension/module/dockercart_redirects');

        $current_url = $this->getCurrentUrl();
        $normalized_url = $this->normalizeUrl($current_url);

        $redirect = $this->findRedirect($normalized_url, $current_url);

        if ($redirect) {
            $this->performRedirect($redirect, $current_url);
        }
    }

    private function getCurrentUrl() {
        $url = '';

        if (isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['QUERY_STRING'])) {
            $url = '?' . $_SERVER['QUERY_STRING'];
        }

        return $url;
    }

    private function normalizeUrl($url) {
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';

        if (isset($parsed['query']) && strpos($path, 'index.php') !== false) {
            parse_str($parsed['query'], $qs);
            if (!empty($qs['route'])) {
                $route_path = $qs['route'];
                if (strpos($route_path, '/') !== 0) {
                    $route_path = '/' . $route_path;
                }
                $route_path = preg_replace('/\?.*/', '', $route_path);
                $path = $route_path;
            }
        }

        $path = strtolower($path);

        if ($path != '/' && substr($path, -1) == '/') {
            $path = substr($path, 0, -1);
        }

        // Do NOT strip language prefixes — keep them for strict matching
        // This ensures /macbook != /uk-ua/macbook in redirect matching

        return $path;
    }

    private function findRedirect($url, $original_request_url = null) {
        $this->load->model('extension/module/dockercart_redirects');

        if (!isset($this->session->data['redirect_chain'])) {
            $this->session->data['redirect_chain'] = array();
        }

        if (in_array($url, $this->session->data['redirect_chain'])) {
            $this->logger->info("Redirect loop detected for URL: $url — clearing redirect chain and retrying");
            $this->session->data['redirect_chain'] = array();
        }

        if (count($this->session->data['redirect_chain']) >= $this->redirect_limit) {
            $this->logger->info("Redirect limit exceeded for URL: $url");
            $this->session->data['redirect_chain'] = array();
            return null;
        }

        // Primary exact/regex lookup
        $result = $this->model_extension_module_dockercart_redirects->getActiveRedirectsForUrl($url);

        // If nothing found, try several sensible fallbacks using the original request
        if (empty($result) && $original_request_url) {
            $this->logger->info('findRedirect: primary lookup failed for ' . $url . '; trying fallbacks for original_request_url=' . $original_request_url);

            // 1) Try the original request string (may include host or full URL)
            $result = $this->model_extension_module_dockercart_redirects->getActiveRedirectsForUrl($original_request_url);

            // 1.b) Try matching stored absolute URL that includes host (construct from current host)
            if (empty($result)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $full_url = $scheme . '://' . $host . $url;
                $this->logger->info('findRedirect: trying full-url fallback ' . $full_url);
                $result = $this->model_extension_module_dockercart_redirects->getActiveRedirectsForUrl($full_url);
            }

            if (empty($result)) {
                // 2) Parse path portion
                $parsed_orig = parse_url($original_request_url);
                $orig_path = isset($parsed_orig['path']) ? strtolower($parsed_orig['path']) : '';

                if ($orig_path != '/' && substr($orig_path, -1) == '/') {
                    $orig_path = substr($orig_path, 0, -1);
                }

                if ($orig_path && $orig_path != $url) {
                    $this->logger->info('findRedirect: trying path fallback ' . $orig_path);
                    $result = $this->model_extension_module_dockercart_redirects->getActiveRedirectsForUrl($orig_path);
                }
            }

            // 3) Try without leading slash or with explicit leading slash variants
            if (empty($result) && isset($orig_path)) {
                $variants = array(ltrim($orig_path, '/'), '/' . ltrim($orig_path, '/'));
                foreach ($variants as $v) {
                    if ($v && $v != $url) {
                        $this->logger->info('findRedirect: trying variant ' . $v);
                        $result = $this->model_extension_module_dockercart_redirects->getActiveRedirectsForUrl($v);
                        if (!empty($result)) {
                            break;
                        }
                    }
                }
            }
        }

        if (isset($result['redirect_id'])) {
            return $result;
        }

        if (is_array($result) && !empty($result)) {
            foreach ($result as $redirect) {
                if ($redirect['is_regex'] == 1) {
                    if (@preg_match($redirect['old_url'], $url)) {
                        $redirect['new_url'] = preg_replace($redirect['old_url'], $redirect['new_url'], $url);
                        return $redirect;
                    }
                }
            }
        }

        return null;
    }

    private function performRedirect($redirect, $original_url) {
        $this->session->data['redirect_chain'][] = $this->normalizeUrl($original_url);

        $target_url = $redirect['new_url'];

        if ($redirect['preserve_query']) {
            $parsed = parse_url($original_url);
            if (isset($parsed['query']) && !empty($parsed['query'])) {
                $target_separator = strpos($target_url, '?') !== false ? '&' : '?';
                $target_url .= $target_separator . $parsed['query'];
            }
        }

        if (strpos($target_url, 'http') !== 0) {
            if (strpos($target_url, '/') === 0) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $target_url = $scheme . '://' . $host . $target_url;
            } else {
                $target_url = $this->url->link($target_url, '', true);
            }
        }

        $this->load->model('extension/module/dockercart_redirects');
        $this->model_extension_module_dockercart_redirects->incrementHits($redirect['redirect_id']);

        $this->logger->info("Redirect: {$original_url} -> {$target_url} ({$redirect['code']})");

        header("Location: " . $target_url, true, (int)$redirect['code']);
        exit();
    }
}
