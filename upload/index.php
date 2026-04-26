<?php

// Version
$version = '1.0.0';
$version_files = [
	dirname(__DIR__) . '/VERSION',
	dirname(__DIR__, 2) . '/VERSION'
];

foreach ($version_files as $version_file) {
	if (is_file($version_file)) {
		$file_version = trim((string)file_get_contents($version_file));

		if ($file_version !== '') {
			$version = $file_version;
			break;
		}
	}
}

define('VERSION', $version);
define('DOCKERCART_VERSION', $version);

// Configuration
if (is_file('config.php')) {
	/** @phpstan-ignore-next-line requireOnce.fileNotFound */
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	http_response_code(503);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'DockerCart is not configured. Check .env and config.php bootstrap.';
	exit;
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');
