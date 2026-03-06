<?php

class DockercartLicense {

    private $logger;
    private $registry;

    private $cache;
    private $domain;
    private $config;
    private $db;

    const CACHE_TTL = 604800;

    public function __construct($registry) {
        // Keep a reference to the registry for later use and pass it to the logger
        $this->registry = $registry;

        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'license');
        $this->cache = $registry->get('cache');
        $this->config = $registry->get('config');
        $this->db = $registry->get('db');
        $this->domain = $this->getNormalizedDomain();   
    }

    public function verify($license_key, $module_code, $skip_cache = false) {
        if (!$this->moduleExists($module_code)) {
            return $this->error('Module not configured: ' . $module_code . '. Please add Public Key in admin panel.');
        }

        if (!$this->validateFormat($license_key)) {
            return $this->error('Invalid license key format');
        }

        if (!$skip_cache) {
            $cached = $this->getFromCache($license_key, $module_code);
            if ($cached !== null) {
                if (is_array($cached)) {
                    $this->logger->info('License verification: CACHE HIT for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');
                    return $cached;
                } else {
                    $this->clearCache($license_key, $module_code);
                }
            }
        } else {
            $this->logger->info('License verification: SKIPPING CACHE for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');
        }

        $license_data = $this->parse($license_key, $module_code);
        if ($license_data === false) {
            $result = $this->error('Invalid license signature');
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyModule($license_data['module'], $module_code)) {
            $result = $this->error('License not valid for module: ' . $module_code);
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyDomain($license_data['domain'])) {
            $result = $this->error('License not valid for domain: ' . $this->domain);
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyExpiration($license_data['expires'])) {
            $result = $this->error('License expired on: ' . date('Y-m-d', $license_data['expires']));
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        $result = array(
            'valid' => true,
            'module' => $license_data['module'],
            'domain' => $license_data['domain'],
            'expires' => $license_data['expires'],
            'expires_formatted' => $license_data['expires'] > 0 ? date('Y-m-d', $license_data['expires']) : 'Lifetime',
            'license_id' => $license_data['license_id'],
            'version' => isset($license_data['version']) ? $license_data['version'] : '1.0',
            'verified_at' => time()
        );

        $this->saveToCache($license_key, $module_code, $result);
        $this->logger->info('License verification: SUCCESS for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');

        return $result;
    }

    public function verifyWithPublicKey($license_key, $public_key, $module_code, $skip_cache = false) {
        if (!$this->validateFormat($license_key)) {
            return $this->error('Invalid license key format');
        }

        if (empty($public_key)) {
            return $this->error('Public key is required for verification');
        }

        if (!$skip_cache) {
            $cached = $this->getFromCache($license_key, $module_code);
            if ($cached !== null) {
                if (is_array($cached)) {
                    $this->logger->info('License verification (with key): CACHE HIT for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');
                    return $cached;
                } else {
                    $this->clearCache($license_key, $module_code);
                }
            }
        } else {
            $this->logger->info('License verification (with key): SKIPPING CACHE for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');
        }

        $license_data = $this->parseWithPublicKey($license_key, $public_key);
        if ($license_data === false) {
            $result = $this->error('Invalid license signature');
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyModule($license_data['module'], $module_code)) {
            $result = $this->error('License not valid for module: ' . $module_code);
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyDomain($license_data['domain'])) {
            $result = $this->error('License not valid for domain: ' . $this->domain);
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        if (!$this->verifyExpiration($license_data['expires'])) {
            $result = $this->error('License expired on: ' . date('Y-m-d', $license_data['expires']));
            $this->saveToCache($license_key, $module_code, $result);
            return $result;
        }

        $result = array(
            'valid' => true,
            'module' => $license_data['module'],
            'domain' => $license_data['domain'],
            'expires' => $license_data['expires'],
            'expires_formatted' => $license_data['expires'] > 0 ? date('Y-m-d', $license_data['expires']) : 'Lifetime',
            'license_id' => $license_data['license_id'],
            'version' => isset($license_data['version']) ? $license_data['version'] : '1.0',
            'verified_at' => time()
        );

        $this->saveToCache($license_key, $module_code, $result);
        $this->logger->info('License verification (with key): SUCCESS for ' . $this->maskKey($license_key) . ' [' . $module_code . ']');

        return $result;
    }

    private function parse($license_key, $module_code) {
        try {
            $parts = explode('-', $license_key, 3);

            if (count($parts) !== 3) {
                return false;
            }

            list($prefix, $payload, $signature) = $parts;

            if ($prefix !== 'DCFL') {
                return false;
            }

            $json_data = base64_decode($payload);
            if ($json_data === false) {
                return false;
            }

            $license_data = json_decode($json_data, true);
            if (!is_array($license_data)) {
                return false;
            }

            if (!isset($license_data['module']) ||
                !isset($license_data['domain']) ||
                !isset($license_data['expires']) ||
                !isset($license_data['license_id'])) {
                return false;
            }

            $signature_decoded = base64_decode($signature);
            if ($signature_decoded === false) {
                return false;
            }

            if (!$this->verifySignature($payload, $signature_decoded, $module_code)) {
                return false;
            }

            return $license_data;

        } catch (Exception $e) {
            $this->logger->info('License parse error: ' . $e->getMessage());
            return false;
        }
    }

    private function parseWithPublicKey($license_key, $public_key_pem) {
        try {
            $parts = explode('-', $license_key, 3);

            if (count($parts) !== 3) {
                return false;
            }

            list($prefix, $payload, $signature) = $parts;

            if ($prefix !== 'DCFL') {
                return false;
            }

            $json_data = base64_decode($payload);
            if ($json_data === false) {
                return false;
            }

            $license_data = json_decode($json_data, true);
            if (!is_array($license_data)) {
                return false;
            }

            if (!isset($license_data['module']) ||
                !isset($license_data['domain']) ||
                !isset($license_data['expires']) ||
                !isset($license_data['license_id'])) {
                return false;
            }

            $signature_decoded = base64_decode($signature);
            if ($signature_decoded === false) {
                return false;
            }

            if (!$this->verifySignatureWithKey($payload, $signature_decoded, $public_key_pem)) {
                return false;
            }

            return $license_data;

        } catch (Exception $e) {
            $this->logger->info('License parse error (with key): ' . $e->getMessage());
            return false;
        }
    }

    private function verifySignature($data, $signature, $module_code) {
        $public_key_pem = $this->getModulePublicKey($module_code);

        if (empty($public_key_pem)) {
            $this->logger->info('Public key not found for module: ' . $module_code);
            return false;
        }

        $public_key = openssl_pkey_get_public($public_key_pem);

        if ($public_key === false) {
            $this->logger->info('Invalid public key for module: ' . $module_code);
            return false;
        }

        $result = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    private function verifySignatureWithKey($data, $signature, $public_key_pem) {
        if (empty($public_key_pem)) {
            $this->logger->info('Public key is empty for verification');
            return false;
        }

        $public_key = openssl_pkey_get_public($public_key_pem);

        if ($public_key === false) {
            $this->logger->info('Invalid public key provided for verification');
            return false;
        }

        $result = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    private function getModulePublicKey($module_code) {
        $config_key = 'module_' . $module_code . '_public_key';
        $public_key = $this->config->get($config_key);

        if (!empty($public_key)) {
            return $public_key;
        }

        return null;
    }

    private function moduleExists($module_code) {
        $public_key = $this->getModulePublicKey($module_code);
        return !empty($public_key);
    }

    public function getRegisteredModules() {
        $modules = array();

        if (!$this->db) {
            $this->logger->info('Warning: Database not available in registry');
            return $modules;
        }

        try {
            $query = $this->db->query("
                SELECT DISTINCT `key` FROM " . DB_PREFIX . "setting
                WHERE `key` LIKE 'module_dockercart_%_public_key'
                AND store_id = 0
            ");

            if ($query->rows) {
                foreach ($query->rows as $row) {
                    $key = $row['key'];
                    if (preg_match('/^module_(dockercart_[a-z_]+)_public_key$/', $key, $matches)) {
                        $module_code = $matches[1];
                        $modules[$module_code] = array(
                            'code' => $module_code,
                            'configured' => true
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->info('Error querying registered modules: ' . $e->getMessage());
        }

        return $modules;
    }

    private function validateFormat($license_key) {
        if (empty($license_key)) {
            return false;
        }

        if (!preg_match('/^DCFL-[A-Za-z0-9+\/=]+-[A-Za-z0-9+\/=]+$/', $license_key)) {
            return false;
        }

        return true;
    }

    private function verifyModule($license_module, $current_module) {
        return $license_module === $current_module;
    }

    private function verifyDomain($license_domain) {
        $current_domain = $this->domain;
        $license_domain = $this->getNormalizedDomain($license_domain);

        if ($current_domain === $license_domain) {
            return true;
        }

        if (strpos($license_domain, '*.') === 0) {
            $base_domain = substr($license_domain, 2);
            if (substr($current_domain, -strlen($base_domain)) === $base_domain) {
                return true;
            }
        }

        return false;
    }

    private function verifyExpiration($expires) {
        if ($expires === 0) {
            return true;
        }

        return time() < $expires;
    }

    private function getNormalizedDomain($domain = null) {
        if ($domain === null) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }

        $domain = strtolower($domain);
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = preg_replace('/:.*$/', '', $domain);

        return $domain;
    }

    private function getFromCache($license_key, $module_code) {
        $cache_key = 'dockercart_license_' . md5($license_key . $this->domain . $module_code);
        return $this->cache->get($cache_key);
    }

    private function saveToCache($license_key, $module_code, $result) {
        $cache_key = 'dockercart_license_' . md5($license_key . $this->domain . $module_code);
        $this->cache->set($cache_key, $result, self::CACHE_TTL);
    }

    public function clearCache($license_key = null, $module_code = null) {
        if ($license_key !== null && $module_code !== null) {
            $cache_key = 'dockercart_license_' . md5($license_key . $this->domain . $module_code);
            $this->cache->delete($cache_key);
        }
    }

    private function maskKey($license_key) {
        if (strlen($license_key) < 20) {
            return substr($license_key, 0, 8) . '...';
        }

        return substr($license_key, 0, 12) . '...' . substr($license_key, -8);
    }

    private function error($message) {
        return array(
            'valid' => false,
            'error' => $message,
            'domain' => $this->domain
        );
    }

    private function log($message) {
        if ($this->logger) {
            // Use the logger's info method to write the message (logger handles debug check)
            $this->logger->info('[DockerCart License Manager] ' . $message);
        }
    }
}
