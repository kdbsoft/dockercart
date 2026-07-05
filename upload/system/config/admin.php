<?php
// Site
$_['site_url']          = HTTP_SERVER;
$_['site_ssl']          = HTTPS_SERVER;

// Database
$_['db_autostart']      = true;
$_['db_engine']         = DB_DRIVER; // mpdo, mysqli or pgsql
$_['db_hostname']       = DB_HOSTNAME;
$_['db_username']       = DB_USERNAME;
$_['db_password']       = DB_PASSWORD;
$_['db_database']       = DB_DATABASE;
$_['db_port']           = DB_PORT;

// Session
$_['session_autostart'] = true;

// Template
$_['template_cache']    = true;

// Actions
$_['action_pre_action'] = array(
	'startup/startup',
	'startup/error',
	'startup/event',
	'startup/sass',
	'startup/login',
	'startup/permission'
);

// Actions
$_['action_default'] = 'common/dashboard';

// Action Events
$_['action_event'] = array(
	'controller/*/before' => array(
		'event/language/before'
	),
	'controller/*/after' => array(
		'event/language/after'
	),
	'controller/extension/module/*/before' => array(
		'event/dockercart_license_admin'
	),
	'controller/extension/feed/*/before' => array(
		'event/dockercart_license_admin'
	),
	'controller/extension/payment/*/before' => array(
		'event/dockercart_license_admin'
	),
	'controller/extension/shipping/*/before' => array(
		'event/dockercart_license_admin'
	),
	'controller/extension/total/*/before' => array(
		'event/dockercart_license_admin'
	),
	'view/*/before' => array(
		999  => 'event/language',
		1000 => 'event/theme'
	),
	'view/extension/module/*/after' => array(
		'event/dockercart_about_tab'
	),
	'view/extension/feed/*/after' => array(
		'event/dockercart_about_tab'
	),
	'view/extension/payment/*/after' => array(
		'event/dockercart_about_tab'
	),
	'view/extension/shipping/*/after' => array(
		'event/dockercart_about_tab'
	),
	'view/extension/total/*/after' => array(
		'event/dockercart_about_tab'
	)
);