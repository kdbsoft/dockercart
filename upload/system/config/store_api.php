<?php
// * Store API Configuration File
//
// The `store_api` application reuses the admin MVC tree: DIR_APPLICATION points
// at upload/admin/, so model()/controller() resolve to the admin models (the
// real business logic) while OCMOD modifications of the admin app keep applying.
//
// It is the customer-facing counterpart of system/config/api.php:
//   - no session autostart (bearer token auth, stateless),
//   - no startup/login + startup/permission (they redirect to the login form),
//   - startup/store_api replaces them (path routing, customer token auth,
//     scope authorisation, JSON errors).

// Site
$_['site_url']          = HTTP_SERVER;
$_['site_ssl']          = HTTPS_SERVER;

// Url
$_['url_autostart']     = false;

// Database
$_['db_autostart']      = true;
$_['db_engine']         = DB_DRIVER; // mpdo, mysqli or pgsql
$_['db_hostname']       = DB_HOSTNAME;
$_['db_username']       = DB_USERNAME;
$_['db_password']       = DB_PASSWORD;
$_['db_database']       = DB_DATABASE;
$_['db_port']           = DB_PORT;

// Session
// Nothing about a store API request is stateful: authentication comes from the
// bearer token and no cookie is ever sent.
$_['session_autostart'] = false;
$_['session_engine']    = SESSION_ENGINE;
$_['session_name']      = 'OCSESSID';

// Template (unused by the API, kept so any shared code that reads it works)
$_['template_engine']    = 'twig';
$_['template_directory'] = '';
$_['template_cache']     = false;

// Error
$_['error_display']  = false;
$_['error_log']      = true;
$_['error_filename'] = 'store_api_error.log';

// Actions
$_['action_pre_action'] = array(
	'startup/startup',
	'startup/error',
	'startup/event',
	'startup/store_api'
);

// Unknown routes fall through to the shared JSON 404 controller.
$_['action_default'] = 'api/error/notFound';

// A controller that cannot be resolved (missing action, typo in the endpoint
// table) must also answer in JSON, never with the panel's HTML 404 page.
$_['action_error'] = 'api/error/notFound';

// Action Events
$_['action_event'] = array(
	'controller/*/before' => array(
		'event/language/before'
	),
	'controller/*/after' => array(
		'event/language/after'
	)
);
