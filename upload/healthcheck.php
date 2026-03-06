<?php
// Simple health check endpoint for DockerCart
// Usage: visit /healthcheck.php

header('Content-Type: application/json; charset=utf-8');

$result = [
    'time' => date('c'),
    'php'  => phpversion(),
];

// Try to load application config (defines DB and DIR constants)
$ok = true;

$config_file = __DIR__ . '/config.php';
if (file_exists($config_file)) {
    @include_once $config_file;
} else {
    // If upload/config.php is not present, try parent directory (safety)
    $parent_config = dirname(__DIR__) . '/upload/config.php';
    if (file_exists($parent_config)) {
        @include_once $parent_config;
    }
}

// Database check (uses constants from upload/config.php)
if (defined('DB_HOSTNAME')) {
    try {
        $mysqli = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int) (defined('DB_PORT') ? DB_PORT : 3306));

        if ($mysqli->connect_errno) {
            $ok = false;
            $result['database'] = ['ok' => false, 'error' => $mysqli->connect_error];
        } else {
            $res = $mysqli->query('SELECT 1');
            if ($res) {
                $result['database'] = ['ok' => true];
                $res->free();
            } else {
                $ok = false;
                $result['database'] = ['ok' => false, 'error' => $mysqli->error];
            }
            $mysqli->close();
        }
    } catch (Throwable $e) {
        $ok = false;
        $result['database'] = ['ok' => false, 'error' => $e->getMessage()];
    }
} else {
    $ok = false;
    $result['database'] = ['ok' => false, 'error' => 'DB constants not defined (config.php not loaded)'];
}

// Storage directory check
if (defined('DIR_STORAGE')) {
    $storage_ok = is_dir(DIR_STORAGE) && is_writable(DIR_STORAGE);
    $result['storage'] = ['path' => DIR_STORAGE, 'ok' => $storage_ok];
    if (!$storage_ok) {
        $ok = false;
    }
} else {
    $result['storage'] = ['path' => null, 'ok' => null, 'error' => 'DIR_STORAGE not defined'];
}

// Optional cache check (memcached / memcache)
if (defined('CACHE_HOSTNAME') && defined('CACHE_PORT') && (class_exists('Memcached') || function_exists('memcache_connect'))) {
    try {
        if (class_exists('Memcached')) {
            $mc = new Memcached();
            $mc->addServer(CACHE_HOSTNAME, (int) CACHE_PORT);
            $stats = $mc->getVersion();
            $cache_ok = !empty($stats);
        } else {
            $conn = @memcache_connect(CACHE_HOSTNAME, (int) CACHE_PORT);
            $cache_ok = (bool) $conn;
        }
        $result['cache'] = ['ok' => (bool) $cache_ok];
        if (!$cache_ok) {
            $ok = false;
        }
    } catch (Throwable $e) {
        $ok = false;
        $result['cache'] = ['ok' => false, 'error' => $e->getMessage()];
    }
} else {
    $result['cache'] = ['ok' => null, 'note' => 'memcache/memcached not available or not configured'];
}

$status = $ok ? 'ok' : 'unhealthy';
http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $status, 'details' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
