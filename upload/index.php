<?php

// Version
define('VERSION', '1.0.3');

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
